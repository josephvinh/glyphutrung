<?php
/**
 * LỊCH CÁ NHÂN — ghi chú riêng tư của từng thành viên.
 *
 *   POST api/notes.php?action=list
 *   POST api/notes.php?action=save    { id?, title, note, date (YYYY-MM-DD), time (HH:MM|''), allDay }
 *   POST api/notes.php?action=toggle  { id }         — đánh dấu xong / chưa
 *   POST api/notes.php?action=delete  { id }
 *
 * Mọi truy vấn ràng member_id = người đăng nhập. Không ai đụng ghi chú
 * của người khác — kể cả Quản trị (đây là việc riêng tư).
 */

require __DIR__ . '/_bootstrap.php';

$me     = require_login();
$mid    = (int) $me['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();

/** Xoá cache data.php của chính người này để lần nạp sau thấy ngay */
function xoa_cache_cua(int $mid): void
{
    $y = current_year();
    // Xoá cả 3 part (core/heavy/all) vì cache data.php nay tách theo part
    if ($y) foreach (['_core', '_heavy', '_all'] as $p) {
        Cache::del('data_' . (int) $y['id'] . '_' . $mid . $p);
    }
}

/** Gộp ngày + giờ thành DATETIME; việc cả ngày thì nhắc 07:00 */
function ghep_remind_at(string $date, string $time, bool $allDay): ?string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;
    $hhmm = $allDay ? '07:00' : (preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '07:00');
    return $date . ' ' . $hhmm . ':00';
}

/** Một ghi chú -> hình dạng cho giao diện */
function note_ra(array $r): array
{
    return [
        'id'       => (int) $r['id'],
        'title'    => $r['title'],
        'note'     => $r['note'] ?? '',
        'remindAt' => substr($r['remind_at'], 0, 16),   // 'YYYY-MM-DD HH:MM'
        'allDay'   => (bool) $r['all_day'],
        'done'     => (bool) $r['done'],
    ];
}

switch ($action) {

    // -------------------------------------------------------------
    case 'list':
        $rows = db_all('SELECT * FROM personal_notes WHERE member_id = ? ORDER BY remind_at', [$mid]);
        json_out(['ok' => true, 'notes' => array_map('note_ra', $rows)]);

    // -------------------------------------------------------------
    case 'save':
        require_write();

        $id     = (int) ($in['id'] ?? 0);
        $title  = trim((string) ($in['title'] ?? ''));
        $note   = trim((string) ($in['note'] ?? ''));
        $date   = trim((string) ($in['date'] ?? ''));
        $time   = trim((string) ($in['time'] ?? ''));
        $allDay = !empty($in['allDay']);

        if ($title === '') json_fail('Vui lòng nhập tên việc.');
        $remindAt = ghep_remind_at($date, $time, $allDay);
        if ($remindAt === null) json_fail('Ngày nhắc không hợp lệ.');

        $title = mb_substr($title, 0, 160);
        $note  = mb_substr($note, 0, 2000);
        $now   = date('Y-m-d H:i:s');

        if ($id) {
            // Chỉ sửa được ghi chú của CHÍNH MÌNH
            $old = db_one('SELECT id FROM personal_notes WHERE id = ? AND member_id = ?', [$id, $mid]);
            if (!$old) json_fail('Không tìm thấy ghi chú.', 404);
            // Đổi giờ nhắc -> cho phép nhắc lại (notified_at về NULL)
            db_run('UPDATE personal_notes SET title=?, note=?, remind_at=?, all_day=?, notified_at=NULL, updated_at=?
                     WHERE id=? AND member_id=?',
                [$title, $note, $remindAt, $allDay ? 1 : 0, $now, $id, $mid]);
        } else {
            $id = db_insert('INSERT INTO personal_notes (member_id, title, note, remind_at, all_day, created_at, updated_at)
                             VALUES (?,?,?,?,?,?,?)',
                [$mid, $title, $note, $remindAt, $allDay ? 1 : 0, $now, $now]);
        }

        xoa_cache_cua($mid);
        json_out(['ok' => true, 'id' => (int) $id, 'remindAt' => substr($remindAt, 0, 16)]);

    // -------------------------------------------------------------
    case 'toggle':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        $n  = db_one('SELECT done FROM personal_notes WHERE id = ? AND member_id = ?', [$id, $mid]);
        if (!$n) json_fail('Không tìm thấy ghi chú.', 404);
        $moi = $n['done'] ? 0 : 1;
        db_run('UPDATE personal_notes SET done=?, updated_at=? WHERE id=? AND member_id=?',
               [$moi, date('Y-m-d H:i:s'), $id, $mid]);
        xoa_cache_cua($mid);
        json_out(['ok' => true, 'done' => (bool) $moi]);

    // -------------------------------------------------------------
    case 'delete':
        require_write();
        $id = (int) ($in['id'] ?? 0);
        db_run('DELETE FROM personal_notes WHERE id = ? AND member_id = ?', [$id, $mid]);
        xoa_cache_cua($mid);
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
