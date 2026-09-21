<?php
$manifest = require __DIR__ . '/assets/asset_manifest.php';
$base = __DIR__ . '/assets/js/';
$files = array_merge(
    [$base . 'modules/toast.js'],
    array_map(fn($m) => $base . 'modules/' . $m . '.js', $manifest['js_modules']),
    [$base . 'app.js']
);
$srcMax = 0;
foreach ($files as $f) {
    $m = is_file($f) ? filemtime($f) : 0;
    $srcMax = max($srcMax, $m);
    echo basename($f) . ": " . date('Y-m-d H:i:s', $m) . "\n";
}
echo "\nbundle.min.js: " . (is_file($base . 'bundle.min.js') ? date('Y-m-d H:i:s', filemtime($base . 'bundle.min.js')) : 'N/A') . "\n";
echo "srcMax: " . date('Y-m-d H:i:s', $srcMax) . "\n";
