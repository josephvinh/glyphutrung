<?php
/**
 * TẠO VAI "DEMO" — xem đủ chức năng, KHÔNG được thao tác  (chạy một lần)
 *
 *   php config/migrate_demo_accounts.php
 *
 * Idempotent. Vai 'demo' (toàn đoàn) có quyền 'view' trên MỌI module; các hành
 * động ghi còn bị chặn thêm ở require_write() (xem _bootstrap.php).
 * Chỉ Quản Trị cấp vai này: thành viên tự đăng ký, Quản Trị duyệt ở màn
 * Nhân sự và chọn "Demo (chỉ xem, không thao tác)".
 */

require __DIR__ . '/db.php';

db_run("INSERT INTO roles (code, label, level, scope, descr)
        VALUES ('demo', 'Tài khoản Demo', 1, 'toàn đoàn', 'Chỉ xem toàn bộ chức năng, không được thao tác')
        ON DUPLICATE KEY UPDATE label=VALUES(label), scope=VALUES(scope), descr=VALUES(descr)");
db_run("INSERT IGNORE INTO titles (role_code, label, sort_order) VALUES ('demo', 'Demo', 1)");

$mods = array_column(db_all("SELECT module_key FROM modules"), 'module_key');
foreach ($mods as $mod) {
    db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?, 'demo', 'view')
            ON DUPLICATE KEY UPDATE level='view'", [$mod]);
}

echo "Xong: vai 'demo' có quyền xem trên " . count($mods) . " module.\n";
echo "Người đang đăng nhập cần tải lại trang để nhận quyền mới.\n";
