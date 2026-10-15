<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — PHÂN TRANG + FILTER + PHÂN QUYỀN PHẠM VI
 *
 * GET api/logs.php?page=1&limit=20
 *      &actor=...&module=...&action=...&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Phân quyền (xem can_view_logs() + log_scope_where_with_params() trong _common.php):
 *  - admin / BĐH: thấy tất cả.
 *  - Vai khác: chỉ thấy log do mình tạo + log về lớp mình chủ nhiệm.
 *
 * Response thêm các trường mới (so với bản cũ): ip, user_agent, target_*,
 * diff (parse từ diff_json — null với log cũ chưa có diff).
 */
require __DIR__ . '/_bootstrap.php';

$me = require_login();

if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);

// Pagination
$page  = max(1, (int) ($_GET['page']  ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// Scope (phân quyền phạm vi) — luôn có
[$scopeWhere, $scopeParams] = log_scope_where_with_params($me);

// Filter từ query string — tất cả optional
$where  = [$scopeWhere];
$params = $scopeParams;

if (!empty($_GET['module'])) {
    $where[]  = 'module = ?';
    $params[] = (string) $_GET['module'];
}
if (!empty($_GET['action'])) {
    $where[]  = 'action = ?';
    $params[] = (string) $_GET['action'];
}
if (!empty($_GET['actor'])) {
    // Tìm theo tên (LIKE) — không dùng actor_id vì nhiều log cũ có actor_id NULL.
    $where[]  = 'actor_name LIKE ?';
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

$whereSql = ' WHERE ' . implode(' AND ', $where);

// Đếm tổng
$total = (int) (db_one("SELECT COUNT(*) n FROM activity_logs $whereSql", $params)['n'] ?? 0);
$totalPages = (int) ceil($total / $limit);

// Lấy logs
$rows = db_all(
    "SELECT * FROM activity_logs
     $whereSql
     ORDER BY id DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$logs = array_map(function (array $l): array {
    return [
        'id'           => (int) $l['id'],
        'at'           => substr($l['logged_at'], 0, 16),
        'ts'           => strtotime($l['logged_at']) * 1000,
        'actor'        => $l['actor_name'],
        'action'       => $l['action'],
        'module'       => $l['module'],
        'what'         => $l['what'],
        'detail'       => $l['detail'] ?? '',
        'ip'           => $l['ip'] ?? null,
        'user_agent'   => $l['user_agent'] ?? null,
        'target_type'  => $l['target_type'] ?? null,
        'target_id'    => isset($l['target_id']) ? (int) $l['target_id'] : null,
        'target_label' => $l['target_label'] ?? null,
        'diff'         => !empty($l['diff_json'])
            ? json_decode($l['diff_json'], true)
            : null,
    ];
}, $rows);

json_out([
    'ok' => true,
    'data' => $logs,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'totalPages'  => $totalPages,
        'hasNext'     => $page < $totalPages,
        'hasPrev'     => $page > 1,
    ],
]);
