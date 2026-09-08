<?php
/**
 * MODULE HƯỚNG DẪN — đăng ký module 'guide' + quyền (chạy một lần).
 *
 *   php config/migrate_guide.php
 *
 * Idempotent. Nội dung hướng dẫn nằm ở config/huong_dan.php (không cần DB).
 */

require __DIR__ . '/db.php';

db_run("INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
        VALUES ('guide', 'Hướng dẫn', 'info', 'text-sky-600', 'glv', 12)");
foreach (['admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi'] as $role) {
    db_run("INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES ('guide', ?, 'view')", [$role]);
}
echo "Xong: module 'guide' + quyền (mọi vai xem).\n";
