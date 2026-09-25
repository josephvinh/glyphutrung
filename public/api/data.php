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
 *
 * TUỲ CHỌN PAGINATION:
 * - ?page=1&limit=50 : phân trang danh sách thiếu nhi
 * - ?page=all : trả toàn bộ (backward compatible)
 */

require __DIR__ . '/_bootstrap.php';

// Rate limiting cho API đọc
enforce_api_read_limit();

// Pagination config
const DEFAULT_PAGE_LIMIT = 100;
const MAX_PAGE_LIMIT = 500;

$page = isset($_GET['page']) && $_GET['page'] !== 'all'
    ? max(1, (int) $_GET['page'])
    : 1;
$limit = isset($_GET['limit'])
    ? min(MAX_PAGE_LIMIT, max(1, (int) $_GET['limit']))
    : DEFAULT_PAGE_LIMIT;
$isPaginated = isset($_GET['page']) && $_GET['page'] !== 'all';

$me   = require_login();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];

// Tải 2 BƯỚC cho nhẹ máy yếu:
//   'core'  = mọi thứ TRỪ điểm danh/điểm  -> app dùng được ngay lúc mở
//   'heavy' = CHỈ điểm danh/điểm          -> tải NỀN ngay sau đó
//   'all'   = cả gói (tương thích các nơi gọi cũ, vd nhập CSV)
$part = $_GET['part'] ?? 'all';
if (!in_array($part, ['core', 'heavy', 'all'], true)) $part = 'all';

// Check cache first — khoá theo part để 3 loại không đè lên nhau
$cacheKey = "data_{$yid}_{$me['id']}_{$part}";
if ($cached = Cache::get($cacheKey)) {
    json_out($cached);
}

