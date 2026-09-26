<?php
/**
 * Check permissions in DB for org and staff modules.
 * Run: php scratch/check-permissions.php
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../public/api/_bootstrap.php';

echo "=== Permissions table ===\n";
$perms = db_all("SELECT module_key, role_code, level FROM permissions ORDER BY module_key, role_code");

$modules = [];
foreach ($perms as $p) {
    $m = $p['module_key'];
    if (!isset($modules[$m])) $modules[$m] = [];
    $modules[$m][$p['role_code']] = $p['level'];
}

echo "Modules with permissions:\n";
foreach ($modules as $m => $roles) {
    echo "  $m:\n";
    foreach ($roles as $role => $level) {
        echo "    $role = $level\n";
    }
}

echo "\n=== Check org permissions ===\n";
$orgPerms = db_all("SELECT * FROM permissions WHERE module_key = 'org'");
if (count($orgPerms) === 0) {
    echo "⚠️ NO 'org' permissions found! This is likely the bug.\n";
    echo "Expected: admin=edit, bdh=edit\n";
} else {
    echo "Found " . count($orgPerms) . " permission entries for 'org'\n";
    foreach ($orgPerms as $p) {
        echo "  {$p['role_code']} = {$p['level']}\n";
    }
}

echo "\n=== Check staff permissions ===\n";
$staffPerms = db_all("SELECT * FROM permissions WHERE module_key = 'staff'");
if (count($staffPerms) === 0) {
    echo "⚠️ NO 'staff' permissions found!\n";
} else {
    echo "Found " . count($staffPerms) . " permission entries for 'staff'\n";
    foreach ($staffPerms as $p) {
        echo "  {$p['role_code']} = {$p['level']}\n";
    }
}

echo "\n=== Simulate canManageOrg for each role ===\n";
$roles = ['admin', 'bdh', 'truong_khoi', 'glv_chu_nhiem', 'glv', 'du_bi'];
foreach ($roles as $role) {
    $orgLevel = $modules['org'][$role] ?? 'none';
    $staffLevel = $modules['staff'][$role] ?? 'none';
    $canOrg = ($orgLevel === 'edit') ? 'YES' : 'NO';
    $canStaff = ($staffLevel !== 'none') ? 'YES' : 'NO';
    echo sprintf("%-15s org=%-5s staff=%-5s canOrg=%s canStaff=%s\n", $role, $orgLevel, $staffLevel, $canOrg, $canStaff);
}
