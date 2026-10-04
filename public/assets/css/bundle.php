<?php
/**
 * GỘP CSS — nối các tệp CSS thành MỘT cho bản thật. Thứ tự theo
 * asset_manifest (cascade: tailwind nền trước, phần ghi đè sau).
 */
$manifest = require __DIR__ . '/../asset_manifest.php';
$base = __DIR__ . '/';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('Vary: Accept-Encoding');

$files = array_map(fn($c) => $base . $c . '.css', $manifest['css']);

/* Bản NÉN — phục vụ nếu mới hơn mọi tệp nguồn; cũ hơn thì nối thô (fallback). */
$min = $base . 'bundle.min.css';
if (is_file($min)) {
    $srcMax = 0;
    foreach ($files as $f) if (is_file($f)) $srcMax = max($srcMax, (int) filemtime($f));
    if (filemtime($min) >= $srcMax) { readfile($min); exit; }
}

foreach ($files as $f) {
    if (!is_file($f)) continue;
    echo "\n/* === " . basename($f) . " === */\n";
    readfile($f);
    echo "\n";
}
