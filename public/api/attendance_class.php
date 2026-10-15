<?php
/**
 * ĐIỂM CHUYÊN CẦN THEO LỚP (mới — task 3 🟠 8)
 *
 *   GET api/attendance_class.php?term_id=1&class_id=2
 *
 * Trả 1 lần cho cả lớp, tránh N+1 của attendance_scores.php khi
 * màn điểm (scores.js) duyệt từng em. Mỗi dòng trong mảng gồm:
 *   { student_id, total_expected, total_present, total_late,
 *     total_absent, percent, rank, programs: [...] }
 *
 * Lưu ý phạm vi: phải gọi can_access_class() một lần cho cả lớp
 * (không xét từng em — tránh lộ em ngoài phạm vi, dù truy vấn
 * GROUP BY đã tự giới hạn qua JOIN enrollments).
 *
 * Ghi nhận: vắng vẫn đếm theo buổi (dự_kiến − có_mặt − trễ),
 * KHÔNG loại trừ em vào giữa kỳ — tạm chấp nhận theo mô tả hiện
 * tại, sẽ refine khi có yêu cầu thực tế từ GLV (xem mục 8 review
 * PR #285).
 */
require __DIR__ . '/_bootstrap.php';

$me = require_permission('attendance', 'view');

$termId  = (int) ($_GET['term_id'] ?? 0);
$classId = (int) ($_GET['class_id'] ?? 0);
if (!$termId)  json_fail('Thiếu term_id.');
if (!$classId) json_fail('Thiếu class_id.');

if (!can_access_class($me, 'attendance', $classId, 'view')) {
    json_fail('Bạn không phụ trách lớp này.', 403);
}

$term = db_one('SELECT id, year_id, start_date, end_date FROM terms WHERE id = ?', [$termId]);
if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

// Mục 2 review vòng 5: LEFT JOIN programs theo year_id, COALESCE(is_active, 1)
// — chưa có dòng term_programs = đang bật; dòng chỉ lưu ngoại lệ (tắt).
$programs = db_all('SELECT p.id AS program_id, p.name AS program_name
                    FROM programs p
                    LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
                    WHERE p.year_id = ? AND p.status = "kích hoạt" AND p.count_for_attendance = 1
                      AND COALESCE(tp.is_active, 1) = 1
                    ORDER BY p.sort_order, p.name', [$termId, $term['year_id']]);

// QUERY 1: buổi dự kiến theo từng CHƯƠNG TRÌNH trong học kỳ (độc lập
// từng em). Mục A review vòng 2: GROUP BY student_id, program_id đếm
// chỉ trên các dòng điểm danh của em đó, nên em vắng hoàn toàn cho
// expected=0/percent=100% (sai), và em mới vào giữa kỳ mất hết dòng.
$expectedByProgram = [];
$expectedRows = db_all("
    SELECT a.program_id, COUNT(DISTINCT a.session_date) AS c
    FROM attendances a
    WHERE a.session_date BETWEEN ? AND ?
      AND a.program_id IN (SELECT p.id FROM programs p
                           LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
                           WHERE p.year_id = ? AND p.count_for_attendance = 1
                             AND COALESCE(tp.is_active, 1) = 1)
    GROUP BY a.program_id
", [$term['start_date'], $term['end_date'], $termId, $term['year_id']]);
foreach ($expectedRows as $er) {
    $expectedByProgram[(int) $er['program_id']] = (int) $er['c'];
}

// QUERY 2: danh sách em của lớp (kể cả em không có dòng điểm danh nào
// — sẽ hiện 0% thay vì biến mất khỏi kết quả).
$students = db_all(
    'SELECT student_id FROM enrollments
      WHERE year_id = ? AND class_id = ? AND status = ?',
    [$term['year_id'], $classId, 'đang sinh hoạt']
);

// Khởi tạo mọi em với 0 mọi thứ, kèm programs[] rỗng.
$byStudent = [];
foreach ($students as $s) {
    $sid = (int) $s['student_id'];
    $byStudent[$sid] = [
        'student_id'     => $sid,
        'programs'       => [],
        'total_expected' => 0,
        'total_present'  => 0,
        'total_late'     => 0,
    ];
}

// QUERY 3: present/late theo (student, program) cho cả lớp.
$attendanceRows = db_all("
    SELECT a.student_id, a.program_id,
           SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) AS present,
           SUM(CASE WHEN a.status = 'đi trễ'  THEN 1 ELSE 0 END) AS late
    FROM attendances a
    WHERE a.session_date BETWEEN ? AND ?
      AND a.program_id IN (SELECT p.id FROM programs p
                           LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
                           WHERE p.year_id = ? AND p.count_for_attendance = 1
                             AND COALESCE(tp.is_active, 1) = 1)
      AND a.student_id IN (SELECT student_id FROM enrollments
                            WHERE year_id = ? AND class_id = ?
                              AND status = 'đang sinh hoạt')
    GROUP BY a.student_id, a.program_id
", [$term['start_date'], $term['end_date'], $termId, $term['year_id'], $term['year_id'], $classId]);

foreach ($attendanceRows as $r) {
    $sid = (int) $r['student_id'];
    $pid = (int) $r['program_id'];
    if (!isset($byStudent[$sid])) continue; // em không còn trong lớp
    $pr = (int) $r['present'];
    $lt = (int) $r['late'];
    $exp = $expectedByProgram[$pid] ?? 0;

    $byStudent[$sid]['programs'][] = [
        'program_id' => $pid,
        'expected'   => $exp,
        'present'    => $pr,
        'late'       => $lt,
        'absent'     => max(0, $exp - $pr - $lt),
    ];
    $byStudent[$sid]['total_expected'] += $exp;
    $byStudent[$sid]['total_present']  += $pr;
    $byStudent[$sid]['total_late']     += $lt;
}

// Với mỗi em: thêm programs[] theo danh sách $programs (kể cả chương
// trình em không điểm danh buổi nào), rồi tính tổng.
foreach ($byStudent as &$s) {
    $existingPids = array_column($s['programs'], 'program_id');
    foreach ($programs as $p) {
        $pid = (int) $p['program_id'];
        if (in_array($pid, $existingPids, true)) continue;
        $exp = $expectedByProgram[$pid] ?? 0;
        $s['programs'][] = [
            'program_id'   => $pid,
            'program_name' => $p['program_name'],
            'expected'     => $exp,
            'present'      => 0,
            'late'         => 0,
            'absent'       => $exp,
        ];
        $s['total_expected'] += $exp;
    }
    // Sắp xếp programs[] theo thứ tự $programs để UI ổn định.
    usort($s['programs'], function ($a, $b) use ($programs) {
        $ia = array_search($a['program_id'], array_column($programs, 'program_id'));
        $ib = array_search($b['program_id'], array_column($programs, 'program_id'));
        return $ia <=> $ib;
    });
}
unset($s);

$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds ORDER BY min_percent DESC');
foreach ($byStudent as &$s) {
    $ab = $s['total_expected'] - $s['total_present'] - $s['total_late'];
    $s['total_absent'] = max(0, $ab);
    $s['percent'] = $s['total_expected'] > 0
        ? round(($s['total_present'] + $s['total_late']) / $s['total_expected'] * 100, 2)
        : 0;
    $s['rank'] = 'KĐ';
    foreach ($thresholds as $t) {
        if ($s['percent'] >= (float) $t['min_percent']) {
            $s['rank'] = $t['label'];
            break;
        }
    }
}
unset($s);

json_out(array_values($byStudent));
