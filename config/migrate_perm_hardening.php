<?php
/**
 * MIGRATE: dọn phân quyền còn lệch giữa schema và DB thật (F6 + F7)
 *
 *   php config/migrate_perm_hardening.php
 *
 * Idempotent — chạy lại nhiều lần vẫn ra một kết quả.
 *
 * F6: Vai "Dự Bị" (du_bi) trên DB thật có scope = '' (rỗng, không hợp lệ với
 *     ENUM) khiến assignment_covers_class()/resolve_class_ids_from_scope() coi
 *     như không phủ lớp nào → du_bi tuy có attendance=edit nhưng không điểm
 *     danh được lớp nào. Đặt lại scope = 'lớp' cho đúng bản chất "hỗ trợ tại lớp".
 *
 * F7: Module Thư viện (thu_vien) có trong schema.sql nhưng thiếu bản ghi quyền
 *     trên DB thật → permission_of('thu_vien') luôn 'none'. Bổ sung cho đủ vai.
 */
require __DIR__ . '/db.php';

echo "=== Migration: perm hardening (F6 + F7) ===\n";

/* -------- F6: scope của Dự Bị -------- */
$n6 = db_run(
    "UPDATE roles SET scope = 'lớp'
      WHERE code = 'du_bi' AND (scope = '' OR scope IS NULL)"
);
echo "F6. du_bi.scope = 'lớp': $n6 dòng cập nhật\n";

/* -------- F7: quyền module Thư viện -------- */
// Đảm bảo module tồn tại (khớp seed trong schema.sql).
db_run(
    "INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
     VALUES ('thu_vien', 'Thư viện', 'scroll-text', 'text-amber-600', 'glv', 7)"
);

$thuVienPerms = [
    ['admin', 'edit'], ['bdh', 'edit'], ['truong_khoi', 'view'],
    ['glv_chu_nhiem', 'view'], ['glv', 'view'], ['du_bi', 'view'],
];
$n7 = 0;
foreach ($thuVienPerms as [$role, $lv]) {
    $n7 += db_run(
        "INSERT IGNORE INTO permissions (module_key, role_code, level)
         VALUES ('thu_vien', ?, ?)",
        [$role, $lv]
    );
}
echo "F7. Bổ sung quyền thu_vien: $n7 dòng mới\n";

echo "\nXong. Người đang đăng nhập cần tải lại trang để nhận thay đổi.\n";
