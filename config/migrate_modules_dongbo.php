<?php
/**
 * ĐỒNG BỘ icon / màu / nhãn / khu của bảng `modules` về ĐÚNG CHUẨN code.
 *
 *   php config/migrate_modules_dongbo.php
 *
 * Vì sao cần: bảng `modules` (BOOT.modules) quyết định icon + màu hiển thị.
 * Dữ liệu cũ từ lần cài trước có thể sai — VD `color='text-white'` khiến icon
 * trắng trên nền trắng (vô hình). Migration này UPDATE mọi module đang có về
 * đúng giá trị chuẩn. Idempotent, chỉ sửa dòng đã tồn tại (không tạo mới).
 */

require __DIR__ . '/db.php';

// key => [label, icon, color, area, sort_order] — khớp config/install.php
$canon = [
    'students'      => ['Danh sách',    'users',          'text-blue-600',    'glv', 1],
    'attendance'    => ['Điểm danh',    'clipboard-check','text-blue-600',    'glv', 2],
    'leave'         => ['Xin phép',     'file-text',      'text-blue-600',    'glv', 3],
    'birthdays'     => ['Sinh nhật',    'cake',           'text-rose-500',    'glv', 4],
    'reporthub'     => ['Báo cáo',      'bar-chart-3',    'text-emerald-600', 'glv', 5],
    'analytics'     => ['Phân tích',    'bar-chart-2',    'text-violet-600',  'glv', 6],
    'stats'         => ['Thống kê',     'bar-chart-3',    'text-emerald-600', 'glv', 7],
    'org'           => ['Khối lớp',     'layers',         'text-indigo-600',  'glv', 8],
    'reports'       => ['Sổ liên lạc',  'clipboard-list', 'text-amber-600',   'glv', 9],
    'scores'        => ['Điểm số',      'graduation-cap', 'text-violet-600',  'glv', 10],
    'notes'         => ['Lịch của tôi', 'calendar-check', 'text-teal-600',    'glv', 11],
    'guide'         => ['Hướng dẫn',    'info',           'text-sky-600',     'glv', 12],
    'promotion'     => ['Lên lớp',      'trending-up',    'text-violet-600',  'bdh', 1],
    'programs'      => ['Chương trình', 'calendar-plus',  'text-amber-600',   'bdh', 2],
    'calendar'      => ['Lịch trình',   'calendar-days',  'text-teal-600',    'bdh', 3],
    'announcements' => ['Thông báo',    'megaphone',      'text-rose-500',    'bdh', 4],
    'staff'         => ['Nhân sự',      'user-cog',       'text-cyan-600',    'bdh', 5],
    'years'         => ['Niên khoá',    'calendar-range', 'text-indigo-600',  'bdh', 6],
];

$n = 0;
foreach ($canon as $key => [$label, $icon, $color, $area, $sort]) {
    $n += db_run("UPDATE modules SET label=?, icon=?, color=?, area=?, sort_order=?
                  WHERE module_key=?", [$label, $icon, $color, $area, $sort, $key]);
}
echo "Đã đồng bộ icon/màu/nhãn cho các module (đụng $n dòng).\n";
echo "Người dùng tải lại trang để thấy icon đúng màu.\n";
