<?php
/**
 * Simulate bundle.php output to verify org.js functions are included.
 */
$manifest = require __DIR__ . '/../public/assets/asset_manifest.php';
$base = __DIR__ . '/../public/assets/js/';

$files = array_merge(
    [$base . 'modules/toast.js'],
    array_map(fn($m) => $base . 'modules/' . $m . '.js', $manifest['js_modules']),
    [$base . 'app.js']
);

$concatenated = '';
foreach ($files as $f) {
    if (is_file($f)) $concatenated .= file_get_contents($f) . "\n;\n";
}

$checks = [
    // org.js functions
    'openCreateBlock' => 'org.js: openCreateBlock',
    'openEditBlock' => 'org.js: openEditBlock',
    'openCreateClass' => 'org.js: openCreateClass',
    'openEditClass' => 'org.js: openEditClass',
    'saveBlock' => 'org.js: saveBlock',
    'saveClass' => 'org.js: saveClass',
    'deleteBlock' => 'org.js: deleteBlock',
    'deleteClass' => 'org.js: deleteClass',
    'setBlockHead' => 'org.js: setBlockHead',
    'setClassHead' => 'org.js: setClassHead',
    // library.js functions
    'editFileItem' => 'library.js: editFileItem',
    'openCatManager' => 'library.js: openCatManager',
    'libMore' => 'library.js: libMore',
    'libSaveCategory' => 'library.js: libSaveCategory',
    'libToggleCategory' => 'library.js: libToggleCategory',
    'openLibArticle' => 'index.php: openLibArticle (should NOT exist)',
    // TNTT namespace
    'TNTT.org=' => 'org.js merged into TNTT',
    'TNTT.library=' => 'library.js merged into TNTT',
    'TNTT_MODULES' => 'app.js: TNTT_MODULES check',
];

echo "=== Bundle content verification ===\n";
echo "Total concatenated size: " . number_format(strlen($concatenated)) . " bytes\n";
echo "\n";
$allOk = true;
foreach ($checks as $pattern => $desc) {
    $found = strpos($concatenated, $pattern) !== false;
    $status = $found ? '[OK]' : '[MISSING]';
    if (!$found && strpos($pattern, 'NOT exist') === false) $allOk = false;
    if (strpos($pattern, 'NOT exist') !== false && $found) {
        $status = '[UNEXPECTED]';
        $allOk = false;
    }
    echo "$status $desc\n";
}

echo "\n";
if ($allOk) {
    echo "✓ Bundle is correct — all functions present, no old code.\n";
} else {
    echo "✗ Bundle has issues — some functions missing.\n";
}
