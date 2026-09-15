<?php
/**
 * CẤU HÌNH KẾT NỐI (giá trị MẶC ĐỊNH, lên git)
 *
 * KHÔNG sửa file này trên máy chủ. Thay vào đó tạo `config/config.local.php`
 * (KHÔNG lên git) để ghi đè DB thật + secrets — xem `config/config.local.example.php`.
 * Nhờ vậy `git pull` không bao giờ đụng cấu hình riêng của máy chủ.
 */

$config = [
    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'name'     => 'ylcqukhi_glyphutrung',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],

    // Giờ chốt = giờ bắt đầu + số phút này, áp dụng toàn hệ thống
    'cutoff_minutes' => 30,

    // Ngưỡng xét lên lớp
    'pass_score'      => 5,
    'pass_attendance' => 60,

    // Mật khẩu cấp cho tài khoản mới. Lần đăng nhập đầu buộc phải đổi.
    'default_password' => 'tntt@2026',

    // Khoá để chạy install.php qua trình duyệt khi máy chủ không có
    // Terminal. Đặt một chuỗi ngẫu nhiên dài. Để rỗng nghĩa là CẤM hẳn,
    // chỉ chạy trình cài đặt bằng dòng lệnh.
    'setup_key' => '123456789012120937867508',

    'push' => [
        'public'  => 'BKwJPh2CRLonC6WHGRXHifm1SUuwOhHOSgy6ZmkiAe3X8aLhNNIuJ58dgsu9yTlx2XuCPy_eHK60KDF68F9NDB8',
        'private' => 't1m_pTScHFjS8Z2CQcNXUYOqJGKUw5vyTru_mT2UQac',
        'subject' => 'mailto:tuongngocvinh@gmail.com',
    ],

    // THƯ VIỆN TÀI LIỆU — nơi lưu file + giới hạn.
    'library' => [
        // Thư mục lưu file NGOÀI web (không gọi URL trực tiếp được). Nếu host
        // không ghi được ngoài docroot, đổi sang một thư mục trong public có
        // .htaccess chặn (xem docs/thiet-ke-thu-vien-tai-lieu.md) rồi ghi đè
        // giá trị này trong config.local.php.
        'storage_path'     => __DIR__ . '/../storage/library',
        'max_size_mb'      => 15,
        'allowed_view'     => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],   // xem trực tiếp
        'allowed_download' => ['doc', 'docx', 'ppt', 'pptx'],          // chỉ tải về
    ],

    // ĐỂ NGUYÊN true trên máy chủ thật: cấm install.php/seed_demo.php qua trình
    // duyệt + không lộ lỗi CSDL cho người dùng. Máy nhà đặt false qua config.local.php.
    'production' => true,
];

// ---------------------------------------------------------------------------
// GHI ĐÈ THEO MÁY — config/config.local.php (KHÔNG lên git).
// Dùng để đặt DB thật + secrets trên hosting, hoặc DB demo khi test ở máy nhà,
// mà KHÔNG phải sửa file này (tránh xung đột mỗi lần git pull). Trộn đệ quy nên
// chỉ cần khai những khoá muốn đổi (vd chỉ 'db'). Env TNTT_DB_* trong db.php
// vẫn ghi đè tiếp lên trên cùng.
// ---------------------------------------------------------------------------
$__local = __DIR__ . '/config.local.php';
if (is_file($__local)) {
    $__over = require $__local;
    if (is_array($__over)) {
        $config = array_replace_recursive($config, $__over);
    }
}

return $config;
