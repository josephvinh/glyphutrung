<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — XUẤT CSV / XLSX
 *
 * GET api/logs_export.php?format=xlsx|csv
 *      &actor=...&module=...&action=...&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Phân quyền: giống logs.php (admin / BĐH / GLV theo phạm vi).
 * Chỉ admin/BĐH thấy IP/user_agent.
 *
 * Mask BẮT BUỘC trong mọi cột (what, detail, before, after):
 *  - key 'parent_phone' / 'phone' / 'father_phone' / 'mother_phone' / 'sdt'
 *    → thay bằng '0901***567' (giữ 4 đầu + 3 cuối).
 *  - Áp dụng cả diff lồng nhau (recursive).
 *  - Áp dụng cả chuỗi 9-12 số liên tiếp (không nằm trong key).
 *
 * Cột mới: 'Trước' (before), 'Sau' (after) — đã mask SĐT.
 *
 * Sau khi stream thành công: ghi 1 dòng log (module='logs_export').
 */
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 1) . '/src/LogMask.php';

$me = require_login();
if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);

// admin/BĐH thấy ip/user_agent; vai khác không thấy
$isAdminOrBdh = in_array($me['role_code'] ?? '', ['admin', 'bdh'], true);

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
    $fromInput = (string) $_GET['from'];
    $fromObj = \DateTime::createFromFormat('Y-m-d', $fromInput);
    if ($fromObj && $fromObj->format('Y-m-d') === $fromInput) {
        $from = $fromObj->format('Y-m-d 00:00:00');
        $where[] = 'logged_at >= ?';
        $params[] = $from;
    }
}
if (!empty($_GET['to'])) {
    $toInput = (string) $_GET['to'];
    $toObj = \DateTime::createFromFormat('Y-m-d', $toInput);
    if ($toObj && $toObj->format('Y-m-d') === $toInput) {
        $to = $toObj->format('Y-m-d 23:59:59');
        $where[] = 'logged_at <= ?';
        $params[] = $to;
    }
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

// ---- Mask SĐT (recursive trong cả diff_json, what, detail) ------------
foreach ($rows as &$r) {
    // Mask what/detail
    $r['what'] = \TNTT\LogMask::maskString($r['what'] ?? '');
    $r['detail'] = \TNTT\LogMask::maskString($r['detail'] ?? '');

    // Mask diff before/after
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
    'Mô tả', 'Chi tiết', 'Đối tượng', 'Trước', 'Sau', 'IP', 'Trình duyệt',
];

/**
 * Format diff array thành string để hiển thị trong Excel.
 * Ví dụ: ["name" => "A", "score" => 5] → "name: A | score: 5"
 */
function formatDiffForExport(mixed $diff): string
{
    if (!is_array($diff)) return '';
    $parts = [];
    foreach ($diff as $k => $v) {
        if (is_array($v)) {
            $parts[] = $k . ': ' . formatDiffForExport($v);
        } else {
            $parts[] = $k . ': ' . (string) $v;
        }
    }
    return implode(' | ', $parts);
}

$exportRows = [];
foreach ($rows as $r) {
    $diff = !empty($r['diff_json']) ? json_decode($r['diff_json'], true) : null;

    $exportRows[] = [
        (int) $r['id'],
        $r['logged_at'],
        $r['actor_name'],
        $r['action'],
        $r['module'],
        $r['what'],
        $r['detail'] ?? '',
        !empty($r['target_label']) ? $r['target_label'] : '',
        formatDiffForExport($diff['before'] ?? null),
        formatDiffForExport($diff['after'] ?? null),
        // Chỉ admin/BĐH thấy IP/UA
        $isAdminOrBdh ? ($r['ip'] ?? '') : '',
        $isAdminOrBdh ? ($r['user_agent'] ?? '') : '',
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
