<?php
/**
 * EXPORT REPORTS
 *
 *   POST api/export.php?action=report      { termId, studentId, format }
 *   POST api/export.php?action=attendance   { yearId, format }
 *   POST api/export.php?action=scores       { termId, classId, format }
 *
 * Returns data URLs for browser-side download (no server file storage).
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_login();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$in     = json_input();
$action = $_GET['action'] ?? '';
$format = strtolower($in['format'] ?? 'csv');

if (!in_array($format, ['pdf', 'excel', 'csv'])) {
    json_fail('Định dạng không hỗ trợ.', 400);
}

switch ($action) {

    // -------------------------------------------------------------
    // EXPORT REPORT CARD (PDF)
    // -------------------------------------------------------------
    case 'report':
        $studentId = (int) ($in['studentId'] ?? 0);
        $termId    = (int) ($in['termId'] ?? 0);
        if (!$studentId || !$termId) json_fail('Thiếu thông tin em hoặc học kỳ.');

        // Get student info
        $student = db_one(
            "SELECT s.*, c.name AS class_name
               FROM enrollments e
               JOIN students s ON s.id = e.student_id
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE e.year_id = ? AND e.student_id = ?",
            [$year['id'], $studentId]);
        if (!$student) json_fail('Không tìm thấy em này trong niên khoá.', 404);

        // Phiếu liên lạc chứa điểm — chặn theo phạm vi 'scores' của lớp em này
        $enr = db_one('SELECT class_id FROM enrollments WHERE year_id = ? AND student_id = ?',
                      [$year['id'], $studentId]);
        if (!$enr || !can_access_class($me, 'scores', (int) $enr['class_id'], 'view')) {
            json_fail('Bạn không phụ trách lớp của em này.', 403);
        }

        // Get term info
        $term = db_one('SELECT * FROM terms WHERE id = ? AND year_id = ?', [$termId, $year['id']]);
        if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

        // Get report
        $report = db_one(
            'SELECT * FROM reports WHERE term_id = ? AND student_id = ?',
            [$termId, $studentId]);

        // Build report card HTML
        $html = build_report_card_html($student, $term, $report);

        // Generate PDF using data URL
        if ($format === 'pdf') {
            // For PDF, we'll return a print-optimized HTML that can be printed to PDF
            $url = 'data:text/html;charset=utf-8,' . rawurlencode($html);
            json_out(['ok' => true, 'url' => $url, 'filename' => 'Phieu_Lien_Lac_' . preg_replace('/\s+/', '_', $student['full_name']) . '.html']);
        } else {
            // For CSV/Excel export of report
            $csv = build_report_csv($student, $term, $report);
            $mime = $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';
            $url = 'data:' . $mime . ';charset=utf-8;base64,' . base64_encode($csv);
            $ext = $format === 'excel' ? 'xls' : 'csv';
            json_out(['ok' => true, 'url' => $url, 'filename' => 'Phieu_Lien_Lac_' . preg_replace('/\s+/', '_', $student['full_name']) . '.' . $ext]);
        }

    // -------------------------------------------------------------
    // EXPORT ATTENDANCE SHEET (CSV/Excel)
    // -------------------------------------------------------------
    case 'attendance':
        $classId = (int) ($in['classId'] ?? 0);
        $allow   = accessible_class_ids($me, 'attendance', 'view'); // null = toàn đoàn

        $dk = '';
        $params = [$year['id']];
        if ($classId > 0) {
            if ($allow !== null && !in_array($classId, $allow, true)) {
                json_fail('Bạn không phụ trách lớp này.', 403);
            }
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        } elseif ($allow !== null) {
            // Không chỉ định lớp: chỉ export các lớp trong phạm vi
            if (!$allow) json_fail('Bạn chưa được phân công lớp nào.', 403);
            $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $params = array_merge($params, $allow);
        }

        $students = db_all(
            "SELECT s.id, s.code, s.full_name, s.holy_name, c.name AS class_name
               FROM enrollments e
               JOIN students s ON s.id = e.student_id
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'{$dk}
              ORDER BY c.name, s.code",
            $params);

        // Get all sessions for the year
        $sessions = db_all(
            "SELECT p.id, p.name, p.day_of_week, p.start_time, pr.session_date
               FROM programs p
               LEFT JOIN program_sessions pr ON pr.program_id = p.id AND pr.year_id = ?
              WHERE p.year_id = ? AND p.status = 'kích hoạt'
              ORDER BY pr.session_date, p.name",
            [$year['id'], $year['id']]);

        // Build attendance matrix
        $csv = build_attendance_csv($students, $sessions, $year);

        $mime = $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';
        $url = 'data:' . $mime . ';charset=utf-8;base64,' . base64_encode($csv);
        $ext = $format === 'excel' ? 'xls' : 'csv';
        json_out(['ok' => true, 'url' => $url, 'filename' => 'Bang_Diem_Danh_' . date('Y-m-d') . '.' . $ext]);

    // -------------------------------------------------------------
    // EXPORT SCORES (CSV/Excel)
    // -------------------------------------------------------------
    case 'scores':
        $termId  = (int) ($in['termId'] ?? 0);
        $classId = (int) ($in['classId'] ?? 0);

        if (!$termId) {
            json_fail('Thiếu thông tin học kỳ.');
        }

        $term = db_one('SELECT * FROM terms WHERE id = ? AND year_id = ?', [$termId, $year['id']]);
        if (!$term) json_fail('Không tìm thấy học kỳ.', 404);

        // Lấy danh sách môn học để làm header
        $scoreTypes = db_all('SELECT * FROM score_types ORDER BY sort_order');

        // Get students — chặn theo phạm vi 'scores'
        $allow = accessible_class_ids($me, 'scores', 'view'); // null = toàn đoàn
        $dk = '';
        $params = [$year['id'], $termId];
        if ($classId > 0) {
            if ($allow !== null && !in_array($classId, $allow, true)) {
                json_fail('Bạn không phụ trách lớp này.', 403);
            }
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        } elseif ($allow !== null) {
            if (!$allow) json_fail('Bạn chưa được phân công lớp nào.', 403);
            $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $params = array_merge($params, $allow);
        }

        $students = db_all(
            "SELECT s.id, s.code, s.full_name, s.holy_name, c.name AS class_name
               FROM enrollments e
               JOIN students s ON s.id = e.student_id
               LEFT JOIN classes c ON c.id = e.class_id
              WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'{$dk}
              ORDER BY c.name, s.code",
            $params);

        // Get scores
        $scores = db_all('SELECT * FROM scores WHERE term_id = ?', [$termId]);

        $csv = build_scores_csv($students, $scores, $scoreTypes, $term);

        $mime = $format === 'excel' ? 'application/vnd.ms-excel' : 'text/csv';
        $url = 'data:' . $mime . ';charset=utf-8;base64,' . base64_encode($csv);
        $ext = $format === 'excel' ? 'xls' : 'csv';
        json_out(['ok' => true, 'url' => $url, 'filename' => 'Bang_Diem_' . preg_replace('/\s+/', '_', $term['name']) . '_' . date('Y-m-d') . '.' . $ext]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}

// -------------------------------------------------------------
// HELPER FUNCTIONS
// -------------------------------------------------------------

function build_report_card_html(array $student, array $term, ?array $report): string
{
    $holyName = htmlspecialchars($student['holy_name'] ?? '');
    $fullName = htmlspecialchars($student['full_name'] ?? '');
    $code = htmlspecialchars($student['code'] ?? '');
    $className = htmlspecialchars($student['class_name'] ?? '');
    $birthDate = $student['birth_date'] ?? '';

    $attendance = [
        'total' => 0, 'present' => 0, 'late' => 0,
        'excused' => 0, 'unexcused' => 0, 'rate' => 0
    ];
    if ($report) {
        $attendance = [
            'total' => (int) ($report['att_total'] ?? 0),
            'present' => (int) ($report['att_present'] ?? 0),
            'late' => (int) ($report['att_late'] ?? 0),
            'excused' => (int) ($report['att_excused'] ?? 0),
            'unexcused' => (int) ($report['att_unexcused'] ?? 0),
            'rate' => (int) ($report['att_rate'] ?? 0),
        ];
    }

    $score = $report['score'] ?? '';
    $conduct = $report['conduct'] ?? '';
    $rank = $report['rank_label'] ?? '';
    $remark = htmlspecialchars($report['remark'] ?? '');
    $status = $report['status'] ?? 'chưa lập';

    // Pre-compute display values to avoid ternary expressions inside heredoc
    $scoreDisplay = ($score !== '' && $score !== null) ? $score : '–';
    $conductDisplay = ($conduct !== '' && $conduct !== null) ? ucfirst($conduct) : '–';
    $rankDisplay = ($rank !== '' && $rank !== null) ? $rank : '–';
    $remarkDisplay = ($remark !== '' && $remark !== null) ? $remark : '(chưa có nhận xét)';
    $createdBy = $report['created_by'] ?? '';
    $totalLabel = $attendance['total'] ?? 0;
    $attTotal = $attendance['total'];
    $attPresent = $attendance['present'];
    $attLate = $attendance['late'];
    $attExcused = $attendance['excused'];
    $attUnexcused = $attendance['unexcused'];
    $attRate = $attendance['rate'];
    $termName = $term['name'] ?? '';
    $termFrom = $term['from'] ?? '';
    $termTo = $term['to'] ?? '';

    ob_start();
    require __DIR__ . '/../../views/partial_report_card.php';
    return ob_get_clean();
}

function build_report_csv(array $student, array $term, ?array $report): string
{
    $rows = [];
    $rows[] = "\xEF\xBB\xBF" . 'Mã số,Họ tên,Lớp,Học kỳ,Tổng buổi,Có mặt,Đi trễ,Vắng có phép,Vắng không phép,Tỷ lệ,Điểm,Hạnh kiểm,Xếp loại,Nhận xét,Trạng thái';

    $att = $report ? [
        (int) ($report['att_total'] ?? 0),
        (int) ($report['att_present'] ?? 0),
        (int) ($report['att_late'] ?? 0),
        (int) ($report['att_excused'] ?? 0),
        (int) ($report['att_unexcused'] ?? 0),
        (int) ($report['att_rate'] ?? 0),
    ] : [0, 0, 0, 0, 0, 0];

    $rows[] = sprintf('%s,%s,%s,%s,%d,%d,%d,%d,%d,%d%%,%s,%s,%s,"%s",%s',
        csv_escape($student['code'] ?? ''),
        csv_escape(($student['holy_name'] ?? '') . ' ' . ($student['full_name'] ?? '')),
        csv_escape($student['class_name'] ?? ''),
        csv_escape($term['name']),
        $att[0], $att[1], $att[2], $att[3], $att[4], $att[5],
        csv_escape($report['score'] ?? ''),
        csv_escape($report['conduct'] ?? ''),
        csv_escape($report['rank_label'] ?? ''),
        csv_escape($report['remark'] ?? ''),
        csv_escape($report['status'] ?? 'chưa lập')
    );

    return implode("\r\n", $rows);
}

function build_attendance_csv(array $students, array $sessions, array $year): string
{
    // Get attendance records
    $attendance = db_all(
        "SELECT student_id, program_id, session_date, status FROM attendances
         WHERE year_id = ?",
        [$year['id']]);

    // Index attendance by student_id|program_id|session_date
    $attIndex = [];
    foreach ($attendance as $a) {
        $key = $a['student_id'] . '|' . $a['program_id'] . '|' . $a['session_date'];
        $attIndex[$key] = $a['status'];
    }

    // Build headers: Student info + each session date
    $headers = ['Mã số', 'Họ tên', 'Lớp'];
    $sessionCols = [];

    foreach ($sessions as $s) {
        if ($s['session_date']) {
            $headers[] = date('d/m', strtotime($s['session_date']));
            $sessionCols[] = $s;
        }
    }

    $rows = [];
    $rows[] = "\xEF\xBB\xBF" . implode(',', array_map('csv_escape', $headers));

    foreach ($students as $st) {
        $row = [
            csv_escape($st['code'] ?? ''),
            csv_escape(($st['holy_name'] ?? '') . ' ' . ($st['full_name'] ?? '')),
            csv_escape($st['class_name'] ?? ''),
        ];

        $present = 0;
        $total = 0;

        foreach ($sessionCols as $sc) {
            $key = $st['id'] . '|' . $sc['program_id'] . '|' . $sc['session_date'];
            $status = $attIndex[$key] ?? null;

            if ($status !== null) {
                $total++;
                if ($status === 'có mặt' || $status === 'đi trễ') {
                    $present++;
                }
                // Short codes: P=present, L=late, E=excused, A=absent
                $shortCode = $status === 'có mặt' ? 'P' : ($status === 'đi trễ' ? 'L' : ($status === 'vắng có phép' ? 'E' : 'A'));
                $row[] = $shortCode;
            } else {
                $row[] = '-';
            }
        }

        // Add attendance rate at the end
        $rate = $total > 0 ? round($present / $total * 100) : 0;
        $row[] = $rate . '%';

        $rows[] = implode(',', $row);
    }

    return implode("\r\n", $rows);
}

function build_scores_csv(array $students, array $scores, array $scoreTypes, array $term): string
{
    // Index scores by student_id|type_code
    $scoreIndex = [];
    foreach ($scores as $s) {
        $scoreIndex[$s['student_id'] . '|' . $s['type_code']] = $s['value'];
    }

    // Build headers
    $headers = ['Mã số', 'Họ tên', 'Lớp'];
    foreach ($scoreTypes as $st) {
        $headers[] = $st['label'] ?? $st['code'];
    }
    $headers[] = 'Trung bình';

    $rows = [];
    $rows[] = "\xEF\xBB\xBF" . implode(',', array_map('csv_escape', $headers));

    foreach ($students as $st) {
        $row = [
            csv_escape($st['code'] ?? ''),
            csv_escape(($st['holy_name'] ?? '') . ' ' . ($st['full_name'] ?? '')),
            csv_escape($st['class_name'] ?? ''),
        ];

        $sum = 0;
        $count = 0;

        foreach ($scoreTypes as $stType) {
            $key = $st['id'] . '|' . $stType['code'];
            $val = $scoreIndex[$key] ?? null;

            if ($val !== null) {
                $row[] = $val;
                $sum += $val;
                $count++;
            } else {
                $row[] = '';
            }
        }

        $avg = $count > 0 ? round($sum / $count, 1) : '';
        $row[] = $avg;

        $rows[] = implode(',', $row);
    }

    return implode("\r\n", $rows);
}

function csv_escape(string $value): string
{
    // CHỐNG CSV/EXCEL FORMULA INJECTION:
    // Ô bắt đầu bằng = + - @ (hoặc tab/xuống dòng) bị Excel/Google Sheets
    // hiểu là CÔNG THỨC. Kẻ xấu đặt tên/nhận xét kiểu =HYPERLINK(...) hay
    // =cmd|... để lừa người mở file. Thêm dấu nháy đơn ở đầu -> ép thành
    // văn bản thuần, không còn là công thức.
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $value = "'" . $value;
    }
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}
