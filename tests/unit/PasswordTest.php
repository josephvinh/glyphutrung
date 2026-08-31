<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/password.php';

use PHPUnit\Framework\TestCase;

class PasswordTest extends TestCase {

    public function test_password_hash_upgrade_generates_valid_argon2id_hash(): void {
        $password = 'test_password_123';
        $hash = password_hash_upgrade($password);

        // Verify it starts with the Argon2id prefix
        $this->assertStringStartsWith('$argon2id$', $hash);

        // Verify the hash is not empty and longer than the password
        $this->assertNotEmpty($hash);
        $this->assertGreaterThan(strlen($password), strlen($hash));
    }

    public function test_password_verify_upgrade_returns_true_for_correct_password(): void {
        $password = 'my_secure_password';
        $hash = password_hash_upgrade($password);

        // Test without memberId
        $this->assertTrue(password_verify_upgrade($password, $hash));
    }

    public function test_password_verify_upgrade_returns_false_for_wrong_password(): void {
        $password = 'correct_password';
        $wrongPassword = 'wrong_password';
        $hash = password_hash_upgrade($password);

        $this->assertFalse(password_verify_upgrade($wrongPassword, $hash));
    }

    public function test_password_verify_upgrade_detects_bcrypt_and_rehashes(): void {
        // Create a bcrypt hash using the old method
        $password = 'old_bcrypt_password';
        $bcryptHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        // Verify it's actually a bcrypt hash (not Argon2id)
        $this->assertStringStartsWith('$2y$', $bcryptHash);

        // Mock db_run to capture the upgrade query
        $updatedHash = null;
        $capturedId = null;

        // Override db_run temporarily
        global $db_run_override;
        $db_run_override = function($sql, $params) use (&$updatedHash, &$capturedId) {
            if (strpos($sql, 'UPDATE members SET password_hash') !== false) {
                $updatedHash = $params[0];
                $capturedId = $params[1];
            }
            return 1;
        };

        // Verify with bcrypt hash - should return true and trigger upgrade
        $result = password_verify_upgrade($password, $bcryptHash, 42);

        $this->assertTrue($result);
        $this->assertNotNull($updatedHash, 'db_run should have been called to update the hash');
        $this->assertEquals(42, $capturedId, 'memberId should be passed to db_run');
        $this->assertStringStartsWith('$argon2id$', $updatedHash, 'New hash should be Argon2id');

        // Clean up
        unset($GLOBALS['db_run_override']);
    }

    public function test_is_bcrypt_hash_returns_true_for_bcrypt(): void {
        $bcryptHash = password_hash('test', PASSWORD_BCRYPT);
        $this->assertTrue(is_bcrypt_hash($bcryptHash));
    }

    public function test_is_bcrypt_hash_returns_false_for_argon2id(): void {
        $argon2idHash = password_hash_upgrade('test');
        $this->assertFalse(is_bcrypt_hash($argon2idHash));
    }

    public function test_is_argon2id_hash_returns_true_for_argon2id(): void {
        $argon2idHash = password_hash_upgrade('test');
        $this->assertTrue(is_argon2id_hash($argon2idHash));
    }

    public function test_is_argon2id_hash_returns_false_for_bcrypt(): void {
        $bcryptHash = password_hash('test', PASSWORD_BCRYPT);
        $this->assertFalse(is_argon2id_hash($bcryptHash));
    }

    public function test_argon2id_hash_parameters_are_correct(): void {
        $password = 'test_password';
        $hash = password_hash_upgrade($password);

        // Parse the hash to verify parameters
        // Argon2id format: $argon2id$v=19$m=65536,t=4,p=3$salta$... (6 parts)
        $parts = explode('$', $hash);
        $this->assertCount(6, $parts);

        // $argon2id$v=19$m=65536,t=4,p=3$... -> parts[3] is "m=65536,t=4,p=3"
        $params = explode(',', $parts[3]);
        $memoryFound = false;
        $timeFound = false;
        $threadsFound = false;

        foreach ($params as $param) {
            if ($param === 'm=65536') $memoryFound = true;
            if ($param === 't=4') $timeFound = true;
            if ($param === 'p=3') $threadsFound = true;
        }

        $this->assertTrue($memoryFound, 'memory_cost should be 65536');
        $this->assertTrue($timeFound, 'time_cost should be 4');
        $this->assertTrue($threadsFound, 'threads should be 3');
    }
}
