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

/**
 * Fallback verses khi API fail
 * Nguồn: Kinh Thánh CGKPV 2011 - đã duyệt, có dấu tiếng Việt
 */
const FALLBACK_VERSES = [
    ['text' => 'Thiên Chúa yêu thế gian đến nỗi đã ban Con Một, để ai tin vào Con của Người thì khỏi phải chết, nhưng được sống muôn đời.', 'ref' => 'Ga 3:16'],
    ['text' => 'Tôi ở với anh em mọi ngày cho đến tận thế hoàn tất.', 'ref' => 'Mt 28:20'],
    ['text' => 'Hãy đến cùng tôi, tất cả những ai đang vất vả mang gánh nặng nề, và tôi sẽ cho nghỉ ngơi bồi dưỡng.', 'ref' => 'Mt 11:28'],
    ['text' => 'Tôi là con đường, là sự thật và là sự sống. Không ai đến với Cha mà không qua tôi.', 'ref' => 'Ga 14:6'],
    ['text' => 'Thiên Chúa là Đấng chăn nuôi tôi, tôi sẽ không thiếu thốn gì.', 'ref' => 'Tv 23:1'],
    ['text' => 'Phúc thay người chẳng nghe theo lời bọn ác nhân, nhưng vui thú với lề luật CHÚA.', 'ref' => 'Tv 1:1-2'],
    ['text' => 'Lạy Chúa, xin dạy cho con biết con phải sống thế nào để xứng đáng trước mặt Ngài.', 'ref' => 'Tv 90:12'],
    ['text' => 'Anh em hãy vui luôn trong niềm vui của Chúa. Tôi nhắc lại: vui lên anh em!', 'ref' => 'Pl 4:4'],
    ['text' => 'Đừng lo lắng gì cả, nhưng trong mọi hoàn cảnh, hãy đem lời cầu khẩn, van xin và tạ ơn, mà giãi bày trước mặt Thiên Chúa.', 'ref' => 'Pl 4:6'],
    ['text' => 'Bình an của Thiên Chúa là bình an vượt lên trên mọi hiểu biết, sẽ giữ cho lòng trí anh em được kết hợp với Đức Ki-tô Giê-su.', 'ref' => 'Pl 4:7'],
    ['text' => 'Với Đấng ban sức mạnh cho tôi, tôi chịu được hết.', 'ref' => 'Pl 4:13'],
    ['text' => 'Thiên Chúa của tôi sẽ thỏa mãn mọi nhu cầu của anh em một cách tuyệt vời, theo sự giàu sang của Người.', 'ref' => 'Pl 4:19'],
    ['text' => 'Tôi để tâm trí con an nghỉ nơi đất, cho con được thỏa mãn khi vận mạng con được ban bố.', 'ref' => 'Tv 103:5'],
    ['text' => 'Lòng nhân hậu và tình thương của CHÚA đồng hành cùng anh em mọi ngày, cho đến muôn đời.', 'ref' => 'Tv 103:17'],
    ['text' => 'Người chăn nuôi lành mạnh cho tôi, Người dẫn tôi đi theo con đường công chính.', 'ref' => 'Tv 23:3'],
    ['text' => 'Dầu qua lũng âm u, tôi cũng không sợ hãi gì, vì CHÚA ở cùng tôi.', 'ref' => 'Tv 23:4'],
    ['text' => 'Nước Thiên Chúa đang đến gần. Hãy hối cải và tin vào Tin Mừng.', 'ref' => 'Mc 1:15'],
    ['text' => 'Thiên Chúa là tình yêu. Ai ở trong tình yêu thì ở trong Thiên Chúa, và Thiên Chúa ở trong họ.', 'ref' => '1 Ga 4:16'],
    ['text' => 'Chúng ta hãy yêu thương nhau, vì tình yêu bắt nguồn từ Thiên Chúa.', 'ref' => '1 Ga 4:7'],
    ['text' => 'Vậy giờ đây, những ai ở trong Đức Ki-tô Giê-su, thì không còn bị lên án nữa.', 'ref' => 'Rm 8:1'],
    ['text' => 'Phàm ai được Thần Khí Thiên Chúa hướng dẫn, đều là con cái Thiên Chúa.', 'ref' => 'Rm 8:14'],
    ['text' => 'Thiên Chúa làm cho mọi sự đều sinh lợi ích cho những ai yêu mến Người.', 'ref' => 'Rm 8:28'],
    ['text' => 'Không có gì tách được chúng ta ra khỏi tình yêu của Thiên Chúa thể hiện nơi Đức Ki-tô Giê-su.', 'ref' => 'Rm 8:39'],
    ['text' => 'Đến cả đi, hỡi những người đang khát, nước đã sẵn đây!', 'ref' => 'Is 55:1'],
    ['text' => 'Lời Ta cũng vậy, một khi xuất phát từ miệng Ta, sẽ không trở về Ta cái gì, mà sẽ làm điều Ta muốn.', 'ref' => 'Is 55:11'],
    ['text' => 'Hãy vui mừng reo hò, dân Sion, vì Đấng Thánh của Ít-ra-en quang lâm giữa anh em.', 'ref' => 'Is 12:6'],
    ['text' => 'CHÚA phán: Ta sẽ ban tặng anh em một trái tim mới, và đặt một thần khí mới vào lòng anh em.', 'ref' => 'Ed 36:26'],
    ['text' => 'ĐỨC CHÚA là Đấng chăn nuôi tôi, tôi sẽ không thiếu thốn gì. Người cho tôi nằm nghỉ trong đồng cỏ xanh tươi.', 'ref' => 'Tv 23:1-2'],
    ['text' => 'Xin dạy cho con biết cách sống đạo đức để con truyền lại cho hậu thế.', 'ref' => 'Tv 48:13'],
    ['text' => 'Lạy Chúa, xin tỏ cho con thấy đường lối của Ngài, và xin hướng dẫn con trên đường Ngài.', 'ref' => 'Tv 27:11'],
    ['text' => 'Hãy kêu cầu Danh Ngài, loan báo điều đó giữa các dân tộc.', 'ref' => 'Tv 105:1'],
    ['text' => 'Người ta sẽ chẳng còn dạy nhau, kẻ này nói với người kia: "Hãy học cho biết ĐỨC CHÚA", vì hết thảy sẽ biết Ta, từ người nhỏ đến người lớn.', 'ref' => 'Gr 31:34'],
    ['text' => 'Này, Ta làm mọi sự mới.', 'ref' => 'Kh 21:5'],
    ['text' => 'Đấng ngự trên ngôi và Con Chiên sẽ ngự trong đền thờ của Người.', 'ref' => 'Kh 21:22'],
    ['text' => 'Hãy đến, hỡi những ai đang khát, dù là ai cũng hãy đến mà nhận nước.', 'ref' => 'Kh 22:17'],
];

/**
 * DEPRECATED: bible-api.com đã ngưng hỗ trợ tiếng Việt (404).
 * Sử dụng FALLBACK_VERSES thay vì API.
 */
function fetch_random_verse(): ?array
{
    // bible-api.com/api/random?translation=vietnamese → 404
    // Xem issue #229: nên dùng api/loichua.php thay thế
    return null;
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

        // Thử fetch từ API (deprecated - luôn fail)
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
        // Fallback: trả verse ngẫu nhiên mà không lưu
        $verse = get_fallback_verse();
        json_out([
            'success' => true,
            'cached' => false,
            'verse' => $verse['text'],
            'ref' => $verse['ref'],
            'translation' => 'fallback',
            'message' => 'Đây là lời Chúa dành cho bạn hôm nay.',
        ]);
}
