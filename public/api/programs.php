<?php
/**
 * CHƯƠNG TRÌNH SINH HOẠT
 *
 *   POST api/programs.php?action=save    { id, name, type, status,
 *          countForAttendance, startTime, cutoffTime, dayOfWeek, eventDate }
 *   POST api/programs.php?action=delete  { id }
 *
 * Quyền: cần 'edit' module programs (Quản trị / Ban điều hành).
 * Giờ chốt: cutoffTime rỗng -> lưu NULL (máy khách hiểu là start + 30').
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
    case 'save':
        require_post();
        require_csrf();
        require_permission('programs', 'edit');

        $id      = (int) ($in['id'] ?? 0);
        $name    = trim((string) ($in['name'] ?? ''));
        $type    = ($in['type'] ?? 'bắt buộc') === 'chiến dịch' ? 'chiến dịch' : 'bắt buộc';
        $status  = ($in['status'] ?? 'kích hoạt') === 'đã đóng' ? 'đã đóng' : 'kích hoạt';
        $countFA = !empty($in['countForAttendance']) ? 1 : 0;
        $start   = (string) ($in['startTime'] ?? '');
        $cutoff  = trim((string) ($in['cutoffTime'] ?? ''));

        if ($name === '')                            json_fail('Vui lòng nhập tên chương trình.');
        if (!preg_match('/^\d{2}:\d{2}$/', $start))  json_fail('Giờ bắt đầu không hợp lệ.');
        if ($cutoff !== '' && !preg_match('/^\d{2}:\d{2}$/', $cutoff)) json_fail('Giờ chốt không hợp lệ.');
        if ($cutoff !== '' && $cutoff <= $start)      json_fail('Giờ chốt phải sau giờ bắt đầu.');

        // Bắt buộc -> lặp theo thứ; chiến dịch -> một ngày cụ thể
        if ($type === 'bắt buộc') {
            $dow = (int) ($in['dayOfWeek'] ?? 0);
            if ($dow < 0 || $dow > 6) json_fail('Thứ trong tuần không hợp lệ.');
            $eventDate = null;
        } else {
            $dow = null;
            $eventDate = (string) ($in['eventDate'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                json_fail('Chương trình dạng chiến dịch cần chọn ngày diễn ra.');
            }
        }

        $cutoffVal = $cutoff === '' ? null : $cutoff;

        if ($id > 0) {
            if (!db_one('SELECT id FROM programs WHERE id=? AND year_id=?', [$id, $yid])) {
                json_fail('Không tìm thấy chương trình.', 404);
            }
            db_run('UPDATE programs
                       SET name=?, type=?, status=?, count_for_attendance=?,
                           start_time=?, cutoff_time=?, day_of_week=?, event_date=?
                     WHERE id=? AND year_id=?',
                   [$name, $type, $status, $countFA, $start, $cutoffVal, $dow, $eventDate, $id, $yid]);
            log_action('sua', 'programs', 'Sửa chương trình ' . $name,
                       $start . ($cutoffVal ? ' – ' . $cutoffVal : ''));
        } else {
            $id = db_insert('INSERT INTO programs
                       (year_id, name, type, status, count_for_attendance,
                        start_time, cutoff_time, day_of_week, event_date)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                   [$yid, $name, $type, $status, $countFA, $start, $cutoffVal, $dow, $eventDate]);
            log_action('tao', 'programs', 'Tạo chương trình ' . $name,
                       $start . ($cutoffVal ? ' – ' . $cutoffVal : ''));
        }

        Cache::flush();   // chương trình đổi -> mọi người nạp lại thấy ngay
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    case 'delete':
        require_post();
        require_csrf();
        require_permission('programs', 'edit');

        $id   = (int) ($in['id'] ?? 0);
        $prog = db_one('SELECT name FROM programs WHERE id=? AND year_id=?', [$id, $yid]);
        if (!$prog) json_fail('Không tìm thấy chương trình.', 404);

        // Đã có điểm danh gắn với chương trình này thì KHÔNG xoá (mất dữ liệu
        // điểm danh). Muốn ẩn thì đóng chương trình (status='đã đóng').
        if (db_one('SELECT id FROM attendances WHERE program_id=? LIMIT 1', [$id])) {
            json_fail('Chương trình đã có buổi điểm danh — không thể xoá. Hãy ĐÓNG chương trình thay vì xoá.');
        }

        db_run('DELETE FROM programs WHERE id=? AND year_id=?', [$id, $yid]);
        log_action('xoa', 'programs', 'Xóa chương trình ' . $prog['name'], '');

        Cache::flush();
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
