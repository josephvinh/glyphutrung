<?php
/**
 * GỘP CSS — nối các tệp CSS NGUỒN thành MỘT cho bản thật, nén nhẹ tại chỗ.
 *
 * LUÔN dựng từ NGUỒN hiện tại (tailwind/app/...), KHÔNG phục vụ bản build
 * sẵn bundle.min.css. Trước đây nếu bundle.min.css có mtime ≥ nguồn thì nó
 * được phục vụ — mà deploy git/FTP đặt mtime bằng nhau nên bản build cũ
 * (quên chạy build/minify.cjs) đè lên CSS mới -> đổi giao diện mà vẫn cũ.
 * Nay bỏ hẳn bẫy đó: sửa CSS nguồn là hiện ngay, khỏi build lại.
 *
 * Thứ tự theo asset_manifest (cascade: tailwind nền trước, phần ghi đè sau).
 * index.php nạp bundle.php?v=<hash nội dung> nên trình duyệt vẫn cache 1 năm,
 * chỉ tải lại khi nội dung đổi.
 */
$manifest = require __DIR__ . '/../asset_manifest.php';
$base = __DIR__ . '/';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('Vary: Accept-Encoding');

$css = '';
foreach ($manifest['css'] as $c) {
    $f = $base . $c . '.css';
    if (is_file($f)) $css .= "\n" . file_get_contents($f);
}

// Nén nhẹ, an toàn: bỏ comment /* ... */ và gộp khoảng trắng về một dấu cách.
// (Không đụng dấu ngoặc/dấu chấm phẩy nên không có nguy cơ làm hỏng luật CSS.)
$css = preg_replace('!/\*.*?\*/!s', '', $css);
$css = preg_replace('/\s+/', ' ', $css);
echo trim($css);
