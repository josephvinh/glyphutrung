<?php
/**
 * CHẨN ĐOÁN CHỨC NĂNG QUÉT QR
 *
 * Chức năng QR gồm 5 mảnh nằm ở 3 thư mục khác nhau. Thiếu một mảnh là
 * nút bấm không phản ứng gì, mà trình duyệt cũng chẳng báo lỗi rõ ràng.
 * Trang này kiểm đủ 5 mảnh đó.
 *
 *   https://tenmien/kiem-tra-qr.php
 *
 * XOÁ TỆP NÀY sau khi sửa xong.
 */

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
function kt(string $ten, bool $dat, string $ct = ''): void {
    global $loi;
    if (!$dat) $loi++;
    printf("%s  %-38s %s\n", $dat ? '[ OK ]' : '[ LỖI]', $ten, $ct);
}
function chua(string $duong, string $chuoi): bool {
    if (!is_file($duong)) return false;
    return str_contains((string) file_get_contents($duong), $chuoi);
}

$goc = dirname(__DIR__);

echo "CHẨN ĐOÁN CHỨC NĂNG QUÉT QR\n";
echo str_repeat('=', 62) . "\n\n";

// ---------- 1. Tệp JavaScript trong public/ ----------
echo "1. TỆP JAVASCRIPT  (public/assets/js/)\n";
foreach ([
    'modules/qrscan.js'    => 5000,
    'modules/qrcard.js'    => 2000,
    'vendor/jsQR.min.js'   => 100000,
    'vendor/qrcode.min.js' => 10000,
] as $f => $toiThieu) {
    $p  = __DIR__ . '/assets/js/' . $f;
    $co = is_file($p);
    kt($f, $co && filesize($p) >= $toiThieu,
       $co ? number_format(filesize($p)) . ' byte' : 'KHÔNG THẤY');
}

// ---------- 2. Bộ gộp có khai hai mảnh mới chưa ----------
echo "\n2. BỘ GỘP  (public/assets/js/app.js)\n";
$appjs = __DIR__ . '/assets/js/app.js';
kt("app.js có khai 'qrscan'", chua($appjs, "'qrscan'"));
kt("app.js có khai 'qrcard'", chua($appjs, "'qrcard'"));

// ---------- 3. Trang chính có nạp hai mảnh mới không ----------
echo "\n3. TRANG CHÍNH  (public/index.php)\n";
$idx = __DIR__ . '/index.php';
kt("index.php nạp qrscan", chua($idx, "'qrscan'"),
   chua($idx, "'qrscan'") ? '' : 'CHƯA CHÉP index.php mới');
kt("index.php nạp qrcard", chua($idx, "'qrcard'"));

// ---------- 4. Giao diện  (views/ nằm NGOÀI public) ----------
echo "\n4. GIAO DIỆN  (views/  — ngoài thư mục web)\n";
$vAtt = $goc . '/views/module_attendance.php';
$vStu = $goc . '/views/module_students.php';
kt('module_attendance.php có nút Quét QR', chua($vAtt, 'moQuetQR()'),
   chua($vAtt, 'moQuetQR()') ? '' : 'CHƯA CHÉP _views/module_attendance.php');
kt('module_attendance.php có màn quét',     chua($vAtt, 'qrVideo'));
kt('module_attendance.php có nút Kết thúc', chua($vAtt, 'ketThucQuet()'));
kt('module_students.php có nút Thẻ QR',     chua($vStu, 'inTheQR()'),
   chua($vStu, 'inTheQR()') ? '' : 'CHƯA CHÉP _views/module_students.php');

// ---------- 5. Máy chủ có nhận ghi theo lô không ----------
echo "\n5. MÁY CHỦ  (public/api/attendance.php)\n";
$api = __DIR__ . '/api/attendance.php';
kt('api/attendance.php có nhánh scan', chua($api, "=== 'scan'"),
   chua($api, "=== 'scan'") ? '' : 'CHƯA CHÉP _api/attendance.php');
kt('có INSERT IGNORE (không gỡ em ra)', chua($api, 'INSERT IGNORE INTO attendances'));

// Từ đây là chuyện MÔI TRƯỜNG, không phải thiếu tệp. Đếm riêng để kết
// luận không bảo người ta giải nén lại một cách vô ích.
$thieuTep = $loi;

// ---------- 6. Điều kiện chạy camera ----------
echo "\n6. ĐIỀU KIỆN CHẠY CAMERA\n";
$https = !empty($_SERVER['HTTPS'])
      || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
kt('Trang chạy HTTPS', $https, $https ? '' : 'Camera KHÔNG chạy trên http://');

// ---------- Kết luận ----------
echo "\n" . str_repeat('=', 62) . "\n";

if ($thieuTep > 0) {
    echo "THIẾU {$thieuTep} MẢNH TỆP.\n\n";
    echo "Giải nén lại bo_qr.zip và chép ĐÚNG BA ĐÍCH:\n";
    echo "  - mọi thứ trừ _views/ và _api/  ->  public/\n";
    echo "  - _api/attendance.php           ->  public/api/\n";
    echo "  - _views/*.php                  ->  views/   (NGOÀI public)\n";
} elseif (!$https) {
    echo "TỆP ĐỦ CẢ, NHƯNG TRANG KHÔNG CHẠY HTTPS.\n\n";
    echo "Trình duyệt CẤM mở camera trên http:// — đây là luật của trình\n";
    echo "duyệt, không sửa được bằng mã. Bật SSL rồi mở lại bằng https://\n";
} else {
    echo "MỌI MẢNH ĐỀU ĐỦ.\n\n";
    echo "Nếu bấm nút vẫn không thấy gì, làm theo thứ tự:\n";
    echo "  1. Bấm Ctrl+F5 (điện thoại: xoá bộ nhớ đệm trình duyệt).\n";
    echo "     Trình duyệt hay giữ bản app.js cũ.\n";
    echo "  2. Phải CHỌN BUỔI và CHỌN LỚP trước, rồi nút Quét QR mới chạy.\n";
    echo "  3. Lần đầu trình duyệt hỏi quyền camera — phải bấm Cho phép.\n";
    echo "     Lỡ bấm Chặn thì vào cài đặt trang của trình duyệt bật lại.\n";
    echo "  4. Mở Console của trình duyệt xem có dòng đỏ nào không,\n";
    echo "     rồi gửi nguyên văn dòng đó.\n";
}

echo "\nXOÁ TỆP kiem-tra-qr.php sau khi xong.\n";
