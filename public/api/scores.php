<?php
/**
 * ĐIỂM SỐ
 *
 *   POST api/scores.php?action=set           { studentId, termId, type, exam_num, value }
 *   POST api/scores.php?action=delete_exam   { studentId, termId, type, examNum }
 *
 * value là chuỗi rỗng nghĩa là XOÁ điểm, không phải chấm 0.
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? 'set';

// ============================================================
// SHARED VALIDATION
// ============================================================
function validate_score_access(array $me, int $studentId, int $termId): array {
    $year = current_year();
    if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
    if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không sửa điểm được.', 409);

    if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
        json_fail('Học kỳ không thuộc niên khoá đang mở.', 409);
    }

    $enr = db_one('SELECT class_id FROM enrollments WHERE year_id=? AND student_id=?',
        [$year['id'], $studentId]);
    if (!$enr) json_fail('Em này không có trong danh sách năm nay.', 404);

    if (!can_access_class($me, 'scores', (int) $enr['class_id'], 'edit')) {
        json_fail('Bạn không phụ trách lớp của em này.', 403);
    }

    return $year;
}

// ============================================================
// ACTION: set — lưu 1 điểm (hỗ trợ exam_num)
// ============================================================
if ($action === 'set') {
    require_write();
    $me = require_permission('scores', 'edit');

    $in = json_input();
    $studentId = (int) ($in['studentId'] ?? 0);
    $termId    = (int) ($in['termId'] ?? 0);
    $type      = (string) ($in['type'] ?? '');
    $examNum   = max(1, (int) ($in['exam_num'] ?? 1));
    $raw       = trim((string) ($in['value'] ?? ''));

    if (!$studentId || !$termId || $type === '') {
        json_fail('Thiếu thông tin em, học kỳ hoặc đầu điểm.');
    }

    $year = validate_score_access($me, $studentId, $termId);

    if (!db_one('SELECT code FROM score_types WHERE code=?', [$type])) {
        json_fail('Đầu điểm không hợp lệ.');
    }
    $typeRow = db_one('SELECT max_exams FROM score_types WHERE code=?', [$type]);
    if ($examNum < 1 || $examNum > (int) $typeRow['max_exams']) {
        json_fail('Bài số ' . $examNum . ' vượt quá số bài tối đa (' . $typeRow['max_exams'] . ') của đầu điểm này.');
    }

    // Ô trống = xoá điểm
    if ($raw === '') {
        db_run('DELETE FROM scores WHERE term_id=? AND student_id=? AND type_code=? AND exam_num=?',
            [$termId, $studentId, $type, $examNum]);
        Cache::flush();
        json_out(['ok' => true, 'removed' => true]);
    }

    $value = (float) str_replace(',', '.', $raw);
    if (!is_numeric(str_replace(',', '.', $raw)) || $value < 0 || $value > 10) {
        json_fail('Điểm phải là số từ 0 đến 10.');
    }
    $value = round($value, 1);

    db_run('INSERT INTO scores (term_id, student_id, type_code, exam_num, value, updated_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
        [$termId, $studentId, $type, $examNum, $value, $me['id']]);

    Cache::flush();
    json_out(['ok' => true, 'removed' => false, 'value' => $value]);
}

// ============================================================
// ACTION: delete_exam — xoá 1 bài & renumber
// ============================================================
if ($action === 'delete_exam') {
    require_write();
    $me = require_permission('scores', 'edit');

    $in = json_input();
    $studentId = (int) ($in['studentId'] ?? 0);
    $termId    = (int) ($in['termId'] ?? 0);
    $type      = (string) ($in['type'] ?? '');
    $examNum   = (int) ($in['examNum'] ?? 0);

    if (!$studentId || !$termId || !$type || !$examNum) {
        json_fail('Thiếu thông tin.');
    }

    $year = validate_score_access($me, $studentId, $termId);

    $typeRow = db_one('SELECT max_exams FROM score_types WHERE code=?', [$type]);
    if (!$typeRow) json_fail('Đầu điểm không hợp lệ.', 404);
    if ($examNum < 1 || $examNum > (int) $typeRow['max_exams']) {
        json_fail('Bài số ' . $examNum . ' vượt quá số bài tối đa (' . $typeRow['max_exams'] . ').');
    }

    // Bọc transaction để lỗi giữa DELETE và renumber UPDATE không làm lệch số bài.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_run('DELETE FROM scores WHERE term_id=? AND student_id=? AND type_code=? AND exam_num=?',
            [$termId, $studentId, $type, $examNum]);
        db_run('UPDATE scores SET exam_num = exam_num - 1
                WHERE term_id=? AND student_id=? AND type_code=? AND exam_num > ?',
            [$termId, $studentId, $type, $examNum]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    Cache::flush();
    json_out(['ok' => true, 'renumbered' => true]);
}
