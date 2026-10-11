<?php
/**
 * THÔNG BÁO — API
 *
 *   GET  ?action=list          — danh sách, hỗ trợ ?read=0|1|all&page=1&limit=20
 *   POST ?action=mark_read     — đánh dấu đã đọc {ids: [...]} hoặc {all: true}
 *   GET  ?action=unread_count  — số chưa đọc (cho badge)
 */

require __DIR__ . '/_bootstrap.php';

$me     = require_login();
$action = $_GET['action'] ?? '';
$in     = json_input();

switch ($action) {

    case 'list':
        $read = $_GET['read'] ?? 'all';
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = 'member_id = ?';
        $params = [$me['id']];

        if ($read === '0') {
            $where .= ' AND is_read = 0';
        } elseif ($read === '1') {
            $where .= ' AND is_read = 1';
        }

        $total = (int) (db_one(
            "SELECT COUNT(*) c FROM push_notifications WHERE $where", $params)['c'] ?? 0);

        // LIMIT/OFFSET are already validated as safe positive integers above.
        $rows = db_all(
            "SELECT id, tag, title, body, url, is_read, created_at
             FROM push_notifications WHERE $where
             ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset", $params);

        json_out([
            'ok' => true,
            'items' => $rows,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);

    case 'mark_read':
        require_write();
        if (!empty($in['all'])) {
            $count = db_run('UPDATE push_notifications SET is_read = 1
                    WHERE member_id = ? AND is_read = 0', [$me['id']]);
            json_out(['ok' => true, 'count' => $count]);
        } else {
            $ids = array_filter(array_map('intval', $in['ids'] ?? []));
            if (!$ids) json_fail('Không có ID nào.', 400);
            $chan = implode(',', array_fill(0, count($ids), '?'));
            $count = db_run("UPDATE push_notifications SET is_read = 1
                    WHERE member_id = ? AND id IN ($chan) AND is_read = 0",
                   array_merge([$me['id']], $ids));
            json_out(['ok' => true, 'count' => $count]);
        }

    case 'unread_count':
        $n = (int) (db_one(
            'SELECT COUNT(*) c FROM push_notifications WHERE member_id = ? AND is_read = 0',
            [$me['id']])['c'] ?? 0);
        json_out(['ok' => true, 'count' => $n]);

    default:
        json_fail('Hành động không hợp lệ.', 404);
}
