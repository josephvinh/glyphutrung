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

$term = db_one('SELECT id, start_date, end_date FROM terms WHERE id = ?', [$termId]);
if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

// Lấy các chương trình active của học kỳ (chỉ liệt kê, dùng làm tập program_id
// để lọc attendances — tránh đếm nhầm buổi của chương trình không tính CC).
$programs = db_all('SELECT tp.program_id, p.name AS program_name
                    FROM term_programs tp
                    JOIN programs p ON p.id = tp.program_id
                    WHERE tp.term_id = ? AND tp.is_active = 1
                    ORDER BY p.sort_order, p.name', [$termId]);

// 1 query GROUP BY student_id, program_id cho cả lớp — thay cho N+1 vòng
// lặp 2 truy vấn/em của attendance_scores.php cũ.
$rows = db_all("
    SELECT a.student_id,
           a.program_id,
           COUNT(DISTINCT a.session_date) AS expected,
           SUM(CASE WHEN a.status = 'có mặt' THEN 1 ELSE 0 END) AS present,
           SUM(CASE WHEN a.status = 'đi trễ'  THEN 1 ELSE 0 END) AS late
    FROM attendances a
    JOIN enrollments e ON e.student_id = a.student_id
        AND e.year_id = a.year_id
        AND e.class_id = ?
        AND e.status = 'đang sinh hoạt'
    WHERE a.session_date BETWEEN ? AND ?
      AND a.program_id IN (SELECT program_id FROM term_programs
                            WHERE term_id = ? AND is_active = 1)
    GROUP BY a.student_id, a.program_id
", [$classId, $term['start_date'], $term['end_date'], $termId]);

// Gom theo student_id; programs[] liệt kê chi tiết từng chương trình.
$byStudent = [];
foreach ($rows as $r) {
    $sid = (int) $r['student_id'];
    if (!isset($byStudent[$sid])) {
        $byStudent[$sid] = [
            'student_id'      => $sid,
            'programs'        => [],
            'total_expected'  => 0,
            'total_present'   => 0,
            'total_late'      => 0,
        ];
    }
    $exp = (int) $r['expected'];
    $pr  = (int) $r['present'];
    $lt  = (int) $r['late'];
    $byStudent[$sid]['programs'][] = [
        'program_id' => (int) $r['program_id'],
        'expected'   => $exp,
        'present'    => $pr,
        'late'       => $lt,
        'absent'     => max(0, $exp - $pr - $lt),
    ];
    $byStudent[$sid]['total_expected'] += $exp;
    $byStudent[$sid]['total_present']  += $pr;
    $byStudent[$sid]['total_late']     += $lt;
}

$thresholds = db_all('SELECT label, min_percent FROM attendance_thresholds ORDER BY sort_order DESC');
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
