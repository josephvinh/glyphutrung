<?php
/**
 * ĐIỂM DANH
 *
 * Actions:
 *   POST api/attendance.php?action=lookup     — bảng tra cho máy quét
 *   POST api/attendance.php?action=scan      — quét QR (theo lô)
 *   POST api/attendance.php?action=mark     — ghi một em (idempotent, cho offline)
 *   POST api/attendance.php?action=toggle   — bật/tắt một em (chạm tay)
 *   POST api/attendance.php?action=set_status — đổi có mặt ↔ đi trễ (cần "cửa sửa")
 *
 * Trạng thái có mặt hay đi trễ do MÁY CHỦ quyết định theo giờ chốt,
 * không nhận từ trình duyệt — nếu không thì đổi giờ máy điện thoại là
 * biến đi trễ thành có mặt.
 */

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/StampService.php';

require_write();
$me   = require_permission('attendance', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không điểm danh được.', 409);

$in        = json_input();
$programId = (int) ($in['programId'] ?? 0);
$rawDate   = $in['date'] ?? '';
$action    = $_GET['action'] ?? '';

// Chặn action không hợp lệ — không rơi xuống toggle
$VALID_ACTIONS = ['lookup', 'scan', 'mark', 'toggle', 'set_status'];
if (!in_array($action, $VALID_ACTIONS, true)) {
    json_fail('Action không hợp lệ.', 400);
}

if (!$programId || $rawDate === '' || $rawDate === null) json_fail('Thiếu thông tin buổi điểm danh.');

// Ngày phải ĐÚNG dạng YYYY-MM-DD, là ngày có thật, không thừa ký tự nào
// (\z: cả xuống dòng cuối cũng bị loại).
$date = is_string($rawDate) ? $rawDate : '';
if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $md)
    || !checkdate((int) $md[2], (int) $md[3], (int) $md[1])) {
    json_fail('Ngày không hợp lệ (cần dạng năm-tháng-ngày, ví dụ 2026-10-04).', 400);
}

$prog = db_one('SELECT * FROM programs WHERE id = ? AND year_id = ?', [$programId, $year['id']]);
if (!$prog) json_fail('Không tìm thấy chương trình.', 404);
if ($prog['status'] !== 'kích hoạt') json_fail('Chương trình này đã đóng.');

// Buổi phải thật sự diễn ra vào ngày đó
$dow = (int) date('w', strtotime($date));
if ($prog['type'] === 'chiến dịch') {
    $hopLe = ($prog['event_date'] === $date);
} else {
    $days = !empty($prog['days_of_week'] ?? '')
        ? array_map('intval', explode(',', $prog['days_of_week']))
        : ($prog['day_of_week'] === null ? [] : [(int) $prog['day_of_week']]);
    $hopLe = in_array($dow, $days, true);
    $effFrom = $prog['effective_from'] ?? '';
    $effTo   = $prog['effective_to'] ?? '';
    if ($hopLe && !empty($effFrom) && $date < $effFrom) $hopLe = false;
    if ($hopLe && !empty($effTo)   && $date > $effTo)   $hopLe = false;
}
if (!$hopLe) json_fail('Buổi này không diễn ra vào ngày ' . $date . '.');

// Không điểm danh trước cho buổi CHƯA diễn ra.
$laTuongLai = $date > date('Y-m-d');

// Giờ chốt do máy chủ tính — tôn trọng GIỜ CHỐT riêng của buổi.
$cutoffTs   = program_cutoff_ts($prog, $date);
$pastCutoff = time() >= $cutoffTs;

// Ngưỡng "VẮNG" (mốc 2): sau giờ này KHÔNG cho ghi có mặt nữa.
$pastAbsent = !empty($prog['absent_time'] ?? '')
    && time() >= strtotime($date . ' ' . $prog['absent_time']);

// Lớp tham gia chương trình (rỗng/NULL = áp dụng toàn đoàn)
$progClassIds = null;
if (db_has_table('program_classes')) {
    $pcRows = db_all('SELECT class_id FROM program_classes WHERE program_id=?', [$programId]);
    if ($pcRows) $progClassIds = array_map(fn($r) => (int) $r['class_id'], $pcRows);
}

