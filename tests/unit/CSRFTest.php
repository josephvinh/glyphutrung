<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/csrf.php';

use PHPUnit\Framework\TestCase;

class CSRFTest extends TestCase {
    protected function setUp(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
    }

    public function test_csrf_token_generates_64_hex_chars(): void {
        $token = csrf_token();
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
    }

    public function test_verify_csrf_returns_true_for_valid_token(): void {
        $_SESSION['csrf_token'] = 'test_token_123';
        $this->assertTrue(verify_csrf('test_token_123'));
    }

    public function test_verify_csrf_returns_false_for_invalid_token(): void {
        $_SESSION['csrf_token'] = 'test_token_123';
        $this->assertFalse(verify_csrf('wrong_token'));
    }

    public function test_verify_csrf_handles_empty_session(): void {
        $_SESSION = [];
        $this->assertFalse(verify_csrf('any_token'));
    }
}
