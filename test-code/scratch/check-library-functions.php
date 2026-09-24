<?php
/**
 * Check which library functions exist in bundle vs source.
 */
$c = file_get_contents(__DIR__ . '/../public/assets/js/bundle.min.js');
$src = file_get_contents(__DIR__ . '/../public/assets/js/modules/library.js');

$funcs = [
    'openLibItem', 'editArticle', 'editFileItem', 'libApprove', 'libReject',
    'libDelete', 'libRefresh', 'libLoadMine', 'libLoadPending', 'libSetTab',
    'openCompose', 'libPickFile', 'submitCompose', 'libItemIcon', 'libIcon',
    'libSizeLabel', 'libStatusLabel', 'openCatManager', 'libSaveCategory',
    'libToggleCategory', 'libDeleteCategory', 'libMore', 'initLibViewer', 'loadLibrary'
];

echo "=== Function presence ===\n";
echo str_repeat('-', 50) . "\n";
printf("%-25s %10s %10s\n", 'Function', 'Source', 'Bundle');
echo str_repeat('-', 50) . "\n";
$missing = [];
foreach ($funcs as $f) {
    $inSrc = strpos($src, $f) !== false;
    $inBundle = strpos($c, $f) !== false;
    $status = $inBundle ? 'YES' : ($inSrc ? 'SRC-ONLY' : 'MISSING');
    printf("%-25s %10s %10s\n", $f, $inSrc ? 'yes' : 'no', $inBundle ? 'yes' : 'no');
    if ($inSrc && !$inBundle) $missing[] = $f;
}

echo "\n=== MISSING from bundle (but exist in source): ===\n";
foreach ($missing as $f) echo "  $f\n";

// Check app.js for library merging
echo "\n=== app.js checks ===\n";
$appJs = file_get_contents(__DIR__ . '/../public/assets/js/app.js');
$patterns = ['library', 'openLibItem', 'TNTT_MODULES', 'libCompose', 'libViewer'];
foreach ($patterns as $p) {
    $pos = strpos($appJs, $p);
    echo "$p: " . ($pos === false ? 'NOT FOUND' : "found at offset $pos") . "\n";
}
