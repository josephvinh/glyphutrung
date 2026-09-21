<?php
/**
 * Check staff module presence in bundle.
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

$checks = [
    'openStaff' => 'org.js: opens staff module',
    'pendingMembers' => 'org.js: pending members data',
    'bdhMembers' => 'org.js: BĐH members',
    'canManageOrg' => 'org.js: permission getter',
    'module_staff' => 'core.js: module definition',
    'showMemberModal' => 'org.js: member modal state',
    'openEditMember' => 'org.js: edit member function',
    'openCreateMember' => 'org.js: create member function',
    'staff' => 'core.js: staff key in moduleDefs',
];

echo "=== Staff module check ===\n";
$allOk = true;
foreach ($checks as $pattern => $desc) {
    $found = strpos($concat, $pattern) !== false;
    if (!$found) $allOk = false;
    echo ($found ? '[OK] ' : '[MISSING] ') . "$pattern\n";
}

echo "\n=== Verifying staff.js DOES NOT exist (correct) ===\n";
$staffJs = $base . 'modules/staff.js';
echo "staff.js exists: " . (is_file($staffJs) ? 'YES (unexpected!)' : 'NO (correct)') . "\n";

echo "\n=== Conclusion ===\n";
if ($allOk) {
    echo "✅ Staff module logic is present in bundle (via core.js + org.js).\n";
    echo "Staff uses: openStaff() from org.js, pendingMembers from org.js.\n";
    echo "If staff module is not showing, check:\n";
    echo "  1. User role has access to 'staff' module\n";
    echo "  2. permissions table has entry for user's role\n";
    echo "  3. canManageOrg returns true for user's role\n";
} else {
    echo "❌ Some staff functions are missing.\n";
}
