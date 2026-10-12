<?php
/**
 * TRẢ VỀ MỐC THỜI GIAN CẬP NHẬT GẦN NHẤT
 *
 * Dùng cho cơ chế Short Polling (10s/lần).
 * Cực kỳ nhẹ, không kết nối Database, chỉ đọc file tĩnh.
 * Mốc nằm ở storage/cache/ (ngoài web root): sync.txt = mọi thứ đổi,
 * sync_att.txt = riêng điểm danh. ?att=1 trả cả hai ("chung:điểm danh").
 */
require __DIR__ . '/_bootstrap_page.php'; // Gọn nhẹ, không nạp toàn bộ framework nặng

// Không cần phiên: nhả khoá session để không chặn data.php đang chạy song song.
session_write_close();

$dir = dirname(__DIR__, 2) . '/storage/cache/';
$doc = function (string $ten) use ($dir): string {
    $v = @file_get_contents($dir . $ten);
    return ($v === false || trim($v) === '') ? '0' : trim($v);
};
$chung    = $doc('sync.txt');       // mọi thứ đổi (giây)
$diemDanh = $doc('sync_att.txt');   // chỉ điểm danh đổi (mili-giây)

header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (($_GET['att'] ?? '') === '1') {
    // Bản mới: "<chung>:<điểm danh>" để máy khách tự quyết nạp cả gói hay
    // chỉ phần điểm danh.
    echo $chung . ':' . $diemDanh;
} else {
    // Bản cũ (trang đã mở từ trước khi cập nhật): một con số duy nhất. Lấy mốc
    // lớn hơn để thay đổi điểm danh vẫn làm máy cũ nạp lại như trước.
    echo max((int) $chung, intdiv((int) $diemDanh, 1000));
}
