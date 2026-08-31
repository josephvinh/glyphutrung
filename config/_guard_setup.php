<?php
/**
 * CHỐT BẢO VỆ CHO SCRIPT CÀI ĐẶT
 *
 * install.php và seed_demo.php ghi thẳng vào cơ sở dữ liệu mà không
 * cần đăng nhập. Nếu để nguyên trên máy chủ, bất kỳ ai biết đường dẫn
 * cũng chạy được — nạp dữ liệu rác, hoặc tạo tài khoản với mật khẩu
 * mặc định ai cũng biết.
 *
 * Quy tắc: chạy được khi
 *   1. gọi từ dòng lệnh  (php config/install.php), hoặc
 *   2. gọi từ chính máy chủ (127.0.0.1 / ::1), hoặc
 *   3. kèm đúng khoá cài đặt:  ?key=<setup_key trong config.php>
 *
 * Sau khi cài xong nên xoá hẳn hai file này khỏi máy chủ.
 */

function guard_setup(string $ten): void
{
    if (PHP_SAPI === 'cli') return;                    // 1. dòng lệnh

    // 2. ngay trên máy dev.
    //    CHỈ chấp nhận khi chưa bật production: nhiều máy chủ chạy sau
    //    proxy nội bộ nên REMOTE_ADDR cũng là 127.0.0.1 — tin vào nó
    //    trên máy chủ thật là tự mở cửa.
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!app_config('production') && ($ip === '127.0.0.1' || $ip === '::1')) return;

    $key = (string) app_config('setup_key');
    $gui = (string) ($_GET['key'] ?? '');
    // hash_equals: so sánh không lệ thuộc thời gian, chống dò từng ký tự
    if ($key !== '' && hash_equals($key, $gui)) return; // 3. đúng khoá

    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(403);
    exit("Không được phép chạy $ten qua trình duyệt.\n"
       . "Hãy chạy bằng dòng lệnh:  php config/$ten\n");
}
