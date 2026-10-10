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
 */

require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/StampService.php';

/**
 * Trả JSON kèm ETag (băm nội dung). Máy khách gửi lại If-None-Match: nếu dữ
 * liệu không đổi thì trả 304 rỗng — khỏi tải lại vài trăm KB điểm danh mỗi
 * lần mở lại app. Băm trên chính nội dung nên không bao giờ trả bản cũ sai.
 */
function data_out(array $payload): never
{
    // Bản lấy từ cache đã qua json_decode nên {} rỗng thành []; ép về {} (cả hai map
    // này vốn là object ở nguồn) để nội dung (và ETag) không đổi tuỳ trúng/trượt cache.
    foreach (['programClasses', 'stampSummaries'] as $k) {
        if (isset($payload[$k]) && !$payload[$k]) $payload[$k] = (object) [];
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $etag = '"' . md5($body) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, no-cache');
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
}

/**
 * Lớp được XEM dữ liệu module $mod: giao phạm vi hồ sơ P(me) = allowed_class_ids()
 * với phạm vi các phân công có quyền ≥ view trên $mod (accessible_class_ids()).
 * null = toàn đoàn, [] = không gì.
 *
 * Lấy GIAO: (1) không bao giờ gửi dữ liệu của em mà người xem không nhận hồ sơ
 * (client ghép theo studentId, dữ liệu thừa chỉ là rò rỉ — #78); (2) tôn trọng ma
 * trận quyền chỉnh được trong app THEO TỪNG PHÂN CÔNG. Không dùng permission_of()
 * vì hàm đó gộp quyền mọi vai rồi bỏ qua phạm vi (lai phạm vi vai này với quyền vai kia).
 */
function data_scope_for(array $me, string $mod): ?array
{
    static $bases = [];                      // cùng $me gọi cho 3 module: tính P(me) một lần
    $base = $bases[(int) $me['id']] ??= [allowed_class_ids($me)];
    $base = $base[0];
    if ($base === []) return [];
    $m = accessible_class_ids($me, $mod, 'view');
    if ($m === null) return $base;           // base có thể null (toàn đoàn)
    if ($base === null) return $m;
    return array_values(array_intersect($base, $m));
}

/**
 * Mệnh đề lọc theo lớp qua ghi danh năm $yid: trả [sqlJoin, params] để chèn vào
 * FROM của truy vấn có cột em $studentCol; null = không lọc (toàn đoàn).
 * Lọc bằng id LỚP (vài chục phần tử, bind bằng ?) — không liệt kê id em.
 * uq_enr (year_id, student_id): mỗi em đúng một dòng ghi danh/năm nên JOIN không
 * nhân bản dòng. Gọi với $ids === [] là lỗi của nơi gọi (phải trả [] mà không truy vấn).
 */
function data_class_filter(?array $ids, string $studentCol, int $yid): ?array
{
    if ($ids === null) return null;
    $ph = implode(',', array_fill(0, count($ids), '?'));
    return [" JOIN enrollments e ON e.student_id = {$studentCol} AND e.year_id = ? AND e.class_id IN ({$ph})",
            array_merge([$yid], array_map('intval', $ids))];
}

/** Các dòng của một khối dữ liệu theo phạm vi: $sql chứa {JOIN} ngay sau bảng chính. */
function data_scoped_rows(?array $ids, string $sql, string $studentCol, int $yid, array $params): array
{
    if ($ids === []) return [];
    $f = data_class_filter($ids, $studentCol, $yid);
    if ($f === null) return db_all(str_replace('{JOIN}', '', $sql), $params);   // SQL cũ, không đổi
    return db_all(str_replace('{JOIN}', $f[0], $sql), array_merge($f[1], $params));
}

// Rate limiting cho API đọc
enforce_api_read_limit();

// Đổi phiên bản khi đổi hình dạng/phạm vi dữ liệu: khoá cache cũ (có thể đang
// chứa dữ liệu rộng hơn phạm vi mới) không bao giờ được đọc lại và tự hết hạn.
const DATA_CACHE_VER = 'v2';

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
// Đọc xong phiên là nhả khoá ngay: PHP khoá file session suốt request, nên nếu giữ
// thì core, heavy và nhịp dò sync.php xếp hàng nối đuôi nhau (PWA mở lại càng chậm).
session_write_close();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];

// Tải 2 BƯỚC cho nhẹ máy yếu:
//   'core'  = mọi thứ TRỪ điểm danh/điểm  -> app dùng được ngay lúc mở
//   'heavy' = CHỈ điểm danh/điểm          -> tải NỀN ngay sau đó
//   'all'   = cả gói (tương thích các nơi gọi cũ, vd nhập CSV)
$part = $_GET['part'] ?? 'all';
if (!in_array($part, ['core', 'heavy', 'all'], true)) $part = 'all';

