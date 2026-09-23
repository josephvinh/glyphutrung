<?php
/**
 * NÉN PHẢN HỒI TĨNH — dùng chung cho js/bundle.php, css/bundle.php và trang HTML.
 *
 * Vì sao cần: api/_bootstrap.php đã nén JSON, nhưng bundle JS/CSS gộp và trang
 * index.php lại phục vụ TRẦN. Bundle JS thô ~287 KB là byte tải ở LẦN MỞ ĐẦU
 * (trước khi service worker kịp cache) — mạng 4G yếu là mấy giây màn hình trắng.
 *
 * Cùng cơ chế với _bootstrap.php nhưng gộp về một chỗ và ưu tiên MỘT lớp nén
 * (else-if): tránh nén chồng brotli-trên-gzip. Bỏ qua nếu server đã tự nén
 * (zlib.output_compression) hoặc trình duyệt không nhận. Phải gọi TRƯỚC khi in
 * bất kỳ thứ gì (readfile/echo) để buffer bắt được toàn bộ đầu ra.
 */
function tntt_nen_tinh(): void
{
    // Server đã bật nén ở tầng của nó -> để yên, khỏi nén chồng.
    if (ini_get('zlib.output_compression')) return;

    $accept = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';

    // Brotli nén mạnh hơn gzip ~20% — ưu tiên nếu có extension và trình duyệt nhận.
    if (extension_loaded('brotli') && stripos($accept, 'br') !== false) {
        $ok = @ob_start(fn($buf) => brotli_compress($buf, 0, 5)); // level 5: cân bằng tốc độ/nén
        if (!$ok) return; // Fallback: phục vụ thô nếu buffer lỗi
        header('Content-Encoding: br');
        header('Vary: Accept-Encoding');
        return;
    }

    // gzip: ob_gzhandler tự đặt Content-Encoding và Vary giúp.
    if (extension_loaded('zlib') && stripos($accept, 'gzip') !== false) {
        $ok = @ob_start('ob_gzhandler');
        if (!$ok) return; // Fallback: phục vụ thô nếu buffer lỗi
    }
}
