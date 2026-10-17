<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — PHÂN TRANG + FILTER + PHÂN QUYỀN PHẠM VI
 *
 * GET api/logs.php?page=1&limit=20
 *      &actor=...&module=...&action=...&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Phân quyền (xem can_view_logs() + log_scope_where_with_params() trong _common.php):
 *  - admin / BĐH: thấy tất cả (kể cả ip, user_agent).
 *  - Vai khác: chỉ thấy log do mình tạo + log về lớp mình chủ nhiệm;
 *    KHÔNG thấy ip/user_agent (bảo mật).
 *
 * Security: what/detail luôn mask SĐT (dùng cho audit).
 * Response thêm các trường mới (so với bản cũ): ip, user_agent, target_*,
 * diff (parse từ diff_json — null với log cũ chưa có diff).
 */
require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 1) . '/src/LogMask.php';

$me = require_login();

if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);

// admin/BĐH thấy ip/user_agent; vai khác không thấy
$isAdminOrBdh = in_array($me['role_code'] ?? '', ['admin', 'bdh'], true);

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

$logs = array_map(function (array $l) use ($isAdminOrBdh): array {
    // Mask SĐT trong what/detail (bảo mật - không lộ SĐT phụ huynh)
    $what = \TNTT\LogMask::maskString($l['what'] ?? '');
    $detail = \TNTT\LogMask::maskString($l['detail'] ?? '');

    // Parse diff và mask SĐT trong before/after
    $diff = null;
    if (!empty($l['diff_json'])) {
        $diffRaw = json_decode($l['diff_json'], true);
        if (is_array($diffRaw)) {
            $diff = [
                'before' => \TNTT\LogMask::phoneRecursive($diffRaw['before'] ?? null),
                'after'  => \TNTT\LogMask::phoneRecursive($diffRaw['after'] ?? null),
            ];
        }
    }

    return [
        'id'           => (int) $l['id'],
        'at'           => substr($l['logged_at'], 0, 16),
        'ts'           => strtotime($l['logged_at']) * 1000,
        'actor'        => $l['actor_name'],
        'action'       => $l['action'],
        'module'       => $l['module'],
        'what'         => $what,
        'detail'       => $detail,
        // Chỉ admin/BĐH thấy ip/user_agent
        'ip'           => $isAdminOrBdh ? ($l['ip'] ?? null) : null,
        'user_agent'   => $isAdminOrBdh ? ($l['user_agent'] ?? null) : null,
        'target_type'  => $l['target_type'] ?? null,
        'target_id'    => isset($l['target_id']) ? (int) $l['target_id'] : null,
        'target_label' => $l['target_label'] ?? null,
        'diff'         => $diff,
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
