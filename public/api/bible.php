<?php
/**
 * LOI CHUA MOI NGAY
 *
 *   GET  api/bible.php?action=random   — Lay verse ngau nhien (rate limit 1/IP/gio)
 *   GET  api/bible.php?action=list     — Danh sach IP da lay (admin)
 *   GET  api/bible.php?action=stats    — Thong ke tong quan (admin)
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';
$ip = client_ip();

/** Tu tao table neu chua co */
function ensure_bible_table(): void {
    try {
        db_run('CREATE TABLE IF NOT EXISTS bible_daily (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL,
            verse_text TEXT NOT NULL,
            verse_ref VARCHAR(100) NOT NULL,
            verse_translation VARCHAR(50) DEFAULT "vietnamese",
            fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_fetched_at (fetched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    } catch (Throwable $e) {
        // Ignore - table might already exist
    }
}

/** Fallback verses khi API fail */
const FALLBACK_VERSES = [
    ['text' => 'Dung lo au dieu gi, nhung trong moi viec hay trinh bay nhu cau cua anh em cho Duc Cha Troi, va Ngai se ban su binh an cua Duc Cha Troi, vuot qua moi dieu chung ta co the hieu biet.', 'ref' => 'Philippians 4:6'],
    ['text' => 'Vi Cha yeu thuong the gian nay, Ngai da ban Con Mot, de ai tin Con Ngai cung duoc su song doi doi.', 'ref' => 'John 3:16'],
    ['text' => 'Toi o voi anh em moi ngay cho den tan the hoan tat.', 'ref' => 'Matthew 28:20'],
    ['text' => 'Hay vui len va hat ngo khen, vi Dang Toan Nang da lam nhung dieu vi dai.', 'ref' => 'Psalms 126:3'],
    ['text' => 'Cha la Dang chan nuoi toi, toi se khong thieu thon gi.', 'ref' => 'Psalms 23:1'],
    ['text' => 'Hay cay thuong yeu Duc Cha Troi, hay cho doi Ngai va giu vung long.', 'ref' => 'Lamentations 3:24-25'],
    ['text' => 'Moi su deu co luc, co thi giong, co luc chua benh, co luc pha do, co luc xay dung.', 'ref' => 'Ecclesiastes 3:3'],
    ['text' => 'Long toi hat mua Cha, toi se ta on Cha den doi doi.', 'ref' => 'Psalms 30:12'],
    ['text' => 'Nhung dieu bat kha kha thi o noi nguoi khong the lam duoc, nhung khong phai noi Duc Cha Troi.', 'ref' => 'Jeremiah 32:17'],
    ['text' => 'Neu toi len troi, Ngai o do; neu xuong am phu, Ngai cung o do.', 'ref' => 'Psalms 139:8'],
];

/**
 * Lay verse ngau nhien tu bible-api.com
 */
function fetch_random_verse(): ?array
{
    $url = 'https://bible-api.com/api/random?translation=vietnamese';

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data['verses'][0])) {
        return null;
    }

    $verse = $data['verses'][0];
    return [
        'text' => trim($verse['text'] ?? ''),
        'ref' => trim($verse['reference'] ?? ''),
    ];
}

/**
 * Lay verse fallback ngau nhien
 */
function get_fallback_verse(): array
{
    $index = array_rand(FALLBACK_VERSES);
    return FALLBACK_VERSES[$index];
}

/**
 * Kiem tra rate limit: 1 lan/IP/gio
 */
function get_cached_verse(string $ip): ?array
{
    $oneHourAgo = date('Y-m-d H:i:s', time() - 3600);

    $row = db_one(
        'SELECT verse_text, verse_ref, verse_translation, fetched_at
           FROM bible_daily
          WHERE ip_address = ? AND fetched_at > ?
          ORDER BY fetched_at DESC
          LIMIT 1',
        [$ip, $oneHourAgo]
    );

    if (!$row) {
        return null;
    }

    return [
        'text' => $row['verse_text'],
        'ref' => $row['verse_ref'],
        'translation' => $row['verse_translation'],
        'cached' => true,
        'fetched_at' => $row['fetched_at'],
    ];
}

