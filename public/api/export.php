<?php
/**
 * EXPORT REPORTS
 *
 *   POST api/export.php?action=report      { termId, studentId, format }
 *   POST api/export.php?action=attendance   { yearId, format }
 *   POST api/export.php?action=scores       { termId, classId, format }
 *
 * Trả về dữ liệu bảng ({sheet:{name,rows}, filename}); trình duyệt tự dựng file .xlsx
 * (không lưu file trên máy chủ, không cần thư viện zip phía PHP).
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_login();
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$in     = json_input();
$action = $_GET['action'] ?? '';
$format = strtolower($in['format'] ?? 'xlsx');

if (!in_array($format, ['pdf', 'xlsx'])) {
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
            json_out(['ok' => true,
                'sheet' => ['name' => 'Phiếu liên lạc', 'rows' => build_report_rows($student, $term, $report)],
                'filename' => 'Phieu_Lien_Lac_' . preg_replace('/\s+/', '_', $student['full_name']) . '.xlsx']);
        }

    // -------------------------------------------------------------
    // EXPORT ATTENDANCE SHEET (xlsx)
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
        // (bảng program_sessions không tồn tại: buổi = các ngày đã có điểm danh của chương trình)
        $sessions = db_all(
            "SELECT DISTINCT p.id AS program_id, p.name, a.session_date
               FROM attendances a
               JOIN programs p ON p.id = a.program_id
              WHERE a.year_id = ? AND p.status = 'kích hoạt'
              ORDER BY a.session_date, p.name",
            [$year['id']]);

        // Build attendance matrix
        json_out(['ok' => true,
            'sheet' => ['name' => 'Điểm danh', 'rows' => build_attendance_rows($students, $sessions, $year)],
            'filename' => 'Bang_Diem_Danh_' . date('Y-m-d') . '.xlsx']);

    // -------------------------------------------------------------
    // EXPORT SCORES (xlsx)
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
        $params = [$year['id']]; // SQL bên dưới chỉ có 1 dấu ? cho niên khoá; học kỳ chỉ dùng để lấy điểm
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

        // Get scores with exams
        $scores = db_all(
            'SELECT s.*, e.type_code FROM scores s JOIN score_exams e ON e.id = s.exam_id WHERE e.term_id = ?',
            [$termId]
        );

        json_out(['ok' => true,
            'sheet' => ['name' => 'Bảng điểm', 'rows' => build_scores_rows($students, $scores, $scoreTypes)],
            'filename' => 'Bang_Diem_' . preg_replace('/\s+/', '_', $term['name']) . '_' . date('Y-m-d') . '.xlsx']);

    // -------------------------------------------------------------
    // EXPORT ATTENDANCE DETAIL (xlsx)
    // each row = 1 attendance record with full details
    // -------------------------------------------------------------
    case 'attendance-detail':
        // GET params
        $classId  = isset($_GET['classId']) && $_GET['classId'] !== '' ? (int) $_GET['classId'] : null;
        $fromDate = $_GET['fromDate'] ?? '';
        $toDate   = $_GET['toDate'] ?? '';
        $programId = isset($_GET['programId']) && $_GET['programId'] !== '' ? (int) $_GET['programId'] : null;

        // Validate date range
        $today = date('Y-m-d');
        $yearStart = null;

        // Get school year start date
        $schoolYear = db_one('SELECT start_date, end_date FROM school_years WHERE is_current = 1 LIMIT 1');
        if ($schoolYear) {
            $yearStart = $schoolYear['start_date'];
        }

        // Default dates
        if ($fromDate === '') $fromDate = $yearStart ?: date('Y-01-01');
        if ($toDate === '')   $toDate   = $today;

        // Validate format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
            json_fail('Định dạng ngày không hợp lệ. Dùng YYYY-MM-DD.', 400);
        }

        // Validate range
        if ($fromDate > $toDate) {
            json_fail('Ngày bắt đầu phải trước ngày kết thúc.', 400);
        }

        // Max 365 days
        $daysDiff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        if ($daysDiff > 365) {
            json_fail('Khoảng thời gian không được vượt quá 365 ngày.', 400);
        }

        // Authorization check
        $allow = accessible_class_ids($me, 'attendance', 'view');
        if ($classId !== null) {
            if ($allow !== null && !in_array($classId, $allow, true)) {
                json_fail('Bạn không phụ trách lớp này.', 403);
            }
        } elseif ($allow !== null && empty($allow)) {
            json_fail('Bạn chưa được phân công lớp nào.', 403);
        }

        // Build query params
        $params = [$year['id'], $fromDate, $toDate];
        $dk = 'a.year_id = ? AND a.session_date BETWEEN ? AND ?';

        if ($classId !== null) {
            $dk .= ' AND e.class_id = ?';
            $params[] = $classId;
        } elseif ($allow !== null) {
            $dk .= ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $params = array_merge($params, $allow);
        }

        if ($programId !== null) {
            $dk .= ' AND a.program_id = ?';
            $params[] = $programId;
        }

        // Get attendance records with student + program info
        $records = db_all(
            "SELECT
                a.session_date,
                s.code AS student_code,
                CONCAT(COALESCE(s.holy_name, ''), ' ', s.full_name) AS full_name,
                c.name AS class_name,
                p.name AS program_name,
                a.status,
                '' AS note, -- attendances không có cột note
                m.full_name AS marked_by_name
             FROM attendances a
             JOIN students s ON s.id = a.student_id
             JOIN enrollments e ON e.student_id = s.id AND e.year_id = a.year_id
             JOIN classes c ON c.id = e.class_id
             JOIN programs p ON p.id = a.program_id
             LEFT JOIN members m ON m.id = a.marked_by
             WHERE {$dk}
             ORDER BY a.session_date DESC, c.name, s.code",
            $params);

        // Get approved leave requests for the same period/class
        $lrParams = [$year['id'], $fromDate, $toDate, 'đã duyệt']; // 4 dấu ? đầu: năm, từ, đến, trạng thái
        $lrDk = 'lr.year_id = ? AND lr.session_date BETWEEN ? AND ? AND lr.status = ?';

        if ($classId !== null) {
            $lrDk .= ' AND e.class_id = ?';
            $lrParams[] = $classId;
        } elseif ($allow !== null) {
            $lrDk .= ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $lrParams = array_merge($lrParams, $allow);
        }

        if ($programId !== null) {
            $lrDk .= ' AND lr.program_id = ?';
            $lrParams[] = $programId;
        }

        $leaveRequests = db_all(
            "SELECT
                lr.session_date,
                s.code AS student_code,
                CONCAT(COALESCE(s.holy_name, ''), ' ', s.full_name) AS full_name,
                c.name AS class_name,
                p.name AS program_name,
                lr.reason
             FROM leave_requests lr
             JOIN students s ON s.id = lr.student_id
             JOIN enrollments e ON e.student_id = s.id AND e.year_id = lr.year_id
             JOIN classes c ON c.id = e.class_id
             JOIN programs p ON p.id = lr.program_id
             WHERE {$lrDk}
             ORDER BY lr.session_date DESC, c.name, s.code",
            $lrParams);

        // Build attendance index to track existing records
        $attendedKeys = [];
        foreach ($records as $r) {
            $key = $r['student_code'] . '|' . $r['session_date'] . '|' . $r['program_name'];
            $attendedKeys[$key] = true;
        }

        // Build leave request index
        $leaveKeys = [];
        foreach ($leaveRequests as $lr) {
            $key = $lr['student_code'] . '|' . $lr['session_date'] . '|' . $lr['program_name'];
            $leaveKeys[$key] = $lr['reason'];
        }

        // Combine records: attendance + leave requests not already in attendance
        $rows = [];
        foreach ($records as $r) {
            $statusLabel = $r['status'];
            if ($r['status'] === 'có mặt') $statusLabel = 'Có mặt';
            elseif ($r['status'] === 'đi trễ') $statusLabel = 'Đi trễ';
            elseif ($r['status'] === 'vắng có phép') $statusLabel = 'Vắng mặt';
            elseif ($r['status'] === 'vắng không phép') $statusLabel = 'Vắng mặt';

            $rows[] = [
                'date'      => date('d/m/Y', strtotime($r['session_date'])),
                'code'      => $r['student_code'],
                'name'      => $r['full_name'],
                'class'     => $r['class_name'],
                'program'   => $r['program_name'],
                'status'    => $statusLabel,
                'note'      => $r['note'],
                'marked_by' => $r['marked_by_name'] ?? '',
            ];
        }

        // Add leave requests that are not already in attendance
        foreach ($leaveRequests as $lr) {
            $key = $lr['student_code'] . '|' . $lr['session_date'] . '|' . $lr['program_name'];
            if (!isset($attendedKeys[$key])) {
                $rows[] = [
                    'date'      => date('d/m/Y', strtotime($lr['session_date'])),
                    'code'      => $lr['student_code'],
                    'name'      => $lr['full_name'],
                    'class'     => $lr['class_name'],
                    'program'   => $lr['program_name'],
                    'status'    => 'Vắng mặt',
                    'note'      => $lr['reason'] ?? '',
                    'marked_by' => '',
                ];
            }
        }

        // Sort by date desc, then class, then code
        usort($rows, function($a, $b) {
            $dateA = DateTime::createFromFormat('d/m/Y', $a['date']);
            $dateB = DateTime::createFromFormat('d/m/Y', $b['date']);
            $dateCmp = $dateB <=> $dateA; // desc
            if ($dateCmp !== 0) return $dateCmp;
            $classCmp = strcmp($a['class'], $b['class']);
            if ($classCmp !== 0) return $classCmp;
            return strcmp($a['code'], $b['code']);
        });


        // Filename - sanitize special characters
        $className = '';
        if ($classId !== null) {
            $cls = db_one('SELECT name FROM classes WHERE id = ?', [$classId]);
            if ($cls) {
                // Remove unsafe characters: / \ : * ? " < > |
                $safeClassName = preg_replace('/[\/\\\\:*?"<>|]/u', '', $cls['name']);
                $className = preg_replace('/\s+/', '_', $safeClassName) . '_';
            }
        }
        $filename = 'Diem_Danh_' . $className . $fromDate . '_' . $toDate . '.xlsx';

        json_out(['ok' => true,
            'sheet' => ['name' => 'Điểm danh chi tiết', 'rows' => build_attendance_detail_rows($rows)],
            'filename' => $filename, 'count' => count($rows)]);

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

function build_report_rows(array $student, array $term, ?array $report): array
{
    $att = $report ? [
        (int) ($report['att_total'] ?? 0),
        (int) ($report['att_present'] ?? 0),
        (int) ($report['att_late'] ?? 0),
        (int) ($report['att_excused'] ?? 0),
        (int) ($report['att_unexcused'] ?? 0),
        (int) ($report['att_rate'] ?? 0),
    ] : [0, 0, 0, 0, 0, 0];

    return [
        ['Mã số', 'Họ tên', 'Lớp', 'Học kỳ', 'Tổng buổi', 'Có mặt', 'Đi trễ', 'Vắng có phép', 'Vắng không phép', 'Tỷ lệ', 'Điểm', 'Hạnh kiểm', 'Xếp loại', 'Nhận xét', 'Trạng thái'],
        [
            (string) ($student['code'] ?? ''),
            trim(($student['holy_name'] ?? '') . ' ' . ($student['full_name'] ?? '')),
            (string) ($student['class_name'] ?? ''),
            (string) $term['name'],
            $att[0], $att[1], $att[2], $att[3], $att[4], $att[5] . '%',
            (string) ($report['score'] ?? ''),
            (string) ($report['conduct'] ?? ''),
            (string) ($report['rank_label'] ?? ''),
            (string) ($report['remark'] ?? ''),
            (string) ($report['status'] ?? 'chưa lập'),
        ],
    ];
}

function build_attendance_rows(array $students, array $sessions, array $year): array
{
    $attendance = db_all(
        "SELECT student_id, program_id, session_date, status FROM attendances
         WHERE year_id = ?",
        [$year['id']]);

    // Index attendance by student_id|program_id|session_date
    $attIndex = [];
    foreach ($attendance as $a) {
        $attIndex[$a['student_id'] . '|' . $a['program_id'] . '|' . $a['session_date']] = $a['status'];
    }

    $headers = ['Mã số', 'Họ tên', 'Lớp'];
    $sessionCols = [];
    foreach ($sessions as $s) {
        if ($s['session_date']) {
            // Thêm tên chương trình vào tiêu đề để phân biệt các buổi cùng ngày
            $shortName = mb_substr($s['name'] ?? 'CT', 0, 3);
            $headers[] = $shortName . ' ' . date('d/m', strtotime($s['session_date']));
            $sessionCols[] = $s;
        }
    }
    $rows = [$headers];
    foreach ($students as $st) {
        $row = [
            (string) ($st['code'] ?? ''),
            trim(($st['holy_name'] ?? '') . ' ' . ($st['full_name'] ?? '')),
            (string) ($st['class_name'] ?? ''),
        ];
        foreach ($sessionCols as $sc) {
            $status = $attIndex[$st['id'] . '|' . $sc['program_id'] . '|' . $sc['session_date']] ?? null;
            if ($status === null) { $row[] = '-'; continue; }
            // P=có mặt, L=trễ, E=vắng có phép, A=vắng không phép
            $row[] = $status === 'có mặt' ? 'P' : ($status === 'đi trễ' ? 'L' : ($status === 'vắng có phép' ? 'E' : 'A'));
        }
        $rows[] = $row;
    }
    return $rows;
}

function build_scores_rows(array $students, array $scores, array $scoreTypes): array
{
    // Index by student_id|exam_id
    $scoreIndex = [];
    foreach ($scores as $s) {
        $scoreIndex[$s['student_id'] . '|' . $s['exam_id']] = $s['value'];
    }

    // Get exams for this term
    global $termId;
    $exams = db_all(
        'SELECT e.id, e.type_code FROM score_exams e WHERE e.term_id = ? ORDER BY e.type_code, e.id',
        [$termId]
    );

    $headers = ['Mã số', 'Họ tên', 'Lớp'];
    foreach ($scoreTypes as $st) {
        // Check how many exams of this type
        $typeExams = array_filter($exams, fn($e) => $e['type_code'] === $st['code']);
        $cnt = count($typeExams);
        if ($cnt <= 1) {
            $headers[] = $st['label'] ?? $st['code'];
        } else {
            foreach ($typeExams as $e) {
                $headers[] = ($st['label'] ?? $st['code']) . ' #' . $e['id'];
            }
            $headers[] = 'TB ' . ($st['label'] ?? $st['code']);
        }
    }
    $headers[] = 'Trung bình';

    $rows = [$headers];
    foreach ($students as $st) {
        $row = [
            (string) ($st['code'] ?? ''),
            trim(($st['holy_name'] ?? '') . ' ' . ($st['full_name'] ?? '')),
            (string) ($st['class_name'] ?? ''),
        ];
        $sum = 0;
        $count = 0;
        foreach ($scoreTypes as $stType) {
            $typeExams = array_filter($exams, fn($e) => $e['type_code'] === $stType['code']);
            $typeVals = [];
            foreach ($typeExams as $e) {
                $val = $scoreIndex[$st['id'] . '|' . $e['id']] ?? null;
                if ($val !== null) {
                    $typeVals[] = (float) $val;
                    $row[] = (float) $val;
                } else {
                    $row[] = '';
                }
            }
            // Average of type
            if (count($typeExams) > 1) {
                if (count($typeVals) > 0) {
                    $avg = round(array_sum($typeVals) / count($typeVals), 1);
                    $row[] = $avg;
                    $sum += $avg * (int) $stType['weight'];
                    $count += (int) $stType['weight'];
                } else {
                    $row[] = '';
                }
            }
            // Simple sum for 0 or 1 exam
            if (count($typeExams) <= 1 && count($typeVals) > 0) {
                $sum += $typeVals[0];
                $count++;
            }
        }
        $row[] = $count > 0 ? round($sum / $count, 1) : '';
        $rows[] = $row;
    }
    return $rows;
}

function build_attendance_detail_rows(array $rows): array
{
    $out = [['STT', 'Mã số', 'Họ tên', 'Lớp', 'Ngày', 'Buổi', 'Trạng thái', 'Ghi chú', 'Người ghi']];
    $seq = 1;
    foreach ($rows as $r) {
        $out[] = [
            $seq++,
            (string) ($r['code'] ?? ''),
            (string) ($r['name'] ?? ''),
            (string) ($r['class'] ?? ''),
            (string) ($r['date'] ?? ''),
            (string) ($r['program'] ?? ''),
            (string) ($r['status'] ?? ''),
            (string) ($r['note'] ?? ''),
            (string) ($r['marked_by'] ?? ''),
        ];
    }
    return $out;
}
