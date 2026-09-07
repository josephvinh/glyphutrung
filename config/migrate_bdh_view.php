<?php
/**
 * BĐH: điểm số + phiếu liên lạc -> chỉ XEM (giám sát)  (chạy một lần)
 *
 *   php config/migrate_bdh_view.php
 *
 * Idempotent. Nhập/sửa điểm số + phiếu liên lạc là việc của GVCN/GLV lớp;
 * Ban Điều Hành chỉ giám sát + xuất báo cáo. Quản Trị (admin) vẫn edit.
 */

require __DIR__ . '/db.php';

db_run("UPDATE permissions SET level = 'view'
         WHERE role_code = 'bdh' AND module_key IN ('scores', 'reports')");

echo "Xong: BĐH -> 'view' cho điểm số + phiếu liên lạc.\n";
echo "Người đang đăng nhập cần tải lại trang để nhận quyền mới.\n";
