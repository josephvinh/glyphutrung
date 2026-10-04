<?php
/**
 * LỜI CHÚA MỖI NGÀY
 *
 *   GET  api/bible.php?action=random   — Lấy verse ngẫu nhiên (rate limit 1/IP/giờ)
 *   GET  api/bible.php?action=list     — Danh sách IP đã lấy (admin)
 *   GET  api/bible.php?action=stats    — Thống kê tổng quan (admin)
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';
$ip = client_ip();

/** Fallback verses khi API fail */
const FALLBACK_VERSES = [
    ['text' => 'Đừng lo âu điều gì, nhưng trong mọi việc hãy trình bày nhu cầu của anh em cho Đức Chúa Trời, và Ngài sẽ ban sự bình an của Đức Chúa Trời, vượt quá mọi điều chúng ta có thể hiểu biết.', 'ref' => 'Philippians 4:6'],
    ['text' => 'Vì Chúa yêu thương thế gian này, Ngài đã ban Con Một, để ai tin Con Ngài cũng được sự sống đời đời.', 'ref' => 'John 3:16'],
    ['text' => 'Tôi ở với anh em mọi ngày cho đến tận thế hoàn tất.', 'ref' => 'Matthew 28:20'],
    ['text' => 'Hãy vui lên và hát ngợi khen, vì Đấng Toàn Năng đã làm những điều vĩ đại.', 'ref' => 'Psalms 126:3'],
    ['text' => 'Chúa là Đấng chăn nuôi tôi, tôi sẽ không thiếu thốn gì.', 'ref' => 'Psalms 23:1'],
    ['text' => 'Hãy cậy thương yêu Đức Chúa Trời, hãy chờ đợi Ngài và giữ vững lòng.', 'ref' => 'Lamentations 3:24-25'],
    ['text' => 'Mọi sự đều có lúc, có thì giống, có lúc chữa bệnh, có lúc phá đổ, có lúc xây dựng.', 'ref' => 'Ecclesiastes 3:3'],
    ['text' => 'Lòng tôi hát mừng Chúa, tôi sẽ tạ ơn Chúa đến đời đời.', 'ref' => 'Psalms 30:12'],
    ['text' => 'Những điều bất khả khả thì ở nơi người không thể làm được, nhưng không phải nơi Đức Chúa Trời.', 'ref' => 'Jeremiah 32:17'],
    ['text' => 'Nếu tôi lên trời, Ngài ở đó; nếu xuống âm phủ, Ngài cũng ở đó.', 'ref' => 'Psalms 139:8'],
];

/**
 * Lấy verse ngẫu nhiên từ bible-api.com
 * Returns array ['text' => string, 'ref' => string] hoặc null nếu fail
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
 * Lấy verse fallback ngẫu nhiên
 */
function get_fallback_verse(): array
{
    $index = array_rand(FALLBACK_VERSES);
    return FALLBACK_VERSES[$index];
}

/**
 * Kiểm tra rate limit: 1 lần/IP/giờ
 * Returns cached verse if within 1 hour, null otherwise
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
 * Lưu verse vào database
 */
function save_verse(string $ip, string $text, string $ref, string $translation = 'vietnamese'): int
{
    return db_insert(
        'INSERT INTO bible_daily (ip_address, verse_text, verse_ref, verse_translation, fetched_at)
         VALUES (?, ?, ?, ?, NOW())',
        [$ip, $text, $ref, $translation]
    );
}

switch ($action) {

    // ================================================================
    case 'random':
        // Kiểm tra rate limit: có verse trong vòng 1 giờ không?
        $cached = get_cached_verse($ip);

        if ($cached) {
            json_out([
                'success' => true,
                'cached' => true,
                'verse' => $cached['text'],
                'ref' => $cached['ref'],
                'translation' => $cached['translation'] ?? 'vietnamese',
                'message' => 'Đây là lời Chúa dành cho bạn hôm nay.',
            ]);
        }

        // Thử fetch từ API
        $verse = fetch_random_verse();

        // Fallback nếu API fail
        if ($verse === null) {
            $verse = get_fallback_verse();
            $translation = 'fallback';
        } else {
            $translation = 'vietnamese';
        }

        // Lưu vào database
        save_verse($ip, $verse['text'], $verse['ref'], $translation);

        json_out([
            'success' => true,
            'cached' => false,
            'verse' => $verse['text'],
            'ref' => $verse['ref'],
            'translation' => $translation,
            'message' => 'Đây là lời Chúa dành cho bạn hôm nay.',
        ]);

    // ================================================================
    case 'list':
        // Chỉ admin mới xem được danh sách IP
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
                    'ip' => $r['ip_address'],
                    'verse' => $r['verse_text'],
                    'ref' => $r['verse_ref'],
                    'translation' => $r['verse_translation'],
                    'fetched_at' => $r['fetched_at'],
                ];
            }, $rows),
        ]);

    // ================================================================
    case 'stats':
        // Chỉ admin mới xem được thống kê
        $me = require_permission('settings', 'view');

        $totalRequests = (int) db_val('SELECT COUNT(*) FROM bible_daily') ?? 0;

        $uniqueIps = (int) db_val('SELECT COUNT(DISTINCT ip_address) FROM bible_daily') ?? 0;

        $todayStart = date('Y-m-d') . ' 00:00:00';
        $todayUniqueIps = (int) db_val(
            'SELECT COUNT(DISTINCT ip_address) FROM bible_daily WHERE fetched_at >= ?',
            [$todayStart]
        ) ?? 0;

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
        json_fail('Hành động không hợp lệ.', 404);
}
