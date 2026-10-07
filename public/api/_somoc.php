<?php
/**
 * LOGIC CỔNG TRA CỨU CÔNG KHAI (Sổ Mộc) — thuần, test được.
 *
 * Dùng bởi public/somoc.php (trang public, KHÔNG đăng nhập, theo mẫu
 * public/bxh.php: chỉ nạp config/db.php, không nạp _bootstrap.php để khỏi
 * dính header Content-Type: application/json / session của tầng API).
 * Tách riêng khỏi trang để test bằng PHPUnit không cần dựng HTML/HTTP.
 *
 * Bảo mật (SPEC-MOC-DIEN-TU §6.3 + Global Constraints):
 *   - somoc_public_summary() CHỈ lộ đúng 8 khoá liệt kê trong docblock của
 *     nó — KHÔNG bao giờ trả các trường khác của students (SĐT, địa chỉ,
 *     tên cha/mẹ...). Định danh em qua students.code, không qua id.
 *   - Rate-limit theo IP mượn mẫu login_throttle()/login_failed() trong
 *     _bootstrap.php để chặn dò quét toàn bộ dải mã (GDGLPT260001, GDGLPT260002, ...).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/StampService.php';
require_once __DIR__ . '/_http_util.php'; // client_ip() + json_out()/json_fail() — SINGLE SOURCE, xem docblock ở đó

/* =====================================================================
   RATE LIMIT — mượn mẫu login_throttle()/register_throttle() ở _bootstrap.php
   Cửa sổ 10 phút, tối đa 30 lượt/IP: đủ rộng để một gia đình tra vài lần
   liên tiếp (gõ nhầm mã, tra cho nhiều con...) nhưng đủ hẹp để chặn dò quét
   tuần tự dải mã (GDGLPT260001, GDGLPT260002, ...) — mã thiếu nhi không có bí mật gì khác
   để đoán ngoài việc thử lần lượt nên phải chặn CHẶT hơn login (vốn còn có
   mật khẩu bảo vệ phía sau).
   ===================================================================== */
if (!defined('TRACUU_CUA_SO_PHUT')) define('TRACUU_CUA_SO_PHUT', 10);
if (!defined('TRACUU_TOI_DA_IP'))   define('TRACUU_TOI_DA_IP', 30);

// client_ip() và json_fail() đến từ _http_util.php (require ở trên) —
// dùng CHUNG một bản với _bootstrap.php, không định nghĩa lại ở đây nữa
// (tránh trôi lệch âm thầm + nguy cơ "Cannot redeclare" khi nạp khác thứ tự).

/**
 * Chặn TRƯỚC khi tra cứu (mẫu login_throttle()): quá TRACUU_TOI_DA_IP lượt
 * trong TRACUU_CUA_SO_PHUT phút từ cùng một IP thì dừng luôn tại đây, trả
 * 429 qua json_fail(). GỌI TRƯỚC somoc_public_summary() ở mỗi lượt submit.
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
function somoc_public_summary(string $code, int $yearId): ?array
{
    // PR-2: Chỉ tra cứu em không bị ẩn/xóa
    $student = db_one('SELECT id, code, full_name FROM students WHERE code = ? AND hidden_at IS NULL AND deleted_at IS NULL', [$code]);
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

/**
 * MỘC ĐÓNG THEO NGÀY (cả niên khoá) — cho LỊCH ĐÓNG MỘC của trang tra cứu.
 *
 * Ghép giao dịch earn/bonus với NGÀY ĐIỂM DANH THẬT (attendances.session_date
 * qua ref_attendance_id), KHÔNG dùng created_at (là lúc recalc chạy, có thể
 * khác ngày đi lễ). Chỉ lấy 'attendance' + 'streak_bonus' — đúng phần Mộc kiếm
 * được (khớp total_earned ở StampService); 'spend' (đổi quà) và 'manual_adjust'
 * (điều chỉnh tay) KHÔNG gắn với một ngày đi lễ nên không lên lịch (xem
 * somoc_moc_thuong_khac() cho phần 'manual_adjust').
 *
 * Tách khỏi somoc.php để test được bằng PHPUnit mà không cần dựng HTML/HTTP.
 *
 * @return array<string,int>  ['Y-m-d' => tổng Mộc đóng ngày đó], chỉ ngày >0,
 *   dùng làm nguồn cho JS dựng lịch từng tháng (lật tháng không tốn lượt tra).
 */
function somoc_moc_by_day(int $studentId, int $yearId): array
{
    $rows = db_all(
        "SELECT a.session_date AS ngay, SUM(st.amount) AS moc
           FROM stamp_transactions st
           JOIN attendances a ON a.id = st.ref_attendance_id
          WHERE st.student_id = ? AND st.year_id = ?
            AND st.type IN ('attendance','streak_bonus')
          GROUP BY a.session_date",
        [$studentId, $yearId]
    );

    $out = [];
    foreach ($rows as $r) {
        $m = (int) $r['moc'];
        if ($m > 0) $out[(string) $r['ngay']] = $m;
    }
    return $out;
}

