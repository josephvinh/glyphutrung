<?php
/**
 * LOGIC CỔNG TRA CỨU CÔNG KHAI (Sổ Mộc) — thuần, test được.
 *
 * Dùng bởi public/tracuu.php (trang public, KHÔNG đăng nhập, theo mẫu
 * public/bxh.php: chỉ nạp config/db.php, không nạp _bootstrap.php để khỏi
 * dính header Content-Type: application/json / session của tầng API).
 * Tách riêng khỏi trang để test bằng PHPUnit không cần dựng HTML/HTTP.
 *
 * Bảo mật (SPEC-MOC-DIEN-TU §6.3 + Global Constraints):
 *   - tracuu_public_summary() CHỈ lộ đúng 8 khoá liệt kê trong docblock của
 *     nó — KHÔNG bao giờ trả các trường khác của students (SĐT, địa chỉ,
 *     tên cha/mẹ...). Định danh em qua students.code, không qua id.
 *   - Rate-limit theo IP mượn mẫu login_throttle()/login_failed() trong
 *     _bootstrap.php để chặn dò quét toàn bộ dải mã (HS001, HS002, ...).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/StampService.php';

/* =====================================================================
   RATE LIMIT — mượn mẫu login_throttle()/register_throttle() ở _bootstrap.php
   Cửa sổ 10 phút, tối đa 30 lượt/IP: đủ rộng để một gia đình tra vài lần
   liên tiếp (gõ nhầm mã, tra cho nhiều con...) nhưng đủ hẹp để chặn dò quét
   tuần tự dải mã (HS001, HS002, ...) — mã thiếu nhi không có bí mật gì khác
   để đoán ngoài việc thử lần lượt nên phải chặn CHẶT hơn login (vốn còn có
   mật khẩu bảo vệ phía sau).
   ===================================================================== */
if (!defined('TRACUU_CUA_SO_PHUT')) define('TRACUU_CUA_SO_PHUT', 10);
if (!defined('TRACUU_TOI_DA_IP'))   define('TRACUU_TOI_DA_IP', 30);

if (!function_exists('client_ip')) {
    // Định nghĩa lại y hệt _bootstrap.php::client_ip() — trang public/tracuu.php
    // (mẫu bxh.php) không nạp _bootstrap.php nên hàm này có thể chưa có.
    // function_exists() để không đụng độ khi file này được nạp CÙNG lúc với
    // _bootstrap.php (ví dụ trong test bootstrap, hoặc API xác nhận sau này).
    function client_ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}

if (!function_exists('json_fail')) {
    // Định nghĩa lại y hệt _bootstrap.php::json_fail() — cùng lý do trên.
    function json_fail(string $message, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Chặn TRƯỚC khi tra cứu (mẫu login_throttle()): quá TRACUU_TOI_DA_IP lượt
 * trong TRACUU_CUA_SO_PHUT phút từ cùng một IP thì dừng luôn tại đây, trả
 * 429 qua json_fail(). GỌI TRƯỚC tracuu_public_summary() ở mỗi lượt submit.
 */
function tracuu_throttle(): void
{
    $moc = date('Y-m-d H:i:s', time() - TRACUU_CUA_SO_PHUT * 60);
    $n = (int) (db_one(
        'SELECT COUNT(*) n FROM tracuu_attempts WHERE ip = ? AND tried_at > ?',
        [client_ip(), $moc]
    )['n'] ?? 0);

    if ($n >= TRACUU_TOI_DA_IP) {
        json_fail(
            'Bạn tra cứu quá nhiều lần. Vui lòng đợi ' . TRACUU_CUA_SO_PHUT . ' phút rồi thử lại.',
            429
        );
    }
}

/**
 * Ghi nhận MỘT lượt tra cứu (mẫu login_failed()) — gọi cho MỌI lượt submit,
 * kể cả khi mã không tồn tại (đếm theo lượt gọi, không theo kết quả, để một
 * kẻ dò không "né" được bộ đếm bằng cách chỉ thử các mã sai). Nhân tiện dọn
 * các bản ghi đã quá cũ khỏi cửa sổ xét.
 */
function tracuu_attempt_record(): void
{
    db_run('INSERT INTO tracuu_attempts (ip, tried_at) VALUES (?, NOW())', [client_ip()]);
    db_run(
        'DELETE FROM tracuu_attempts WHERE tried_at < ?',
        [date('Y-m-d H:i:s', time() - TRACUU_CUA_SO_PHUT * 60)]
    );
}

/**
 * TRA CỨU SỔ MỘC CÔNG KHAI theo mã thiếu nhi — CHỈ ĐỌC, không cần đăng nhập.
 *
 * Tái dùng stamp_summary() (đã kẹp current_balance ≥ 0, đã giới hạn
 * recent_transactions) rồi GHÉP THÊM tên + lớp — không tự tính lại phần Mộc.
 * class_name lấy từ enrollment của em trong ĐÚNG $yearId (có thể null nếu
 * năm đó em không ghi danh lớp nào).
 *
 * @return array{
 *   code:string, full_name:string, class_name:?string,
 *   current_balance:int, total_earned:int,
 *   current_streak:int, longest_streak:int,
 *   recent_transactions: array<int,array{amount:int,type:string,description:string,created_at:string}>
 * }|null  null nếu không có em nào mang mã này. CHỈ đúng 8 khoá trên —
 *   TUYỆT ĐỐI không thêm trường nào khác của students (SĐT, địa chỉ, tên
 *   cha/mẹ...) vào đây.
 */
function tracuu_public_summary(string $code, int $yearId): ?array
{
    $student = db_one('SELECT id, code, full_name FROM students WHERE code = ?', [$code]);
    if (!$student) return null;

    $sid = (int) $student['id'];

    $enr = db_one(
        "SELECT c.name AS class_name
           FROM enrollments e
           JOIN classes c ON c.id = e.class_id
          WHERE e.student_id = ? AND e.year_id = ?
          LIMIT 1",
        [$sid, $yearId]
    );

    $summary = stamp_summary($sid, $yearId);

    return [
        'code'                => $student['code'],
        'full_name'           => $student['full_name'],
        'class_name'          => $enr['class_name'] ?? null,
        'current_balance'     => $summary['current_balance'],
        'total_earned'        => $summary['total_earned'],
        'current_streak'      => $summary['current_streak'],
        'longest_streak'      => $summary['longest_streak'],
        'recent_transactions' => $summary['recent_transactions'],
    ];
}
