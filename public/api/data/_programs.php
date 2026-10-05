<?php
/**
 * DATA API — Programs Module
 *
 * Chương trình + program_classes (lớp tham gia)
 */

/**
 * Lấy danh sách chương trình.
 * Bao gồm: auto-close chiến dịch đã qua, tất cả cột cần thiết.
 *
 * @return array Chương trình đã format
 */
function data_load_programs(int $yid, string $yearStatus, string $part): array
{
    // Tự đóng các chương trình "chiến dịch" đã qua ngày (nếu bật auto_close).
    // Lười: chỉ chạy khi cột tồn tại; một UPDATE gọn, không đụng chương trình khác.
    // Bỏ qua ở bước 'heavy' (chỉ trả điểm danh/điểm, KHÔNG gửi programs).
    // Bỏ qua khi niên khoá ĐÃ KHOÁ SỔ (chỉ đọc): mọi endpoint ghi khác đều chặn.
    if ($part !== 'heavy'
        && $yearStatus !== 'đã khóa'
        && db_has_column('programs', 'auto_close_after_event')) {
        db_run("UPDATE programs SET status='đã đóng'
                 WHERE year_id=? AND type='chiến dịch' AND status='kích hoạt'
                   AND auto_close_after_event=1 AND event_date IS NOT NULL AND event_date < CURDATE()",
               [$yid]);
    }

    return array_map(fn($p) => [
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
}

/**
 * Lấy danh sách lớp tham gia mỗi chương trình.
 * RỖNG với một chương trình = áp dụng toàn đoàn.
 *
 * @return object Map programId -> [classId,...]
 */
function data_load_program_classes(int $yid): object
{
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
    return (object) $programClasses;
}
