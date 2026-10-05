<?php
/**
 * NẠP DỮ LIỆU LÀM VIỆC
 *
 *   GET api/data.php
 *
 * Trả về toàn bộ dữ liệu của niên khoá đang mở, đúng hình dạng mà
 * giao diện đang dùng — nên app.js không phải đổi cấu trúc.
 *
 * TUỲ CHỌN PAGINATION:
 * - ?page=1&limit=50 : phân trang danh sách thiếu nhi
 * - ?page=all : trả toàn bộ (backward compatible)
 *
 * TUỲ CHỌN PART (two-step loading):
 * - part=all  : toàn bộ dữ liệu (mặc định)
 * - part=core : mọi thứ TRỪ điểm danh/điểm
 * - part=heavy: CHỈ điểm danh/điểm (tải nền)
 *
 * TUỲ CHỌN CONTACTS:
 * - ?includeContacts=1 : gửi kèm SĐT phụ huynh (màn thông tin phụ huynh)
 */

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/StampService.php';

$page = isset($_GET['page']) && $_GET['page'] !== 'all'
    ? max(1, (int) $_GET['page'])
    : 1;
$limit = isset($_GET['limit'])
    ? min(MAX_PAGE_LIMIT, max(1, (int) $_GET['limit']))
    : DEFAULT_PAGE_LIMIT;
$isPaginated = isset($_GET['page']) && $_GET['page'] !== 'all';

// Tải 2 BƯỚC cho nhẹ máy yếu:
//   'core'  = mọi thứ TRỪ điểm danh/điểm  -> app dùng được ngay lúc mở
//   'heavy' = CHỈ điểm danh/điểm             -> tải NỀN ngay sau đó
//   'all'   = cả gói (tương thích các nơi gọi cũ, vd nhập CSV)
$part = $_GET['part'] ?? 'all';
if (!in_array($part, ['core', 'heavy', 'all'], true)) $part = 'all';

// Filter theo lớp/chương trình để giảm payload (P8 #90)
$filterClassId = isset($_GET['classId']) ? (int) $_GET['classId'] : null;
$filterProgramId = isset($_GET['programId']) ? (int) $_GET['programId'] : null;

// PR-4: Tối thiểu payload - chỉ gửi SĐT phụ huynh khi cần
$includeContacts = isset($_GET['includeContacts']);

// ================================================================
// LOAD SUB-MODULES
// ================================================================
require __DIR__ . '/data/_core.php';
require __DIR__ . '/data/_students.php';
require __DIR__ . '/data/_programs.php';
require __DIR__ . '/data/_attendance.php';
require __DIR__ . '/data/_scores.php';

// Rate limiting cho API đọc
enforce_api_read_limit();

$me   = require_login();
// Đọc xong phiên là nhả khoá ngay: PHP khoá file session suốt request, nên nếu giữ
// thì core, heavy và nhịp dò sync.php xếp hàng nối đuôi nhau (PWA mở lại càng chậm).
session_write_close();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];

// Check cache first — khoá theo part + pagination + filters để không đè nhau
// PR-4: includeContacts cũng là một biến thể của payload
$cacheKey = "data_" . DATA_CACHE_VER . "_{$yid}_{$me['id']}_{$part}_{$page}_{$limit}_{$filterClassId}_{$filterProgramId}_{$includeContacts}";
if ($cached = Cache::get($cacheKey)) {
    data_out($cached);
}

// ================================================================
// BƯỚC 1: CORE DATA (students, programs, stamps)
// ================================================================

// Thiếu nhi + enrollments
$students = data_load_students($yid, $me, $isPaginated, $page, $limit, $includeContacts);

// Sổ Mộc — ví + chuỗi + lịch sử gần nhất cho card hồ sơ (SPEC-MOC-DIEN-TU §6.2).
$stampSummaries = [];
if ($part !== 'heavy') {
    $stuIds = array_map(fn($s) => (int) $s['id'], $students['data']);
    $stampSummaries = stamp_summaries_bulk($stuIds, $yid);
}
$stampSummaries = (object) $stampSummaries;

// Sĩ số từng lớp
$classCounts = data_load_class_counts($yid);

// Chương trình + program_classes
$programs = data_load_programs($yid, $year['status'], $part);
$programClasses = data_load_program_classes($yid);

