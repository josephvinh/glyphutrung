<?php
/**
 * In Phiếu Liên Lạc
 * Hỗ trợ in 1 em hoặc in toàn bộ lớp.
 */
require __DIR__ . '/api/_bootstrap.php';

// Override json header set by _bootstrap.php
header('Content-Type: text/html; charset=utf-8');

$me = require_login();
$year = current_year();
if (!$year) die('Chưa có niên khoá nào đang mở.');

$type = $_GET['type'] ?? '';
$termId = (int)($_GET['termId'] ?? 0);

if (!$termId) die('Thiếu thông tin học kỳ.');

$term = db_one('SELECT * FROM terms WHERE id = ? AND year_id = ?', [$termId, $year['id']]);
if (!$term) die('Không tìm thấy học kỳ.');

$htmls = [];

if ($type === 'report') {
    // In 1 em
    $studentId = (int)($_GET['studentId'] ?? 0);
    $student = db_one(
        "SELECT s.*, c.name AS class_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           LEFT JOIN classes c ON c.id = e.class_id
          WHERE e.year_id = ? AND e.student_id = ?",
        [$year['id'], $studentId]
    );
    if (!$student) die('Không tìm thấy em này.');

    $enr = db_one('SELECT class_id FROM enrollments WHERE year_id = ? AND student_id = ?', [$year['id'], $studentId]);
    if (!$enr || !can_access_class($me, 'scores', (int)$enr['class_id'], 'view')) {
        die('Bạn không phụ trách lớp của em này.');
    }

    $report = db_one('SELECT * FROM reports WHERE term_id = ? AND student_id = ?', [$termId, $studentId]);
    
    // Nếu chưa có phiếu thì bỏ qua hoặc hiện nháp?
    // Nên hiện nháp hoặc hiện form trống
    $htmls[] = build_report_html($student, $term, $report);

} elseif ($type === 'class_reports') {
    // In cả lớp
    $className = $_GET['className'] ?? '';
    if (!$className) die('Thiếu thông tin lớp.');

    $class = db_one('SELECT id FROM classes WHERE name = ?', [$className]);
    if (!$class) die('Lớp không tồn tại.');

    if (!can_access_class($me, 'scores', (int)$class['id'], 'view')) {
        die('Bạn không phụ trách lớp này.');
    }

    $idsFilter = '';
    $params = [$year['id'], $class['id']];
    if (!empty($_GET['ids'])) {
        $ids = explode(',', $_GET['ids']);
        $validIds = [];
        foreach ($ids as $id) { if (is_numeric($id)) $validIds[] = (int)$id; }
        if (!empty($validIds)) {
            $idsFilter = ' AND s.id IN (' . implode(',', $validIds) . ')';
        }
    }

    $students = db_all(
        "SELECT s.*, c.name AS class_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           LEFT JOIN classes c ON c.id = e.class_id
          WHERE e.year_id = ? AND e.class_id = ? AND e.status = 'đang sinh hoạt' {$idsFilter}
          ORDER BY s.code",
        $params
    );

    foreach ($students as $student) {
        $report = db_one('SELECT * FROM reports WHERE term_id = ? AND student_id = ?', [$termId, $student['id']]);
        if ($report) {
            $htmls[] = build_report_html($student, $term, $report);
        }
    }

    if (empty($htmls)) die('Lớp này chưa lập phiếu liên lạc nào.');
} else {
    die('Yêu cầu không hợp lệ.');
}

