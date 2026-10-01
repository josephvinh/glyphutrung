<?php
// Router cho `php -S`: giả lập Apache — tệp không tồn tại thì trả 404 thật
// (mặc định php -S rơi về index.php và trả 200 cho mọi đường dẫn, làm sai kết quả kiểm thử).
//
//   php -S 127.0.0.1:8088 -t public docs/audit/router.php
//
// Đường dẫn .php tồn tại vẫn chạy bình thường (return false => php -S tự phục vụ).
$root = $_SERVER['DOCUMENT_ROOT'];
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p === '/' || is_file($root . $p)) return false;
http_response_code(404);
echo 'Not Found';
return true;