// =====================================================================
//  BẢNG TRA CHO MÁY QUÉT
//
//  Phạm vi: theo LỚP (nhất quán với scan và chạm tay).
// =====================================================================
if ($action === 'lookup') {
    $lopIds = accessible_class_ids($me, 'attendance', 'edit');
    if ($lopIds !== null && !$lopIds) json_out(['ok' => true, 'items' => []]);

    $dk = ''; $tham = [$year['id']];
    if ($lopIds !== null) {
        $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($lopIds), '?')) . ')';
        $tham = array_merge($tham, $lopIds);
    }
    if ($progClassIds !== null) {
        $dk .= ' AND e.class_id IN (' . implode(',', array_fill(0, count($progClassIds), '?')) . ')';
        $tham = array_merge($tham, $progClassIds);
    }

    $rows = db_all(
        "SELECT s.code, s.id, s.full_name, c.name AS class_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes  c ON c.id = e.class_id
          WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'{$dk}
          ORDER BY s.code", $tham);

    json_out([
        'ok'    => true,
        'items' => array_map(fn($r) => [$r['code'], (int) $r['id'], $r['full_name'], $r['class_name']], $rows),
    ]);
}

// =====================================================================
//  GHI THEO LÔ TỪ MÁY QUÉT QR
//
//  Nhận MÃ SỐ chứ không nhận id, vì thẻ QR in mã số.
// =====================================================================
if ($action === 'scan') {
    if (isset($prog['allow_qr']) && !$prog['allow_qr']) json_fail('Buổi này không cho phép quét QR.');
    if ($laTuongLai) json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);
    if ($pastAbsent) json_fail('Đã quá giờ "tính vắng" của buổi — không ghi thêm được.');

    $codes = $in['codes'] ?? [];
    if (!is_array($codes) || !$codes) json_fail('Không có mã nào để ghi.');
    if (count($codes) > 200) json_fail('Mỗi lần chỉ ghi tối đa 200 mã.');

    // Chuẩn hóa mã: lowercase để khớp với DB
    $codes = array_values(array_unique(array_map(
        fn($v) => strtolower(trim((string) $v)),
        $codes
    )));

    // Lấy một lượt tất cả các em tương ứng
    $ph  = implode(',', array_fill(0, count($codes), '?'));
    $ems = db_all("SELECT s.id, s.code, s.full_name, e.status, e.class_id
                     FROM students s
                     JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?
                    WHERE s.code IN ($ph)",
                  array_merge([$year['id']], $codes));

    // Key bằng lowercase để khớp chuẩn hóa
    $theoMa = [];
    foreach ($ems as $e) $theoMa[strtolower($e['code'])] = $e;

    // Phạm vi quét: theo LỚP (dùng accessible_class_ids)
    $lopIds = accessible_class_ids($me, 'attendance', 'edit');

    $them = 0; $daCo = 0; $bo = [];

    foreach ($codes as $ma) {
        $em = $theoMa[$ma] ?? null;

        if (!$em)                                        { $bo[] = [$ma, 'không có em nào mang mã này']; continue; }
        if ($em['status'] !== 'đang sinh hoạt')         { $bo[] = [$ma, $em['full_name'] . ' không còn sinh hoạt']; continue; }
        if ($lopIds !== null && !in_array((int) $em['class_id'], $lopIds, true)) {
            $bo[] = [$ma, $em['full_name'] . ' không thuộc lớp bạn phụ trách']; continue;
        }
        if ($progClassIds !== null && !in_array((int) $em['class_id'], $progClassIds, true)) {
            $bo[] = [$ma, $em['full_name'] . ' không thuộc lớp của buổi này']; continue;
        }
        $hopLe[] = (int) $em['id'];
    }

    if (empty($hopLe)) {
        Cache::flush();
        json_out(['ok' => true, 'added' => 0, 'already' => 0, 'skipped' => $bo, 'status' => $pastCutoff ? 'đi trễ' : 'có mặt']);
    }

    $status = $pastCutoff ? 'đi trễ' : 'có mặt';
    db()->beginTransaction();
    try {
        foreach (array_chunk($hopLe, 200) as $lo) {
            $vals   = implode(',', array_fill(0, count($lo), '(?,?,?,?,?,?,?)'));
            $params = [];
            foreach ($lo as $sid) {
                array_push($params, $year['id'], $programId, $date, $sid, $status, 'qr', $me['id']);
            }
            $them += db_run("INSERT IGNORE INTO attendances
                                (year_id, program_id, session_date, student_id, status, method, marked_by)
                             VALUES $vals", $params);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        json_fail(safe_error($e, 'Ghi điểm danh thất bại, đã hoàn tác: '), 500);
    }
    $daCo = count($hopLe) - $them;

    if (program_earns_stamps($prog)) {
        foreach ($hopLe as $sid) recalc_stamps_safe($sid, $year['id']);
    }

    if ($them > 0) {
        log_action('diemdanh', 'attendance', 'Quét QR ' . $them . ' em',
                   $prog['name'] . ' · ' . $date . ' · ' . $status);
    }

    Cache::flush();
    json_out(['ok' => true, 'added' => $them, 'already' => $daCo,
              'skipped' => $bo, 'status' => $status]);
}

// =====================================================================
//  MARK — ghi một em (idempotent, cho hàng đợi offline)
// =====================================================================
if ($action === 'mark') {
    if ($laTuongLai) json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);

    $studentId = (int) ($in['studentId'] ?? 0);
    if (!$studentId) json_fail('Thiếu thông tin em cần điểm danh.');

    $st = db_one('SELECT s.full_name, e.status, e.class_id
                    FROM enrollments e JOIN students s ON s.id = e.student_id
                   WHERE e.year_id = ? AND e.student_id = ?', [$year['id'], $studentId]);
    if (!$st) json_fail('Em này không có trong danh sách năm nay.', 404);
    if ($st['status'] !== 'đang sinh hoạt') json_fail('Em này không còn sinh hoạt.');

    // Phạm vi: phải có quyền edit trên lớp của em
    if (!can_access_class($me, 'attendance', (int) $st['class_id'], 'edit')) {
        json_fail('Bạn không phụ trách lớp của em ' . $st['full_name'] . '.', 403);
    }

    // Lớp của em phải tham gia chương trình
    if ($progClassIds !== null && !in_array((int) $st['class_id'], $progClassIds, true)) {
        json_fail('Lớp của em ' . $st['full_name'] . ' không thuộc buổi này.', 400);
    }

    // Sau giờ vắng: chỉ "cửa sửa" mới bù được
    if ($pastAbsent && !can_override_session_lock($me, (int) $st['class_id'])) {
        json_fail('Đã quá giờ "tính vắng" của buổi — em ' . $st['full_name'] . ' tính vắng, không ghi được.');
    }

    // Kiểm tra đã có bản ghi chưa
    $existing = db_one('SELECT id FROM attendances
                         WHERE program_id=? AND session_date=? AND student_id=?',
                       [$programId, $date, $studentId]);
    if ($existing) {
        json_out(['ok' => true, 'added' => false, 'status' => $existing['status'] ?? 'có mặt']);
    }

    $status = $pastCutoff ? 'đi trễ' : 'có mặt';
    $added  = false;

    try {
        db_run('INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by, offline_marked)
                VALUES (?,?,?,?,?,?,?,?)',
            [$year['id'], $programId, $date, $studentId, $status, 'tay', $me['id'], $pastCutoff ? 1 : 0]);
        $added = true;
    } catch (Throwable $e) {
        // Race condition: hai request cùng lúc đều INSERT — một thắng, kia bị trùng khóa
        if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
            $existing = db_one('SELECT status FROM attendances
                                 WHERE program_id=? AND session_date=? AND student_id=?',
                               [$programId, $date, $studentId]);
            json_out(['ok' => true, 'added' => false, 'status' => $existing['status'] ?? 'có mặt']);
        }
        json_fail(safe_error($e, 'Ghi điểm danh thất bại: '), 500);
    }

    if ($added && $pastCutoff) {
        log_action('diemdanh', 'attendance', 'Ghi điểm danh cho ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · ' . $status);
    }

    if (program_earns_stamps($prog)) {
        recalc_stamps_safe($studentId, $year['id']);
    }

    Cache::flush();
    json_out([
        'ok'       => true,
        'added'    => $added,
        'status'   => $status,
        'markedAt' => date('H:i'),
        'markedBy' => $me['full_name'],
    ]);
}

