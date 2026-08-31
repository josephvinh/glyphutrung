<?php
/**
 * CẤU HÌNH KẾT NỐI
 *
 * Khi đưa lên AZDIGI thì sửa đúng 4 dòng dưới đây theo thông tin
 * cPanel cấp, không phải đụng vào chỗ nào khác.
 */

return [
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

    // ĐỂ NGUYÊN true khi chạy trên máy chủ thật. Cờ này:
    //   - cấm chạy install.php / seed_demo.php qua trình duyệt
    //   - không đưa nội dung lỗi cơ sở dữ liệu ra cho người dùng
    // Chỉ đổi thành false khi lập trình trên máy mình.
     'push' => [
        'public'  => 'BKwJPh2CRLonC6WHGRXHifm1SUuwOhHOSgy6ZmkiAe3X8aLhNNIuJ58dgsu9yTlx2XuCPy_eHK60KDF68F9NDB8',
        'private' => 't1m_pTScHFjS8Z2CQcNXUYOqJGKUw5vyTru_mT2UQac',
        'subject' => 'mailto:tuongngocvinh@gmail.com',
    ],
    'production' => true,
];
