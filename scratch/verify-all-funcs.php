<?php
/**
 * Final verification: all org.js and library.js functions are in bundle.
 */
$manifest = require __DIR__ . '/../public/assets/asset_manifest.php';
$base = __DIR__ . '/../public/assets/js/';
$files = array_merge(
    [$base . 'modules/toast.js'],
    array_map(fn($m) => $base . 'modules/' . $m . '.js', $manifest['js_modules']),
    [$base . 'app.js']
);

$concat = '';
foreach ($files as $f) {
    if (is_file($f)) $concat .= file_get_contents($f) . "\n;\n";
}

$allFuncs = [
    // org.js
    'openCreateBlock' => 'org.js',
    'openEditBlock' => 'org.js',
    'openCreateClass' => 'org.js',
    'openEditClass' => 'org.js',
    'saveBlock' => 'org.js',
    'saveClass' => 'org.js',
    'deleteBlock' => 'org.js',
    'deleteClass' => 'org.js',
    'setBlockHead' => 'org.js',
    'setClassHead' => 'org.js',
    'canManageOrg' => 'org.js (getter)',
    // library.js
    'editFileItem' => 'library.js',
    'openCatManager' => 'library.js',
    'libMore' => 'library.js',
    'libSaveCategory' => 'library.js',
    'libToggleCategory' => 'library.js',
    'openLibItem' => 'library.js',
    'editArticle' => 'library.js',
    'submitCompose' => 'library.js',
    'libApprove' => 'library.js',
    'libReject' => 'library.js',
    'libDelete' => 'library.js',
];

$ok = 0; $fail = 0;
echo "=== Function presence in bundle ===\n";
foreach ($allFuncs as $f => $src) {
    $found = strpos($concat, $f) !== false;
    if ($found) { $ok++; $mark = '[OK]'; }
    else { $fail++; $mark = '[MISSING]'; }
    echo "$mark $f ($src)\n";
}

echo "\n=== Summary ===\n";
echo "OK: $ok / " . count($allFuncs) . "\n";
echo "Missing: $fail\n";

if ($fail > 0) {
    echo "\n⚠️ WARNING: $fail functions missing from bundle!\n";
} else {
    echo "\n✅ All functions present in bundle.\n";
}

echo "\nBundle size: " . number_format(strlen($concat)) . " bytes\n";
echo "App.js merges: " . (strpos($concat, 'TNTT_MODULES') !== false ? 'YES' : 'NO') . "\n";
