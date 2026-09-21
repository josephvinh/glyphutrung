<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — CÓ PHÂN TRANG
 *
 * GET api/logs.php?page=1&limit=20
 *
 * Trả về nhật ký hoạt động với pagination.
 * Chỉ admin/Ban Điều Hành mới được xem.
 */
require __DIR__ . '/_bootstrap.php';

$me = require_login();

// Chỉ người có quyền edit toàn đoàn mới xem nhật ký
$role = $me['role'] ?? '';
$isAdmin = in_array($role, ['admin', 'ban_dieu_hanh'], true);
if (!$isAdmin) {
    json_fail('Không có quyền xem nhật ký.', 403);
}

// Pagination params
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// Đếm tổng
$total = (int) db_one('SELECT COUNT(*) n FROM activity_logs')['n'];
$totalPages = (int) ceil($total / $limit);

// Lấy logs
$logs = array_map(fn($l) => [
    'id'     => (int) $l['id'],
    'at'     => substr($l['logged_at'], 0, 16),
    'ts'     => strtotime($l['logged_at']) * 1000,
    'actor'  => $l['actor_name'],
    'action' => $l['action'],
    'module' => $l['module'],
    'what'   => $l['what'],
    'detail' => $l['detail'] ?? '',
], db_all(
    "SELECT * FROM activity_logs ORDER BY id DESC LIMIT ? OFFSET ?",
    [$limit, $offset]
));

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
