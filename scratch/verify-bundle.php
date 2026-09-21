<?php
/**
 * Simulate bundle.php to verify it concatenates all modules.
 */
$manifest = require __DIR__ . '/../public/assets/asset_manifest.php';
$base = __DIR__ . '/../public/assets/js/';

$files = array_merge(
    [$base . 'modules/toast.js'],
    array_map(fn($m) => $base . 'modules/' . $m . '.js', $manifest['js_modules']),
    [$base . 'app.js']
);

echo "=== Files that bundle.php will concatenate ===\n";
$totalSize = 0;
$missing = [];
foreach ($files as $f) {
    $exists = is_file($f);
    $size = $exists ? filesize($f) : 0;
    $totalSize += $size;
    $status = $exists ? 'OK' : 'MISSING';
    if (!$exists) $missing[] = $f;
    echo "[$status] " . str_replace($base, '', $f) . " (" . number_format($size) . " bytes)\n";
}

echo "\n=== Summary ===\n";
echo "Total modules: " . count($manifest['js_modules']) . "\n";
echo "Total files: " . count($files) . "\n";
echo "Total size: " . number_format($totalSize) . " bytes (" . round($totalSize/1024, 1) . " KB)\n";
if ($missing) {
    echo "MISSING files: " . implode(', ', $missing) . "\n";
} else {
    echo "All files present ✓\n";
}

// Check specific library functions
echo "\n=== Key library functions in source ===\n";
$libSrc = file_get_contents($base . 'modules/library.js');
$funcs = ['loadLibrary', 'libRefresh', 'openCompose', 'editArticle', 'editFileItem', 'openCatManager', 'libMore', 'libLoadCategories', '_libLoad'];
foreach ($funcs as $f) {
    $pos = strpos($libSrc, $f);
    echo ($pos !== false ? '[YES] ' : '[NO]  ') . "$f\n";
}
