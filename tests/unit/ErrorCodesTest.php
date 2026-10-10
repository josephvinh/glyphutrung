<?php
require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/public/api/_errors.php';

use PHPUnit\Framework\TestCase;

class ErrorCodesTest extends TestCase
{
    public function testHttpCodesExist()
    {
        $this->assertTrue(defined('ERR_OK'));
        $this->assertTrue(defined('ERR_CREATED'));
        $this->assertTrue(defined('ERR_BAD_REQUEST'));
        $this->assertTrue(defined('ERR_UNAUTHORIZED'));
        $this->assertTrue(defined('ERR_FORBIDDEN'));
        $this->assertTrue(defined('ERR_NOT_FOUND'));
        $this->assertTrue(defined('ERR_METHOD_NOT_ALLOWED'));
        $this->assertTrue(defined('ERR_CONFLICT'));
        $this->assertTrue(defined('ERR_VALIDATION_FAILED'));
        $this->assertTrue(defined('ERR_RATE_LIMITED'));
        $this->assertTrue(defined('ERR_INTERNAL'));
        $this->assertTrue(defined('ERR_SERVICE_UNAVAILABLE'));
    }

    public function testAuthCodesExist()
    {
        $this->assertTrue(defined('ERR_INVALID_SESSION'));
        $this->assertTrue(defined('ERR_PERMISSION_DENIED'));
        $this->assertTrue(defined('ERR_CLASS_ACCESS_DENIED'));
        $this->assertTrue(defined('ERR_CSRF_INVALID'));
        $this->assertTrue(defined('ERR_ACCOUNT_DISABLED'));
        $this->assertTrue(defined('ERR_PASSWORD_EXPIRED'));
    }

    public function testNotFoundCodesExist()
    {
        $this->assertTrue(defined('ERR_NOT_FOUND_CLASS'));
        $this->assertTrue(defined('ERR_NOT_FOUND_STUDENT'));
        $this->assertTrue(defined('ERR_NOT_FOUND_PROGRAM'));
        $this->assertTrue(defined('ERR_NOT_FOUND_YEAR'));
    }

    public function testConflictCodesExist()
    {
        $this->assertTrue(defined('ERR_DUPLICATE_ENTRY'));
        $this->assertTrue(defined('ERR_DATA_CONFLICT'));
        $this->assertTrue(defined('ERR_YEAR_LOCKED'));
    }

    public function testValidationCodesExist()
    {
        $this->assertTrue(defined('ERR_INVALID_INPUT'));
        $this->assertTrue(defined('ERR_REQUIRED_FIELD'));
        $this->assertTrue(defined('ERR_VALUE_OUT_OF_RANGE'));
    }

    public function testRateLimitCodesExist()
    {
        $this->assertTrue(defined('ERR_RATE_LIMIT_EXCEEDED'));
        $this->assertTrue(defined('ERR_LOGIN_ATTEMPTS_EXCEEDED'));
    }

    public function testErrorCodesAreStrings()
    {
        $this->assertIsString(ERR_OK);
        $this->assertIsString(ERR_UNAUTHORIZED);
        $this->assertIsString(ERR_PERMISSION_DENIED);
        $this->assertIsString(ERR_NOT_FOUND_STUDENT);
        $this->assertIsString(ERR_VALIDATION_FAILED);
    }
}
