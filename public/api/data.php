<?php
/**
 * NẠP DỮ LIỆU LÀM VIỆC
 *
 *   GET api/data.php
 *
 * Trả về toàn bộ dữ liệu của niên khoá đang mở, đúng hình dạng mà
 * giao diện đang dùng — nên app.js không phải đổi cấu trúc.
 *
 * Vì sao nạp một lượt thay vì phân trang: một giáo xứ cỡ vài trăm em
 * thì cả gói này chưa tới vài trăm KB, tải một lần lúc mở app rồi
 * thao tác offline-nhanh vẫn nhẹ hơn gọi mạng liên tục — nhất là khi
 * sóng trong nhà thờ thường yếu.
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_login();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];

// ---------------------------------------------------------------
// Thiếu nhi — gộp thông tin bền với ghi danh của năm nay
// ---------------------------------------------------------------
$students = array_map(fn($s) => [
    'id'          => (int) $s['id'],
    'code'        => $s['code'],
    'holyName'    => $s['holy_name'],
    'name'        => $s['full_name'],
    'gender'      => (int) $s['gender'],
    'birthDate'   => $s['birth_date'],
    'address'     => $s['address'],
    'fatherName'  => $s['father_name'],
    'fatherPhone' => $s['father_phone'],
    'motherName'  => $s['mother_name'],
    'motherPhone' => $s['mother_phone'],
    'status'      => $s['status'],
    'className'   => $s['class_name'],
    'block'       => $s['block_name'],
], (function () use ($yid, $me) {
    // CHỈ gửi hồ sơ trong phạm vi người này được xem.
    //
    // Trước đây gửi hồ sơ CẢ ĐOÀN cho mọi người đăng nhập — gồm ngày
    // sinh, địa chỉ, tên và số điện thoại cha mẹ. Giao diện có lọc lại,
    // nhưng dữ liệu thô vẫn nằm trong trình duyệt: mở công cụ nhà phát
    // triển là đọc được cả đoàn. Với dữ liệu trẻ em thì không nên.
    //
    // Máy quét QR KHÔNG dùng danh sách này — nó có bảng tra riêng, gọn,
    // chỉ gồm mã số / tên / lớp (api/attendance.php?action=lookup).
    $ids = allowed_class_ids($me);
    if ($ids !== null && !$ids) return [];

    $dk = ''; $tham = [$yid];
    if ($ids !== null) {
        $dk   = ' AND e.class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $tham = array_merge($tham, $ids);
    }

    return db_all(
        "SELECT s.*, e.status, c.name AS class_name, b.name AS block_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes  c ON c.id = e.class_id
           JOIN blocks   b ON b.id = c.block_id
          WHERE e.year_id = ?{$dk}
          ORDER BY s.code", $tham);
})());

// ---------------------------------------------------------------
// Sĩ số từng lớp — đếm ở máy chủ.
//
// Màn Khối & Lớp hiện sĩ số MỌI lớp cho ai cũng xem được. Trước đây
// máy khách tự đếm từ danh sách thiếu nhi; nay danh sách đó đã thu
// hẹp nên đếm ở máy khách sẽ ra 0 cho lớp ngoài phạm vi.
//
// Con số đếm không lộ thông tin riêng tư của em nào.
// ---------------------------------------------------------------
$classCounts = [];
foreach (db_all(
    "SELECT c.name, COUNT(*) AS n
       FROM enrollments e
       JOIN classes c ON c.id = e.class_id
      WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'
      GROUP BY c.id", [$yid]) as $r) {
    $classCounts[$r['name']] = (int) $r['n'];
}

// ---------------------------------------------------------------
// Chương trình
// ---------------------------------------------------------------
$programs = array_map(fn($p) => [
    'id'                 => (int) $p['id'],
    'name'               => $p['name'],
    'type'               => $p['type'],
    'status'             => $p['status'],
    'countForAttendance' => (bool) $p['count_for_attendance'],
    'startTime'          => substr($p['start_time'], 0, 5),
    'dayOfWeek'          => $p['day_of_week'] === null ? null : (int) $p['day_of_week'],
    'eventDate'          => $p['event_date'] ?? '',
], db_all('SELECT * FROM programs WHERE year_id = ? ORDER BY start_time', [$yid]));

// ---------------------------------------------------------------
// Điểm danh — chỉ các em CÓ TỚI
// ---------------------------------------------------------------
$attendances = array_map(fn($a) => [
    'programId' => (int) $a['program_id'],
    'date'      => $a['session_date'],
    'studentId' => (int) $a['student_id'],
    'status'    => $a['status'],
    'method'    => $a['method'],
    'markedBy'  => $a['marked_by_name'] ?? '',
    'markedAt'  => substr($a['marked_at'], 11, 5),
], db_all(
    'SELECT a.*, m.full_name AS marked_by_name
       FROM attendances a
       LEFT JOIN members m ON m.id = a.marked_by
      WHERE a.year_id = ?', [$yid]));

// ---------------------------------------------------------------
// Đơn xin phép
// ---------------------------------------------------------------
$leaves = array_map(fn($l) => [
    'id'           => (int) $l['id'],
    'studentId'    => (int) $l['student_id'],
    'programId'    => (int) $l['program_id'],
    'date'         => $l['session_date'],
    'reason'       => $l['reason'],
    'status'       => $l['status'],
    'createdBy'    => $l['created_by_name'] ?? '',
    'createdAt'    => substr($l['created_at'], 0, 16),
    'approvedBy'   => $l['approved_by_name'] ?? '',
    'approvedAt'   => $l['approved_at'] ? substr($l['approved_at'], 0, 16) : '',
    'rejectReason' => $l['reject_reason'] ?? '',
], db_all(
    'SELECT l.*, c.full_name AS created_by_name, a.full_name AS approved_by_name
       FROM leave_requests l
       LEFT JOIN members c ON c.id = l.created_by
       LEFT JOIN members a ON a.id = l.approved_by
      WHERE l.year_id = ?
      ORDER BY l.session_date DESC, l.id DESC', [$yid]));

// ---------------------------------------------------------------
// Điểm số & sổ liên lạc — theo học kỳ của năm nay
// ---------------------------------------------------------------
$scores = array_map(fn($s) => [
    'studentId' => (int) $s['student_id'],
    'termId'    => (int) $s['term_id'],
    'type'      => $s['type_code'],
    'value'     => (float) $s['value'],
    'at'        => substr($s['updated_at'], 0, 16),
    'by'        => $s['by_name'] ?? '',
], db_all(
    'SELECT sc.*, m.full_name AS by_name
       FROM scores sc
       JOIN terms t ON t.id = sc.term_id
       LEFT JOIN members m ON m.id = sc.updated_by
      WHERE t.year_id = ?', [$yid]));

$reports = array_map(fn($r) => [
    'id'         => (int) $r['id'],
    'studentId'  => (int) $r['student_id'],
    'termId'     => (int) $r['term_id'],
    'attendance' => [
        'present'   => (int) $r['att_present'],
        'late'      => (int) $r['att_late'],
        'excused'   => (int) $r['att_excused'],
        'unexcused' => (int) $r['att_unexcused'],
        'total'     => (int) $r['att_total'],
        'rate'      => (int) $r['att_rate'],
    ],
    'score'     => $r['score'] === null ? '' : (float) $r['score'],
    'conduct'   => $r['conduct'],
    'rank'      => $r['rank_label'],
    'remark'    => $r['remark'] ?? '',
    'status'    => $r['status'],
    'createdBy' => $r['by_name'] ?? '',
    'createdAt' => substr($r['created_at'], 0, 16),
], db_all(
    'SELECT r.*, m.full_name AS by_name
       FROM reports r
       JOIN terms t ON t.id = r.term_id
       LEFT JOIN members m ON m.id = r.created_by
      WHERE t.year_id = ?', [$yid]));

// ---------------------------------------------------------------
// Thông báo — kèm dấu đã đọc của chính người đang đăng nhập
// ---------------------------------------------------------------
$announcements = array_map(fn($a) => [
    'id'            => (int) $a['id'],
    'title'         => $a['title'],
    'body'          => $a['body'],
    'level'         => $a['level'],
    'audienceType'  => $a['audience_type'],
    'audienceValue' => $a['audience_type'] === 'khối' ? ($a['block_name'] ?? '')
                     : ($a['audience_type'] === 'lớp' ? ($a['class_name'] ?? '') : ''),
    'status'        => $a['status'],
    'publishedAt'   => $a['published_at'] ? substr($a['published_at'], 0, 16) : '',
    'expiresAt'     => $a['expires_at'] ?? '',
    'createdBy'     => $a['by_name'] ?? '',
], db_all(
    'SELECT a.*, b.name AS block_name, c.name AS class_name, m.full_name AS by_name
       FROM announcements a
       LEFT JOIN blocks  b ON b.id = a.audience_block
       LEFT JOIN classes c ON c.id = a.audience_class
       LEFT JOIN members m ON m.id = a.created_by
      WHERE a.year_id = ?
      ORDER BY a.published_at DESC, a.id DESC', [$yid]));

$readIds = array_map('intval', array_column(
    db_all('SELECT announcement_id FROM announcement_reads WHERE member_id = ?', [$me['id']]),
    'announcement_id'));

// ---------------------------------------------------------------
// Nhân sự
// ---------------------------------------------------------------
$members = array_map(fn($m) => [
    'id'        => (int) $m['id'],
    'code'      => $m['code'],
    'holyName'  => $m['holy_name'],
    'fullName'  => $m['full_name'],
    'phone'     => $m['phone'],
    'birthDate' => $m['birth_date'] ?? '',
    'role'      => $m['role_code'],
    'title'     => $m['title_label'] ?? '',
    'block'     => $m['block_name'] ?? '',
    'className' => $m['class_name'] ?? '',
    'status'    => $m['status'],
    'registerNote' => $m['register_note'] ?? '',
], db_all(
    'SELECT m.*, t.label AS title_label, b.name AS block_name, c.name AS class_name
       FROM members m
       LEFT JOIN titles  t ON t.id = m.title_id
       LEFT JOIN blocks  b ON b.id = m.block_id
       LEFT JOIN classes c ON c.id = m.class_id
      ORDER BY m.id'));

// ---------------------------------------------------------------
// Nhật ký — 300 dòng gần nhất
// ---------------------------------------------------------------
$logs = array_map(fn($l) => [
    'id'     => (int) $l['id'],
    'at'     => substr($l['logged_at'], 0, 16),
    'ts'     => strtotime($l['logged_at']) * 1000,
    'actor'  => $l['actor_name'],
    'action' => $l['action'],
    'module' => $l['module'],
    'what'   => $l['what'],
    'detail' => $l['detail'] ?? '',
], db_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 300'));

json_out([
    'ok' => true,
    'students'      => $students,
    'classCounts'   => $classCounts,
    'programs'      => $programs,
    'attendances'   => $attendances,
    'leaveRequests' => $leaves,
    'scores'        => $scores,
    'reports'       => $reports,
    'announcements' => $announcements,
    'readAnnouncements' => $readIds,
    'members'       => $members,
    'logs'          => $logs,
]);
