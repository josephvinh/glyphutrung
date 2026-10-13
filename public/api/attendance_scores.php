<?php
/**
 * ĐIỂM CHUYÊN CẦN THEO HỌC SINH
 *
 *   GET api/attendance_scores.php?term_id=1&student_id=101
 */

require __DIR__ . '/_bootstrap.php';

require_login();

$termId    = (int) ($_GET['term_id'] ?? 0);
$studentId = (int) ($_GET['student_id'] ?? 0);

if (!$termId) json_fail('Thiếu term_id.');
if (!$studentId) json_fail('Thiếu student_id.');

// Lấy term để biết năm học
$term = db_one('SELECT year_id FROM terms WHERE id = ?', [$termId]);
if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

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

foreach ($programs as $p) {
    // Đếm buổi dự kiến (buổi đã điểm danh trong học kỳ)
    $expected = db_one('
        SELECT COUNT(DISTINCT a.session_date) as c
        FROM attendances a
        JOIN terms t ON t.year_id = a.year_id
        WHERE a.program_id = ? AND t.id = ?
    ', [$p['program_id'], $termId]);
    $exp = (int) ($expected['c'] ?? 0);

    // Đếm có mặt / đi trễ / vắng cho học sinh này
    $stats = db_one("
        SELECT
            SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN a.status = 'đi trễ' THEN 1 ELSE 0 END) as late
        FROM attendances a
        JOIN terms t ON t.year_id = a.year_id
        WHERE a.program_id = ? AND t.id = ? AND a.student_id = ?
    ", [$p['program_id'], $termId, $studentId]);

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
$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds ORDER BY sort_order DESC');
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