/**
 * Luu verse vao database
 */
function save_verse(string $ip, string $text, string $ref, string $translation = 'vietnamese'): int
{
    return db_insert(
        'INSERT INTO bible_daily (ip_address, verse_text, verse_ref, verse_translation, fetched_at)
         VALUES (?, ?, ?, ?, NOW())',
        [$ip, $text, $ref, $translation]
    );
}

// S10: Cleanup bible_daily records older than 30 days
try {
    db_run('DELETE FROM bible_daily WHERE fetched_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
} catch (Throwable $e) {
    // Ignore cleanup errors
}

switch ($action) {

    // ================================================================
    case 'random':
        // Tu tao table neu chua co
        ensure_bible_table();

        // Kiem tra rate limit: co verse trong vong 1 gio khong?
        $cached = get_cached_verse($ip);

        if ($cached) {
            json_out([
                'success' => true,
                'cached' => true,
                'verse' => $cached['text'],
                'ref' => $cached['ref'],
                'translation' => $cached['translation'] ?? 'vietnamese',
                'message' => 'Day la loi Chua danh cho ban hom nay.',
            ]);
        }

        // Thu fetch tu API
        $verse = fetch_random_verse();

        // Fallback neu API fail
        if ($verse === null) {
            $verse = get_fallback_verse();
            $translation = 'fallback';
        } else {
            $translation = 'vietnamese';
        }

        // Luu vao database
        save_verse($ip, $verse['text'], $verse['ref'], $translation);

        json_out([
            'success' => true,
            'cached' => false,
            'verse' => $verse['text'],
            'ref' => $verse['ref'],
            'translation' => $translation,
            'message' => 'Day la loi Chua danh cho ban hom nay.',
        ]);

    // ================================================================
    case 'list':
        // Chi admin moi xem duoc danh sach IP (da bam)
        $me = require_permission('settings', 'view');

        $rows = db_all(
            'SELECT id, ip_address, verse_text, verse_ref, verse_translation, fetched_at
               FROM bible_daily
              ORDER BY fetched_at DESC
              LIMIT 100'
        );

        json_out([
            'success' => true,
            'count' => count($rows),
            'rows' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'ip' => $r['ip_address'] ? hash('crc32c', $r['ip_address']) : '', // PR-6: Bam IP khi hien thi
                    'verse' => $r['verse_text'],
                    'ref' => $r['verse_ref'],
                    'translation' => $r['verse_translation'],
                    'fetched_at' => $r['fetched_at'],
                ];
            }, $rows),
        ]);

    // ================================================================
    case 'stats':
        // Chi admin moi xem duoc thong ke
        $me = require_permission('settings', 'view');

        $totalRequests = (int) db_val('SELECT COUNT(*) FROM bible_daily');
        $uniqueIps = (int) db_val('SELECT COUNT(DISTINCT ip_address) FROM bible_daily');
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayUniqueIps = (int) db_val(
            'SELECT COUNT(DISTINCT ip_address) FROM bible_daily WHERE fetched_at >= ?',
            [$todayStart]
        );
        $lastRequest = db_val('SELECT MAX(fetched_at) FROM bible_daily');

        json_out([
            'success' => true,
            'stats' => [
                'total_requests' => $totalRequests,
                'unique_ips' => $uniqueIps,
                'today_unique_ips' => $todayUniqueIps,
                'last_request' => $lastRequest ?? '',
            ],
        ]);

    // ================================================================
    default:
        // Fallback: tra verse ngau nhien ma khong luu
        $verse = get_fallback_verse();
        json_out([
            'success' => true,
            'cached' => false,
            'verse' => $verse['text'],
            'ref' => $verse['ref'],
            'translation' => 'fallback',
            'message' => 'Day la loi Chua danh cho ban hom nay.',
        ]);
}
