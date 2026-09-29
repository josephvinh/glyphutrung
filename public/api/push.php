<?php
/**
 * THÔNG BÁO ĐẨY — ĐĂNG KÝ MÁY VÀ LẤY NỘI DUNG
 *
 *   GET  api/push.php?action=key       — khoá công khai để trình duyệt đăng ký
 *   POST api/push.php?action=pending { endpoint } — service worker gọi lấy nội dung mới nhất (không cần đăng nhập)
 *   GET  api/push.php?action=status    — máy này đã bật chưa, cả đoàn bao nhiêu máy
 *   POST api/push.php?action=subscribe { endpoint }
 *   POST api/push.php?action=unsubscribe { endpoint }
 *   POST api/push.php?action=test      — tự gửi cho mình một cái để thử
 */

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 2) . '/config/push.php';

$action = $_GET['action'] ?? '';
$in     = json_input();

switch ($action) {

    // -------------------------------------------------------------
    // Khoá công khai. Không phải bí mật — trình duyệt bắt buộc phải có
    // nó mới đăng ký được với Google/Apple.
    case 'key':
        $cfg = app_config('push');
        json_out(['ok' => true, 'key' => $cfg['public'] ?? '']);

    // -------------------------------------------------------------
    // Service worker gọi khi nghe chuông. Lấy dòng cũ nhất chưa hiện,
    // đánh dấu đã lấy để lần sau không hiện lại.
    case 'pending':
        // Service worker chạy ngoài trang nên có thể không còn phiên đăng
        // nhập (đã đăng xuất / hết hạn). Vẫn phải nhận được thông báo, nên
        // nhận diện bằng endpoint đã đăng ký: đó là địa chỉ dài, ngẫu nhiên,
        // chỉ máy này biết. Không có endpoint thì quay về dùng phiên.
        $ep = trim((string) ($in['endpoint'] ?? ''));
        $me = null;
        if ($ep !== '') {
            $sub = db_one('SELECT member_id FROM push_subscriptions WHERE endpoint = ?', [$ep]);
            if ($sub) $me = ['id' => (int) $sub['member_id']];
        }
        if (!$me) $me = require_login();
        $t  = db_one('SELECT * FROM push_outbox WHERE member_id = ? AND taken_at IS NULL
                      ORDER BY id ASC LIMIT 1', [$me['id']]);
        if (!$t) json_out(['ok' => true, 'item' => null]);

        db_run('UPDATE push_outbox SET taken_at = NOW() WHERE id = ?', [$t['id']]);

        // Còn dòng nào nữa thì ghi vào tiêu đề, đỡ phải dội chuông nhiều lần
        $con = (int) (db_one('SELECT COUNT(*) c FROM push_outbox
                              WHERE member_id = ? AND taken_at IS NULL', [$me['id']])['c'] ?? 0);
        json_out(['ok' => true, 'item' => [
            'title' => $t['title'],
            'body'  => $con > 0 ? $t['body'] . ' (và ' . $con . ' việc khác)' : $t['body'],
            'url'   => $t['url'],
            'tag'   => $t['tag'],
        ]]);

    // -------------------------------------------------------------
    case 'status':
        $me  = require_login();
        $cfg = app_config('push');
        $ep  = (string) ($_GET['endpoint'] ?? '');
        json_out(['ok' => true,
            'available' => !empty($cfg['public']),
            'onThisDevice' => $ep !== '' && (bool) db_one(
                'SELECT id FROM push_subscriptions WHERE member_id = ? AND endpoint = ?', [$me['id'], $ep]),
            'devices' => (int) (db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?',
                                       [$me['id']])['c'] ?? 0),
        ]);

    // -------------------------------------------------------------
    case 'subscribe':
        require_write();
        $me = require_login();
        $ep = trim((string) ($in['endpoint'] ?? ''));
        if ($ep === '' || !preg_match('#^https://#', $ep)) json_fail('Đăng ký không hợp lệ.');
        if (strlen($ep) > 500) json_fail('Địa chỉ đăng ký quá dài.');

        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        // Cùng một máy đăng ký lại (đổi tài khoản, cài lại) thì chuyển
        // đăng ký sang người đang đăng nhập, không tạo dòng thừa.
        db_run('INSERT INTO push_subscriptions (member_id, endpoint, ua, created_at)
                VALUES (?,?,?,NOW())
                ON DUPLICATE KEY UPDATE member_id = VALUES(member_id), ua = VALUES(ua)',
               [$me['id'], $ep, $ua]);
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'unsubscribe':
        require_write();
        $me = require_login();
        $ep = trim((string) ($in['endpoint'] ?? ''));
        db_run('DELETE FROM push_subscriptions WHERE member_id = ? AND endpoint = ?', [$me['id'], $ep]);
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'test':
        require_write();
        $me = require_login();
        // Kiểm tra trước khi gửi: nếu chưa có máy nào đăng ký thì đừng
        // ghi vào hộp thư đi, kẻo để lại một dòng "Thử thông báo" treo ở
        // đó rồi bật lên lúc có việc thật.
        $may = (int) (db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?',
                             [$me['id']])['c'] ?? 0);
        if ($may === 0) json_fail('Máy này chưa bật thông báo. Bật nút ở trên rồi thử lại.', 409);

        $n  = push_bao([$me['id']], 'Thử thông báo',
                       'Nếu thấy dòng này thì máy của bạn nhận thông báo được rồi.',
                       '/', 'tntt-thu');
        if ($n === 0) json_fail('Chưa rung được máy nào. Kiểm tra: đã bật thông báo chưa, máy có mạng không.', 409);
        json_out(['ok' => true, 'devices' => $n]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
