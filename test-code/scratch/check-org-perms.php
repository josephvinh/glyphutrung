<?php
/**
 * Check org permissions in DB.
 * Run: php scratch/check-org-perms.php
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../public/api/_bootstrap.php';

echo "=== permissions table: module_key='org' ===\n";
$rows = db_all("SELECT * FROM permissions WHERE module_key = 'org'");
if (!$rows) {
    echo "NO ROWS for 'org'!\n";
} else {
    foreach ($rows as $r) {
        printf("  role=%s level=%s\n", $r['role_code'], $r['level']);
    }
}

echo "\n=== All permissions ===\n";
$all = db_all("SELECT module_key, role_code, level FROM permissions ORDER BY module_key, role_code");
foreach ($all as $p) {
    if ($p['module_key'] === 'org') printf("* %s / %s = %s\n", $p['module_key'], $p['role_code'], $p['level']);
}

echo "\n=== Test: canManageOrg for different roles ===\n";
// Check if permissions.org.bdh exists
$bdhPerm = db_one("SELECT level FROM permissions WHERE module_key = 'org' AND role_code = 'bdh'");
echo "BDH permission for org: " . ($bdhPerm ? $bdhPerm['level'] : 'NOT FOUND') . "\n";