// -------------------------------------------------------------
function build_report_html(array $student, array $term, ?array $report): string
{
    $report = $report ?? [];
    $holyName = htmlspecialchars($student['holy_name'] ?? '');
    $fullName = htmlspecialchars($student['full_name'] ?? '');
    $code = htmlspecialchars($student['code'] ?? '');
    $className = htmlspecialchars($student['class_name'] ?? '');
    $birthDate = $student['birth_date'] ?? '';

    $attendance = [
        'total' => (int)($report['att_total'] ?? 0),
        'present' => (int)($report['att_present'] ?? 0),
        'late' => (int)($report['att_late'] ?? 0),
        'excused' => (int)($report['att_excused'] ?? 0),
        'unexcused' => (int)($report['att_unexcused'] ?? 0),
        'rate' => (int)($report['att_rate'] ?? 0),
    ];

    $score = $report['score'] ?? '';
    $conduct = $report['conduct'] ?? '';
    $rank = $report['rank_label'] ?? '';
    $remark = htmlspecialchars($report['remark'] ?? '');
    $status = $report['status'] ?? 'chưa lập';

    $scoreDisplay = ($score !== '' && $score !== null) ? $score : '–';
    $conductDisplay = ($conduct !== '' && $conduct !== null) ? ucfirst($conduct) : '–';
    $rankDisplay = ($rank !== '' && $rank !== null) ? $rank : '–';
    $remarkDisplay = ($remark !== '' && $remark !== null) ? $remark : '(chưa có nhận xét)';
    $createdBy = '';
    if (!empty($report['created_by'])) {
        $creator = db_one('SELECT full_name, holy_name FROM members WHERE id = ?', [$report['created_by']]);
        if ($creator) {
            $createdBy = ($creator['holy_name'] ? $creator['holy_name'] . ' ' : '') . $creator['full_name'];
        }
    }

    $termName = htmlspecialchars($term['name'] ?? '');
    $termFrom = isset($term['start_date']) ? date('d/m', strtotime($term['start_date'])) : '';
    $termTo = isset($term['end_date']) ? date('d/m/Y', strtotime($term['end_date'])) : '';

    // Lấy chi tiết điểm của em này để hiển thị trong phiếu liên lạc
    $scores = db_all(
        "SELECT st.code, st.label, sc.value 
         FROM score_types st 
         LEFT JOIN scores sc ON sc.type_code = st.code AND sc.student_id = ? AND sc.term_id = ?
         ORDER BY st.sort_order", 
        [$student['id'], $term['id']]
    );
    
    $scoreRows = '';
    if (!empty($scores)) {
        $scoreRows .= '<div class="detailed-scores-container">';
        $scoreRows .= '<h4 class="section-subtitle">CHI TIẾT ĐIỂM</h4>';
        $scoreRows .= '<div class="detailed-scores-grid">';
        foreach ($scores as $s) {
            $val = $s['value'] !== null ? number_format((float)$s['value'], 1) : '-';
            $scoreRows .= '<div class="score-box">';
            $scoreRows .= '<div class="score-val">' . $val . '</div>';
            $scoreRows .= '<div class="score-lbl">' . htmlspecialchars($s['label'] ?: $s['code']) . '</div>';
            $scoreRows .= '</div>';
        }
        $scoreRows .= '</div></div>';
    }

    ob_start();
    ?>
    <div class="card">
        <div class="header">
            <div class="header-content">
                <p class="org">ĐOÀN THIẾU NHI THÁNH THỂ PHÚ TRÚNG</p>
                <h1>PHIẾU LIÊN LẠC</h1>
                <p class="term"><?= $termName ?> (<?= $termFrom ?> – <?= $termTo ?>)</p>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-item"><span>Họ và tên:</span> <strong><?= $holyName ?> <?= $fullName ?></strong></div>
            <div class="info-item"><span>Mã số:</span> <strong><?= $code ?></strong></div>
            <div class="info-item"><span>Lớp:</span> <strong><?= $className ?></strong></div>
            <div class="info-item"><span>Ngày sinh:</span> <strong><?= $birthDate ? date('d/m/Y', strtotime($birthDate)) : '' ?></strong></div>
        </div>

        <div class="content-wrapper">
            <div class="main-column">
                <div class="section">
                    <h3 class="section-title">HỌC TẬP & HẠNH KIỂM</h3>
                    
                    <?= $scoreRows ?>
                    
                    <div class="grades-grid">
                        <div class="grade-box">
                            <div class="val"><?= $scoreDisplay ?></div>
                            <div class="lbl">Điểm trung bình</div>
                        </div>
                        <div class="grade-box">
                            <div class="val text-capitalize"><?= $conductDisplay ?></div>
                            <div class="lbl">Hạnh kiểm</div>
                        </div>
                        <div class="grade-box rank-box">
                            <div class="val"><?= $rankDisplay ?></div>
                            <div class="lbl">Xếp loại</div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h3 class="section-title">NHẬN XÉT CỦA GIÁO LÝ VIÊN</h3>
                    <div class="remark-box"><?= nl2br($remarkDisplay) ?></div>
                </div>
            </div>

            <div class="side-column">
                <div class="section">
                    <h3 class="section-title">CHUYÊN CẦN</h3>
                    <div class="stats-list">
                        <div class="stat-item"><span class="lbl">Tổng số buổi</span><span class="val"><?= $attendance['total'] ?></span></div>
                        <div class="stat-item"><span class="lbl">Có mặt</span><span class="val text-success"><?= $attendance['present'] ?></span></div>
                        <div class="stat-item"><span class="lbl">Đi trễ</span><span class="val text-warning"><?= $attendance['late'] ?></span></div>
                        <div class="stat-item"><span class="lbl">Có phép</span><span class="val text-primary"><?= $attendance['excused'] ?></span></div>
                        <div class="stat-item"><span class="lbl">Không phép</span><span class="val text-danger"><?= $attendance['unexcused'] ?></span></div>
                    </div>
                    <div class="rate-box">
                        Tỷ lệ có mặt: <strong><?= $attendance['rate'] ?>%</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="signatures">
            <div class="sig-block">
                <p class="sig-title">GLV CHỦ NHIỆM</p>
                <div class="sig-space"></div>
                <p class="sig-name"><?= htmlspecialchars($createdBy) ?></p>
            </div>
            <div class="sig-block">
                <p class="sig-title">PHỤ HUYNH KÝ TÊN</p>
                <div class="sig-space"></div>
                <p class="sig-name">...................................</p>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>In Phiếu Liên Lạc</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --primary: #1e3a8a;
    --primary-light: #eff6ff;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --border: #e2e8f0;
    --success: #16a34a;
    --warning: #d97706;
    --danger: #dc2626;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { 
    font-family: 'Be Vietnam Pro', system-ui, sans-serif; 
    background: #f8fafc; 
    color: var(--text-main);
    line-height: 1.5;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
}

