<?php
// Bật hiển thị lỗi để kiểm tra cấu hình host (rất quan trọng khi debug)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html lang='vi'><head><meta charset='UTF-8'><title>Kiểm Tra & Tối Ưu Hosting</title>";
echo "<style>
    body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; color: #333; max-width: 900px; margin: 0 auto; padding: 20px; }
    h1, h2 { color: #0056b3; }
    .ok { color: #28a745; font-weight: bold; }
    .warn { color: #ff9800; font-weight: bold; }
    .err { color: #dc3545; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
    ul { margin: 0; padding-left: 20px; }
    li { margin-bottom: 5px; }
</style></head><body>";

echo "<h1>Kiểm Tra Lỗi & Cấu Hình Tối Ưu Hosting</h1>";
echo "<hr>";

// 1. Thông tin cấu hình cơ bản
echo "<h2>1. Thông số Hosting cơ bản</h2>";
echo "Phiên bản PHP: <b>" . phpversion() . "</b><br>";
echo "Hệ điều hành: <b>" . PHP_OS . "</b><br>";
echo "Web Server API (SAPI): <b>" . php_sapi_name() . "</b><br>";
echo "Giao thức mạng: <b>" . ($_SERVER['SERVER_PROTOCOL'] ?? 'Không xác định') . "</b><br>";
echo "Giới hạn bộ nhớ (memory_limit): <b>" . ini_get('memory_limit') . "</b> (Khuyến nghị: tối thiểu 128MB, tốt nhất 256MB+)<br>";
echo "Thời gian chạy tối đa (max_execution_time): <b>" . ini_get('max_execution_time') . " giây</b> (Khuyến nghị: 60 - 300s)<br>";
echo "Kích thước upload file (upload_max_filesize): <b>" . ini_get('upload_max_filesize') . "</b><br>";
echo "Kích thước gửi dữ liệu POST (post_max_size): <b>" . ini_get('post_max_size') . "</b><br>";

// 2. Thông tin Tối ưu hóa (Web Optimization)
echo "<h2>2. Thông số hỗ trợ Tối Ưu Tốc Độ Web (Speed & Caching)</h2>";
echo "<table><tr><th>Tính năng Tối ưu</th><th>Trạng thái trên Host</th><th>Giải thích / Khuyến nghị</th></tr>";

// Check OPcache (Tối ưu mã nguồn PHP)
$opcache = function_exists('opcache_get_status') && opcache_get_status() !== false;
echo "<tr><td><b>Zend OPcache</b><br><small>(Bộ nhớ đệm PHP)</small></td>";
echo "<td>" . ($opcache ? "<span class='ok'>Đã Bật</span>" : "<span class='err'>Đang Tắt / Không có</span>") . "</td>";
echo "<td>OPcache biên dịch mã PHP sẵn, giúp web chạy nhanh hơn gấp 2-3 lần. Nếu đang tắt, hãy vào cPanel bật Extension 'opcache'.</td></tr>";

// Check Gzip/zlib Compression (Nén file)
$zlib = ini_get('zlib.output_compression');
echo "<tr><td><b>Nén Gzip</b><br><small>(zlib.output_compression)</small></td>";
echo "<td>" . ($zlib ? "<span class='ok'>Đã Bật</span>" : "<span class='warn'>Đang Tắt</span>") . "</td>";
echo "<td>Nén dữ liệu HTML/CSS gửi cho trình duyệt, làm web nhẹ hơn, load nhanh hơn và tiết kiệm băng thông.</td></tr>";

// Check Object Caching (Redis/Memcached) - Giảm tải Database
$redis = extension_loaded('redis');
$memcached = extension_loaded('memcached');
echo "<tr><td><b>Object Cache</b><br><small>(Redis / Memcached)</small></td>";
echo "<td>";
if ($redis) echo "<span class='ok'>Có Redis</span><br>";
if ($memcached) echo "<span class='ok'>Có Memcached</span>";
if (!$redis && !$memcached) echo "<span class='warn'>Không tìm thấy</span>";
echo "</td><td>Redis/Memcached giúp ghi nhớ các câu lệnh Database. Rất quan trọng cho web dùng WordPress, Magento, Laravel để giảm tải cho máy chủ MySQL.</td></tr>";

// Check HTTP/2
$http2 = isset($_SERVER['SERVER_PROTOCOL']) && strpos($_SERVER['SERVER_PROTOCOL'], 'HTTP/2') !== false;
echo "<tr><td><b>HTTP/2 Protocol</b></td>";
echo "<td>" . ($http2 ? "<span class='ok'>Đang dùng HTTP/2</span>" : "<span class='warn'>Chưa dùng / Không nhận diện được</span>") . "</td>";
echo "<td>HTTP/2 giúp trình duyệt tải cùng lúc nhiều file ảnh, CSS, JS song song. (Lưu ý: Đôi khi mã PHP không nhận ra nhưng server Nginx vẫn hỗ trợ).</td></tr>";

// Check Realpath Cache
$realpath_size = ini_get('realpath_cache_size');
echo "<tr><td><b>Realpath Cache Size</b><br><small>(Bộ đệm đường dẫn)</small></td>";
echo "<td><b>{$realpath_size}</b></td>";
echo "<td>Nên cấu hình ở mức <b>4096k (4MB)</b> trở lên cho các web lớn, giúp PHP tìm file require/include cực nhanh.</td></tr>";

echo "</table>";

// 3. Kiểm tra quyền ghi file (Nguyên nhân lỗi 500 / Không upload được ảnh)
echo "<h2>3. Kiểm tra Lỗi Phân Quyền (File Permission)</h2>";
$test_file = 'test_write_permission.txt';
if (is_writable(__DIR__)) {
    $fp = @fopen($test_file, 'w');
    if ($fp) {
        fwrite($fp, "Host cho phép ghi file.");
        fclose($fp);
        unlink($test_file); 
        echo "<span class='ok'>✔ Host cho phép ghi file và tải ảnh bình thường (Write Permission OK).</span><br>";
    } else {
        echo "<span class='err'>✖ Thư mục cho phép nhưng không thể tạo file (Có lỗi ẩn).</span><br>";
    }
} else {
    echo "<span class='err'>✖ Thư mục không có quyền ghi. Lỗi không upload được ảnh hoặc update theme. Hãy CHMOD thành 755!</span><br>";
}

// 4. Giả lập và bắt lỗi Code
echo "<h2>4. Bắt lỗi ứng dụng PHP (Xử lý Exception)</h2>";
try {
    echo "• Kiểm tra lỗi DivisionByZero: ";
    $result = 10 / 0;
} catch (Throwable $e) {
    echo "<span class='warn'>Đã bắt an toàn: " . $e->getMessage() . "</span><br>";
}
try {
    echo "• Kiểm tra lỗi Undefined Function: ";
    ham_nay_khong_ton_tai_tren_doi();
} catch (Throwable $e) {
    echo "<span class='warn'>Đã bắt an toàn: " . $e->getMessage() . "</span><br>";
}

// 5. Kiểm tra Extension bắt buộc (Cho Web hiện đại)
echo "<h2>5. Kiểm tra Extension bắt buộc</h2>";
$extensions = [
    'mysqli' => 'Kết nối Database MySQL', 
    'pdo_mysql' => 'Trình kết nối MySQL bảo mật mới (Dùng cho Laravel)', 
    'curl' => 'Gửi Request lấy dữ liệu bên ngoài (VD: API thanh toán)', 
    'gd' => 'Thư viện cắt, nén, xử lý hình ảnh', 
    'mbstring' => 'Xử lý chuỗi tiếng Việt Unicode', 
    'zip' => 'Nén và giải nén file', 
    'json' => 'Xử lý dữ liệu định dạng JSON',
    'exif' => 'Đọc thông tin ảnh chụp từ máy ảnh/điện thoại',
    'imagick' => 'Xử lý ảnh chuyên nghiệp (Nét hơn GD)'
];
echo "<ul>";
foreach ($extensions as $ext => $desc) {
    if (extension_loaded($ext)) {
        echo "<li><span class='ok'>✔ $ext</span>: $desc</li>";
    } else {
        echo "<li><span class='err'>✖ $ext</span>: LỖI - Chưa cài đặt ($desc)</li>";
    }
}
echo "</ul>";

echo "<hr><p style='text-align:center;'><i>Công cụ phân tích này giúp bạn biết chắc chắn Host của mình đã sẵn sàng cho một website có tốc độ cao và ổn định hay chưa!</i></p>";
echo "</body></html>";
?>