// ---------------------------------------------------------------
// Thiếu nhi — gộp thông tin bền với ghi danh của năm nay
// TUỲ CHỌN PAGINATION: nếu ?page=N, chỉ trả page đó
// ---------------------------------------------------------------
$students = (function () use ($yid, $me, $isPaginated, $page, $limit) {
    // CHỈ gửi hồ sơ trong phạm vi người này được xem.
    $ids = allowed_class_ids($me);
    if ($ids !== null && !$ids) return ['data' => [], 'total' => 0];

    $dk = ''; $tham = [$yid];
    if ($ids !== null) {
        $dk   = ' AND e.class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $tham = array_merge($tham, $ids);
    }

    // Đếm tổng (cho pagination metadata)
    $total = (int) db_one(
        "SELECT COUNT(*) n FROM enrollments e WHERE e.year_id = ?{$dk}", $tham)['n'];

    // Pagination
    $offset = ($page - 1) * $limit;
    $limitClause = $isPaginated ? "LIMIT {$limit} OFFSET {$offset}" : '';

    $rows = db_all(
        "SELECT s.*, e.status, c.name AS class_name, b.name AS block_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes  c ON c.id = e.class_id
           JOIN blocks   b ON b.id = c.block_id
          WHERE e.year_id = ?{$dk}
          ORDER BY s.code
          {$limitClause}", $tham);

    $data = array_map(fn($s) => [
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
    ], $rows);

    return ['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit];
})();

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
// Tự đóng các chương trình "chiến dịch" đã qua ngày (nếu bật auto_close).
// Lười: chỉ chạy khi cột tồn tại; một UPDATE gọn, không đụng chương trình khác.
// Bỏ qua ở bước 'heavy' (chỉ trả điểm danh/điểm, KHÔNG gửi programs) để khỏi
// ghi thừa vào một endpoint đọc.
// Bỏ qua khi niên khoá ĐÃ KHOÁ SỔ (chỉ đọc): mọi endpoint ghi khác đều chặn
// năm khoá, endpoint đọc này không được là ngoại lệ tự sửa dữ liệu. Đặt điều
// kiện khoá TRƯỚC để năm khoá không tốn cả truy vấn SHOW COLUMNS.
if ($part !== 'heavy'
    && $year['status'] !== 'đã khóa'
    && db_has_column('programs', 'auto_close_after_event')) {
    db_run("UPDATE programs SET status='đã đóng'
             WHERE year_id=? AND type='chiến dịch' AND status='kích hoạt'
               AND auto_close_after_event=1 AND event_date IS NOT NULL AND event_date < CURDATE()",
           [$yid]);
}

$programs = array_map(fn($p) => [
    'id'                 => (int) $p['id'],
    'name'               => $p['name'],
    'type'               => $p['type'],
    'status'             => $p['status'],
    'countForAttendance' => (bool) $p['count_for_attendance'],
    'countForEmulation'  => (bool) ($p['count_for_emulation'] ?? 0),
    'startTime'          => substr($p['start_time'], 0, 5),
    'cutoffTime'         => !empty($p['cutoff_time']) ? substr($p['cutoff_time'], 0, 5) : '',
    'absentTime'         => !empty($p['absent_time']) ? substr($p['absent_time'], 0, 5) : '',
    'dayOfWeek'          => $p['day_of_week'] === null ? null : (int) $p['day_of_week'],
    'daysOfWeek'         => !empty($p['days_of_week'])
                              ? array_map('intval', explode(',', $p['days_of_week']))
                              : ($p['day_of_week'] === null ? [] : [(int) $p['day_of_week']]),
    'eventDate'          => $p['event_date'] ?? '',
    'allowQr'            => (bool) ($p['allow_qr'] ?? 1),
    'color'              => $p['color'] ?? '',
    'icon'               => $p['icon'] ?? '',
    'sortOrder'          => (int) ($p['sort_order'] ?? 1),
    'effectiveFrom'      => $p['effective_from'] ?? '',
    'effectiveTo'        => $p['effective_to'] ?? '',
    'autoCloseAfterEvent'=> (bool) ($p['auto_close_after_event'] ?? 0),
], db_all(
    db_has_column('programs', 'sort_order')
        ? 'SELECT * FROM programs WHERE year_id = ? ORDER BY sort_order, start_time'
        : 'SELECT * FROM programs WHERE year_id = ? ORDER BY start_time',
    [$yid]));

// ---------------------------------------------------------------
// Chương trình gắn lớp — lớp nào tham gia chương trình nào.
// RỖNG với một chương trình = áp dụng toàn đoàn.
// Gửi map programId -> [classId,...] để client lọc buổi theo lớp.
// ---------------------------------------------------------------
$programClasses = [];
if (db_has_table('program_classes')) {
    foreach (db_all(
        'SELECT pc.program_id, pc.class_id
           FROM program_classes pc
           JOIN programs p ON p.id = pc.program_id
          WHERE p.year_id = ?', [$yid]) as $r) {
        $pid = (int) $r['program_id'];
        if (!isset($programClasses[$pid])) $programClasses[$pid] = [];
        $programClasses[$pid][] = (int) $r['class_id'];
    }
}
// Ép thành object {pid: [..]} khi rỗng để JSON ra {} thay vì []
$programClasses = (object) $programClasses;

// ---------------------------------------------------------------
// Điểm danh — chỉ các em CÓ TỚI.
// GIỚI HẠN theo phạm vi: chỉ gửi điểm danh của các em người này được xem.
// Admin/BĐH (phạm vi null) vẫn nhận toàn đoàn (họ cần thống kê cả đoàn);
// GLV/Trưởng khối chỉ nhận lớp/khối mình -> payload nhẹ hẳn.
// ---------------------------------------------------------------
$attRows = [];
if ($part !== 'core') {                          // bước 'core' bỏ qua điểm danh
    $attScopeIds = allowed_class_ids($me);        // null = toàn đoàn
    if ($attScopeIds === null) {
        $attRows = db_all(
            'SELECT a.*, m.full_name AS marked_by_name
               FROM attendances a
               LEFT JOIN members m ON m.id = a.marked_by
              WHERE a.year_id = ?', [$yid]);
    } else {
        // Chỉ điểm danh của các em trong phạm vi (dùng lại danh sách $students)
        $stuIds = array_map(fn($s) => (int) $s['id'], $students);
        if ($stuIds) {
            $ph = implode(',', array_fill(0, count($stuIds), '?'));
            $attRows = db_all(
                "SELECT a.*, m.full_name AS marked_by_name
                   FROM attendances a
                   LEFT JOIN members m ON m.id = a.marked_by
                  WHERE a.year_id = ? AND a.student_id IN ($ph)",
                array_merge([$yid], $stuIds));
        }
    }
}
$attendances = array_map(fn($a) => [
    'programId'  => (int) $a['program_id'],
    'scheduleId' => !empty($a['schedule_id']) ? (int) $a['schedule_id'] : null,  // HƯỚNG B
    'date'      => $a['session_date'],
    'studentId' => (int) $a['student_id'],
    'status'    => $a['status'],
    'method'    => $a['method'],
    'markedBy'  => $a['marked_by_name'] ?? '',
    'markedAt'  => substr($a['marked_at'], 11, 5),
], $attRows);

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
$scores = $part === 'core' ? [] : array_map(fn($s) => [
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

// BƯỚC 2 (tải nền): chỉ cần điểm danh + điểm -> trả sớm, khỏi tính phần
// còn lại (thông báo/RSVP, nhân sự, nhật ký...). Nhẹ và nhanh hơn hẳn.
if ($part === 'heavy') {
    $heavy = ['ok' => true, 'attendances' => $attendances, 'scores' => $scores];
    Cache::set($cacheKey, $heavy, 60);
    json_out($heavy);
}

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
// Trả lời họp của CHÍNH người này + số đếm chung (cho người phát xem nhanh)
$rsvpMine = [];
foreach (db_all('SELECT announcement_id, status FROM meeting_rsvp WHERE member_id = ?', [$me['id']]) as $r) {
    $rsvpMine[(int) $r['announcement_id']] = $r['status'];
}
$rsvpCount = [];   // id -> ['tham gia'=>n, 'không tham gia'=>n]
foreach (db_all("SELECT mr.announcement_id, mr.status, COUNT(*) AS n
                   FROM meeting_rsvp mr
                   JOIN announcements a ON a.id = mr.announcement_id
                  WHERE a.year_id = ?
                  GROUP BY mr.announcement_id, mr.status", [$yid]) as $r) {
    $rsvpCount[(int) $r['announcement_id']][$r['status']] = (int) $r['n'];
}

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
    // Buổi họp + RSVP
    'isMeeting'     => (bool) ($a['is_meeting'] ?? 0),
    'meetingAt'     => !empty($a['meeting_at']) ? substr($a['meeting_at'], 0, 16) : '',
    'meetingPlace'  => $a['meeting_place'] ?? '',
    'myRsvp'        => $rsvpMine[(int) $a['id']] ?? '',
    'rsvpYes'       => $rsvpCount[(int) $a['id']]['tham gia'] ?? 0,
    'rsvpNo'        => $rsvpCount[(int) $a['id']]['không tham gia'] ?? 0,
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
    // Đã có phân công đang hiệu lực chưa (kiêm nhiệm) — giao diện dùng để KHOÁ
    // ô vai trò ở màn Nhân sự: vai/chức vụ + lớp/khối của người có phân công do
    // màn Khối & Lớp quản (backend trả 409 nếu đổi ở đây). Xem StaffService A′.
    'hasAssignment' => !empty($m['has_assignment']),
], db_all(
    'SELECT m.*, t.label AS title_label, b.name AS block_name, c.name AS class_name,
            EXISTS(SELECT 1 FROM member_assignments a
                    WHERE a.member_id = m.id AND a.to_date IS NULL) AS has_assignment
       FROM members m
       LEFT JOIN titles  t ON t.id = m.title_id
       LEFT JOIN blocks  b ON b.id = m.block_id
       LEFT JOIN classes c ON c.id = m.class_id'
    // Ẩn tài khoản Quản trị khỏi mọi người trừ chính admin (F9).
    . (can_see_admin($me) ? '' : " WHERE m.role_code <> 'admin'")
    . ' ORDER BY m.id'));

// ---------------------------------------------------------------
// Nhật ký — 50 dòng gần nhất (đủ xem, nhẹ bandwidth)
// ---------------------------------------------------------------
// TODO: Chuyển sang API riêng có pagination khi có màn xem nhật ký
$logs = array_map(fn($l) => [
    'id'     => (int) $l['id'],
    'at'     => substr($l['logged_at'], 0, 16),
    'ts'     => strtotime($l['logged_at']) * 1000,
    'actor'  => $l['actor_name'],
    'action' => $l['action'],
    'module' => $l['module'],
    'what'   => $l['what'],
    'detail' => $l['detail'] ?? '',
], db_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50'));

// ---------------------------------------------------------------
// Lịch cá nhân — CHỈ ghi chú của chính người đang đăng nhập (riêng tư)
// ---------------------------------------------------------------
$notes = array_map(fn($n) => [
    'id'       => (int) $n['id'],
    'title'    => $n['title'],
    'note'     => $n['note'] ?? '',
    'remindAt' => substr($n['remind_at'], 0, 16),
    'allDay'   => (bool) $n['all_day'],
    'done'     => (bool) $n['done'],
], db_all('SELECT * FROM personal_notes WHERE member_id = ? ORDER BY remind_at', [$me['id']]));

// ---------------------------------------------------------------
// Thư viện — chỉ cần CON SỐ chờ duyệt để vẽ chấm đỏ trên icon.
// Chỉ người có quyền duyệt (edit) mới cần; GLV thường đừng phí lượt đếm.
// ---------------------------------------------------------------
$libraryPending = (permission_of('thu_vien') === 'edit')
    ? (int) db_one('SELECT COUNT(*) n FROM library_items WHERE status = "cho_duyet"')['n']
    : 0;

// Build result
$result = [
    'ok' => true,
    'notes'         => $notes,
    'classCounts'   => $classCounts,
    'programs'      => $programs,
    'programClasses' => $programClasses,
    'attendances'   => $attendances,
    'leaveRequests' => $leaves,
    'scores'        => $scores,
    'reports'       => $reports,
    'announcements' => $announcements,
    'readAnnouncements' => $readIds,
    'members'       => $members,
    'logs'          => $logs,
    'libraryPending' => $libraryPending,
];

// Students: nếu paginated thì trả kèm metadata, không thì trả full array (backward compatible)
if ($isPaginated) {
    $result['students'] = $students['data'];
    $result['pagination'] = [
        'total'     => $students['total'],
        'page'      => $students['page'],
        'limit'     => $students['limit'],
        'totalPages'=> ceil($students['total'] / $students['limit']),
        'hasMore'   => $students['page'] * $students['limit'] < $students['total'],
    ];
} else {
    $result['students'] = $students['data'];
}

// Cache result. Để 60s (trước là 300s) cho "tươi" hơn: thay đổi của người
// khác hiện ra trong vòng ~1 phút thay vì tới 5 phút. Người GHI vẫn được
// xoá cache ngay khi ghi nên luôn thấy mới; đây chỉ ảnh hưởng khung nhìn
// của người khác. Kết hợp auto-đồng-bộ khi mở lại app (shell.js) để bớt
// cảm giác "không realtime".
Cache::set($cacheKey, $result, 60);
json_out($result);
