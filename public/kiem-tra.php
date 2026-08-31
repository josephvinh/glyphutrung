<?php
/**
 * TRANG CHẨN ĐOÁN — dùng khi máy chủ báo lỗi 500
 *
 * Cố ý KHÔNG nạp db.php hay bootstrap, để nó vẫn chạy được kể cả khi
 * chính những tệp đó đang gây lỗi. Chỉ kiểm bằng hàm lõi của PHP.
 *
 *   https://tenmien/kiem-tra.php
 *
 * XOÁ TỆP NÀY sau khi sửa xong.
 */

// Bật hiện lỗi CHO RIÊNG trang này, để thấy được thứ đang bị giấu
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: text/plain; charset=utf-8');

/**
 * CHỐT: trên máy chủ thật phải có khoá mới xem được.
 *
 * Trang này in ra tên cơ sở dữ liệu và tên đăng nhập. Không nhiều,
 * nhưng cũng không có lý do gì để người lạ đọc được.
 *
 * Cố ý VẪN CHO XEM khi chưa có config.php hoặc config hỏng — đó chính
 * là lúc cần trang này nhất, mà lúc đó cũng chưa có gì để lộ.
 */
(function () {
    $cfg = @include dirname(__DIR__) . '/config/config.php';
    if (!is_array($cfg) || empty($cfg['production'])) return;   // máy dev: mở

    $khoa = (string) ($cfg['setup_key'] ?? '');
    $gui  = (string) ($_GET['key'] ?? '');
    if ($khoa !== '' && hash_equals($khoa, $gui)) return;

    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Cần khoá để xem trang này.\n\n"
       . "Mở lại kèm khoá:  ?key=SETUP_KEY\n"
       . "SETUP_KEY là chuỗi trong config/config.php\n");
})();

$loi = 0;
function kt(string $ten, bool $dat, string $chiTiet = '', bool $batBuoc = true): void
{
    global $loi;
    if (!$dat && $batBuoc) $loi++;
    printf("%s  %-34s %s\n", $dat ? '[ OK ]' : ($batBuoc ? '[ LỖI]' : '[ ! ]'), $ten, $chiTiet);
}

echo "KIỂM TRA MÁY CHỦ — TNTT Super App\n";
echo str_repeat('=', 66) . "\n\n";

// ---------- 1. PHP ----------
echo "1. PHP\n";
kt('Phiên bản >= 8.1', PHP_VERSION_ID >= 80100, 'đang chạy ' . PHP_VERSION);
foreach (['pdo_mysql' => true, 'mbstring' => true, 'json' => true, 'openssl' => false] as $ext => $buoc) {
    kt('Phần mở rộng ' . $ext, extension_loaded($ext), $buoc ? '' : '(nên có)', $buoc);
}

// ---------- 2. Cấu trúc thư mục ----------
echo "\n2. CẤU TRÚC THƯ MỤC\n";
$goc = dirname(__DIR__);
kt('Thư mục gốc dự án', is_dir($goc), $goc);
foreach (['config', 'views'] as $d) {
    kt('Có thư mục ' . $d . '/', is_dir($goc . '/' . $d), $goc . '/' . $d);
}
kt('config/ nằm NGOÀI thư mục web',
   strpos(realpath($goc . '/config') ?: '', realpath(__DIR__)) !== 0,
   'thư mục web: ' . __DIR__);

// ---------- 3. Tệp cần có ----------
echo "\n3. TỆP CẦN CÓ\n";
$can = [
    'config/config.php'              => true,
    'config/db.php'                  => true,
    'config/schema.sql'              => true,
    'views/layout_login.php'         => true,
    'assets/css/tailwind.css'        => false,
    'assets/js/app.js'               => false,
];
foreach ($can as $f => $ngoaiPublic) {
    $p = $ngoaiPublic ? $goc . '/' . $f : __DIR__ . '/' . $f;
    kt($f, is_file($p), is_file($p) ? number_format(filesize($p)) . ' byte' : 'KHÔNG THẤY');
}

// api/ nạp bằng require (không phải include) nên thiếu là chết ngay
// -> HTTP 500 trắng. Đây là nguyên nhân 500 hay gặp nhất khi giải nén sót.
echo "
   Đếm tệp trong các thư mục của public/:
