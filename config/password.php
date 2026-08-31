<?php
/**
 * Password Hashing - Argon2id with auto-upgrade support
 *
 * Upgrades password hashing from bcrypt to Argon2id while maintaining
 * backward compatibility for existing passwords.
 */

function password_hash_upgrade(string $password): string {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3,
    ]);
}

function password_verify_upgrade(string $password, string $hash, ?int $memberId = null): bool {
    // Verify with new or old method
    if (password_verify($password, $hash)) {
        // If old hash (bcrypt), upgrade to argon2id
        if (password_needs_rehash($hash, PASSWORD_ARGON2ID)) {
            $newHash = password_hash_upgrade($password);
            // Update in database if memberId is provided
            if ($memberId !== null) {
                // Support test mocking via global override
                if (isset($GLOBALS['db_run_override'])) {
                    $GLOBALS['db_run_override']('UPDATE members SET password_hash = ? WHERE id = ?',
                        [$newHash, $memberId]);
                } else {
                    db_run('UPDATE members SET password_hash = ? WHERE id = ?',
                           [$newHash, $memberId]);
                }
            }
        }
        return true;
    }
    return false;
}

/**
 * Check if a hash is a bcrypt hash (needs upgrading)
 */
function is_bcrypt_hash(string $hash): bool {
    // Bcrypt hashes start with $2a$, $2b$, or $2y$
    return preg_match('/^\$2[aby]\$/', $hash) === 1;
}

/**
 * Check if a hash is an Argon2id hash
 */
function is_argon2id_hash(string $hash): bool {
    return str_starts_with($hash, '$argon2id$');
}
