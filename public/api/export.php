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

        // Get score types
        $scoreTypes = db_all('SELECT * FROM score_types ORDER BY display_order');

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

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Phiếu Liên Lạc - {$fullName}</title>
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Times New Roman', serif; padding: 20px; max-width: 800px; margin: 0 auto; }
.card { border: 2px solid #333; border-radius: 8px; padding: 24px; }
.header { text-align: center; border-bottom: 1px solid #ccc; padding-bottom: 16px; margin-bottom: 20px; }
.header .org { font-size: 14px; font-weight: bold; color: #666; letter-spacing: 2px; }
.header h1 { font-size: 22px; margin: 8px 0; }
.header .term { font-size: 12px; color: #666; }
.info { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; font-size: 14px; }
.info span { color: #666; }
.section { margin-bottom: 16px; }
.section h3 { font-size: 14px; border-bottom: 1px solid #eee; padding-bottom: 4px; margin-bottom: 8px; }
.stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; text-align: center; margin-bottom: 12px; }
.stats div { padding: 8px; background: #f5f5f5; border-radius: 4px; }
.stats .val { font-size: 20px; font-weight: bold; }
.stats .lbl { font-size: 10px; color: #666; }
.grades { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center; margin-bottom: 16px; }
.grades div { padding: 12px; border: 1px solid #ddd; border-radius: 4px; }
.grades .val { font-size: 18px; font-weight: bold; }
.grades .lbl { font-size: 10px; color: #666; margin-top: 4px; }
.rank { background: #e3f2fd; border-color: #2196f3 !important; }
.rank .val { color: #1565c0; }
.remark { background: #fafafa; padding: 12px; border-radius: 4px; margin-bottom: 16px; font-style: italic; }
.signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: center; margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee; }
.signatures p { font-size: 11px; color: #666; margin-bottom: 40px; }
.status { text-align: right; font-size: 12px; color: #888; margin-top: 8px; }
@media print { body { padding: 0; } .card { border: 1px solid #000; } }
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <p class="org">ĐOÀN THIẾU NHI THÁNH THỂ</p>
        <h1>PHIẾU LIÊN LẠC</h1>
        <p class="term">{$termName} ({$termFrom} – {$termTo})</p>
    </div>

    <div class="info">
        <div><span>Họ và tên:</span> <strong>{$holyName} {$fullName}</strong></div>
        <div><span>Mã số:</span> <strong>{$code}</strong></div>
        <div><span>Lớp:</span> <strong>{$className}</strong></div>
        <div><span>Ngày sinh:</span> <strong>{$birthDate}</strong></div>
    </div>

    <div class="section">
        <h3>CHUYÊN CẦN</h3>
        <div class="stats">
            <div><div class="val">{$attTotal}</div><div class="lbl">Tổng số buổi</div></div>
            <div><div class="val" style="color:#2e7d32">{$attPresent}</div><div class="lbl">Có mặt</div></div>
            <div><div class="val" style="color:#f57c00">{$attLate}</div><div class="lbl">Đi trễ</div></div>
            <div><div class="val" style="color:#1976d2">{$attExcused}</div><div class="lbl">Có phép</div></div>
            <div><div class="val" style="color:#c62828">{$attUnexcused}</div><div class="lbl">Không phép</div></div>
        </div>
        <div style="text-align:center; font-weight:bold;">Tỷ lệ có mặt: <span style="font-size:18px">{$attRate}%</span></div>
    </div>

    <div class="section">
        <h3>HỌC TẬP & HẠNH KIỂM</h3>
        <div class="grades">
            <div>
                <div class="val">{$scoreDisplay}</div>
                <div class="lbl">Điểm học lực</div>
            </div>
            <div>
                <div class="val" style="font-size:14px; text-transform:capitalize">{$conductDisplay}</div>
                <div class="lbl">Hạnh kiểm</div>
            </div>
            <div class="rank">
                <div class="val">{$rankDisplay}</div>
                <div class="lbl">Xếp loại</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h3>NHẬN XÉT CỦA GIÁO LÝ VIÊN</h3>
        <div class="remark">{$remarkDisplay}</div>
    </div>

    <div class="signatures">
        <div>
            <p>GLV CHỦ NHIỆM</p>
            <p>{$createdBy}</p>
        </div>
        <div>
            <p>PHỤ HUYNH KÝ TÊN</p>
            <p>.....................</p>
        </div>
    </div>

    <div class="status">Trạng thái: {$status}</div>
</div>
</body>
</html>
HTML;
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
