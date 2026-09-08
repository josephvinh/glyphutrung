<?php
/**
 * ĐĂNG NHẬP / ĐĂNG XUẤT / ĐỔI MẬT KHẨU
 *
 *   POST api/auth.php?action=login    { phone, password }
 *   POST api/auth.php?action=logout
 *   GET  api/auth.php?action=me
 *   POST api/auth.php?action=password { current, new }
 */

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 2) . '/config/push.php';
require dirname(__DIR__, 2) . '/config/password.php';

$action = $_GET['action'] ?? 'me';
$in     = json_input();

/**
 * Chuẩn hoá số điện thoại về dạng 0xxxxxxxxx: bỏ khoảng trắng/dấu, đổi
 * +84 / 84 / 0084 ở đầu thành 0. Để tránh hai "phiên bản" cùng một số
 * gây trùng lặp hoặc đăng nhập không khớp.
 */
function chuan_hoa_sdt(string $s): string
{
    $s = preg_replace('/[\s.\-()]/u', '', trim($s));
    if (strncmp($s, '+84', 3) === 0)      $s = '0' . substr($s, 3);
    elseif (strncmp($s, '0084', 4) === 0) $s = '0' . substr($s, 4);
    elseif (strncmp($s, '84', 2) === 0 && strlen($s) >= 11) $s = '0' . substr($s, 2);
    return $s;
}

/** Gói thông tin tài khoản để trả về cho giao diện */
function member_payload(array $m): array
{
    return [
        'id'            => (int) $m['id'],
        'code'          => $m['code'],
        'holyName'      => $m['holy_name'],
        'fullName'      => $m['full_name'],
        'phone'         => $m['phone'],
        'role'          => $m['role_code'],
        'roleLabel'     => $m['role_label'],
        'roleLevel'     => (int) $m['role_level'],
        'roleScope'     => $m['role_scope'],
        'roleTitle'     => $m['title_label'] ?? $m['role_label'],
        'birthDate'     => $m['birth_date'] ?? '',
        'managedBlock'  => $m['block_name'] ?? '',
        'assignedClass' => $m['class_name'] ?? '',
        'mustChangePw'  => (bool) $m['must_change_pw'],
    ];
}

