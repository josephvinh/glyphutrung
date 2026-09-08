<?php
/**
 * MẪU config.local.php — CHÉP thành `config/config.local.php` trên MÁY CHỦ rồi
 * điền thông tin thật. File thật KHÔNG lên git (đã .gitignore), nên git pull
 * không bao giờ đè lên nó. Chỉ khai những khoá muốn ghi đè.
 *
 *   cp config/config.local.example.php config/config.local.php
 *   (rồi sửa các giá trị dưới cho đúng cPanel/AZDIGI)
 */
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'cpaneluser_tntt',      // tên DB ở cPanel
        'user' => 'cpaneluser_tntt',      // user DB ở cPanel
        'pass' => 'MẬT_KHẨU_DB_MẠNH',
    ],

    // Đổi khác mặc định để an toàn
    'default_password' => 'ĐỔI_MẬT_KHẨU_MẶC_ĐỊNH',
    'setup_key'        => 'CHUỖI_BÍ_MẬT_NGẪU_NHIÊN_DÀI',

    // Khoá VAPID riêng (tạo bằng: php -r 'require "config/push.php"; print_r(push_tao_khoa());')
    // Đổi khoá sẽ làm mọi máy đã đăng ký thông báo phải đăng ký lại.
    // 'push' => ['public' => '...', 'private' => '...', 'subject' => 'mailto:ban@giaoxu'],

    'production' => true,
];
