<?php
/**
 * THÊM VAI "DỰ BỊ" + CHỈNH QUYỀN GVCN / TRƯỞNG KHỐI  (chạy một lần)
 *
 *   php config/migrate_roles_du_bi.php
 *
 * Idempotent — chạy lại nhiều lần vẫn ra đúng một kết quả.
 *
 * Theo cơ cấu thực tế của đoàn:
 *   - Thêm vai "Dự Bị" (dưới Giáo Lý Viên phụ tá): hỗ trợ tại lớp.
 *   - GLV Chủ Nhiệm: được SỬA hồ sơ thiếu nhi + GỬI thông báo lớp mình.
 *   - Trưởng Khối: được SỬA hồ sơ thiếu nhi trong khối.
 *
 * Mức quyền có thể tinh chỉnh sau trong app: Cài Đặt → Phân quyền.
 */

require __DIR__ . '/db.php';

// 1) Vai Dự Bị + một chức danh mặc định
db_run("INSERT INTO roles (code, label, level, scope, descr)
        VALUES ('du_bi', 'Dự Bị', 1, 'lớp', 'Hỗ trợ tại lớp được phân công')
        ON DUPLICATE KEY UPDATE label=VALUES(label), scope=VALUES(scope), descr=VALUES(descr)");
db_run("INSERT IGNORE INTO titles (role_code, label, sort_order) VALUES ('du_bi', 'Dự Bị', 1)");

// 2) Quyền cho Dự Bị trên MỌI module đang có: điểm danh = sửa (hỗ trợ),
//    chương trình/lên lớp = ẩn, còn lại = xem.
$dacBiet = ['attendance' => 'edit', 'programs' => 'none', 'promotion' => 'none'];
$mods = array_column(db_all("SELECT DISTINCT module_key FROM permissions"), 'module_key');
$n = 0;
foreach ($mods as $mod) {
    $lv = $dacBiet[$mod] ?? 'view';
    db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?, 'du_bi', ?)
            ON DUPLICATE KEY UPDATE level=VALUES(level)", [$mod, $lv]);
    $n++;
}

// 3) GLV Chủ Nhiệm: sửa hồ sơ + gửi thông báo lớp mình
db_run("UPDATE permissions SET level='edit'
         WHERE role_code='glv_chu_nhiem' AND module_key IN ('students','announcements')");

// 4) Trưởng Khối: sửa hồ sơ thiếu nhi trong khối
db_run("UPDATE permissions SET level='edit'
         WHERE role_code='truong_khoi' AND module_key='students'");

echo "Xong: thêm vai Dự Bị (quyền trên $n module), cập nhật quyền GVCN + Trưởng Khối.\n";
echo "Người dùng đang đăng nhập cần tải lại trang để nhận quyền mới.\n";
