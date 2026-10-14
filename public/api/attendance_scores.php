<?php
/**
 * ĐIỂM CHUYÊN CẦN THEO HỌC SINH
 *
 *   GET api/attendance_scores.php?term_id=1&student_id=101
 */

require __DIR__ . '/_bootstrap.php';

$me = require_permission('attendance', 'view');

$termId    = (int) ($_GET['term_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);

if (!$termId) json_fail('Thiếu term_id.');
if (!$studentId) json_fail('Thiếu student_id.');

// Lấy term để biết năm học + khoảng ngày của học kỳ
$term = db_one('SELECT id, year_id, start_date, end_date FROM terms WHERE id = ?', [$termId]);
if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

// Phạm vi theo lớp: chỉ GLV của lớp đó (hoặc admin/BĐH/trưởng khối phụ
// trách lớp) mới xem được chuyên cần của một em — chống IDOR xem em
// lớp khác qua đoạn URL đơn giản.
$enr = db_one(
    'SELECT class_id FROM enrollments
     WHERE year_id = ? AND student_id = ? AND status = ?',
    [$term['year_id'], $studentId, 'đang sinh hoạt']
);
if (!$enr) json_fail('Em này không trong danh sách năm học của học kỳ.', 404);
if (!can_access_class($me, 'attendance', (int) $enr['class_id'], 'view')) {
    json_fail('Bạn không phụ trách lớp của em này.', 403);
}

// Lấy các chương trình active của term
$programs = db_all('
    SELECT tp.program_id, p.name as program_name
    FROM term_programs tp
    JOIN programs p ON p.id = tp.program_id
    WHERE tp.term_id = ? AND tp.is_active = 1
    ORDER BY p.sort_order, p.name
', [$termId]);

$totalExpected = 0;
$totalPresent = 0;
$totalLate = 0;
$totalAbsent = 0;
$detail = [];

// Ghi nhận: vắng vẫn đếm theo buổi (dự_kiến − có_mặt − trễ), KHÔNG loại
// trừ em vào giữa kỳ — tạm chấp nhận theo mô tả hiện tại, sẽ refine khi
// có yêu cầu thực tế từ GLV (xem mục 8 review PR #285).
foreach ($programs as $p) {
    // Đếm buổi dự kiến TRONG khoảng [term.start_date, term.end_date].
    // Trước đây JOIN theo year_id đếm cả niên khoá → không đúng "theo học
    // kỳ" như mô tả PR; fix mục 8 review PR #285.
    $expected = db_one('
        SELECT COUNT(DISTINCT a.session_date) as c
        FROM attendances a
        JOIN terms t ON t.id = ?
        WHERE a.program_id = ? AND t.id = ?
          AND a.session_date BETWEEN t.start_date AND t.end_date
    ', [$termId, $p['program_id'], $termId]);
    $exp = (int) ($expected['c'] ?? 0);

    // Đếm có mặt / đi trễ cho học sinh này, cùng khoảng ngày của học kỳ.
    $stats = db_one("
        SELECT
            SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'đi trễ' THEN 1 ELSE 0 END) as late
        FROM attendances a
        JOIN terms t ON t.id = ?
        WHERE a.program_id = ? AND t.id = ? AND a.student_id = ?
          AND a.session_date BETWEEN t.start_date AND t.end_date
    ", [$termId, $p['program_id'], $termId, $studentId]);

    $pr = (int) ($stats['present'] ?? 0);
    $lt = (int) ($stats['late'] ?? 0);
    $ab = max(0, $exp - $pr - $lt); // vắng = dự kiến - có mặt - trễ

    $totalExpected += $exp;
    $totalPresent += $pr;
    $totalLate += $lt;
    $totalAbsent += $ab;

    $percent = $exp > 0 ? round(($pr + $lt) / $exp * 100, 2) : 0;

    $detail[] = [
        'program_id' => $p['program_id'],
        'program_name' => $p['program_name'],
        'expected' => $exp,
        'present' => $pr,
        'late' => $lt,
        'absent' => $ab,
        'percent' => $percent,
    ];
}

// Tổng %
$totalPercent = $totalExpected > 0
    ? round(($totalPresent + $totalLate) / $totalExpected * 100, 2)
    : 0;

// Xếp loại theo thresholds
$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds ORDER BY min_percent DESC');
$rank = 'KĐ';
foreach ($thresholds as $t) {
    if ($totalPercent >= (float) $t['min_percent']) {
        $rank = $t['label'];
        break;
    }
}

json_out([
    'student_id' => $studentId,
    'term_id' => $termId,
    'total_expected' => $totalExpected,
    'total_present' => $totalPresent,
    'total_late' => $totalLate,
    'total_absent' => $totalAbsent,
    'percent' => $totalPercent,
    'rank' => $rank,
    'detail' => $detail,
]);
