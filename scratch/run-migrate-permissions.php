<?php
/**
 * Chạy migration sửa quyền scope trong CSDL.
 * Kết quả trước/sau được in ra để xác nhận.
 */
require __DIR__ . '/../config/db.php';

echo "=== TRƯỚC ===\n";
foreach (db_all(
    "SELECT module_key, role_code, level FROM permissions
      WHERE module_key IN ('org', 'students', 'staff')
      ORDER BY module_key, FIELD(role_code,'admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi')"
) as $r) {
    printf("  %-9s %-16s %s\n", $r['module_key'], $r['role_code'], $r['level']);
}

// Bước 1: org — truong_khoi = view
$n1 = db_run(
    "UPDATE permissions SET level = 'view'
      WHERE module_key = 'org' AND role_code = 'truong_khoi'"
);
echo "\n[1] org truong_khoi → view: $n1 dòng bị ảnh hưởng\n";

// Bước 2: students — bdh = edit
$n2 = db_run(
    "UPDATE permissions SET level = 'edit'
      WHERE module_key = 'students' AND role_code = 'bdh'"
);
echo "[2] students bdh → edit: $n2 dòng bị ảnh hưởng\n";

// Bước 3: Thêm staff permissions (INSERT IGNORE)
$rows = [
    ['staff', 'admin',         'edit'],
    ['staff', 'bdh',           'edit'],
    ['staff', 'truong_khoi',   'view'],
    ['staff', 'glv_chu_nhiem', 'view'],
    ['staff', 'glv',           'view'],
    ['staff', 'du_bi',         'view'],
];
$added = 0;
foreach ($rows as $r) {
    $added += db_run(
        "INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES (?,?,?)",
        $r
    );
}
echo "[3] staff permissions thêm mới: $added dòng\n";

// Bước 4: Thông báo về các trưởng khối chưa có block_id
// (responsible_blocks() tính từ phân công, nên ứng dụng vẫn hoạt động)
echo "[4] Kiểm tra trưởng khối không có block_id:\n";
foreach (db_all(
    "SELECT m.id, m.full_name FROM members m
       WHERE m.role_code = 'truong_khoi' AND m.block_id IS NULL"
) as $r) {
    echo "  - ID=" . $r['id'] . " (" . $r['full_name'] . ") chưa có block_id\n";
    echo "    → Ứng dụng vẫn hoạt động (responsible_blocks() tính từ phân công).\n";
}
echo "  (Nếu muốn gán, cập nhật thủ công bảng members.block_id.)\n";
$n4 = 0;
echo "[4] Gán block_id cho truong_khoi: $n4 dòng\n";

echo "\n=== SAU ===\n";
foreach (db_all(
    "SELECT module_key, role_code, level FROM permissions
      WHERE module_key IN ('org', 'students', 'staff')
      ORDER BY module_key, FIELD(role_code,'admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi')"
) as $r) {
    printf("  %-9s %-16s %s\n", $r['module_key'], $r['role_code'], $r['level']);
}

echo "\n✓ Migration hoàn tất. Không cần khởi động lại ứng dụng.\n";
echo "  Chạy tiếp: php scratch/verify-effective-permissions.php\n";
