<?php
/**
 * MIGRATE: Sửa quyền scope cho module Khối lớp & Nhân sự
 *
 * Chạy: php config/migrate_permissions.php
 *
 * Mục đích:
 *   - Trưởng Khối (truong_khoi) được SỬA khối-lớp TRONG KHỐI MÌNH (F5).
 *     (Đã bị can_manage_block/class giới hạn phạm vi ở tầng API.)
 *   - Ban Điều Hành (bdh) được SỬA module Tổ chức (org) và danh sách thiếu nhi.
 *   - Module Nhân sự (staff) có quyền hợp lệ cho mọi vai.
 *
 * Idempotent: chạy lại vô hại.
 */
require __DIR__ . '/db.php';

echo "=== Migration: permissions fix ===\n";

// Bước 1: Quyền org — Trưởng Khối được SỬA (quản khối-lớp trong khối mình);
//         BĐH được SỬA org (gộp từ fix_permission.php đã bỏ — xem F5/F8).
$n1 = db_run(
    "UPDATE permissions SET level = 'edit'
     WHERE module_key = 'org' AND role_code IN ('truong_khoi', 'bdh')"
);
echo "1. org.truong_khoi + org.bdh = edit: $n1 dòng cập nhật\n";

// Bước 2: Sửa quyền students — BĐH được sửa
$n2 = db_run(
    "UPDATE permissions SET level = 'edit'
     WHERE module_key = 'students' AND role_code = 'bdh'"
);
echo "2. students.bdh = edit: $n2 dòng cập nhật\n";

// Bước 3: Thêm quyền staff cho các vai còn thiếu
$staffPerms = [
    ['admin', 'edit'],
    ['bdh', 'edit'],
    ['truong_khoi', 'view'],
    ['glv_chu_nhiem', 'view'],
    ['glv', 'view'],
    ['du_bi', 'view'],
];
$n3 = 0;
foreach ($staffPerms as [$role, $level]) {
    $n3 += db_run(
        "INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES ('staff', ?, ?)",
        [$role, $level]
    );
}
echo "3. Thêm staff permissions: $n3 dòng mới\n";

// Bước 4: Xác nhận
echo "\n=== Kết quả ===\n";
$rows = db_all(
    "SELECT module_key, role_code, level FROM permissions
     WHERE module_key IN ('org', 'students', 'staff')
     ORDER BY module_key, FIELD(role_code,'admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi')"
);
foreach ($rows as $r) {
    $mark = ($r['module_key'] === 'org' && in_array($r['role_code'], ['truong_khoi', 'bdh'], true) && $r['level'] === 'edit')
         || ($r['module_key'] === 'staff' && $r['level'] !== 'none')
         || ($r['module_key'] === 'students' && $r['role_code'] === 'bdh' && $r['level'] === 'edit')
        ? '✓' : ' ';
    echo "{$mark} {$r['module_key']}/{$r['role_code']} = {$r['level']}\n";
}

echo "\nXong.\n";
