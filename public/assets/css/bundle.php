<?php
/**
 * GỘP CSS — nối các tệp CSS thành MỘT cho bản thật. Thứ tự theo
 * asset_manifest (cascade: tailwind nền trước, phần ghi đè sau).
 */
$manifest = require __DIR__ . '/../asset_manifest.php';
$base = __DIR__ . '/';

header('Content-Type: text/css; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');

foreach ($manifest['css'] as $c) {
    $f = $base . $c . '.css';
    if (!is_file($f)) continue;
    echo "\n/* === " . $c . ".css === */\n";
    readfile($f);
    echo "\n";
}
