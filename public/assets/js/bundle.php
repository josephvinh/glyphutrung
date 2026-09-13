<?php
/**
 * GỘP JS — nối toast + các module + app.js thành MỘT tệp cho bản thật.
 * index.php nạp `bundle.php?v=<mtime lớn nhất>`; sửa file nào -> ?v đổi ->
 * trình duyệt tự tải bản mới, còn lại thì cache một năm.
 *
 * Thứ tự PHẢI khớp index.php: toast -> module (theo asset_manifest) -> app.js.
 */
$manifest = require __DIR__ . '/../asset_manifest.php';
$base = __DIR__ . '/';

$files = array_merge(
    [$base . 'modules/toast.js'],
    array_map(fn($m) => $base . 'modules/' . $m . '.js', $manifest['js_modules']),
    [$base . 'app.js']
);

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');

/* Bản NÉN (build/minify.cjs) — phục vụ nếu nó MỚI HƠN mọi tệp nguồn.
   Cũ hơn (quên build sau khi sửa code) thì rơi về nối thô bên dưới -> không
   bao giờ phục vụ code cũ/hỏng. */
$min = $base . 'bundle.min.js';
if (is_file($min)) {
    $srcMax = 0;
    foreach ($files as $f) if (is_file($f)) $srcMax = max($srcMax, (int) filemtime($f));
    if (filemtime($min) >= $srcMax) { readfile($min); exit; }
}

foreach ($files as $f) {
    if (!is_file($f)) continue;
    echo "\n/* === " . basename($f) . " === */\n";
    readfile($f);
    // Dấu ; giữa các tệp: phòng file thiếu ; cuối làm ASI dính hai câu lệnh.
    echo "\n;\n";
}
