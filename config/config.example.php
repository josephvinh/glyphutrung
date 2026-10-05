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

    // PR-2: Retention policy cho hồ sơ thiếu nhi
    // Ẩn: sau 12 tháng không hoạt động (không ghi danh, không cập nhật)
    // Xóa: sau 7 năm từ ngày tạo hoặc theo yêu cầu phụ huynh
    'retention' => [
        'hide_after_months'  => 12,  // Ẩn sau 12 tháng
        'delete_after_years' => 7,   // Xóa sau 7 năm
    ],

    // Mật khẩu cấp cho tài khoản mới. Lần đăng nhập đầu buộc phải đổi.
    // ⚠️ QUAN TRỌNG: Đổi thành chuỗi ngẫu nhiên dài trước khi deploy production!
    // Ví dụ: openssl_rand_pseudo_bytes(16) -> hex hoặc dùng password generator
    'default_password' => 'CHANGE_ME_BEFORE_PRODUCTION',

    // Khoá để chạy install.php qua trình duyệt khi máy chủ không có
    // Terminal. Đặt một chuỗi ngẫu nhiên dài (tối thiểu 32 ký tự).
    // Để rỗng nghĩa là CẤM hẳn, chỉ chạy trình cài đặt bằng dòng lệnh.
    // ⚠️ QUAN TRỌNG: Đổi thành giá trị ngẫu nhiên khác trước khi deploy!
    // Sinh khoá: php -r "echo bin2hex(random_bytes(32));"
    'setup_key' => 'CHANGE_ME_TO_RANDOM_32_PLUS_CHARS',

    // ĐỂ NGUYÊN true khi chạy trên máy chủ thật. Cờ này:
    //   - cấm chạy install.php / seed_demo.php qua trình duyệt
    //   - không đưa nội dung lỗi cơ sở dữ liệu ra cho người dùng
    // Chỉ đổi thành false khi lập trình trên máy mình.
    // Thông báo đẩy ra màn hình điện thoại.
    // Sinh khoá bằng:  php config/tao_khoa_push.php
    // Để trống thì app vẫn chạy bình thường, chỉ là không gửi thông báo.
    'push' => [
        // ⚠️ QUAN TRỌNG: Thay bằng VAPID keys thật từ https://web-push-codelab.glitch.me/
        'public'  => '',
        'private' => '',
        'subject' => 'mailto:ĐIỀN_EMAIL_CỦA_BẠN',
    ],

    'production' => true,
];
