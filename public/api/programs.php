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
        require_write();
        require_permission('programs', 'edit');

        $id      = (int) ($in['id'] ?? 0);
        $name    = trim((string) ($in['name'] ?? ''));
        $type    = ($in['type'] ?? 'bắt buộc') === 'chiến dịch' ? 'chiến dịch' : 'bắt buộc';
        $status  = ($in['status'] ?? 'kích hoạt') === 'đã đóng' ? 'đã đóng' : 'kích hoạt';
        $countFA = !empty($in['countForAttendance']) ? 1 : 0;
        $countEm = !empty($in['countForEmulation'])  ? 1 : 0;
        $start   = (string) ($in['startTime'] ?? '');
        $cutoff  = trim((string) ($in['cutoffTime'] ?? ''));
        $absent  = trim((string) ($in['absentTime'] ?? ''));

        // Tùy chọn hiển thị / hành vi
        $allowQr = !empty($in['allowQr']) || !isset($in['allowQr']) ? 1 : 0;   // mặc định bật
        $color   = trim((string) ($in['color'] ?? '')) ?: null;
        $icon    = trim((string) ($in['icon'] ?? '')) ?: null;
        $sortOrd = (int) ($in['sortOrder'] ?? 1);
        $autoClose = !empty($in['autoCloseAfterEvent']) ? 1 : 0;

        if ($name === '')                            json_fail('Vui lòng nhập tên chương trình.');
        if (!preg_match('/^\d{2}:\d{2}$/', $start))  json_fail('Giờ bắt đầu không hợp lệ.');
        // Hai mốc giờ BẮT BUỘC (bỏ mặc định ẩn +30'): giờ tính đi trễ và
        // giờ khoá sổ. Thứ tự: bắt đầu < tính đi trễ ≤ khoá sổ.
        if (!preg_match('/^\d{2}:\d{2}$/', $cutoff))  json_fail('Vui lòng nhập giờ tính đi trễ hợp lệ.');
        if ($cutoff <= $start)                        json_fail('Giờ tính đi trễ phải sau giờ bắt đầu.');
        if (!preg_match('/^\d{2}:\d{2}$/', $absent))  json_fail('Vui lòng nhập giờ khoá sổ (tính vắng) hợp lệ.');
        if ($absent < $cutoff)                        json_fail('Giờ khoá sổ phải từ giờ tính đi trễ trở đi.');

        // Bắt buộc -> lặp theo thứ (một hoặc NHIỀU thứ); chiến dịch -> một ngày
        $daysCsv = null;
        if ($type === 'bắt buộc') {
            $days = is_array($in['daysOfWeek'] ?? null)
                  ? array_values(array_unique(array_filter(array_map('intval', $in['daysOfWeek']),
                        fn($d) => $d >= 0 && $d <= 6)))
                  : [];
            if (!$days) {                                  // tương thích: chỉ 1 thứ
                $dow1 = (int) ($in['dayOfWeek'] ?? 0);
                if ($dow1 < 0 || $dow1 > 6) json_fail('Thứ trong tuần không hợp lệ.');
                $days = [$dow1];
            }
            sort($days);
            $dow = $days[0];                               // thứ "chính" (tương thích cột cũ)
            $daysCsv = implode(',', $days);
            $eventDate = null;
        } else {
            $dow = null;
            $eventDate = (string) ($in['eventDate'] ?? '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate)) {
                json_fail('Chương trình dạng chiến dịch cần chọn ngày diễn ra.');
            }
        }

        // Khoảng ngày áp dụng (buổi lặp)
        $effFrom = trim((string) ($in['effectiveFrom'] ?? ''));
        $effTo   = trim((string) ($in['effectiveTo'] ?? ''));
        $effFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effFrom) ? $effFrom : null;
        $effTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effTo)   ? $effTo   : null;
        if ($effFrom && $effTo && $effFrom > $effTo) json_fail('Ngày áp dụng: "từ" phải trước "đến".');

        $cutoffVal = $cutoff === '' ? null : $cutoff;
        $absentVal = $absent === '' ? null : $absent;

        // Lớp gắn (rỗng = toàn đoàn). Lọc còn các lớp thật.
        $classIds = is_array($in['classIds'] ?? null)
            ? array_values(array_unique(array_filter(array_map('intval', $in['classIds']), fn($c) => $c > 0)))
            : [];

        $cols = 'name=?, type=?, status=?, count_for_attendance=?, count_for_emulation=?,
                 start_time=?, cutoff_time=?, absent_time=?, day_of_week=?, days_of_week=?, event_date=?,
                 allow_qr=?, color=?, icon=?, sort_order=?, effective_from=?, effective_to=?, auto_close_after_event=?';
        $vals = [$name, $type, $status, $countFA, $countEm, $start, $cutoffVal, $absentVal,
                 $dow, $daysCsv, $eventDate, $allowQr, $color, $icon, $sortOrd, $effFrom, $effTo, $autoClose];

        if ($id > 0) {
            if (!db_one('SELECT id FROM programs WHERE id=? AND year_id=?', [$id, $yid])) {
                json_fail('Không tìm thấy chương trình.', 404);
            }
            db_run("UPDATE programs SET $cols WHERE id=? AND year_id=?", array_merge($vals, [$id, $yid]));
            log_action('sua', 'programs', 'Sửa chương trình ' . $name,
                       $start . ($cutoffVal ? ' – ' . $cutoffVal : ''));
        } else {
            $id = db_insert("INSERT INTO programs SET year_id=?, $cols", array_merge([$yid], $vals));
            log_action('tao', 'programs', 'Tạo chương trình ' . $name,
                       $start . ($cutoffVal ? ' – ' . $cutoffVal : ''));
        }

        // Đồng bộ lớp gắn: xoá hết rồi chèn lại theo lựa chọn (rỗng = toàn đoàn)
        db_run('DELETE FROM program_classes WHERE program_id=?', [$id]);
        if ($classIds) {
            // chỉ chèn lớp thật sự tồn tại
            $ph  = implode(',', array_fill(0, count($classIds), '?'));
            $ok  = db_all("SELECT id FROM classes WHERE id IN ($ph)", $classIds);
            $okIds = array_map(fn($r) => (int) $r['id'], $ok);
            foreach ($okIds as $cid) {
                db_run('INSERT IGNORE INTO program_classes (program_id, class_id) VALUES (?,?)', [$id, $cid]);
            }
        }

        Cache::flush();   // chương trình đổi -> mọi người nạp lại thấy ngay
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    case 'delete':
        require_write();
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
