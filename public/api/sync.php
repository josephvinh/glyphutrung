<?php
/**
 * TRẢ VỀ MỐC THỜI GIAN CẬP NHẬT GẦN NHẤT
 *
 * Dùng cho cơ chế Short Polling (10s/lần). 
 * Cực kỳ nhẹ, không kết nối Database, chỉ đọc file tĩnh.
 */
require __DIR__ . '/_bootstrap_page.php'; // Gọn nhẹ, không nạp toàn bộ framework nặng

$syncFile = __DIR__ . '/../cache/sync.txt';
$ts = file_exists($syncFile) ? file_get_contents($syncFile) : '0';

header('Content-Type: text/plain');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $ts;
