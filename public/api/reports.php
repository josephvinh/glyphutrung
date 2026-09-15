<?php
/**
 * SỔ LIÊN LẠC
 *
 *   POST api/reports.php?action=save   { studentId, termId, attendance{...}, score, conduct, rank, remark, send }
 *   POST api/reports.php?action=delete { studentId, termId }
 *
 * Các cột att_* là BẢN CHỤP tại thời điểm lập phiếu. Máy chủ nhận
 * nguyên con số giao diện gửi lên thay vì tự tính lại — vì đó chính
 * là con số chủ nhiệm đã nhìn thấy và chịu trách nhiệm khi bấm gửi.
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_permission('reports', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ.', 409);

$action = $_GET['action'] ?? '';
$in     = json_input();

$studentId = (int) ($in['studentId'] ?? 0);
$termId    = (int) ($in['termId'] ?? 0);

if (!$studentId || !$termId) json_fail('Thiếu thông tin em hoặc học kỳ.');
if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
    json_fail('Học kỳ không thuộc niên khoá đang mở.', 409);
}

$st = db_one('SELECT s.full_name, e.class_id FROM enrollments e JOIN students s ON s.id=e.student_id
               WHERE e.year_id=? AND e.student_id=?', [$year['id'], $studentId]);
if (!$st) json_fail('Em này không có trong danh sách năm nay.', 404);
if (!can_access_class($me, 'reports', (int) $st['class_id'], 'edit')) {
    json_fail('Bạn không phụ trách lớp của em này.', 403);
}

switch ($action) {

    // -------------------------------------------------------------
    case 'save':
        require_write();
        $send   = (bool) ($in['send'] ?? false);
        $remark = trim((string) ($in['remark'] ?? ''));
        $rawSc  = trim((string) ($in['score'] ?? ''));

        if ($rawSc !== '') {
            $v = (float) str_replace(',', '.', $rawSc);
            if (!is_numeric(str_replace(',', '.', $rawSc)) || $v < 0 || $v > 10) {
                json_fail('Điểm học lực phải là số từ 0 đến 10, hoặc để trống nếu chưa có.');
            }
        }
        if ($send && $remark === '') {
            json_fail('Vui lòng ghi nhận xét trước khi gửi phiếu cho phụ huynh.');
        }

        $a = $in['attendance'] ?? [];
        if (!is_array($a)) $a = [];
        $conduct = (string) ($in['conduct'] ?? 'tốt');
        $rank    = (string) ($in['rank'] ?? 'Trung bình');

        if (!in_array($conduct, ['tốt', 'khá', 'trung bình', 'cần cố gắng'], true)) $conduct = 'tốt';
        if (!in_array($rank, ['Giỏi', 'Khá', 'Trung bình', 'Yếu'], true)) $rank = 'Trung bình';

        db_run('INSERT INTO reports (term_id, student_id, att_present, att_late, att_excused,
                                     att_unexcused, att_total, att_rate, score, conduct,
                                     rank_label, remark, status, created_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    att_present = VALUES(att_present), att_late = VALUES(att_late),
                    att_excused = VALUES(att_excused), att_unexcused = VALUES(att_unexcused),
                    att_total = VALUES(att_total), att_rate = VALUES(att_rate),
                    score = VALUES(score), conduct = VALUES(conduct),
                    rank_label = VALUES(rank_label), remark = VALUES(remark),
                    status = VALUES(status), created_by = VALUES(created_by)',
            [$termId, $studentId,
             (int) ($a['present'] ?? 0), (int) ($a['late'] ?? 0), (int) ($a['excused'] ?? 0),
             (int) ($a['unexcused'] ?? 0), (int) ($a['total'] ?? 0), (int) ($a['rate'] ?? 0),
             $rawSc === '' ? null : round((float) str_replace(',', '.', $rawSc), 2),
             $conduct, $rank, $remark, $send ? 'đã gửi' : 'nháp', $me['id']]);

        $term = db_one('SELECT name FROM terms WHERE id=?', [$termId]);
        log_action($send ? 'duyet' : 'sua', 'reports',
                   ($send ? 'Gửi' : 'Lưu nháp') . ' phiếu liên lạc của ' . $st['full_name'],
                   $term['name'] . ' · xếp loại ' . $rank);

        json_out(['ok' => true, 'createdBy' => $me['full_name'], 'createdAt' => date('Y-m-d H:i')]);

    // -------------------------------------------------------------
    case 'delete':
        require_write();
        db_run('DELETE FROM reports WHERE term_id=? AND student_id=?', [$termId, $studentId]);
        log_action('xoa', 'reports', 'Xóa phiếu liên lạc của ' . $st['full_name'], '');
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