// ---------------------------------------------------------------------
//  TOGGLE — chạm tay bật/tắt một em
// ---------------------------------------------------------------------
$studentId = (int) ($in['studentId'] ?? 0);
if (!$studentId) json_fail('Thiếu thông tin em cần điểm danh.');

$st = db_one('SELECT s.full_name, e.status, e.class_id
                FROM enrollments e JOIN students s ON s.id = e.student_id
               WHERE e.year_id = ? AND e.student_id = ?', [$year['id'], $studentId]);
if (!$st) json_fail('Em này không có trong danh sách năm nay.', 404);
if ($st['status'] !== 'đang sinh hoạt') json_fail('Em này không còn sinh hoạt.');

// Chạm tay = GHI. Xét theo TỪNG phân công.
if (!can_access_class($me, 'attendance', (int) $st['class_id'], 'edit')) {
    json_fail('Bạn không phụ trách lớp của em ' . $st['full_name'] . '.', 403);
}

// ---------------------------------------------------------------------
//  SET_STATUS — đổi có mặt ↔ đi trễ của bản ghi ĐÃ CÓ
// ---------------------------------------------------------------------
if ($action === 'set_status') {
    if (!can_override_session_lock($me, (int) $st['class_id'])) {
        json_fail('Chỉ admin, BĐH, trưởng khối hoặc GLV chủ nhiệm mới sửa được trạng thái điểm danh.', 403);
    }
    $moi = $in['status'] ?? '';
    if (!is_string($moi) || !in_array($moi, ['có mặt', 'đi trễ'], true)) {
        json_fail('Trạng thái không hợp lệ (chỉ "có mặt" hoặc "đi trễ").', 400);
    }
    $cu = db_one('SELECT id, status FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                 [$programId, $date, $studentId]);
    if (!$cu) {
        json_fail('Em ' . $st['full_name'] . ' chưa được ghi điểm danh buổi này, không có gì để sửa.', 404);
    }

    $doi = ($cu['status'] !== $moi);
    if ($doi) {
        db_run('UPDATE attendances SET status = ? WHERE id = ?', [$moi, $cu['id']]);
        log_action('diemdanh', 'attendance', 'Sửa trạng thái điểm danh của ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · ' . $cu['status'] . ' → ' . $moi);
        if (program_earns_stamps($prog)) {
            recalc_stamps_safe($studentId, $year['id']);
        }
        Cache::flush();
    }
    json_out(['ok' => true, 'status' => $moi, 'changed' => $doi]);
}

// ---------------------------------------------------------------------
//  TOGGLE tiếp: gỡ bản ghi cũ HOẶC ghi mới
// ---------------------------------------------------------------------
$existing = db_one('SELECT * FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                   [$programId, $date, $studentId]);

// GỠ bản ghi cũ luôn được — kể cả sau giờ chốt (gỡ bù)
if ($existing) {
    db_run('DELETE FROM attendances WHERE id = ?', [$existing['id']]);
    if ($pastCutoff) {
        log_action('diemdanh', 'attendance', 'Gỡ điểm danh của ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · đang là ' . $existing['status']);
    }
    if (program_earns_stamps($prog)) {
        recalc_stamps_safe($studentId, $year['id']);
    }
    Cache::flush();
    json_out(['ok' => true, 'removed' => true]);
}

// GHI MỚI cho buổi tương lai bị chặn.
if ($laTuongLai) {
    json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);
}

// Lớp của em phải tham gia chương trình.
if ($progClassIds !== null && !in_array((int) $st['class_id'], $progClassIds, true)) {
    json_fail('Lớp của em ' . $st['full_name'] . ' không thuộc buổi này.', 400);
}

// Sau giờ vắng: chỉ "cửa sửa" mới bù được.
if ($pastAbsent && !can_override_session_lock($me, (int) $st['class_id'])) {
    json_fail('Đã quá giờ "tính vắng" của buổi — em ' . $st['full_name'] . ' tính vắng, không ghi được.');
}

$status = $pastCutoff ? 'đi trễ' : 'có mặt';
$added  = false;

try {
    db_run('INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
            VALUES (?,?,?,?,?,?,?)',
        [$year['id'], $programId, $date, $studentId, $status, 'tay', $me['id']]);
    $added = true;
} catch (Throwable $e) {
    // Race condition: hai request cùng lúc đều INSERT — một thắng, kia bị trùng khóa
    if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
        $existing = db_one('SELECT status FROM attendances
                             WHERE program_id=? AND session_date=? AND student_id=?',
                           [$programId, $date, $studentId]);
        json_out(['ok' => true, 'removed' => false, 'status' => $existing['status'] ?? $status]);
    }
    json_fail(safe_error($e, 'Ghi điểm danh thất bại: '), 500);
}

if ($added && $pastCutoff) {
    log_action('diemdanh', 'attendance', 'Ghi điểm danh cho ' . $st['full_name'],
               $prog['name'] . ' · ' . $date . ' · ' . $status);
}

if (program_earns_stamps($prog)) {
    recalc_stamps_safe($studentId, $year['id']);
}

Cache::flush();
json_out([
    'ok'       => true,
    'removed'  => false,
    'status'   => $status,
    'markedAt' => date('H:i'),
    'markedBy' => $me['full_name'],
]);
