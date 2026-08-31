<?php
/**
 * Script to upgrade all password hashes from bcrypt to Argon2id
 * Run once: php scripts/upgrade_passwords.php
 *
 * This script identifies bcrypt hashes and reports them.
 * Auto-upgrade happens on next login via password_verify_upgrade().
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/password.php';

echo "=== Password Hash Upgrade Check ===\n\n";

$members = db_all('SELECT id, phone, password_hash FROM members');
$argon2id = 0;
$bcrypt = 0;
$unknown = 0;

foreach ($members as $member) {
    $hash = $member['password_hash'];

    if (is_argon2id_hash($hash)) {
        $argon2id++;
    } elseif (is_bcrypt_hash($hash)) {
        $bcrypt++;
        echo "Bcrypt hash detected:\n";
        echo "  Member ID: {$member['id']}\n";
        echo "  Phone: {$member['phone']}\n";
        echo "  Hash prefix: " . substr($hash, 0, 10) . "...\n";
        echo "  Will auto-upgrade on next login\n\n";
    } else {
        $unknown++;
        echo "Unknown hash format:\n";
        echo "  Member ID: {$member['id']}\n";
        echo "  Phone: {$member['phone']}\n";
        echo "  Hash prefix: " . substr($hash, 0, 10) . "...\n\n";
    }
}

echo "=== Summary ===\n";
echo "Argon2id (up to date): {$argon2id}\n";
echo "Bcrypt (will upgrade on login): {$bcrypt}\n";
echo "Unknown format: {$unknown}\n";
echo "\nAuto-upgrade will happen when each user logs in.\n";
echo "No manual action needed for bcrypt hashes.\n";
