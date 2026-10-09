<?php
/**
 * TIỆN ÍCH HTTP DÙNG CHUNG — client_ip() + json_out()
 *
 * SINGLE SOURCE cho các hàm này. Trước đây `_bootstrap.php` định nghĩa
 * chúng inline, còn `public/api/_somoc.php` (trang public không nạp
 * `_bootstrap.php`, mẫu `public/bxh.php`) phải ĐỊNH NGHĨA LẠI y hệt, bọc
 * `function_exists()`. Hai bản dễ TRÔI LỆCH ÂM THẦM (sửa `client_ip()` để
 * tin `X-Forwarded-For` sau proxy chẳng hạn — bản copy không theo, làm yếu
 * rate-limit) và có nguy cơ "Cannot redeclare function" nếu một file sau
 * này (Task P3-2/P3-3, trộn luồng public + `_bootstrap`) `require`
 * `_somoc.php` TRƯỚC `_bootstrap.php`.
 *
 * Giải pháp: tách về ĐÚNG MỘT file nhỏ, không phụ thuộc gì khác (không
 * session, không CSP, không DB) — cả `_bootstrap.php` (đầu file) lẫn
 * `_somoc.php` cùng `require_once` file này. Trang public vẫn nhẹ như
 * `bxh.php` (không kéo theo toàn bộ `_bootstrap.php`), còn hai nơi gọi
 * cùng chạy chung một bản duy nhất, không còn nguy cơ redeclare dù nạp
 * theo thứ tự nào.
 */

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }
}

if (!function_exists('json_out')) {
    /**
     * Trả JSON rồi dừng. Tự đặt Content-Type: các trang gọi hàm này KHÔNG
     * nạp `_bootstrap.php` (vốn đặt header JSON chung cho mọi endpoint API)
     * nên phải tự đảm bảo header đúng ở đây — đặt lại ở API cũng vô hại
     * (header() ghi đè giá trị cùng tên, không tạo dòng trùng).
     */
    function json_out($data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Response helpers are in _response.php (includes at end for load order)
require_once __DIR__ . '/_response.php';
