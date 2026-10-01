<?php
/**
 * THÔNG BÁO ĐẨY — ĐĂNG KÝ MÁY VÀ LẤY NỘI DUNG
 *
 *   GET  api/push.php?action=key       — khoá công khai để trình duyệt đăng ký
 *   POST api/push.php?action=pending { token } — service worker gọi lấy nội dung mới nhất (không cần đăng nhập)
 *   GET  api/push.php?action=status    — máy này đã bật chưa, cả đoàn bao nhiêu máy, lần gửi gần nhất
 *   POST api/push.php?action=subscribe { endpoint } — trả { token }; endpoint của người khác → 409 endpoint_owned
 *   POST api/push.php?action=unsubscribe { endpoint }
 *   POST api/push.php?action=test      — xếp hàng gửi cho mình một cái để thử (gửi sau khi trả phản hồi)
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
        // nhận diện bằng TOKEN do máy chủ cấp lúc đăng ký (chỉ lưu băm).
        // Token sai/đã xoay/không có thì rơi xuống phiên đăng nhập → 401 nếu
        // không có phiên, không phân biệt "token sai" với "không có token".
        // Chỉ nhận chuỗi: mảng/số/null thì coi như không có (không ép kiểu → không Warning).
        $token = push_chuoi($in['token'] ?? null);
        $ep    = trim(push_chuoi($in['endpoint'] ?? null));
        $me    = null;
        // Chỉ thành viên đang phục vụ / tạm nghỉ mới nhận nội dung. 'chờ duyệt' (chưa được
        // duyệt vào đoàn) và 'đã nghỉ' bị loại như nhau: rơi xuống require_login() → 401.
        if (push_token_dung_dinh_dang($token)) {
            $sub = db_one("SELECT s.member_id FROM push_subscriptions s
                             JOIN members m ON m.id = s.member_id
                            WHERE s.token_hash = ? AND m.status IN ('đang phục vụ','tạm nghỉ')",
                          [push_bam_token($token)]);
            if ($sub) $me = ['id' => (int) $sub['member_id']];
        }
        // Chuyển tiếp: dòng CŨ (chưa có token) vẫn nhận bằng endpoint tới hạn
        // push_con_nhan_endpoint_cu(). Dòng đã có token thì endpoint hết giá trị.
        if (!$me && $ep !== '' && push_con_nhan_endpoint_cu()) {
            $sub = db_one("SELECT s.member_id FROM push_subscriptions s
                             JOIN members m ON m.id = s.member_id
                            WHERE s.endpoint = ? AND s.token_hash IS NULL
                              AND m.status IN ('đang phục vụ','tạm nghỉ')", [$ep]);
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
        $ep  = push_chuoi($_GET['endpoint'] ?? null);
        $may = $ep !== '' ? db_one(
            'SELECT id, last_ok_at, last_fail_code, (token_hash IS NULL) AS chua_token
               FROM push_subscriptions WHERE member_id = ? AND endpoint = ?',
            [$me['id'], $ep]) : null;
        // Xả hàng đợi cơ hội: app gọi status mỗi lần mở, nhân dịp đó rung nốt
        // những máy còn tồn (sau khi đã trả phản hồi) nên không cần cron.
        push_hen_sau_phan_hoi();
        json_out(['ok' => true,
            'available' => !empty($cfg['public']),
            'onThisDevice' => (bool) $may,
            // Dòng đăng ký từ trước bản vá chưa có token: trang sẽ tự đăng ký lại để lấy
            'needToken' => $may ? (bool) $may['chua_token'] : false,
            'lastOkAt' => $may['last_ok_at'] ?? null,
            'lastFailCode' => isset($may['last_fail_code']) ? (int) $may['last_fail_code'] : null,
            'devices' => (int) (db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?',
                                       [$me['id']])['c'] ?? 0),
        ]);

    // -------------------------------------------------------------
    case 'subscribe':
        require_write();
        $me = require_login();
        // KHÔNG trim: khoảng trắng/CRLF ở đuôi phải bị từ chối chứ không được "sửa hộ".
        $epRaw = $in['endpoint'] ?? '';
        if ($epRaw === null || $epRaw === '') json_fail('Đăng ký không hợp lệ.');
        // Mảng/số/bool không phải endpoint: từ chối cùng thông điệp như host lạ, không ép kiểu.
        if (!is_string($epRaw)) json_fail('Trình duyệt này dùng máy chủ thông báo chưa được hỗ trợ.', 422);
        $ep = $epRaw;
        // Chống SSRF (#99): chỉ nhận endpoint của các dịch vụ push thật.
        // Thông điệp chung, không phản chiếu lại dữ liệu người gửi.
        if (!push_endpoint_hop_le($ep)['ok']) {
            json_fail('Trình duyệt này dùng máy chủ thông báo chưa được hỗ trợ.', 422);
        }

        $ua = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);

        // KHÔNG BAO GIỜ chuyển quyền sở hữu endpoint của người khác (#107).
        // Máy dùng chung (A đăng xuất, B đăng nhập) chỉ cần huỷ đăng ký trong
        // trình duyệt rồi đăng ký lại: trình duyệt cấp endpoint MỚI. Nên người
        // dùng thật luôn có đường ra, còn kẻ biết endpoint người khác thì không
        // làm được gì.
        $token = push_sinh_token();
        $bam   = push_bam_token($token);

        $hang = db_one('SELECT id, member_id FROM push_subscriptions WHERE endpoint = ?', [$ep]);
        if (!$hang) {
            try {
                db_run('INSERT INTO push_subscriptions (member_id, endpoint, ua, created_at, token_hash)
                        VALUES (?,?,?,NOW(),?)', [$me['id'], $ep, $ua, $bam]);
                json_out(['ok' => true, 'token' => $token]);
            } catch (PDOException $e) {
                if ($e->getCode() !== '23000') throw $e;
                // Hai request đua nhau cùng endpoint: đọc lại xem ai thắng
                $hang = db_one('SELECT id, member_id FROM push_subscriptions WHERE endpoint = ?', [$ep]);
                if (!$hang) json_fail('Không đăng ký được, thử lại sau.', 409);
            }
        }
        if ((int) $hang['member_id'] !== (int) $me['id']) {
            json_out(['ok' => false, 'code' => 'endpoint_owned',
                      'error' => 'Máy này đang nhận thông báo cho tài khoản khác. Đang đăng ký lại…'], 409);
        }

        // Cùng người đăng ký lại: xoay token (token cũ hết hiệu lực), nâng cấp dòng cũ chưa có token
        db_run('UPDATE push_subscriptions SET token_hash = ?, ua = ? WHERE id = ? AND member_id = ?',
               [$bam, $ua, $hang['id'], $me['id']]);
        json_out(['ok' => true, 'token' => $token]);

    // -------------------------------------------------------------
    case 'unsubscribe':
        require_write();
        $me = require_login();
        $ep = trim(push_chuoi($in['endpoint'] ?? null));
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
        if ($n === 0) json_fail('Chưa xếp hàng gửi được cho máy nào. Kiểm tra: đã bật thông báo chưa, máy chủ đã có khoá thông báo chưa.', 409);
        // Việc gửi diễn ra SAU khi trả phản hồi này (không giữ request chờ push service)
        json_out(['ok' => true, 'devices' => $n, 'queued' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
