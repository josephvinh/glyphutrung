<?php
/**
 * ĐỒNG BỘ BẢNG modules + permissions CHO CÁC MODULE MỚI  (chạy một lần)
 *
 *   php config/migrate_modules_sync.php
 *
 * Idempotent — chạy lại vẫn ra đúng một kết quả.
 *
 * Vì sao cần: danh sách module hiển thị lấy từ BẢNG `modules` (BOOT.modules,
 * xem _bootstrap_page.php), KHÔNG phải từ moduleDefs trong core.js. Khi thêm
 * "Báo cáo" (reporthub), "Phân tích", "Lịch trình" vào code mà quên thêm dòng
 * vào bảng này -> icon không hiện dù code đã có.
 *
 * Migration này:
 *   1) Thêm 3 module còn thiếu: reporthub (Báo cáo), analytics (Phân tích),
 *      calendar (Lịch trình).
 *   2) Chuyển Nhân sự + Niên khoá sang khu điều hành (bdh).
 *   3) Cấp quyền "xem" cho 3 module trên với mọi vai (không có quyền thì
 *      module vẫn bị ẩn dù đã có trong bảng modules).
 */

require __DIR__ . '/db.php';

// 1) Thêm module còn thiếu — INSERT IGNORE để không đụng dòng đã có.
//    (key, label, icon, color, area, sort_order)
$them = [
    ['reporthub', 'Báo cáo',    'bar-chart-3',  'text-emerald-600', 'glv', 5],
    ['analytics', 'Phân tích',  'bar-chart-2',  'text-purple-600',  'glv', 6],
    ['calendar',  'Lịch trình', 'calendar-days','text-teal-600',    'bdh', 4],
];
$nThem = 0;
foreach ($them as $m) {
    $nThem += db_run("INSERT IGNORE INTO modules (module_key,label,icon,color,area,sort_order,is_enabled)
                      VALUES (?,?,?,?,?,?,1)", $m);
}

// 2) Nhân sự + Niên khoá -> khu điều hành (theo bố cục mới)
db_run("UPDATE modules SET area='bdh' WHERE module_key IN ('staff','years')");

// 3) Quyền "xem" cho Báo cáo/Phân tích/Lịch trình với mọi vai.
$vais = array_column(db_all("SELECT code FROM roles"), 'code');
$nQuyen = 0;
foreach (['reporthub', 'analytics', 'calendar'] as $mod) {
    foreach ($vais as $vai) {
        $nQuyen += db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?, 'view')
                           ON DUPLICATE KEY UPDATE level = level", [$mod, $vai]);
    }
}

echo "Xong.\n";
echo " - Thêm module còn thiếu (reporthub/analytics/calendar): $nThem dòng mới.\n";
echo " - Nhân sự + Niên khoá chuyển sang khu điều hành.\n";
echo " - Cấp/giữ quyền xem: đã xử lý " . count($vais) . " vai × 3 module.\n";
echo "Người dùng cần tải lại trang (hoặc xoá cache) để thấy.\n";