// Filter theo lớp/chương trình để giảm payload (P8 #90)
// ?classId=X    -> chỉ tải dữ liệu của lớp X (admin/BĐH)
// ?programId=X  -> chỉ tải điểm danh của chương trình X
$filterClassId = isset($_GET['classId']) ? (int) $_GET['classId'] : null;
$filterProgramId = isset($_GET['programId']) ? (int) $_GET['programId'] : null;

// Check cache first — khoá theo part để 3 loại không đè lên nhau
$cacheKey = "data_" . DATA_CACHE_VER . "_{$yid}_{$me['id']}_{$part}_{$filterClassId}_{$filterProgramId}";
if ($cached = Cache::get($cacheKey)) {
    data_out($cached);
}

// ---------------------------------------------------------------
// Thiếu nhi — gộp thông tin bền với ghi danh của năm nay
// TUỲ CHỌN PAGINATION: nếu ?page=N, chỉ trả page đó
// ---------------------------------------------------------------
$students = (function () use ($yid, $me, $isPaginated, $page, $limit) {
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
// Sổ Mộc — ví + chuỗi + lịch sử gần nhất cho card hồ sơ (SPEC-MOC-DIEN-TU §6.2).
//
// DÙNG LẠI đúng $students đã lọc theo allowed_class_ids ở trên: không tự
// mở lại phạm vi ở đây, tránh lệch ranh giới lớp giữa hai chỗ. Bỏ qua ở
// bước 'heavy' cho nhẹ (giống scores) — tab hồ sơ đọc từ bước 'core'.
//
// stamp_summaries_bulk() — KHÔNG gọi stamp_summary() trong vòng lặp: với
// admin (phạm vi toàn đoàn, hàng trăm em) một cặp truy vấn riêng cho mỗi
// em sẽ thành ~2×N truy vấn trên đúng đường tải chính của app. Gộp thành
// hai truy vấn IN (...) cho toàn bộ $students, giống cách attendances/
// leaves/scores đã làm ở dưới.
// ---------------------------------------------------------------
$stampSummaries = [];
if ($part !== 'heavy') {
    // $students là cấu trúc phân trang ['data'=>[...], ...] (master #42) — lấy
    // đúng danh sách hàng em ở khoá 'data'.
    $stuIds = array_map(fn($s) => (int) $s['id'], $students['data']);
    $stampSummaries = stamp_summaries_bulk($stuIds, $yid);
}
// Ép thành object {studentId: {...}} khi rỗng để JSON ra {} thay vì [] —
// giống programClasses ở dưới, tránh client phải phân biệt hai kiểu.
$stampSummaries = (object) $stampSummaries;

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
// Filter thêm theo classId/programId để giảm payload (P8 #90).
// ---------------------------------------------------------------
$attRows = [];
if ($part !== 'core') {                          // bước 'core' bỏ qua điểm danh
    $attScopeIds = allowed_class_ids($me);      // null = toàn đoàn

    // Filter theo programId: chỉ tải điểm danh của chương trình này
    if ($filterProgramId !== null) {
        $sql = 'SELECT a.*, m.full_name AS marked_by_name
                FROM attendances a
                LEFT JOIN members m ON m.id = a.marked_by
                WHERE a.year_id = ? AND a.program_id = ?';
        $params = [$yid, $filterProgramId];
        // Nếu có phạm vi, lọc thêm theo lớp
        if ($attScopeIds !== null && $attScopeIds !== []) {
            // Lấy studentIds trong phạm vi
            $stuInScope = db_all('SELECT student_id FROM enrollments WHERE year_id = ? AND class_id IN (' . implode(',', array_fill(0, count($attScopeIds), '?')) . ')', array_merge([$yid], $attScopeIds));
            $stuIds = array_column($stuInScope, 'student_id');
            if ($stuIds) {
                $ph = implode(',', array_fill(0, count($stuIds), '?'));
                $attRows = db_all(
                    "SELECT a.*, m.full_name AS marked_by_name
                       FROM attendances a
                       LEFT JOIN members m ON m.id = a.marked_by
                      WHERE a.year_id = ? AND a.program_id = ? AND a.student_id IN ($ph)",
                    array_merge([$yid, $filterProgramId], $stuIds));
            }
        } else {
            $attRows = db_all($sql, $params);
        }
    } elseif ($filterClassId !== null) {
        // Filter theo classId: chỉ tải điểm danh của lớp này
        $stuInClass = db_all('SELECT student_id FROM enrollments WHERE year_id = ? AND class_id = ?', [$yid, $filterClassId]);
        $stuIds = array_column($stuInClass, 'student_id');
        if ($stuIds) {
            $ph = implode(',', array_fill(0, count($stuIds), '?'));
            $attRows = db_all(
                "SELECT a.*, m.full_name AS marked_by_name
                   FROM attendances a
                   LEFT JOIN members m ON m.id = a.marked_by
                  WHERE a.year_id = ? AND a.student_id IN ($ph)",
                array_merge([$yid], $stuIds));
        }
    } elseif ($attScopeIds === null) {
        // Không filter: tải toàn bộ (admin/BĐH)
        $attRows = db_all(
            'SELECT a.*, m.full_name AS marked_by_name
               FROM attendances a
               LEFT JOIN members m ON m.id = a.marked_by
              WHERE a.year_id = ?', [$yid]);
    } else {
        // Chỉ điểm danh của các em trong phạm vi (dùng lại danh sách $students).
        $stuIds = array_map(fn($s) => (int) $s['id'], $students['data']);
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
// Đơn xin phép — chỉ đơn của em thuộc phạm vi được xem module 'leave' (#78)
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
], data_scoped_rows(data_scope_for($me, 'leave'),
    'SELECT l.*, c.full_name AS created_by_name, a.full_name AS approved_by_name
       FROM leave_requests l{JOIN}
       LEFT JOIN members c ON c.id = l.created_by
       LEFT JOIN members a ON a.id = l.approved_by
      WHERE l.year_id = ?
      ORDER BY l.session_date DESC, l.id DESC', 'l.student_id', $yid, [$yid]));

// ---------------------------------------------------------------
// Điểm số & sổ liên lạc — theo học kỳ của năm nay, chỉ em thuộc phạm vi
// được xem module 'scores' / 'reports' (#78)
// ---------------------------------------------------------------
$scores = $part === 'core' ? [] : array_map(fn($s) => [
    'studentId' => (int) $s['student_id'],
    'examId'    => (int) ($s['exam_id'] ?? 0),
    'termId'    => (int) $s['term_id'],
    'type'      => $s['type_code'],
    'value'     => (float) $s['value'],
    'at'        => substr($s['updated_at'], 0, 16),
    'by'        => $s['by_name'] ?? '',
], data_scoped_rows(data_scope_for($me, 'scores'),
    'SELECT sc.*, m.full_name AS by_name, e.type_code
       FROM scores sc{JOIN}
       JOIN score_exams e ON e.id = sc.exam_id
       JOIN terms t ON t.id = e.term_id
       LEFT JOIN members m ON m.id = sc.updated_by
      WHERE t.year_id = ?', 'sc.student_id', $yid, [$yid]));

// BƯỚC 2 (tải nền): chỉ cần điểm danh + điểm -> trả sớm, khỏi tính phần
// còn lại (thông báo/RSVP, nhân sự, nhật ký...). Nhẹ và nhanh hơn hẳn.
if ($part === 'heavy') {
    $heavy = ['ok' => true, 'attendances' => $attendances, 'scores' => $scores];
    Cache::set($cacheKey, $heavy, 60);
    data_out($heavy);
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
], data_scoped_rows(data_scope_for($me, 'reports'),
    'SELECT r.*, m.full_name AS by_name
       FROM reports r{JOIN}
       JOIN terms t ON t.id = r.term_id
       LEFT JOIN members m ON m.id = r.created_by
      WHERE t.year_id = ?', 'r.student_id', $yid, [$yid]));

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
// Nhân sự — danh bạ chỉ cho người có quyền 'staff' ≥ view (#78). Người chỉ
// có quyền xem không nhận hồ sơ CHỜ DUYỆT và lời nhắn đăng ký (registerNote).
// Không lọc theo lớp: GLV được xem danh bạ toàn bộ nhân sự.
// KHÔNG bỏ khoá 'members' — client gọi .filter/.find trên nó; không được xem thì [].
// ---------------------------------------------------------------
$staffLv = permission_of('staff');
$memberWhere = [];
// Ẩn tài khoản Quản trị khỏi mọi người trừ chính admin (F9).
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
    // Đã có phân công đang hiệu lực chưa (kiêm nhiệm) — giao diện dùng để KHOÁ
    // ô vai trò ở màn Nhân sự: vai/chức vụ + lớp/khối của người có phân công do
    // màn Khối & Lớp quản (backend trả 409 nếu đổi ở đây). Xem StaffService A′.
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

// ---------------------------------------------------------------
// Nhật ký — 50 dòng gần nhất (đủ xem, nhẹ bandwidth). CHỈ người được xem
// nhật ký (= quyền màn Cài đặt, can_view_logs); người khác nhận [] (#78).
// ---------------------------------------------------------------
// TODO: Chuyển sang API riêng có pagination khi có màn xem nhật ký
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

// Build result — backward compatible with ok:true at top level
$result = [
    'ok' => true,
    'notes'         => $notes,
    // 'students' được gán bên dưới (khối phân trang của master); ở đây chỉ thêm
    // Sổ Mộc cho các em trong phạm vi.
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

// Students: nếu paginated thì trả kèm metadata trong meta.pagination
if ($isPaginated) {
    $result['students'] = $students['data'];
    $result['meta'] = [
        'pagination' => [
            'total'     => $students['total'],
            'page'      => $students['page'],
            'limit'     => $students['limit'],
            'totalPages'=> ceil($students['total'] / $students['limit']),
            'hasMore'   => $students['page'] * $students['limit'] < $students['total'],
        ],
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
data_out($result);