";
foreach (['api' => 14, 'assets/js/modules' => 14, 'assets/css' => 2] as $d => $toiThieu) {
    $duong = __DIR__ . '/' . $d;
    $n = is_dir($duong) ? count(glob($duong . '/*.*')) : 0;
    kt($d . '/', $n >= $toiThieu, $n . '/' . $toiThieu . ' tệp' . ($n === 0 ? '  <- THIẾU HẲN, đây là nguyên nhân 500' : ($n < $toiThieu ? '  <- thiếu tệp' : '')));
}
$vieTs = $goc . '/views';
kt('views/ (ngoài public)', is_dir($vieTs) && count(glob($vieTs . '/*.php')) >= 20,
   (is_dir($vieTs) ? count(glob($vieTs . '/*.php')) : 0) . '/21 tệp');

// ---------- 4. Cấu hình ----------
echo "\n4. CẤU HÌNH\n";
$cfgPath = $goc . '/config/config.php';
if (!is_file($cfgPath)) {
    kt('Đọc được config.php', false, 'chưa tạo — hãy chép từ config.example.php');
} else {
    $cfg = @include $cfgPath;
    kt('config.php trả về mảng', is_array($cfg));
    if (is_array($cfg)) {
        $db = $cfg['db'] ?? [];
        foreach (['host', 'name', 'user'] as $k) {
            $v = (string) ($db[$k] ?? '');
            $chuaDien = $v === '' || str_starts_with($v, 'ĐIỀN');
            kt('db.' . $k, !$chuaDien, $chuaDien ? 'CHƯA ĐIỀN' : $v);
        }
        $pass = (string) ($db['pass'] ?? '');
        kt('db.pass', $pass !== '' && !str_starts_with($pass, 'ĐIỀN'),
           $pass === '' ? 'trống' : (str_starts_with($pass, 'ĐIỀN') ? 'CHƯA ĐIỀN' : 'đã điền (' . strlen($pass) . ' ký tự)'));
        kt('production = true', ($cfg['production'] ?? false) === true,
           ($cfg['production'] ?? false) ? '' : 'ĐANG false — phải đổi thành true', true);

        // ---------- 5. Kết nối CSDL ----------
        echo "\n5. KẾT NỐI CƠ SỞ DỮ LIỆU\n";
        try {
            $dsn = 'mysql:host=' . ($db['host'] ?? '127.0.0.1')
                 . ';port=' . ($db['port'] ?? 3306)
                 . ';dbname=' . ($db['name'] ?? '')
                 . ';charset=' . ($db['charset'] ?? 'utf8mb4');
            $pdo = new PDO($dsn, $db['user'] ?? '', $db['pass'] ?? '',
                           [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            kt('Kết nối được', true, $pdo->query('SELECT VERSION()')->fetchColumn());
            $n = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables
                                     WHERE table_schema = DATABASE()")->fetchColumn();
            kt('Đã dựng bảng', $n >= 20, $n . ' bảng' . ($n < 20 ? ' — chưa chạy trình cài đặt' : ''));
        } catch (Throwable $e) {
            kt('Kết nối được', false, $e->getMessage());
        }
    }
}

// ---------- 6. Máy chủ web ----------
echo "\n6. MÁY CHỦ WEB\n";
kt('Đang chạy HTTPS', !empty($_SERVER['HTTPS']), !empty($_SERVER['HTTPS']) ? '' : 'chưa bật — làm bước 6', false);
kt('Có .htaccess trong public/', is_file(__DIR__ . '/.htaccess'), '', false);
echo "       Máy chủ: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'không rõ') . "\n";
echo "       Thư mục web: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'không rõ') . "\n";

// ---------- Kết luận ----------
echo "\n" . str_repeat('=', 66) . "\n";
if ($loi === 0) {
    echo "KHÔNG THẤY LỖI. Nếu trang chính vẫn 500, xem tiếp:\n";
    echo "  - Đổi tên public/.htaccess thành .htaccess.tat rồi mở lại trang.\n";
    echo "    Hết 500 nghĩa là hosting cấm một chỉ thị trong đó.\n";
    echo "  - Xem nhật ký lỗi: cPanel > Metrics > Errors\n";
} else {
    echo "CÓ $loi LỖI ở trên. Sửa từ trên xuống rồi tải lại trang này.\n";
}
echo "\nXOÁ TỆP kiem-tra.php sau khi sửa xong.\n";
