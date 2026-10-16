<?php
/**
 * CẤU HÌNH HỆ THỐNG — chỉ Quản Trị
 *
 *   POST api/settings.php?action=permission { moduleKey, roleCode, level }
 *   POST api/settings.php?action=module     { moduleKey }         — bật/tắt bảo trì
 *   POST api/settings.php?action=resetPerms
 *   POST api/settings.php?action=clearLogs
 */

require __DIR__ . '/_bootstrap.php';

$me = require_login();
if ($me['role_code'] !== 'admin') json_fail('Chỉ Quản Trị Hệ Thống mới đổi được cấu hình.', 403);

$action = $_GET['action'] ?? '';
$in     = json_input();

/** Bản phân quyền gốc, dùng cho nút "Đặt lại mặc định" */
function default_permissions(): array
{
    return [
        'students'      => ['edit','edit','view','view','view'],
        'attendance'    => ['edit','edit','edit','edit','edit'],
        'leave'         => ['edit','edit','edit','edit','view'],
        'birthdays'     => ['view','view','view','view','view'],
        'stats'         => ['view','view','view','view','view'],
        'org'           => ['edit','edit','view','view','view'],
        'staff'         => ['edit','edit','view','view','view'],
        'years'         => ['edit','edit','view','view','view'],
        'reports'       => ['edit','edit','edit','edit','view'],
        'scores'        => ['edit','edit','edit','edit','edit'],
        'promotion'     => ['edit','edit','view','none','none'],
        'programs'      => ['edit','edit','none','none','none'],
        'announcements' => ['edit','edit','edit','view','view'],
    ];
}

switch ($action) {

    // -------------------------------------------------------------
    case 'permission':
        require_write();
        $mod   = (string) ($in['moduleKey'] ?? '');
        $role  = (string) ($in['roleCode'] ?? '');
        $level = (string) ($in['level'] ?? '');

        if (!in_array($level, ['none', 'view', 'edit'], true)) json_fail('Mức quyền không hợp lệ.');
        if (!db_one('SELECT module_key FROM modules WHERE module_key=?', [$mod])) {
            json_fail('Không tìm thấy chức năng.', 404);
        }
        if (!db_one('SELECT code FROM roles WHERE code=?', [$role])) {
            json_fail('Không tìm thấy vai trò.', 404);
        }
        // Chặn tự khoá chính mình ra ngoài
        if ($role === 'admin' && $level !== 'edit') {
            json_fail('Không thể hạ quyền của Quản Trị Hệ Thống — bạn sẽ tự khoá mình ra ngoài.');
        }

        $old = db_one('SELECT level FROM permissions WHERE module_key=? AND role_code=?', [$mod, $role]);
        db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = VALUES(level)', [$mod, $role, $level]);

        $names = ['none' => 'Không thấy', 'view' => 'Chỉ xem', 'edit' => 'Toàn quyền'];
        $label = db_one('SELECT label FROM modules WHERE module_key=?', [$mod])['label'];
        $rlab  = db_one('SELECT label FROM roles WHERE code=?', [$role])['label'];
        log_action('phanquyen', 'settings', 'Đổi quyền ' . $label . ' của ' . $rlab,
                   ($names[$old['level'] ?? 'none']) . ' → ' . $names[$level],
            [
                'before' => ['level' => $old['level'] ?? 'none'],
                'after'  => ['level' => $level],
                'target_type'  => 'permission',
                'target_id'    => $mod . '/' . $role,
                'target_label' => $label . ' / ' . $rlab,
            ]);

        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'module':
        require_write();
        $mod = (string) ($in['moduleKey'] ?? '');
        $m = db_one('SELECT * FROM modules WHERE module_key=?', [$mod]);
        if (!$m) json_fail('Không tìm thấy chức năng.', 404);

        $now = $m['is_enabled'] ? 0 : 1;
        db_run('UPDATE modules SET is_enabled=? WHERE module_key=?', [$now, $mod]);
        log_action('baotri', 'settings',
                   ($now ? 'Mở lại' : 'Tạm khóa') . ' chức năng ' . $m['label'],
                   $now ? 'hoạt động bình thường' : 'đang bảo trì',
            [
                'before' => ['is_enabled' => (int) $m['is_enabled']],
                'after'  => ['is_enabled' => $now],
                'target_type'  => 'module',
                'target_id'    => (int) $m['id'],
                'target_label' => $m['label'],
            ]);

        json_out(['ok' => true, 'enabled' => (bool) $now]);

    // -------------------------------------------------------------
    case 'resetPerms':
        require_write();
        $order = ['admin', 'bdh', 'truong_khoi', 'glv_chu_nhiem', 'glv'];
        try {
            trong_giao_dich(function () use ($order) {
                foreach (default_permissions() as $mod => $levels) {
                    foreach ($order as $i => $role) {
                        db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                                ON DUPLICATE KEY UPDATE level = VALUES(level)', [$mod, $role, $levels[$i]]);
                    }
                }
            });
        } catch (\Throwable $e) {
            json_fail(safe_error($e, 'Không đặt lại được: '), 500);
        }
        log_action('phanquyen', 'settings', 'Đặt lại toàn bộ phân quyền về mặc định', '',
            [
                'before' => null,
                'after'  => ['reset' => 'default'],
                'target_type'  => 'permission',
                'target_id'    => 0,
                'target_label' => 'Tất cả module',
            ]);
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'clearLogs':
        require_write();
        // Lọc theo khoảng ngày (tùy chọn): chỉ xoá log TRONG khoảng,
        // giúp dọn dẹp từng đợt thay vì xoá sạch một lần. from/to dạng
        // YYYY-MM-DD, không truyền thì xoá TẤT CẢ (giữ tương thích cũ).
        $where = [];
        $params = [];
        if (!empty($in['from'])) {
            $from = date('Y-m-d 00:00:00', strtotime((string) $in['from']));
            if ($from) { $where[] = 'logged_at >= ?'; $params[] = $from; }
        }
        if (!empty($in['to'])) {
            $to = date('Y-m-d 23:59:59', strtotime((string) $in['to']));
            if ($to) { $where[] = 'logged_at <= ?'; $params[] = $to; }
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $deleted = (int) (db_one("SELECT COUNT(*) n FROM activity_logs$whereSql", $params)['n'] ?? 0);
        db_run("DELETE FROM activity_logs$whereSql", $params);
        $mota = $deleted . ' dòng' . (($in['from'] ?? '') || ($in['to'] ?? '')
            ? ' (' . ($in['from'] ?? '…') . ' → ' . ($in['to'] ?? '…') . ')'
            : '');
        log_action('xoa', 'settings', 'Xóa nhật ký thao tác', $mota,
            [
                'before' => ['deleted_rows' => $deleted],
                'after'  => null,
                'target_type'  => 'activity_log',
                'target_id'    => 0,
                'target_label' => 'Nhật ký thao tác',
            ]);
        json_out(['ok' => true, 'deleted' => $deleted]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
