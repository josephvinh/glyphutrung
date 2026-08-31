# Task 2 Brief: Security - Password Hashing Upgrade (bcrypt → argon2id)

## Task Description
Upgrade password hashing from bcrypt to Argon2id with auto-upgrade for existing passwords.

## Files to Create/Modify

### Create: `config/password.php`
```php
<?php
/**
 * Password Hashing - Argon2id with auto-upgrade support
 */

function password_hash_upgrade(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

function password_verify_upgrade(string $password, string $hash, ?int $memberId = null): bool {
    // Verify với method mới hoặc cũ
    if (password_verify($password, $hash)) {
        // Nếu hash cũ (bcrypt), upgrade lên argon2id
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash_upgrade($password);
            // Cập nhật vào database nếu có memberId
            if ($memberId !== null) {
                db_run('UPDATE members SET password_hash = ? WHERE id = ?',
                       [$newHash, $memberId]);
            }
        }
        return true;
    }
    return false;
}
```

### Modify: `public/api/auth.php`
Find the login function and update password verification:
```php
// Thay đổi từ:
if (password_verify($password, $row['password_hash']))

// Thành:
if (password_verify_upgrade($password, $row['password_hash'], $row['id']))
```

Find the create/update member function and update password hashing:
```php
// Thay đổi từ:
$hash = password_hash($password, PASSWORD_DEFAULT);

// Thành:
$hash = password_hash_upgrade($password);
```

### Create: `scripts/upgrade_passwords.php`
```php
<?php
/**
 * Script để upgrade tất cả password hashes từ bcrypt sang Argon2id
 * Chạy một lần: php scripts/upgrade_passwords.php
 */

require_once __DIR__ . '/../config/db.php';

$members = db_all('SELECT id, password_hash FROM members');
$upgraded = 0;

foreach ($members as $member) {
    $hash = $member['password_hash'];
    
    // Nếu đã là Argon2id thì bỏ qua
    if (str_starts_with($hash, '$argon2id$')) {
        continue;
    }
    
    // Parse bcrypt hash để lấy password (bcrypt verify)
    if (password_verify('dummy', $hash)) {
        // Đây là bcrypt hash cũ - user sẽ cần đổi password ở lần đăng nhập
        // Hoặc có thể dùng cách khác để upgrade
        echo "Member ID {$member['id']}: bcrypt hash detected (needs manual reset)\n";
    }
}

echo "Upgrade check complete. {$upgraded} passwords upgraded.\n";
```

## Requirements
- Use Argon2id algorithm
- Parameters: memory_cost=65536, time_cost=4, threads=3
- Auto-upgrade existing passwords on login
- Backward compatible with existing bcrypt hashes

## Acceptance Criteria
1. New passwords are hashed with Argon2id
2. Existing bcrypt passwords are auto-upgraded on successful login
3. Login works with both bcrypt and Argon2id passwords
4. Script to identify bcrypt hashes exists