/**
 * TỔNG MỘC "THƯỞNG KHÁC" — phần 'manual_adjust' (Huynh Trưởng tặng/điều chỉnh
 * tay), KHÔNG gắn với một buổi đi lễ nên KHÔNG hiện trên lịch và KHÔNG nằm
 * trong total_earned (StampService chỉ cộng earn/bonus vào total_earned; phần
 * manual_adjust chỉ chảy vào current_balance của ví).
 *
 * Trang tra cứu hiện MỘT dòng "🎁 Mộc thưởng khác: +N" khi số này > 0 để em/phụ
 * huynh hiểu vì sao Ví có thể nhiều hơn tổng Mộc trên lịch (tránh "kiện cáo"
 * nhầm là thiếu Mộc). Trả về TỔNG RÒNG (điều chỉnh âm cũng cộng dồn); nơi gọi
 * tự quyết chỉ khoe khi > 0.
 *
 * @return int  Σ amount của các giao dịch type='manual_adjust' trong năm.
 */
function somoc_moc_thuong_khac(int $studentId, int $yearId): int
{
    return (int) (db_val(
        "SELECT COALESCE(SUM(amount),0) FROM stamp_transactions
          WHERE student_id = ? AND year_id = ? AND type = 'manual_adjust'",
        [$studentId, $yearId]
    ) ?? 0);
}

/**
 * LỜI TRONG "LÁ THƯ" của trang tra cứu: một câu KHEN/động viên (đổi theo chuỗi
 * đi lễ của em), một gợi ý đổi quà khi Ví nhiều Mộc, một câu NHẮC NHỞ và một
 * câu châm ngôn/Lời Chúa (hai câu sau xoay vòng ngẫu nhiên cho đỡ nhàm). Xưng
 * "em", gọi bằng tên (từ cuối họ tên).
 *
 * Tách khỏi somoc.php để test được nhánh KHEN theo chuỗi (deterministic);
 * phần 'nhac'/'cham' dùng array_rand nên test chỉ kiểm cấu trúc, không kiểm giá
 * trị cụ thể.
 *
 * @param array $k  bản tổng hợp có current_streak/longest_streak/current_balance/full_name
 * @return array{khen:string, themVi:string, nhac:string, cham:string}
 */
function somoc_loi_la_thu(array $k): array
{
    $streak  = (int) ($k['current_streak'] ?? 0);
    $longest = (int) ($k['longest_streak'] ?? 0);
    $bal     = (int) ($k['current_balance'] ?? 0);
    $parts   = preg_split('/\s+/', trim((string) ($k['full_name'] ?? '')));
    $goi     = (is_array($parts) && $parts && end($parts) !== '') ? end($parts) : 'em';

    // (1) KHEN theo chuỗi đi lễ — có nhánh AN ỦI khi chuỗi vừa đứt.
    if ($streak >= 8) {
        $khen = "🔥 Quá tuyệt, $goi ơi! Em đã đi lễ $streak tuần liền không nghỉ — Chúa và các Huynh Trưởng tự hào về em lắm!";
    } elseif ($streak >= 4) {
        $khen = "🔥 Giỏi lắm $goi! Chuỗi đi lễ $streak tuần liền của em đang cháy rất đẹp — ráng giữ lửa nhé!";
    } elseif ($streak >= 1) {
        $khen = "🌱 $goi đang có chuỗi $streak tuần đi lễ rồi đó — cố thêm chút nữa cho ngọn lửa lớn hơn nhé!";
    } elseif ($longest >= 3) {
        // Chuỗi đang là 0 nhưng từng giữ được khá lâu -> an ủi, mời quay lại.
        $khen = "🫂 Đừng buồn nếu chuỗi bị gián đoạn nhé $goi — em từng giữ được $longest tuần liền cơ mà! Chúa Nhật này quay lại đi lễ là ngọn lửa cháy lại ngay.";
    } else {
        $khen = "🕊️ Chúa Nhật này $goi nhớ tới nhà thờ dự lễ, để nhóm lại ngọn lửa yêu Chúa nhé!";
    }

    // (2) Nhánh riêng khi Ví nhiều Mộc -> gợi ý đổi quà.
    $themVi = ($bal >= 100)
        ? "🎁 Em đã dành dụm được $bal Mộc rồi — ghé mục Đổi quà chọn một phần thưởng xứng đáng cho mình nhé!"
        : "";

    // (3) NHẮC NHỞ xoay vòng mỗi lần xem cho đỡ nhàm.
    $dsNhac = [
        "Nhớ đi lễ Chúa Nhật đều đặn, chuyên cần học Giáo Lý và luôn sống ngoan, vâng lời ông bà cha mẹ em nhé! 💛",
        "Mỗi ngày cố gắng làm một việc hy sinh nhỏ và một việc tốt cho bạn bè em nhé! 💛",
        "Nhớ đọc kinh sáng tối và siêng năng rước lễ để ở gần Chúa Giêsu hơn nhé! 💛",
        "Đi học Giáo Lý đúng giờ, mặc đồng phục gọn gàng và lễ phép với mọi người em nhé! 💛",
    ];
    $nhac = $dsNhac[array_rand($dsNhac)];

    // Khẩu hiệu / Lời Chúa theo văn phong TNTT — cũng xoay vòng.
    $dsCham = [
        "Cầu nguyện · Rước lễ · Hy sinh · Làm tông đồ",
        "“Hãy để trẻ nhỏ đến với Thầy” (Mc 10,14)",
        "“Các con là muối cho đời, là ánh sáng cho trần gian” (x. Mt 5,13-14)",
        "Sống ngày Thánh Thể: Chúa ở cùng em mọi ngày!",
    ];
    $cham = $dsCham[array_rand($dsCham)];

    return ['khen' => $khen, 'themVi' => $themVi, 'nhac' => $nhac, 'cham' => $cham];
}
