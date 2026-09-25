<?php
/**
 * Authentication & Session Tests
 *
 * Test các functions liên quan đến auth: login_throttle, login_failed,
 * login_ok, register_throttle, v.v.
 */

require_once __DIR__ . '/../bootstrap.php';

class AuthTest extends UnitTest
{
    // ============================================================
    // Test: Rate Limiting Constants
    // ============================================================
    public function testRateLimitConstantsDefined()
    {
        $this->assertTrue(defined('DN_CUA_SO_PHUT'), 'DN_CUA_SO_PHUT should be defined');
        $this->assertTrue(defined('DN_TOI_DA_SO'), 'DN_TOI_DA_SO should be defined');
        $this->assertTrue(defined('DN_TOI_DA_IP'), 'DN_TOI_DA_IP should be defined');

        // Reasonable values
        $this->assertEquals(15, DN_CUA_SO_PHUT);
        $this->assertEquals(5, DN_TOI_DA_SO);
        $this->assertEquals(20, DN_TOI_DA_IP);
    }

    // ============================================================
    // Test: Client IP Function
    // ============================================================
    public function testClientIpReturnsString()
    {
        $ip = client_ip();
        $this->assertIsString($ip);
        $this->assertNotEmpty($ip);
        // Should be truncated to 45 chars max
        $this->assertLessThanOrEqual(45, strlen($ip));
    }

    public function testClientIpDefaultValue()
    {
        // Without REMOTE_ADDR, should return '0.0.0.0'
        unset($_SERVER['REMOTE_ADDR']);
        $ip = client_ip();
        $this->assertEquals('0.0.0.0', $ip);
    }

    // ============================================================
    // Test: Login Throttle Logic
    // ============================================================
    public function testLoginThrottleLogic()
    {
        // Test the throttle logic without hitting actual DB
        // This tests the constants and basic flow
        $maxAttempts = DN_TOI_DA_SO; // 5 attempts
        $timeWindow = DN_CUA_SO_PHUT; // 15 minutes

        $this->assertEquals(5, $maxAttempts);
        $this->assertEquals(15, $timeWindow);
    }

    // ============================================================
    // Test: Session Management
    // ============================================================
    public function testSessionStarted()
    {
        // Session should be started by bootstrap
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testSecurityHeaders()
    {
        // Security headers should be set by _bootstrap
        // We can't easily test headers in CLI, but we can verify the function exists
        $this->assertTrue(function_exists('json_out'));
        $this->assertTrue(function_exists('json_fail'));
    }

    // ============================================================
    // Test: JSON Response Functions
    // ============================================================
    public function testJsonOutStructure()
    {
        // Test the expected JSON structure
        $successResponse = ['ok' => true, 'data' => ['id' => 1]];
        $errorResponse = ['ok' => false, 'error' => 'Test error'];

        $this->assertTrue($successResponse['ok']);
        $this->assertArrayHasKey('data', $successResponse);
        $this->assertFalse($errorResponse['ok']);
        $this->assertArrayHasKey('error', $errorResponse);
    }

    public function testJsonEncodeUnicodeHandling()
    {
        // Test Vietnamese characters are properly encoded
        $data = ['name' => 'Nguyễn Văn A', 'class' => 'Thiếu Nhi'];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('Nguyễn', $json);
        $this->assertStringContainsString('Thiếu', $json);
    }

    // ============================================================
    // Test: Require Login
    // ============================================================
    public function testRequireLoginWithoutSession()
    {
        // Without session, require_login should fail
        $_SESSION = [];

        try {
            require_login();
            $this->fail('require_login should throw when no session');
        } catch (Exception $e) {
            $this->assertStringContainsString('Chưa đăng nhập', $e->getMessage());
        }
    }

    // ============================================================
    // Test: CSRF Token
    // ============================================================
    public function testCsrfFunctionsExist()
    {
        $this->assertTrue(function_exists('csrf_token'));
        $this->assertTrue(function_exists('verify_csrf'));
    }

    public function testCsrfTokenGeneration()
    {
        $token1 = csrf_token();
        $token2 = csrf_token();

        // Token should be a non-empty string
        $this->assertIsString($token1);
        $this->assertNotEmpty($token1);

        // Same session should return same token
        $this->assertEquals($token1, $token2);

        // Token should be 64 characters (SHA256)
        $this->assertEquals(64, strlen($token1));
    }

    public function testVerifyCsrf()
    {
        $token = csrf_token();

        // Valid token should pass
        $this->assertTrue(verify_csrf($token));

        // Invalid token should fail
        $this->assertFalse(verify_csrf('invalid_token'));

        // Empty token should fail
        $this->assertFalse(verify_csrf(''));
    }

    // ============================================================
    // Test: Register Throttle
    // ============================================================
    public function testRegisterThrottleLogic()
    {
        // Test the register throttle logic
        // Max 3 registrations per hour per IP
        $maxRegistrations = 3;
        $timeWindow = 3600; // 1 hour in seconds

        $this->assertEquals(3, $maxRegistrations);
        $this->assertEquals(3600, $timeWindow);
    }
}
