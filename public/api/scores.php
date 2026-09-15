<?php
/**
 * ĐIỂM SỐ
 *
 *   POST api/scores.php?action=set { studentId, termId, type, value }
 *
 * value là chuỗi rỗng nghĩa là XOÁ điểm, không phải chấm 0 — đúng như
 * quy tắc đã chốt ở giao diện. Nếu coi ô trống là 0 thì em chưa kiểm
 * tra sẽ bị kéo tụt điểm trung bình oan.
 */

require __DIR__ . '/_bootstrap.php';

require_write();  // hành động ghi: bắt buộc POST + CSRF
$me   = require_permission('scores', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không sửa điểm được.', 409);

$in        = json_input();
$studentId = (int) ($in['studentId'] ?? 0);
$termId    = (int) ($in['termId'] ?? 0);
$type      = (string) ($in['type'] ?? '');
$raw       = trim((string) ($in['value'] ?? ''));

if (!$studentId || !$termId || $type === '') json_fail('Thiếu thông tin em, học kỳ hoặc đầu điểm.');

// Học kỳ phải thuộc niên khoá đang mở
if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
    json_fail('Học kỳ không thuộc niên khoá đang mở.', 409);
}
if (!db_one('SELECT code FROM score_types WHERE code=?', [$type])) {
    json_fail('Đầu điểm không hợp lệ.');
}
$enr = db_one('SELECT class_id FROM enrollments WHERE year_id=? AND student_id=?', [$year['id'], $studentId]);
if (!$enr) {
    json_fail('Em này không có trong danh sách năm nay.', 404);
}
if (!can_access_class($me, 'scores', (int) $enr['class_id'], 'edit')) {
    json_fail('Bạn không phụ trách lớp của em này.', 403);
}

// Ô trống = xoá điểm
if ($raw === '') {
    db_run('DELETE FROM scores WHERE term_id=? AND student_id=? AND type_code=?',
           [$termId, $studentId, $type]);
    Cache::flush();
    json_out(['ok' => true, 'removed' => true]);
}

$value = (float) str_replace(',', '.', $raw);
if (!is_numeric(str_replace(',', '.', $raw)) || $value < 0 || $value > 10) {
    json_fail('Điểm phải là số từ 0 đến 10.');
}
$value = round($value, 1);

db_run('INSERT INTO scores (term_id, student_id, type_code, value, updated_by)
        VALUES (?,?,?,?,?)
        ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
    [$termId, $studentId, $type, $value, $me['id']]);

Cache::flush();
json_out(['ok' => true, 'removed' => false, 'value' => $value]);