.card-wrapper { 
    width: 210mm; /* A4 width */
    min-height: 297mm; /* A4 height */
    margin: 40px auto; 
    background: #fff; 
    padding: 20mm; /* A4 margins */
    box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
    border-radius: 8px; 
}
.card { 
    border: 2px solid var(--primary); 
    border-radius: 12px; 
    padding: 30px; 
    height: 100%;
    position: relative; 
}
.card::before {
    content: '';
    position: absolute;
    top: 6px; left: 6px; right: 6px; bottom: 6px;
    border: 1px solid var(--primary);
    border-radius: 8px;
    opacity: 0.2;
    pointer-events: none;
}

.header { text-align: center; border-bottom: 2px solid var(--border); padding-bottom: 20px; margin-bottom: 24px; position: relative; }
.header .org { font-size: 13px; font-weight: 700; color: var(--text-muted); letter-spacing: 1.5px; text-transform: uppercase; }
.header h1 { font-size: 26px; font-weight: 800; color: var(--primary); margin: 8px 0; letter-spacing: 1px; }
.header .term { font-size: 14px; font-weight: 500; color: var(--text-muted); }

.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 28px; background: var(--primary-light); padding: 16px 20px; border-radius: 8px; }
.info-item { font-size: 15px; }
.info-item span { color: var(--text-muted); display: inline-block; width: 85px; }
.info-item strong { color: var(--primary); font-weight: 700; }

.content-wrapper { display: grid; grid-template-columns: 1fr 220px; gap: 24px; margin-bottom: 30px; }

.section { margin-bottom: 24px; }
.section-title { font-size: 15px; font-weight: 700; color: var(--primary); border-bottom: 1px solid var(--primary); padding-bottom: 6px; margin-bottom: 16px; text-transform: uppercase; }
.section-subtitle { font-size: 12px; font-weight: 700; color: var(--text-muted); margin-bottom: 10px; }

/* Scores */
.detailed-scores-container { margin-bottom: 20px; }
.detailed-scores-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.score-box { background: #f1f5f9; padding: 10px; border-radius: 6px; text-align: center; border: 1px solid var(--border); }
.score-box .score-val { font-size: 16px; font-weight: 700; color: var(--primary); }
.score-box .score-lbl { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; }

.grades-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; text-align: center; }
.grade-box { padding: 14px; border: 1px solid var(--border); border-radius: 8px; background: #fff; }
.grade-box .val { font-size: 20px; font-weight: 800; color: var(--text-main); }
.grade-box .lbl { font-size: 11px; font-weight: 600; color: var(--text-muted); margin-top: 6px; text-transform: uppercase; }
.rank-box { background: var(--primary); border-color: var(--primary); }
.rank-box .val, .rank-box .lbl { color: #fff; }

.text-capitalize { text-transform: capitalize; }

/* Attendance */
.stats-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.stat-item { display: flex; justify-content: space-between; padding: 8px 12px; background: #f8fafc; border-radius: 6px; font-size: 14px; }
.stat-item .lbl { color: var(--text-muted); font-weight: 500; }
.stat-item .val { font-weight: 700; }
.text-success { color: var(--success); }
.text-warning { color: var(--warning); }
.text-danger { color: var(--danger); }
.text-primary { color: var(--primary); }
.rate-box { text-align: center; background: #f0fdf4; color: #166534; padding: 10px; border-radius: 6px; font-size: 14px; border: 1px solid #bbf7d0; }
.rate-box strong { font-size: 18px; margin-left: 4px; }

/* Remark */
.remark-box { background: #f8fafc; padding: 16px; border-radius: 8px; font-style: italic; font-size: 15px; border-left: 3px solid var(--primary); min-height: 80px; }

/* Signatures */
.signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 40px; text-align: center; margin-top: 40px; padding-top: 30px; border-top: 1px dashed var(--border); }
.sig-title { font-size: 13px; font-weight: 700; color: var(--text-main); margin-bottom: 60px; }
.sig-name { font-size: 15px; font-weight: 700; color: var(--primary); }

@media print {
    @page { size: A4 portrait; margin: 0; }
    body { background: #fff; padding: 0; }
    .card-wrapper { 
        width: 100%; min-height: 100vh;
        margin: 0; padding: 15mm; 
        box-shadow: none; border-radius: 0; 
        page-break-after: always; 
    }
    .card-wrapper:last-child { page-break-after: avoid; }
}
</style>
</head>
<body onload="setTimeout(() => window.print(), 500)">
    <?php foreach ($htmls as $html): ?>
        <div class="card-wrapper">
            <?= $html ?>
        </div>
    <?php endforeach; ?>
</body>
</html>
