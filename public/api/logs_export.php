<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — XUẤT CSV / XLSX
 *
 * GET api/logs_export.php?format=xlsx|csv
 *      &actor=...&module=...&action=...&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Phân quyền: giống logs.php (admin / BĐH / GLV theo phạm vi).
 *
 * Mask BẮT BUỘC trong cột before/after:
 *  - key 'parent_phone' / 'phone' / 'father_phone' / 'mother_phone' / 'sdt'
 *    → thay bằng '0901***567' (giữ 4 đầu + 3 cuối).
 *  - Áp dụng cả diff lồng nhau (recursive).
 *  - Áp dụng cả giá trị KHÔNG nằm trong diff (nếu nằm ở cột riêng).
 *
 * Sau khi stream thành công: ghi 1 dòng log (module='logs_export').
 */
require __DIR__ . '/_bootstrap.php';

$me = require_login();
if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);

$format = strtolower((string) ($_GET['format'] ?? 'xlsx'));
if (!in_array($format, ['xlsx', 'csv'], true)) {
    json_fail('Định dạng không hợp lệ (xlsx|csv).', 400);
}

// ---- Lọc giống logs.php ----------------------------------------------
[$whereSql, $params] = log_scope_where_with_params($me);
$where = [$whereSql];

if (!empty($_GET['module'])) {
    $where[] = 'module = ?';
    $params[] = (string) $_GET['module'];
}
if (!empty($_GET['action'])) {
    $where[] = 'action = ?';
    $params[] = (string) $_GET['action'];
}
if (!empty($_GET['actor'])) {
    $where[] = 'actor_name LIKE ?';
    $params[] = '%' . (string) $_GET['actor'] . '%';
}
if (!empty($_GET['from'])) {
    $from = date('Y-m-d 00:00:00', strtotime((string) $_GET['from']));
    if ($from) { $where[] = 'logged_at >= ?'; $params[] = $from; }
}
if (!empty($_GET['to'])) {
    $to = date('Y-m-d 23:59:59', strtotime((string) $_GET['to']));
    if ($to) { $where[] = 'logged_at <= ?'; $params[] = $to; }
}

$whereClause = ' WHERE ' . implode(' AND ', $where);

// ---- Lấy dữ liệu (giới hạn 5.000 dòng) ------------------------------
const EXPORT_LIMIT = 5000;
$total = (int) (db_one("SELECT COUNT(*) n FROM activity_logs $whereClause", $params)['n'] ?? 0);
if ($total > EXPORT_LIMIT) {
    json_fail("Quá nhiều dòng ($total). Vui lòng thu hẹp filter (tối đa " . EXPORT_LIMIT . " dòng/lần).", 400);
}

$rows = db_all(
    "SELECT * FROM activity_logs $whereClause ORDER BY id DESC LIMIT " . EXPORT_LIMIT,
    $params
);

// ---- Mask SĐT (recursive trong cả diff_json) -------------------------
foreach ($rows as &$r) {
    if (!empty($r['diff_json'])) {
        $diff = json_decode($r['diff_json'], true);
        if (is_array($diff)) {
            $diff['before'] = \TNTT\LogMask::phoneRecursive($diff['before'] ?? null);
            $diff['after']  = \TNTT\LogMask::phoneRecursive($diff['after'] ?? null);
            $r['diff_json'] = json_encode($diff, JSON_UNESCAPED_UNICODE);
        }
    }
    unset($r);
}

// ---- Tạo file tạm, stream --------------------------------------------
$ts = date('Ymd_His');
$baseName = "nhat-ky-thao-tac_{$ts}";
$tmpFile  = sys_get_temp_dir() . '/' . $baseName . '.' . $format;

// Tạo rows cho export (mỗi dòng = 1 log)
$headers = [
    'ID', 'Thời gian', 'Người thực hiện', 'Hành động', 'Module',
    'Mô tả', 'Chi tiết', 'Đối tượng', 'IP', 'Trình duyệt',
];
$exportRows = [];
foreach ($rows as $r) {
    $exportRows[] = [
        (int) $r['id'],
        $r['logged_at'],
        $r['actor_name'],
        $r['action'],
        $r['module'],
        $r['what'],
        $r['detail'] ?? '',
        !empty($r['target_label']) ? $r['target_label'] : '',
        $r['ip'] ?? '',
        $r['user_agent'] ?? '',
    ];
}

if ($format === 'xlsx') {
    xlsx_export($tmpFile, $headers, $exportRows, [
        'sheet_name' => 'Nhật ký',
    ]);
} else {
    // CSV: ghi với UTF-8 BOM để Excel mở đúng tiếng Việt
    $fp = fopen($tmpFile, 'w');
    if ($fp === false) json_fail('Không tạo được file tạm.', 500);
    fwrite($fp, "\xEF\xBB\xBF");
    fputcsv($fp, $headers);
    foreach ($exportRows as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
}

if (!is_file($tmpFile) || filesize($tmpFile) === 0) {
    json_fail('Không tạo được file xuất.', 500);
}

// ---- Ghi log "tao logs_export" ---------------------------------------
$filterDesc = [];
if (!empty($_GET['module'])) $filterDesc[] = 'module=' . $_GET['module'];
if (!empty($_GET['action'])) $filterDesc[] = 'action=' . $_GET['action'];
if (!empty($_GET['actor']))  $filterDesc[] = 'actor=' . $_GET['actor'];
if (!empty($_GET['from']))   $filterDesc[] = 'from=' . $_GET['from'];
if (!empty($_GET['to']))     $filterDesc[] = 'to=' . $_GET['to'];
log_action('tao', 'logs_export',
    'Xuất nhật ký',
    strtoupper($format) . ' · ' . count($exportRows) . ' dòng'
        . ($filterDesc ? ' · filter: ' . implode(',', $filterDesc) : '')
);

// ---- Gửi file cho client ---------------------------------------------
$filename = $baseName . '.' . $format;
$mime = $format === 'xlsx'
    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    : 'text/csv; charset=utf-8';

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($tmpFile));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
readfile($tmpFile);

// Dọn file tạm
@unlink($tmpFile);