// ================================================================
// BƯỚC 2: HEAVY DATA (attendances, scores) - early exit
// ================================================================
if ($part === 'heavy') {
    $attendances = data_load_attendances($yid, $me, $part, $filterClassId, $filterProgramId);
    $scores = $part === 'core' ? [] : data_load_scores($yid, $me);

    $heavy = ['ok' => true, 'attendances' => $attendances, 'scores' => $scores];
    Cache::set($cacheKey, $heavy, 60);
    data_out($heavy);
}

// ================================================================
// PHẦN CÒN LẠI: attendance, scores, reports, announcements, staff, logs
// ================================================================
$attendances = data_load_attendances($yid, $me, $part, $filterClassId, $filterProgramId);
$leaves = data_load_leaves($yid, $me);
$scores = data_load_scores($yid, $me);
$reports = data_load_reports($yid, $me);

// Thông báo — kèm dấu đã đọc của chính người đang đăng nhập
$rsvpMine = [];
foreach (db_all('SELECT announcement_id, status FROM meeting_rsvp WHERE member_id = ?', [$me['id']]) as $r) {
    $rsvpMine[(int) $r['announcement_id']] = $r['status'];
}
$rsvpCount = [];
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

// Nhân sự — danh bạ chỉ cho người có quyền 'staff' ≥ view (#78)
$staffLv = permission_of('staff');
$memberWhere = [];
if (!can_see_admin($me))  $memberWhere[] = "m.role_code <> 'admin'";
if ($staffLv !== 'edit') $memberWhere[] = "m.status <> 'chờ duyệt'";
$members = $staffLv === 'none' ? [] : array_map(fn($m) => [
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
    'registerNote' => $staffLv === 'edit' ? ($m['register_note'] ?? '') : '',
    'hasAssignment' => !empty($m['has_assignment']),
], db_all(
    'SELECT m.*, t.label AS title_label, b.name AS block_name, c.name AS class_name,
            EXISTS(SELECT 1 FROM member_assignments a
                    WHERE a.member_id = m.id AND a.to_date IS NULL
                      AND a.role_code <> \'thu_thu\') AS has_assignment
       FROM members m
       LEFT JOIN titles  t ON t.id = m.title_id
       LEFT JOIN blocks  b ON b.id = m.block_id
       LEFT JOIN classes c ON c.id = m.class_id'
    . ($memberWhere ? ' WHERE ' . implode(' AND ', $memberWhere) : '')
    . ' ORDER BY m.id'));

// Nhật ký — 50 dòng gần nhất (đủ xem, nhẹ bandwidth)
$logs = !can_view_logs($me) ? [] : array_map(fn($l) => [
    'id'     => (int) $l['id'],
    'at'     => substr($l['logged_at'], 0, 16),
    'ts'     => strtotime($l['logged_at']) * 1000,
    'actor'  => $l['actor_name'],
    'action' => $l['action'],
    'module' => $l['module'],
    'what'   => $l['what'],
    'detail' => $l['detail'] ?? '',
], db_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50'));

// Lịch cá nhân — chỉ ghi chú của chính người đang đăng nhập
$notes = array_map(fn($n) => [
    'id'       => (int) $n['id'],
    'title'    => $n['title'],
    'note'     => $n['note'] ?? '',
    'remindAt' => substr($n['remind_at'], 0, 16),
    'allDay'   => (bool) $n['all_day'],
    'done'     => (bool) $n['done'],
], db_all('SELECT * FROM personal_notes WHERE member_id = ? ORDER BY remind_at', [$me['id']]));

// Thư viện — số chờ duyệt cho icon chấm đỏ
$libraryPending = (permission_of('thu_vien') === 'edit')
    ? (int) db_one('SELECT COUNT(*) n FROM library_items WHERE status = "cho_duyet"')['n']
    : 0;

// ================================================================
// BUILD RESULT
// ================================================================
$result = [
    'ok' => true,
    'notes'         => $notes,
    'stampSummaries' => $stampSummaries,
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

// Students: nếu paginated thì trả kèm metadata, không thì trả full array
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
// khác hiện ra trong vòng ~1 phút thay vì tới 5 phút.
Cache::set($cacheKey, $result, 60);
data_out($result);
