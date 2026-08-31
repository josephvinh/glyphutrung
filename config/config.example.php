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
        'name'     => 'ĐIỀN_TÊN_CƠ_SỞ_DỮ_LIỆU',
        'user'     => 'ĐIỀN_TÊN_ĐĂNG_NHẬP_CSDL',
        'pass'     => 'ĐIỀN_MẬT_KHẨU_CSDL',
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
    'setup_key' => 'ĐIỀN_CHUỖI_NGẪU_NHIÊN',

    // ĐỂ NGUYÊN true khi chạy trên máy chủ thật. Cờ này:
    //   - cấm chạy install.php / seed_demo.php qua trình duyệt
    //   - không đưa nội dung lỗi cơ sở dữ liệu ra cho người dùng
    // Chỉ đổi thành false khi lập trình trên máy mình.
    // Thông báo đẩy ra màn hình điện thoại.
    // Sinh khoá bằng:  php config/tao_khoa_push.php
    // Để trống thì app vẫn chạy bình thường, chỉ là không gửi thông báo.
    'push' => [
        'public'  => '',
        'private' => '',
        'subject' => 'mailto:ĐIỀN_EMAIL_CỦA_BẠN',
    ],

    'production' => true,
];
