<?php
/**
 * Kiểm tra bundle thực sự chứa gì.
 */
$c = file_get_contents(__DIR__ . '/../public/assets/js/bundle.min.js');

// Check library-specific content
$checks = [
    'library.js content' => 'TNTT.library={',
    'editFileItem' => 'editFileItem',
    'openCatManager' => 'openCatManager',
    'libMore' => 'libMore',
    'openCompose' => 'openCompose',
    'libViewer' => 'libViewer',
    'loadLibrary' => 'loadLibrary',
    'libRefresh' => 'libRefresh',
    'libLoadMine' => 'libLoadMine',
    'libLoadPending' => 'libLoadPending',
    'libSetTab' => 'libSetTab',
    'libApprove' => 'libApprove',
    'libReject' => 'libReject',
    'libDelete' => 'libDelete',
    'libItemIcon' => 'libItemIcon',
    'libCompose' => 'libCompose',
    'TNTT_MODULES check' => 'TNTT_MODULES',
    'app.js merged' => 'TNTT.core=',
    'org.js merged' => 'TNTT.org=',
];

echo "=== Bundle content checks ===\n";
$missing = [];
foreach ($checks as $label => $pattern) {
    $found = strpos($c, $pattern) !== false;
    echo ($found ? '[OK] ' : '[MISSING] ') . "$label\n";
    if (!$found) $missing[] = $label;
}

// Check source library.js
$src = file_get_contents(__DIR__ . '/../public/assets/js/modules/library.js');
echo "\n=== Source library.js functions ===\n";
preg_match_all('/^\s+(async\s+)?(\w+)\s*\(/m', $src, $m);
foreach ($m[2] as $f) {
    $inBundle = strpos($c, $f) !== false;
    echo ($inBundle ? '[OK] ' : '[MISSING] ') . "$f\n";
    if (!$inBundle) $missing[] = "library.js function: $f";
}

echo "\n=== Bundle size ===\n";
echo number_format(strlen($c)) . " bytes\n";
echo "Source modules total: 282 KB (approx)\n";

echo "\n=== Conclusions ===\n";
if (strpos($c, 'TNTT.library={') !== false) {
    echo "✓ library.js IS in bundle (as object)\n";
} else {
    echo "✗ library.js NOT in bundle as object\n";
}

if (strpos($c, 'TNTT_MODULES') !== false) {
    echo "✓ Alpine.js component merge IS in bundle\n";
} else {
    echo "✗ Alpine.js component merge NOT in bundle\n";
}
