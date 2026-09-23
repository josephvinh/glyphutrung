<?php
/**
 * THỜI KHOÁ BIỂU LỚP
 *
 *   GET  api/schedules.php              — lấy danh sách thời khóa biểu của niên khoá hiện tại
 *   POST api/schedules.php?action=save  — tạo / cập nhật thời khóa biểu lớp
 *   POST api/schedules.php?action=delete — xoá thời khóa biểu lớp
 *
 * Quyền: cần 'edit' module programs (Quản trị / Ban điều hành).
 */
require __DIR__ . '/_bootstrap.php';

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();

switch ($action) {

    // -------------------------------------------------------------
    // Lấy danh sách thời khóa biểu
    // -------------------------------------------------------------
    case 'list':
        $me = require_login();

        $scopeIds = allowed_class_ids($me);
        if ($scopeIds !== null && !$scopeIds) json_out(['ok' => true, 'schedules' => []]);
        $sql      = 'SELECT cs.*, c.name AS class_name, p.name AS program_name
                       FROM class_schedules cs
                       JOIN classes c ON c.id = cs.class_id
                       LEFT JOIN programs p ON p.id = cs.program_id
                      WHERE cs.year_id = ?';
        $params   = [$yid];

        // Lọc theo phạm vi người dùng
        if ($scopeIds !== null) {
            $sql .= ' AND cs.class_id IN (' . implode(',', array_fill(0, count($scopeIds), '?')) . ')';
            $params = array_merge($params, $scopeIds);
        }

        $sql .= ' ORDER BY c.sort_order, cs.day_of_week, cs.start_time';

        $rows = db_all($sql, $params);
        $schedules = array_map(fn($r) => [
            'id'          => (int) $r['id'],
            'yearId'      => (int) $r['year_id'],
            'classId'     => (int) $r['class_id'],
            'className'   => $r['class_name'],
            'programId'   => $r['program_id'] !== null ? (int) $r['program_id'] : null,
            'programName' => $r['program_name'] ?? '',
            'dayOfWeek'   => (int) $r['day_of_week'],
            'startTime'   => substr($r['start_time'], 0, 5),
            'cutoffTime'  => !empty($r['cutoff_time']) ? substr($r['cutoff_time'], 0, 5) : null,
            'slot'        => $r['slot'],
            'activeFrom'  => $r['active_from'] ?? '',
            'activeTo'    => $r['active_to'] ?? '',
            'status'      => $r['status'],
        ], $rows);

        json_out(['ok' => true, 'schedules' => $schedules]);

    // -------------------------------------------------------------
    // Tạo / cập nhật thời khóa biểu
    // -------------------------------------------------------------
    case 'save':
        require_write();
        require_permission('programs', 'edit');

        $id        = (int) ($in['id'] ?? 0);
        $classId   = (int) ($in['classId'] ?? 0);
        $programId = isset($in['programId']) && $in['programId'] !== null && $in['programId'] !== ''
                     ? (int) $in['programId'] : null;
        $dow       = (int) ($in['dayOfWeek'] ?? -1);
        $start     = trim((string) ($in['startTime'] ?? ''));
        $cutoff    = trim((string) ($in['cutoffTime'] ?? ''));
        $slot      = ($in['slot'] ?? '') !== '' && in_array($in['slot'], ['sáng','chiều','tối'], true)
                     ? $in['slot'] : null;
        $activeFrom = trim((string) ($in['activeFrom'] ?? ''));
        $activeTo   = trim((string) ($in['activeTo'] ?? ''));
        $status   = ($in['status'] ?? 'kích hoạt') === 'tạm ngưng' ? 'tạm ngưng' : 'kích hoạt';

        // Validation
        if ($classId <= 0) json_fail('Vui lòng chọn lớp.');
        if ($dow < 0 || $dow > 6) json_fail('Thứ trong tuần không hợp lệ (0-6).');
        if (!preg_match('/^\d{2}:\d{2}$/', $start)) json_fail('Giờ bắt đầu không hợp lệ (HH:MM).');
        if ($cutoff !== '' && !preg_match('/^\d{2}:\d{2}$/', $cutoff)) json_fail('Giờ chốt không hợp lệ (HH:MM).');
        if ($cutoff !== '' && $cutoff <= $start) json_fail('Giờ chốt phải sau giờ bắt đầu.');

        // Kiểm tra lớp tồn tại
        if (!db_one('SELECT id FROM classes WHERE id=?', [$classId])) {
            json_fail('Lớp không tồn tại.', 404);
        }

        // Kiểm tra program nếu có
        if ($programId !== null) {
            if (!db_one('SELECT id FROM programs WHERE id=? AND year_id=?', [$programId, $yid])) {
                json_fail('Chương trình không tồn tại hoặc không thuộc niên khoá này.', 400);
            }
        }

        // Validate ngày active_from/to
        $activeFromVal = $activeFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeFrom)
                         ? $activeFrom : null;
        $activeToVal   = $activeTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $activeTo)
                         ? $activeTo : null;
        if ($activeFromVal && $activeToVal && $activeFromVal > $activeToVal) {
            json_fail('Ngày bắt đầu phải trước ngày kết thúc.');
        }

        $cutoffVal = $cutoff !== '' ? $cutoff : null;

        if ($id > 0) {
            // Cập nhật bản ghi hiện có
            if (!db_one('SELECT id FROM class_schedules WHERE id=? AND year_id=?', [$id, $yid])) {
                json_fail('Không tìm thấy thời khóa biểu.', 404);
            }
            // Chặn trùng slot khi SỬA (tránh đụng khoá uq_cs_slot -> lỗi 500)
            $dup = db_one(
                'SELECT id FROM class_schedules
                  WHERE year_id=? AND class_id=? AND day_of_week=? AND start_time=? AND program_id <=> ? AND id<>?',
                [$yid, $classId, $dow, $start, $programId, $id]);
            if ($dup) {
                json_fail('Lớp này đã có lịch vào thứ và giờ đã chọn. Vui lòng chọn giá trị khác.');
            }
            db_run('UPDATE class_schedules
                       SET class_id=?, program_id=?, day_of_week=?, start_time=?,
                           cutoff_time=?, slot=?, active_from=?, active_to=?, status=?
                     WHERE id=? AND year_id=?',
                   [$classId, $programId, $dow, $start, $cutoffVal, $slot,
                    $activeFromVal, $activeToVal, $status, $id, $yid]);
            log_action('sua', 'class_schedules', 'Sửa thời khóa biểu lớp',
                       "lớp=$classId, thứ=$dow, giờ=$start");
        } else {
            // Kiểm tra trùng lặp (cùng lớp, cùng ngày, cùng giờ, cùng program)
            $exists = db_one(
                'SELECT id FROM class_schedules
                  WHERE year_id=? AND class_id=? AND day_of_week=? AND start_time=? AND program_id <=> ?',
                [$yid, $classId, $dow, $start, $programId]);
            if ($exists) {
                json_fail('Lớp này đã có lịch vào thứ và giờ đã chọn. Vui lòng chọn giá trị khác.');
            }

            $id = db_insert('INSERT INTO class_schedules
                       (year_id, class_id, program_id, day_of_week, start_time,
                        cutoff_time, slot, active_from, active_to, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?)',
                   [$yid, $classId, $programId, $dow, $start, $cutoffVal, $slot,
                    $activeFromVal, $activeToVal, $status]);
            log_action('tao', 'class_schedules', 'Tạo thời khóa biểu lớp',
                       "lớp=$classId, thứ=$dow, giờ=$start");
        }

        Cache::flush();
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    // Xoá thời khóa biểu
    // -------------------------------------------------------------
    case 'delete':
        require_write();
        require_permission('programs', 'edit');

        $id = (int) ($in['id'] ?? 0);
        $sc = db_one('SELECT id FROM class_schedules WHERE id=? AND year_id=?', [$id, $yid]);
        if (!$sc) json_fail('Không tìm thấy thời khóa biểu.', 404);

        // Kiểm tra đã có điểm danh gắn với schedule này chưa
        if (db_one('SELECT id FROM attendances WHERE schedule_id=? LIMIT 1', [$id])) {
            json_fail('Đã có điểm danh gắn với lịch này — không thể xoá. '
                     . 'Hãy đánh dấu TẠM NGƯNG thay vì xoá.');
        }

        db_run('DELETE FROM class_schedules WHERE id=? AND year_id=?', [$id, $yid]);
        log_action('xoa', 'class_schedules', 'Xóa thời khóa biểu', "id=$id");

        Cache::flush();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    // Seed dữ liệu từ program hiện tại (tiện ích)
    // -------------------------------------------------------------
    case 'seed':
        require_write();
        require_permission('programs', 'edit');

        // Lấy các program "bắt buộc" đang kích hoạt
        $programs = db_all(
            'SELECT id, name, start_time, cutoff_time, day_of_week
               FROM programs
              WHERE year_id=? AND type="bắt buộc" AND status="kích hoạt"
              ORDER BY start_time', [$yid]);
        if (empty($programs)) {
            json_fail('Chưa có chương trình bắt buộc nào đang kích hoạt.');
        }

        $count = 0;
        foreach ($programs as $p) {
            // Lấy các lớp có học sinh đang sinh hoạt trong niên khoá
            $classes = db_all(
                "SELECT DISTINCT e.class_id
                   FROM enrollments e
                   JOIN classes c ON c.id = e.class_id
                  WHERE e.year_id=? AND e.status='đang sinh hoạt'
                  ORDER BY c.sort_order", [$yid]);

            foreach ($classes as $c) {
                // Kiểm tra đã có schedule cho lớp + program + ngày này chưa
                $exists = db_one(
                    'SELECT id FROM class_schedules
                      WHERE year_id=? AND class_id=? AND program_id=? AND day_of_week=?',
                    [$yid, $c['class_id'], $p['id'], $p['day_of_week']]);
                if ($exists) continue;

                db_insert('INSERT INTO class_schedules
                           (year_id, class_id, program_id, day_of_week, start_time, cutoff_time)
                         VALUES (?,?,?,?,?,?)',
                    [$yid, $c['class_id'], $p['id'], $p['day_of_week'],
                     substr($p['start_time'], 0, 5),
                     !empty($p['cutoff_time']) ? substr($p['cutoff_time'], 0, 5) : null]);
                $count++;
            }
        }

        Cache::flush();
        json_out(['ok' => true, 'created' => $count,
                  'message' => "Đã tạo $count lịch từ chương trình hiện tại."]);

    // -------------------------------------------------------------
    // Lấy danh sách ngoại lệ của một schedule
    // -------------------------------------------------------------
    case 'exceptions':
        $me = require_login();

        $scheduleId = (int) ($_GET['scheduleId'] ?? 0);
        if ($scheduleId <= 0) json_fail('Thiếu scheduleId.');

        // Lịch phải thuộc niên khoá hiện tại VÀ nằm trong phạm vi lớp người dùng
        // được phép xem (khớp hardening F1–F9 — không để rò ngoại lệ lớp khác).
        $sc = db_one('SELECT * FROM class_schedules WHERE id=? AND year_id=?', [$scheduleId, $yid]);
        if (!$sc) json_fail('Không tìm thấy lịch.', 404);
        $scopeIds = allowed_class_ids($me);
        if ($scopeIds !== null && !in_array((int) $sc['class_id'], $scopeIds, true)) {
            json_fail('Bạn không có quyền xem lịch của lớp này.', 403);
        }

        $exceptions = array_map(fn($e) => [
            'id'         => (int) $e['id'],
            'scheduleId' => (int) $e['schedule_id'],
            'onDate'     => $e['on_date'],
            'kind'       => 'nghỉ',
            'note'       => $e['note'] ?? '',
        ], db_all(
            'SELECT * FROM schedule_exceptions WHERE schedule_id=? ORDER BY on_date',
            [$scheduleId]));

        json_out(['ok' => true, 'exceptions' => $exceptions]);

    // -------------------------------------------------------------
    // Lưu ngoại lệ (tạo/cập nhật/xoá)
    // -------------------------------------------------------------
    case 'saveException':
        require_write();
        require_permission('programs', 'edit');

        $id         = (int) ($in['id'] ?? 0);
        $scheduleId = (int) ($in['scheduleId'] ?? 0);
        $onDate     = trim((string) ($in['onDate'] ?? ''));
        $note       = trim((string) ($in['note'] ?? ''));

        // Validation — ngoại lệ chỉ là BÁO NGHỈ một buổi
        if ($scheduleId <= 0) json_fail('Thiếu scheduleId.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $onDate)) json_fail('Ngày không hợp lệ.');

        // Kiểm tra schedule tồn tại
        if (!db_one('SELECT id FROM class_schedules WHERE id=? AND year_id=?', [$scheduleId, $yid])) {
            json_fail('Không tìm thấy lịch.', 404);
        }

        if ($id > 0) {
            // Cập nhật (chỉ ghi chú)
            if (!db_one('SELECT id FROM schedule_exceptions WHERE id=? AND schedule_id=?', [$id, $scheduleId])) {
                json_fail('Không tìm thấy ngoại lệ.', 404);
            }
            db_run('UPDATE schedule_exceptions SET note=? WHERE id=? AND schedule_id=?',
                   [$note ?: null, $id, $scheduleId]);
            log_action('sua', 'schedule_exceptions', 'Sửa báo nghỉ', "id=$id, ngày=$onDate");
        } else {
            // Chặn trùng báo nghỉ cùng (lịch, ngày) -> tránh đụng khoá uq_se (500)
            if (db_one('SELECT id FROM schedule_exceptions WHERE schedule_id=? AND on_date=?',
                       [$scheduleId, $onDate])) {
                json_fail('Ngày này đã báo nghỉ cho lịch. Hãy sửa hoặc xoá mục hiện có.');
            }
            // Tạo mới
            $id = db_insert('INSERT INTO schedule_exceptions (schedule_id, on_date, kind, note)
                            VALUES (?,?,?,?)',
                [$scheduleId, $onDate, 'nghỉ', $note ?: null]);
            log_action('tao', 'schedule_exceptions', 'Báo nghỉ buổi', "lịch=$scheduleId, ngày=$onDate");
        }

        Cache::flush();
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    // Xoá ngoại lệ
    // -------------------------------------------------------------
    case 'deleteException':
        require_write();
        require_permission('programs', 'edit');

        $id = (int) ($in['id'] ?? 0);
        // Chỉ cho xoá ngoại lệ thuộc lịch của NIÊN KHOÁ hiện tại
        if (!db_one('SELECT se.id FROM schedule_exceptions se
                       JOIN class_schedules cs ON cs.id = se.schedule_id
                      WHERE se.id=? AND cs.year_id=?', [$id, $yid])) {
            json_fail('Không tìm thấy ngoại lệ.', 404);
        }

        db_run('DELETE FROM schedule_exceptions WHERE id=?', [$id]);
        log_action('xoa', 'schedule_exceptions', 'Xóa ngoại lệ lịch', "id=$id");

        Cache::flush();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