switch ($action) {

    // -------------------------------------------------------------
    case 'login':
        require_post();
        $phone = chuan_hoa_sdt((string) ($in['phone'] ?? ''));
        $pass  = (string) ($in['password'] ?? '');

        if ($phone === '' || $pass === '') {
            json_fail('Vui lòng nhập số điện thoại và mật khẩu.');
        }

        $m = db_one(
            'SELECT m.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                    t.label AS title_label, b.name AS block_name, c.name AS class_name
               FROM members m
               JOIN roles r ON r.code = m.role_code
               LEFT JOIN titles t ON t.id = m.title_id
               LEFT JOIN blocks b ON b.id = m.block_id
               LEFT JOIN classes c ON c.id = m.class_id
              WHERE m.phone = ?',
            [$phone]
        );

        // Hết lượt thử thì dừng ngay, không kiểm mật khẩu nữa
        login_throttle($phone);

        // Cùng một thông điệp cho cả hai trường hợp — không tiết lộ
        // số điện thoại nào có tài khoản.
        if (!$m || !password_verify_upgrade($pass, $m['password_hash'], $m['id'])) {
            login_failed($phone);
            json_fail('Số điện thoại hoặc mật khẩu không đúng.', 401);
        }
        if ($m['status'] === 'chờ duyệt') {
            json_fail('Tài khoản của bạn đang chờ Ban Điều Hành duyệt. '
                    . 'Sau khi được duyệt và phân công lớp, bạn sẽ đăng nhập được.', 403);
        }
        if ($m['status'] === 'đã nghỉ') {
            json_fail('Tài khoản đã ngưng hoạt động. Vui lòng liên hệ Ban Điều Hành.', 403);
        }

        // Cấp phiên mới để chặn cướp phiên
        login_ok($phone);
        session_regenerate_id(true);
        $_SESSION['member_id'] = (int) $m['id'];

        db_run('UPDATE members SET last_login_at = NOW() WHERE id = ?', [$m['id']]);
        log_action('tao', 'auth', 'Đăng nhập hệ thống', $m['phone']);

        json_out(['ok' => true, 'user' => member_payload($m)]);

    // -------------------------------------------------------------
    case 'logout':
        if (current_member()) log_action('xoa', 'auth', 'Đăng xuất', '');
        $_SESSION = [];
        session_destroy();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'me':
        $m = current_member();
        if (!$m) json_out(['ok' => false, 'user' => null], 200);
        json_out(['ok' => true, 'user' => member_payload($m)]);

    // -------------------------------------------------------------
    case 'password':
        require_post();
        $me      = require_login();
        $current = (string) ($in['current'] ?? '');
        $new     = (string) ($in['new'] ?? '');

        if (strlen($new) < 6) json_fail('Mật khẩu mới phải từ 6 ký tự trở lên.');
        if (!password_verify_upgrade($current, $me['password_hash'], $me['id'])) {
            json_fail('Mật khẩu hiện tại không đúng.');
        }
        if ($current === $new) json_fail('Mật khẩu mới phải khác mật khẩu cũ.');

        db_run('UPDATE members SET password_hash = ?, must_change_pw = 0 WHERE id = ?',
               [password_hash_upgrade($new), $me['id']]);
        log_action('sua', 'auth', 'Đổi mật khẩu', '');

        json_out(['ok' => true]);

    // -------------------------------------------------------------
    // TỰ ĐĂNG KÝ — endpoint công khai, KHÔNG yêu cầu đăng nhập.
    //
    // Tài khoản tạo ra ở trạng thái 'chờ duyệt' và KHÔNG vào được app
    // cho tới khi Ban Điều Hành duyệt và phân công lớp. Đây là chốt
    // chặn quan trọng: dữ liệu thiếu nhi có tên, ngày sinh, địa chỉ
    // nhà và số điện thoại phụ huynh — không thể để ai đăng ký cũng xem.
    case 'register':
        require_post();
        $holy  = trim((string) ($in['holyName'] ?? ''));
        $name  = trim((string) ($in['fullName'] ?? ''));
        $phone = chuan_hoa_sdt((string) ($in['phone'] ?? ''));
        $pass  = (string) ($in['password'] ?? '');
        $note  = trim((string) ($in['note'] ?? ''));
        $birth = trim((string) ($in['birthDate'] ?? ''));

        // Danh xưng người đăng ký tự khai: chỉ Giáo Lý Viên hoặc Dự Bị.
        // Đây mới là VAI KHỞI TẠO (còn chờ duyệt); nhiệm vụ thật (Trưởng khối,
        // BĐH, Chủ nhiệm...) do Ban Điều Hành gán khi duyệt.
        $danhXung = ($in['danhXung'] ?? 'glv') === 'du_bi' ? 'du_bi' : 'glv';

        if ($name === '')  json_fail('Vui lòng nhập họ và tên.');
        if ($birth === '') json_fail('Vui lòng nhập ngày sinh.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) json_fail('Ngày sinh không hợp lệ.');
        // Chặn nhầm ngày kiểu 2020; thành viên phải đủ tuổi tối thiểu
        $tuoi = (int) ((time() - strtotime($birth)) / 31556952);
        if ($tuoi < 13 || $tuoi > 90) json_fail('Ngày sinh không hợp lý — thành viên phải từ 13 tuổi trở lên.');
        if (!preg_match('/^0\d{8,10}$/', $phone)) {
            json_fail('Số điện thoại không hợp lệ. Nhập dạng 09xxxxxxxx.');
        }
        if (strlen($pass) < 6) json_fail('Mật khẩu phải từ 6 ký tự trở lên.');

        if (db_one('SELECT id FROM members WHERE phone = ?', [$phone])) {
            json_fail('Số điện thoại này đã được đăng ký. Nếu quên mật khẩu, liên hệ Ban Điều Hành để cấp lại.');
        }

        // Mã GLV cấp sẵn để BĐH có cái mà gọi, dù chưa duyệt
        $max  = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n
                          FROM members WHERE code LIKE 'GLV%'");
        $code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);

        $titleId = db_one("SELECT id FROM titles WHERE role_code=? ORDER BY sort_order LIMIT 1", [$danhXung])['id'] ?? null;

        // must_change_pw = 0 vì mật khẩu do chính họ đặt, không phải cấp tạm
        db_insert('INSERT INTO members (code, holy_name, full_name, phone, birth_date, password_hash,
                                        role_code, title_id, status, must_change_pw,
                                        register_note, registered_at)
                   VALUES (?,?,?,?,?,?,?,?,?,0,?,NOW())',
            [$code, $holy, $name, $phone, $birth, password_hash_upgrade($pass),
             $danhXung, $titleId, 'chờ duyệt', $note !== '' ? $note : null]);

        // Báo cho Ban Điều Hành có hồ sơ mới chờ duyệt. Người vừa đăng ký
        // chưa vào được app nên chắc chắn không tự báo cho mình.
        push_bao(push_nguoi_duyet(), 'Thành viên mới chờ duyệt',
                 $name . ($note !== '' ? ' — ' . mb_substr($note, 0, 80) : ''),
                 '/#members', 'tntt-tv-moi');

        json_out(['ok' => true, 'code' => $code]);

    // -------------------------------------------------------------
    // Tự sửa thông tin của chính mình. Vai trò, chức danh và phân công
    // KHÔNG nhận từ đây — đó là việc của Ban Điều Hành bên api/org.php.
    case 'profile':
        require_post();
        $me    = require_login();
        $name  = trim((string) ($in['fullName'] ?? ''));
        $phone = trim((string) ($in['phone'] ?? ''));

        if ($name === '')  json_fail('Vui lòng nhập họ và tên.');
        if ($phone === '') json_fail('Vui lòng nhập số điện thoại.');
        if (db_one('SELECT id FROM members WHERE phone = ? AND id <> ?', [$phone, $me['id']])) {
            json_fail('Số điện thoại này đã có tài khoản khác dùng.');
        }

        $birth = trim((string) ($in['birthDate'] ?? ''));
        if ($birth !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
            json_fail('Ngày sinh không hợp lệ.');
        }
        db_run('UPDATE members SET holy_name = ?, full_name = ?, phone = ?, birth_date = ? WHERE id = ?',
               [trim((string) ($in['holyName'] ?? '')), $name, $phone,
                $birth !== '' ? $birth : null, $me['id']]);
        log_action('sua', 'profile', 'Cập nhật thông tin cá nhân', $name);

        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
