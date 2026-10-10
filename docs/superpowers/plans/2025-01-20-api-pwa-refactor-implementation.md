# API + PWA Refactor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor API layer (response format, error codes, validation, CSRF, rate limiting) and PWA (service worker modules, cache strategies, offline queue) for improved performance, reliability, and maintainability.

**Architecture:** Big Bang breaking change - rewrite API layer with standardized response format, modularize service worker, implement IndexedDB-based offline queue with FIFO sync.

**Tech Stack:** PHP 8.x (existing), Vanilla JS service worker, IndexedDB for offline storage

**Spec:** `docs/superpowers/specs/2025-01-20-api-pwa-refactor-design.md`

---

## Global Constraints

- Database schema: **Không thay đổi**
- Breaking change: API response format thay đổi → client Alpine.js cần update đồng thời
- Service worker: Force update on deploy
- PHP 8.x compatibility required
- Tests phải chạy pass trước commit

---

## File Structure

```
public/api/
├── _errors.php           # [NEW] Error code constants
├── _validator.php        # [NEW] Validation + CSRF classes
├── _response.php         # [NEW] Response helpers
├── _http_util.php        # [REWRITE] HTTP utilities
├── _bootstrap.php        # [REWRITE] Bootstrap with new helpers
└── *.php                 # [UPDATE] All endpoints use new response format

public/assets/js/
├── sw/                   # [NEW] Service worker modules
│   ├── core/
│   │   ├── cache-manager.js
│   │   ├── network-manager.js
│   │   └── sync-manager.js
│   ├── strategies/
│   │   ├── cache-first.js
│   │   ├── network-first.js
│   │   └── stale-while-revalidate.js
│   └── utils/
│       ├── logger.js
│       └── constants.js
├── offline-queue.js      # [NEW] Offline queue class
├── modules/
│   ├── core.js          # [UPDATE] New API format
│   └── shell.js         # [UPDATE] New sync logic
└── sw.js                # [REWRITE] Service worker entry point

public/
└── sw.js                # [REWRITE] Service worker entry point

config/
└── config.php           # [UPDATE] Rate limit configs

tests/unit/
├── ErrorCodesTest.php    # [NEW] Test error codes
├── ValidatorTest.php     # [NEW] Test validator class
├── OfflineQueueTest.php  # [NEW] Test offline queue
└── ResponseFormatTest.php # [NEW] Test API response format
```

---

## Task 1: Create Error Code Registry

**Files:**
- Create: `public/api/_errors.php`
- Test: `tests/unit/ErrorCodesTest.php`

**Interfaces:**
- Consumes: Nothing
- Produces: `ERR_*` constants for all API endpoints

- [ ] **Step 1: Write test for error codes**

```php
<?php
// tests/unit/ErrorCodesTest.php
require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/public/api/_errors.php';

class ErrorCodesTest extends PHPUnit_Framework_TestCase
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

    public function testDomainCodesExist()
    {
        $this->assertTrue(defined('ERR_INVALID_SESSION'));
        $this->assertTrue(defined('ERR_PERMISSION_DENIED'));
        $this->assertTrue(defined('ERR_CLASS_ACCESS_DENIED'));
        $this->assertTrue(defined('ERR_CSRF_INVALID'));
        $this->assertTrue(defined('ERR_NOT_FOUND_CLASS'));
        $this->assertTrue(defined('ERR_NOT_FOUND_STUDENT'));
        $this->assertTrue(defined('ERR_DUPLICATE_ENTRY'));
        $this->assertTrue(defined('ERR_DATA_CONFLICT'));
    }

    public function testErrorCodesAreStrings()
    {
        $this->assertIsString(ERR_OK);
        $this->assertIsString(ERR_UNAUTHORIZED);
        $this->assertIsString(ERR_PERMISSION_DENIED);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php phpunit.phar tests/unit/ErrorCodesTest.php -v`
Expected: FAIL - file does not exist

- [ ] **Step 3: Create error codes file**

```php
<?php
/**
 * ERROR CODE REGISTRY
 *
 * Centralized error codes for API responses.
 * All API endpoints MUST use these constants instead of string literals.
 *
 * Format: ERR_<CATEGORY>_<SUBCATEGORY>
 * Examples:
 *   - ERR_UNAUTHORIZED (HTTP 401)
 *   - ERR_PERMISSION_DENIED (HTTP 403)
 *   - ERR_NOT_FOUND_STUDENT (HTTP 404)
 */

// HTTP Status Codes
define('ERR_OK',                    'OK');                    // 200
define('ERR_CREATED',               'CREATED');               // 201
define('ERR_BAD_REQUEST',           'BAD_REQUEST');           // 400
define('ERR_UNAUTHORIZED',         'AUTH_REQUIRED');         // 401
define('ERR_FORBIDDEN',            'FORBIDDEN');             // 403
define('ERR_NOT_FOUND',             'NOT_FOUND');             // 404
define('ERR_METHOD_NOT_ALLOWED',    'METHOD_NOT_ALLOWED');   // 405
define('ERR_CONFLICT',              'CONFLICT');              // 409
define('ERR_VALIDATION_FAILED',     'VALIDATION_FAILED');     // 422
define('ERR_RATE_LIMITED',          'RATE_LIMITED');         // 429
define('ERR_INTERNAL',              'INTERNAL_ERROR');        // 500
define('ERR_SERVICE_UNAVAILABLE',   'SERVICE_UNAVAILABLE');  // 503

// Authentication & Authorization
define('ERR_INVALID_SESSION',       'INVALID_SESSION');       // 401
define('ERR_PERMISSION_DENIED',    'PERMISSION_DENIED');    // 403
define('ERR_CLASS_ACCESS_DENIED',   'CLASS_ACCESS_DENIED'); // 403
define('ERR_CSRF_INVALID',         'CSRF_INVALID');         // 403
define('ERR_ACCOUNT_DISABLED',      'ACCOUNT_DISABLED');     // 403
define('ERR_PASSWORD_EXPIRED',      'PASSWORD_EXPIRED');     // 403

// Resource Not Found
define('ERR_NOT_FOUND_CLASS',       'CLASS_NOT_FOUND');      // 404
define('ERR_NOT_FOUND_STUDENT',     'STUDENT_NOT_FOUND');    // 404
define('ERR_NOT_FOUND_PROGRAM',     'PROGRAM_NOT_FOUND');    // 404
define('ERR_NOT_FOUND_YEAR',        'YEAR_NOT_FOUND');       // 404

// Data Conflicts
define('ERR_DUPLICATE_ENTRY',       'DUPLICATE_ENTRY');      // 409
define('ERR_DATA_CONFLICT',         'DATA_CONFLICT');        // 409
define('ERR_YEAR_LOCKED',           'YEAR_LOCKED');          // 409

// Validation Errors
define('ERR_INVALID_INPUT',          'INVALID_INPUT');         // 422
define('ERR_REQUIRED_FIELD',        'REQUIRED_FIELD');        // 422
define('ERR_VALUE_OUT_OF_RANGE',    'VALUE_OUT_OF_RANGE');    // 422

// Rate Limiting
define('ERR_RATE_LIMIT_EXCEEDED',   'RATE_LIMIT_EXCEEDED');  // 429
define('ERR_LOGIN_ATTEMPTS_EXCEEDED', 'LOGIN_ATTEMPTS_EXCEEDED'); // 429
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php phpunit.phar tests/unit/ErrorCodesTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add public/api/_errors.php tests/unit/ErrorCodesTest.php
git commit -m "feat(api): add centralized error code registry

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 2: Create Validation Layer

**Files:**
- Create: `public/api/_validator.php`
- Test: `tests/unit/ValidatorTest.php`

**Interfaces:**
- Consumes: Input data (array, string, int)
- Produces: `Validator` class with fluent interface

- [ ] **Step 1: Write test for validator**

```php
<?php
// tests/unit/ValidatorTest.php
require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/public/api/_errors.php';
require_once ROOT_PATH . '/public/api/_validator.php';

class ValidatorTest extends PHPUnit_Framework_TestCase
{
    public function testRequiredPass()
    {
        $v = new Validator(['name' => 'Test']);
        $v->required('name');
        $this->assertTrue($v->validate());
        $this->assertEmpty($v->getErrors());
    }

    public function testRequiredFail()
    {
        $v = new Validator([]);
        $v->required('name');
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('REQUIRED_FIELD', $errors['name']['code']);
    }

    public function testIntegerPass()
    {
        $v = new Validator(['age' => 25]);
        $v->integer('age');
        $this->assertTrue($v->validate());
    }

    public function testIntegerFail()
    {
        $v = new Validator(['age' => 'twenty-five']);
        $v->integer('age');
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals('INVALID_INPUT', $errors['age']['code']);
    }

    public function testMaxLengthPass()
    {
        $v = new Validator(['name' => 'Short']);
        $v->maxLength('name', 10);
        $this->assertTrue($v->validate());
    }

    public function testMaxLengthFail()
    {
        $v = new Validator(['name' => 'Very Long Name']);
        $v->maxLength('name', 5);
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals('VALUE_OUT_OF_RANGE', $errors['name']['code']);
    }

    public function testInArrayPass()
    {
        $v = new Validator(['status' => 'active']);
        $v->inArray('status', ['active', 'inactive']);
        $this->assertTrue($v->validate());
    }

    public function testInArrayFail()
    {
        $v = new Validator(['status' => 'unknown']);
        $v->inArray('status', ['active', 'inactive']);
        $this->assertFalse($v->validate());
        $errors = $v->getErrors();
        $this->assertEquals('INVALID_INPUT', $errors['status']['code']);
    }

    public function testChainedValidation()
    {
        $v = new Validator(['name' => 'Test', 'age' => 25]);
        $v->required('name')
          ->required('age')
          ->string('name')
          ->integer('age')
          ->maxLength('name', 100);
        $this->assertTrue($v->validate());
        $this->assertEmpty($v->getErrors());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php phpunit.phar tests/unit/ValidatorTest.php -v`
Expected: FAIL - file does not exist

- [ ] **Step 3: Create validator class**

```php
<?php
/**
 * VALIDATION LAYER
 *
 * Centralized input validation for API endpoints.
 * Provides fluent interface for chaining validation rules.
 */
class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field): self
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            $this->errors[$field] = [
                'code' => ERR_REQUIRED_FIELD,
                'message' => "Trường '{$field}' là bắt buộc."
            ];
        }
        return $this;
    }

    public function integer(string $field): self
    {
        if (isset($this->data[$field]) && $this->data[$field] !== '') {
            if (!is_numeric($this->data[$field]) || (int) $this->data[$field] != $this->data[$field]) {
                $this->errors[$field] = [
                    'code' => ERR_INVALID_INPUT,
                    'message' => "Trường '{$field}' phải là số nguyên."
                ];
            }
        }
        return $this;
    }

    public function string(string $field): self
    {
        if (isset($this->data[$field]) && !is_string($this->data[$field])) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' phải là chuỗi."
            ];
        }
        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (isset($this->data[$field]) && strlen((string) $this->data[$field]) > $max) {
            $this->errors[$field] = [
                'code' => ERR_VALUE_OUT_OF_RANGE,
                'message' => "Trường '{$field}' không được dài quá {$max} ký tự."
            ];
        }
        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        if (isset($this->data[$field]) && strlen((string) $this->data[$field]) < $min) {
            $this->errors[$field] = [
                'code' => ERR_VALUE_OUT_OF_RANGE,
                'message' => "Trường '{$field}' phải có ít nhất {$min} ký tự."
            ];
        }
        return $this;
    }

    public function inArray(string $field, array $allowed): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' có giá trị không hợp lệ."
            ];
        }
        return $this;
    }

    public function email(string $field): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = [
                'code' => ERR_INVALID_INPUT,
                'message' => "Trường '{$field}' phải là email hợp lệ."
            ];
        }
        return $this;
    }

    public function validate(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getFirstError(): ?array
    {
        return !empty($this->errors) ? reset($this->errors) : null;
    }
}

/**
 * CSRF VALIDATOR
 *
 * Centralized CSRF token validation with Origin/Referer checking.
 */
class CSRFValidator
{
    public static function validate(): void
    {
        // Only check for POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        // Check if session has CSRF token
        if (empty($_SESSION['csrf_token'])) {
            json_fail(ERR_CSRF_INVALID, 'CSRF token not found. Please reload the page.', 403);
        }

        // Get token from POST or header
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!verify_csrf($token)) {
            json_fail(ERR_CSRF_INVALID, 'Invalid CSRF token.', 403);
        }

        // Optional: Check Origin header for additional security
        $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
        if ($origin) {
            $allowedOrigins = app_config('allowed_origins', []);
            if (!empty($allowedOrigins)) {
                $parsedOrigin = parse_url($origin, PHP_URL_HOST);
                $parsedSelf = parse_url(app_config('app_url'), PHP_URL_HOST);
                if ($parsedOrigin !== $parsedSelf) {
                    json_fail(ERR_CSRF_INVALID, 'Invalid request origin.', 403);
                }
            }
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php phpunit.phar tests/unit/ValidatorTest.php -v`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add public/api/_validator.php tests/unit/ValidatorTest.php
git commit -m "feat(api): add validation layer with CSRF validator

- Validator class with fluent validation methods
- CSRFValidator with Origin/Referer checking
- Unit tests for all validation methods

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 3: Rewrite HTTP Utilities and Response Helpers

**Files:**
- Create: `public/api/_response.php`
- Rewrite: `public/api/_http_util.php`
- Test: `tests/unit/ResponseFormatTest.php`

**Interfaces:**
- Consumes: `ERR_*` constants, data arrays
- Produces: `json_ok()`, `json_created()`, `json_fail()`, `json_validation_fail()`, `json_paginated()` functions

- [ ] **Step 1: Write test for response helpers**

```php
<?php
// tests/unit/ResponseFormatTest.php
require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/public/api/_errors.php';
require_once ROOT_PATH . '/public/api/_http_util.php';
require_once ROOT_PATH . '/public/api/_response.php';

class ResponseFormatTest extends PHPUnit_Framework_TestCase
{
    public function testJsonOkStructure()
    {
        $data = ['name' => 'Test', 'id' => 1];

        // Capture output
        ob_start();
        json_ok($data);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('ok', $decoded);
        $this->assertTrue($decoded['ok']);
        $this->assertArrayHasKey('data', $decoded);
        $this->assertArrayHasKey('meta', $decoded);
        $this->assertEquals('Test', $decoded['data']['name']);
    }

    public function testJsonOkIncludesMeta()
    {
        ob_start();
        json_ok(['test' => true]);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertArrayHasKey('timestamp', $decoded['meta']);
        $this->assertArrayHasKey('requestId', $decoded['meta']);
    }

    public function testJsonFailStructure()
    {
        ob_start();
        json_fail(ERR_BAD_REQUEST, 'Invalid input', ['field' => 'name'], 400);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertFalse($decoded['ok']);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertEquals('BAD_REQUEST', $decoded['error']['code']);
        $this->assertEquals('Invalid input', $decoded['error']['message']);
        $this->assertEquals(['field' => 'name'], $decoded['error']['details']);
    }

    public function testJsonValidationFailStructure()
    {
        $errors = [
            'name' => ['code' => ERR_REQUIRED_FIELD, 'message' => 'Name is required'],
            'email' => ['code' => ERR_INVALID_INPUT, 'message' => 'Invalid email']
        ];

        ob_start();
        json_validation_fail($errors);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertFalse($decoded['ok']);
        $this->assertEquals('VALIDATION_FAILED', $decoded['error']['code']);
        $this->assertEquals($errors, $decoded['error']['details']['fields']);
    }

    public function testJsonPaginatedStructure()
    {
        $items = [['id' => 1], ['id' => 2]];

        ob_start();
        json_paginated($items, 1, 10, 25);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['ok']);
        $this->assertEquals($items, $decoded['data']);
        $this->assertArrayHasKey('pagination', $decoded['meta']);
        $this->assertEquals(1, $decoded['meta']['pagination']['page']);
        $this->assertEquals(10, $decoded['meta']['pagination']['perPage']);
        $this->assertEquals(25, $decoded['meta']['pagination']['total']);
        $this->assertEquals(3, $decoded['meta']['pagination']['totalPages']);
    }

    public function testJsonCreatedStructure()
    {
        ob_start();
        json_created(['id' => 123, 'name' => 'New Item']);
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        $this->assertTrue($decoded['ok']);
        $this->assertEquals(123, $decoded['data']['id']);
        $this->assertEquals('CREATED', $decoded['meta']['status']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php phpunit.phar tests/unit/ResponseFormatTest.php -v`
Expected: FAIL - functions do not exist

- [ ] **Step 3: Create response helpers file**

```php
<?php
/**
 * RESPONSE HELPERS
 *
 * Standardized API response functions.
 * All endpoints MUST use these instead of direct json_out() calls.
 */

/**
 * Generate unique request ID for tracing
 */
function generate_request_id(): string
{
    return 'req_' . bin2hex(random_bytes(8));
}

/**
 * Get standard meta block
 */
function response_meta(int $statusCode = 200): array
{
    return [
        'timestamp' => time(),
        'requestId' => generate_request_id(),
        'status' => $statusCode === 201 ? 'CREATED' : 'OK',
    ];
}

/**
 * Send success response
 */
function json_ok(mixed $data, array $meta = [], int $status = 200): never
{
    $response = [
        'ok' => true,
        'data' => $data,
        'meta' => array_merge(response_meta($status), $meta),
    ];
    json_out($response, $status);
}

/**
 * Send created response (HTTP 201)
 */
function json_created(mixed $data, array $meta = []): never
{
    json_ok($data, $meta, 201);
}

/**
 * Send error response
 */
function json_fail(string $code, string $message, array $details = [], int $status = 400): never
{
    $response = [
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details,
        ],
        'meta' => response_meta($status),
    ];
    json_out($response, $status);
}

/**
 * Send validation error response (HTTP 422)
 */
function json_validation_fail(array $errors): never
{
    $response = [
        'ok' => false,
        'error' => [
            'code' => ERR_VALIDATION_FAILED,
            'message' => 'Dữ liệu không hợp lệ.',
            'details' => ['fields' => $errors],
        ],
        'meta' => response_meta(422),
    ];
    json_out($response, 422);
}

/**
 * Send paginated response
 */
function json_paginated(array $data, int $page, int $perPage, int $total): never
{
    $totalPages = (int) ceil($total / $perPage);

    $response = [
        'ok' => true,
        'data' => $data,
        'meta' => array_merge(response_meta(), [
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'hasMore' => $page * $perPage < $total,
            ],
        ]),
    ];
    json_out($response);
}
```

- [ ] **Step 4: Update _http_util.php to include response helpers**

Add to the end of `_http_util.php`:

```php
// Response helpers are in _response.php
require_once __DIR__ . '/_response.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php phpunit.phar tests/unit/ResponseFormatTest.php -v`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add public/api/_http_util.php public/api/_response.php tests/unit/ResponseFormatTest.php
git commit -m "feat(api): add standardized response helpers

- json_ok(), json_created(), json_fail() with new format
- json_validation_fail() for validation errors
- json_paginated() with pagination metadata
- Request ID generation for tracing

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 4: Update Bootstrap with New Helpers

**Files:**
- Rewrite: `public/api/_bootstrap.php`

**Interfaces:**
- Consumes: `_errors.php`, `_validator.php`, `_response.php`
- Produces: Updated `require_csrf()`, `require_write()` using new helpers

- [ ] **Step 1: Update _bootstrap.php**

Replace the `require_csrf()` function (around line 379-390) with:

```php
/**
 * CSRF protection - uses CSRFValidator from _validator.php
 */
function require_csrf(): void
{
    CSRFValidator::validate();
}
```

Replace `json_fail()` calls in `_bootstrap.php` to use new format:

```php
// Update require_login() to use new error format
function require_login(): array
{
    $me = require_login_pending_pw();
    if (!empty($me['must_change_pw'])) {
        json_fail(ERR_PASSWORD_EXPIRED, 'Bạn cần đổi mật khẩu trước khi tiếp tục sử dụng.', [], 403);
    }
    return $me;
}

function require_login_pending_pw(): array
{
    $me = current_member();
    if (!$me) json_fail(ERR_UNAUTHORIZED, 'Chưa đăng nhập.');
    if ($me['status'] === 'đã nghỉ') json_fail(ERR_ACCOUNT_DISABLED, 'Tài khoản đã ngưng hoạt động.', [], 403);
    return $me;
}

function require_permission(string $moduleKey, string $need = 'view'): array
{
    $me = require_login();
    $have = permission_of($moduleKey);

    if ($have === 'none') json_fail(ERR_PERMISSION_DENIED, 'Bạn không có quyền truy cập chức năng này.', [], 403);
    if ($need === 'edit' && $have !== 'edit') json_fail(ERR_PERMISSION_DENIED, 'Bạn chỉ được xem, không được thay đổi.', [], 403);

    // Module đang bảo trì thì chặn tất cả trừ Quản trị
    $mod = db_one('SELECT is_enabled, label FROM modules WHERE module_key = ?', [$moduleKey]);
    if ($mod && !$mod['is_enabled'] && $me['role_code'] !== 'admin') {
        json_fail(ERR_SERVICE_UNAVAILABLE, 'Chức năng "' . $mod['label'] . '" đang tạm bảo trì.', [], 503);
    }
    return $me;
}
```

Update rate limit messages:

```php
function login_throttle(string $phone): void
{
    // ... existing code ...
    if ($theoSo >= DN_TOI_DA_SO || $theoIp >= DN_TOI_DA_IP) {
        json_fail(ERR_LOGIN_ATTEMPTS_EXCEEDED,
            'Bạn đã nhập sai quá nhiều lần. Vui lòng đợi ' . DN_CUA_SO_PHUT . ' phút rồi thử lại.',
            [], 429);
    }
}
```

- [ ] **Step 2: Verify existing tests still pass**

Run: `php phpunit.phar --testsuite "TNTT Unit Tests" -v 2>&1 | head -100`
Expected: Existing tests pass (may need minor updates for new response format)

- [ ] **Step 3: Commit**

```bash
git add public/api/_bootstrap.php
git commit -m "refactor(api): update bootstrap to use new error codes and CSRF validator

- Replace string error literals with ERR_* constants
- Use CSRFValidator::validate() for CSRF checks
- Update require_login/require_permission with new json_fail format

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 5: Update data.php with New Response Format

**Files:**
- Rewrite: `public/api/data.php`

**Interfaces:**
- Consumes: New response helpers
- Produces: Standardized response format with pagination metadata

- [ ] **Step 1: Update data.php response format**

Replace the existing response format with:

```php
/**
 * Trả JSON kèm ETag (băm nội dung). Máy khách gửi lại If-None-Match: nếu dữ
 * liệu không đổi thì trả 304 rỗng.
 */
function data_out(array $payload): never
{
    // Keep existing cache logic intact
    foreach (['programClasses', 'stampSummaries'] as $k) {
        if (isset($payload[$k]) && !$payload[$k]) $payload[$k] = (object) [];
    }
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $etag = '"' . md5($body) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, no-cache');
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    echo $body;
    exit;
}
```

Update the final output section (around line 584-625) to use new response format:

```php
// Build result
$result = [
    'notes'         => $notes,
    'stampSummaries' => $stampSummaries,
    'classCounts'   => $classCounts,
    'programs'      => $programs,
    'programClasses' => $programClasses,
    'attendances'   => $attendances,
    'leaveRequests' => $leaves,
    'scores'        => $scores,
    'reports'       => $reports,
    'announcements' => $announcements,
    'readAnnouncements' => $readIds,
    'members'       => $members,
    'logs'          => $logs,
    'libraryPending' => $libraryPending,
];

// Students: paginated response
if ($isPaginated) {
    $result['students'] = $students['data'];
    $result['pagination'] = [
        'total'     => $students['total'],
        'page'      => $students['page'],
        'limit'     => $students['limit'],
        'totalPages'=> ceil($students['total'] / $students['limit']),
        'hasMore'   => $students['page'] * $students['limit'] < $students['total'],
    ];
} else {
    $result['students'] = $students['data'];
}

Cache::set($cacheKey, $result, 60);
data_out($result);
```

- [ ] **Step 2: Run tests**

Run: `php phpunit.phar tests/unit/DataScopeTest.php -v`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add public/api/data.php
git commit -m "refactor(api): update data.php response format

- Maintain backward compatibility (ok:true still present)
- Add pagination metadata in meta.pagination
- Keep ETag caching working

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 6: Create Service Worker Core Modules

**Files:**
- Create: `public/assets/js/sw/core/cache-manager.js`
- Create: `public/assets/js/sw/core/network-manager.js`
- Create: `public/assets/js/sw/core/sync-manager.js`
- Create: `public/assets/js/sw/utils/constants.js`
- Create: `public/assets/js/sw/utils/logger.js`

**Interfaces:**
- Consumes: Cache config, fetch requests
- Produces: CacheManager, NetworkManager, SyncManager classes

- [ ] **Step 1: Create cache constants**

```javascript
// public/assets/js/sw/utils/constants.js
export const CACHE_CONFIG = {
  STATIC: {
    name: 'tntt-static-v1',
    maxAge: 7 * 24 * 60 * 60, // 7 days
    strategy: 'cache-first'
  },
  API: {
    name: 'tntt-api-v1',
    maxAge: 60 * 60, // 1 hour
    strategy: 'stale-while-revalidate'
  },
  DATA: {
    name: 'tntt-data-v1',
    maxAge: 5 * 60, // 5 minutes
    strategy: 'network-first'
  }
};

export const API_PATTERNS = {
  STATIC: /\.(js|css|png|svg|jpg|jpeg|webp|woff2?)(\?.*)?$/i,
  BUNDLE: /\/bundle\.php(\?.*)?$/,
  API_DATA: /\/api\/data\.php/,
  API_PUBLIC: /\/api\/(push|sync|bible)\.php/
};

export const MAX_RETRIES = 3;
export const RETRY_DELAY = 1000; // 1 second base delay
```

- [ ] **Step 2: Create logger utility**

```javascript
// public/assets/js/sw/utils/logger.js
const DEBUG = false; // Set to true for development

export function log(...args) {
  if (DEBUG && self.registration.active) {
    console.log('[SW]', new Date().toISOString(), ...args);
  }
}

export function error(...args) {
  console.error('[SW]', new Date().toISOString(), ...args);
}

export function warn(...args) {
  console.warn('[SW]', new Date().toISOString(), ...args);
}
```

- [ ] **Step 3: Create CacheManager**

```javascript
// public/assets/js/sw/core/cache-manager.js
import { CACHE_CONFIG, log, error } from '../utils/index.js';

export class CacheManager {
  constructor() {
    this.caches = {};
  }

  async open(config) {
    const name = config.name;
    if (!this.caches[name]) {
      this.caches[name] = await caches.open(name);
    }
    return this.caches[name];
  }

  async match(request, config) {
    const cache = await this.open(config);
    return cache.match(request);
  }

  async put(request, response, config) {
    const cache = await this.open(config);
    // Clone response before storing (can only be consumed once)
    if (response.ok) {
      await cache.put(request, response.clone());
    }
  }

  async delete(config) {
    const name = config.name;
    if (this.caches[name]) {
      delete this.caches[name];
    }
    await caches.delete(name);
    log(`Cache ${name} deleted`);
  }

  async cleanupOldCaches(currentVersion) {
    const keys = await caches.keys();
    for (const key of keys) {
      if (key.startsWith('tntt-') && key !== currentVersion) {
        log(`Cleaning up old cache: ${key}`);
        await caches.delete(key);
      }
    }
  }

  async getFreshness(response, maxAge) {
    if (!response) return false;
    const dateHeader = response.headers.get('date');
    if (!dateHeader) return false;

    const age = (Date.now() - new Date(dateHeader).getTime()) / 1000;
    return age < maxAge;
  }
}

export const cacheManager = new CacheManager();
```

- [ ] **Step 4: Create NetworkManager**

```javascript
// public/assets/js/sw/core/network-manager.js
import { cacheManager } from './cache-manager.js';
import { CACHE_CONFIG, log, error } from '../utils/index.js';

export class NetworkManager {
  async fetch(request, options = {}) {
    try {
      const response = await fetch(request, {
        credentials: 'include',
        ...options
      });
      return response;
    } catch (err) {
      error('Network fetch failed:', err);
      return null;
    }
  }

  async fetchWithFallback(request, config) {
    // Try network first
    const response = await this.fetch(request);
    if (response && response.ok) {
      await cacheManager.put(request, response, config);
      return response;
    }

    // Fallback to cache
    const cached = await cacheManager.match(request, config);
    if (cached) {
      log(`Serving ${request.url} from cache (fallback)`);
      return cached;
    }

    // No network and no cache
    return null;
  }
}

export const networkManager = new NetworkManager();
```

- [ ] **Step 5: Create SyncManager**

```javascript
// public/assets/js/sw/core/sync-manager.js
import { MAX_RETRIES, RETRY_DELAY, log, error } from '../utils/index.js';

export class SyncManager {
  constructor() {
    this.pendingSyncs = [];
  }

  async registerBackgroundSync(tag) {
    if ('sync' in self.registration) {
      try {
        await self.registration.sync.register(tag);
        log(`Background sync registered: ${tag}`);
        return true;
      } catch (err) {
        error(`Background sync registration failed:`, err);
        return false;
      }
    }
    return false;
  }

  async syncQueue(queueName) {
    // Get queue from IndexedDB
    const queue = await this.getQueue(queueName);
    if (!queue || queue.length === 0) {
      log(`Queue ${queueName} is empty`);
      return;
    }

    for (const item of queue) {
      try {
        await this.processQueueItem(item);
        await this.removeFromQueue(queueName, item.id);
        log(`Synced item ${item.id}`);
      } catch (err) {
        error(`Failed to sync item ${item.id}:`, err);
        await this.incrementRetry(queueName, item.id);
      }
    }
  }

  async processQueueItem(item) {
    const response = await fetch(item.endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item.payload)
    });

    if (!response.ok) {
      throw new Error(`Sync failed: ${response.status}`);
    }

    return response.json();
  }

  async getQueue(name) {
    // IndexedDB operations
    return new Promise((resolve) => {
      const request = indexedDB.open('TNTT', 1);
      request.onerror = () => resolve([]);
      request.onsuccess = () => {
        const db = request.result;
        const tx = db.transaction('offline_queue', 'readonly');
        const store = tx.objectStore('offline_queue');
        const getAll = store.getAll();
        getAll.onsuccess = () => resolve(getAll.result.filter(i => i.status === 'pending'));
        getAll.onerror = () => resolve([]);
      };
    });
  }

  async removeFromQueue(name, id) {
    // Remove from IndexedDB
  }

  async incrementRetry(name, id) {
    // Update retry count in IndexedDB
  }
}

export const syncManager = new SyncManager();
```

- [ ] **Step 6: Commit**

```bash
git add public/assets/js/sw/utils/constants.js public/assets/js/sw/utils/logger.js public/assets/js/sw/core/cache-manager.js public/assets/js/sw/core/network-manager.js public/assets/js/sw/core/sync-manager.js
git commit -m "feat(pwa): add service worker core modules

- CacheManager for cache lifecycle management
- NetworkManager for fetch with fallback
- SyncManager for background sync
- Constants and logger utilities

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 7: Create Cache Strategy Modules

**Files:**
- Create: `public/assets/js/sw/strategies/cache-first.js`
- Create: `public/assets/js/sw/strategies/network-first.js`
- Create: `public/assets/js/sw/strategies/stale-while-revalidate.js`

- [ ] **Step 1: Create cache-first strategy**

```javascript
// public/assets/js/sw/strategies/cache-first.js
import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Cache-First Strategy
 * - Check cache first
 * - Return cached response if fresh
 * - Fetch from network and cache if not
 * - Fallback to network if not cached
 */
export async function cacheFirst(request, config = CACHE_CONFIG.STATIC) {
  // Check cache first
  const cached = await cacheManager.match(request, config);
  if (cached) {
    log(`Cache-first HIT: ${request.url}`);

    // Check freshness
    const isFresh = await cacheManager.getFreshness(cached, config.maxAge);
    if (isFresh) {
      return cached;
    }

    // Return cached but refresh in background
    fetchAndCache(request, config);
    return cached;
  }

  // Not in cache, fetch from network
  log(`Cache-first MISS: ${request.url}`);
  const response = await fetch(request);
  if (response.ok) {
    await cacheManager.put(request, response, config);
  }
  return response;
}

async function fetchAndCache(request, config) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`Background refresh: ${request.url}`);
    }
  } catch (err) {
    // Silently fail - we already returned cached version
  }
}
```

- [ ] **Step 2: Create network-first strategy**

```javascript
// public/assets/js/sw/strategies/network-first.js
import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Network-First Strategy
 * - Try network first
 * - Cache successful response
 * - Fallback to cache if network fails
 */
export async function networkFirst(request, config = CACHE_CONFIG.DATA) {
  try {
    const response = await fetch(request, { credentials: 'include' });
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`Network-first SUCCESS: ${request.url}`);
      return response;
    }
    // Network error (4xx/5xx)
    throw new Error(`HTTP ${response.status}`);
  } catch (err) {
    log(`Network-first FALLBACK: ${request.url}`);
    const cached = await cacheManager.match(request, config);
    if (cached) {
      return cached;
    }
    // No cache available
    return new Response(JSON.stringify({
      ok: false,
      error: { code: 'NETWORK_ERROR', message: 'Không có kết nối và không có dữ liệu cache.' }
    }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}
```

- [ ] **Step 3: Create stale-while-revalidate strategy**

```javascript
// public/assets/js/sw/strategies/stale-while-revalidate.js
import { cacheManager } from '../core/cache-manager.js';
import { CACHE_CONFIG, log } from '../utils/index.js';

/**
 * Stale-While-Revalidate Strategy
 * - Return cached response immediately
 * - Refresh cache in background
 * - Good for: API data that changes occasionally
 */
export async function staleWhileRevalidate(request, config = CACHE_CONFIG.API) {
  const cached = await cacheManager.match(request, config);

  if (cached) {
    log(`SWR HIT: ${request.url}`);
    // Return cached immediately
    // Refresh in background
    refreshCache(request, config);
    return cached;
  }

  // No cache, fetch from network
  log(`SWR MISS: ${request.url}`);
  const response = await fetch(request);
  if (response.ok) {
    await cacheManager.put(request, response, config);
  }
  return response;
}

async function refreshCache(request, config) {
  try {
    const response = await fetch(request);
    if (response.ok) {
      await cacheManager.put(request, response, config);
      log(`SWR background refresh: ${request.url}`);
    }
  } catch (err) {
    // Silently fail
  }
}
```

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/sw/strategies/cache-first.js public/assets/js/sw/strategies/network-first.js public/assets/js/sw/strategies/stale-while-revalidate.js
git commit -m "feat(pwa): add cache strategy modules

- Cache-first for static assets
- Network-first for critical data
- Stale-while-revalidate for API data

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 8: Rewrite Service Worker Entry Point

**Files:**
- Rewrite: `public/sw.js`

**Interfaces:**
- Consumes: Cache strategies, constants
- Produces: Service worker with fetch handling, push notifications, background sync

- [ ] **Step 1: Rewrite sw.js**

```javascript
/* ==========================================================
   SERVICE WORKER - API + PWA Refactor

   Strategies:
   - Static assets: Cache-first (7 days)
   - API data: Network-first with cache fallback
   - Push notifications: Full payload support
   - Background sync: IndexedDB queue
   ========================================================== */

import { cacheManager } from './assets/js/sw/core/cache-manager.js';
import { syncManager } from './assets/js/sw/core/sync-manager.js';
import { cacheFirst } from './assets/js/sw/strategies/cache-first.js';
import { networkFirst } from './assets/js/sw/strategies/network-first.js';
import { staleWhileRevalidate } from './assets/js/sw/strategies/stale-while-revalidate.js';
import { CACHE_CONFIG, API_PATTERNS, log, error } from './assets/js/sw/utils/constants.js';

// Version from URL
const PHIEN_BAN = new URL(self.location.href).searchParams.get('v') || 'tntt-sw-dev';
const KHO = 'tntt-tinh-' + PHIEN_BAN;

// ==========================================================
// INSTALL
// ==========================================================
self.addEventListener('install', (e) => {
  e.waitUntil((async () => {
    log('Service Worker installing...');
    // Precache critical assets
    const kho = await caches.open(KHO);
    try {
      await Promise.allSettled([
        kho.add('/assets/css/bundle.php'),
        kho.add('/assets/js/bundle.php'),
      ]);
    } catch (err) {
      warn('Precache failed:', err);
    }
    self.skipWaiting();
  })());
});

// ==========================================================
// ACTIVATE
// ==========================================================
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    log('Service Worker activating...');
    // Cleanup old caches
    await cacheManager.cleanupOldCaches(KHO);
    await self.clients.claim();
    log('Service Worker activated');
  })());
});

// ==========================================================
// FETCH - Request Handling
// ==========================================================
self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Skip localhost dev
  if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') return;

  // Skip cross-origin
  if (url.origin !== self.location.origin) return;

  // Skip API (use network-first, no caching)
  if (url.pathname.startsWith('/api/')) return;

  // Skip service worker
  if (url.pathname.endsWith('/sw.js')) return;

  // Static assets: Cache-first
  if (API_PATTERNS.STATIC.test(url.pathname) || API_PATTERNS.BUNDLE.test(url.pathname)) {
    e.respondWith(cacheFirst(req, CACHE_CONFIG.STATIC));
    return;
  }

  // API data pages: Stale-while-revalidate
  if (url.pathname.endsWith('.php') && !url.pathname.startsWith('/api/')) {
    e.respondWith(staleWhileRevalidate(req, CACHE_CONFIG.API));
    return;
  }
});

// ==========================================================
// PUSH NOTIFICATIONS
// ==========================================================
self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let notification = {
      title: 'Có việc mới',
      body: 'Mở app để xem chi tiết.',
      icon: 'assets/img/icon.svg',
      badge: 'assets/img/icon-32.png',
      tag: 'tntt-chung',
      data: { url: '/' }
    };

    // Try to parse payload (new format)
    if (e.data) {
      try {
        const data = e.data.json();
        notification = { ...notification, ...data };
      } catch (err) {
        // Fallback to old behavior (fetch from server)
        try {
          notification = await fetchNotificationContent();
        } catch (fetchErr) {
          // Keep default notification
        }
      }
    } else {
      // No payload, fetch from server
      try {
        notification = await fetchNotificationContent();
      } catch (err) {
        // Keep default notification
      }
    }

    // Notify open tabs
    await notifyOpenTabs(notification.data.url);

    // Show notification if no focused tab
    if (!await hasFocusedClient()) {
      await self.registration.showNotification(notification.title, {
        body: notification.body,
        icon: notification.icon,
        badge: notification.badge,
        tag: notification.tag,
        renotify: false,
        data: notification.data,
        vibrate: [80, 40, 80]
      });
    }
  })());
});

async function fetchNotificationContent() {
  const khoa = new URL('__tntt_push_token', self.registration.scope).href;
  let token = '';
  try {
    const luu = await (await caches.open('tntt-push')).match(khoa);
    if (luu) token = (await luu.text()).trim();
  } catch (err) {}

  const dk = token ? null : await self.registration.pushManager.getSubscription();
  const r = await fetch('/api/push.php?action=pending', {
    method: 'POST',
    credentials: 'include',
    cache: 'no-store',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(token ? { token } : { endpoint: dk ? dk.endpoint : '' })
  });

  if (r.ok) {
    const d = await r.json();
    if (d && d.ok && d.item) {
      return {
        title: d.item.title || 'Có việc mới',
        body: d.item.body || 'Mở app để xem chi tiết.',
        icon: d.item.icon || 'assets/img/icon.svg',
        tag: d.item.tag || 'tntt-chung',
        data: { url: d.item.url || '/' }
      };
    }
  }
  throw new Error('Failed to fetch notification');
}

async function hasFocusedClient() {
  const clients = await self.clients.matchAll({ type: 'window' });
  return clients.some(c => c.visibilityState === 'visible');
}

async function notifyOpenTabs(url) {
  const clients = await self.clients.matchAll({ type: 'window' });
  for (const c of clients) {
    if (c.visibilityState === 'visible') {
      c.postMessage({ action: 'RELOAD_DATA', url: url || '/' });
    }
  }
}

// ==========================================================
// NOTIFICATION CLICK
// ==========================================================
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const targetUrl = (e.notification.data && e.notification.data.url) || '/';

  e.waitUntil((async () => {
    // Focus existing window if available
    const clients = await self.clients.matchAll({ type: 'window' });
    for (const c of clients) {
      if (c.url.includes(self.location.origin)) {
        await c.focus();
        if ('navigate' in c) {
          try { await c.navigate(targetUrl); } catch (err) {}
        }
        return;
      }
    }
    // Open new window
    await self.clients.openWindow(targetUrl);
  })());
});

// ==========================================================
// BACKGROUND SYNC
// ==========================================================
self.addEventListener('sync', (e) => {
  if (e.tag === 'offline-sync') {
    e.waitUntil(syncManager.syncQueue('default'));
  }
});
```

- [ ] **Step 2: Commit**

```bash
git add public/sw.js
git commit -m "refactor(pwa): rewrite service worker with modular architecture

- Import cache strategy modules
- Network-first for API calls
- Cache-first for static assets
- Improved push notification handling
- Background sync support

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 9: Create Offline Queue Class

**Files:**
- Create: `public/assets/js/offline-queue.js`

**Interfaces:**
- Consumes: Offline actions (attendance, scores, notes)
- Produces: IndexedDB storage, sync methods

- [ ] **Step 1: Create offline queue implementation**

```javascript
/**
 * OFFLINE QUEUE
 *
 * Queues write operations when offline, syncs when connection restored.
 * Uses IndexedDB for persistent storage.
 */

const DB_NAME = 'TNTT';
const DB_VERSION = 1;
const STORE_NAME = 'offline_queue';
const MAX_RETRIES = 3;
const RETRY_DELAYS = [1000, 5000, 30000]; // Exponential backoff

class OfflineQueue {
  constructor() {
    this.db = null;
    this.isOnline = navigator.onLine;
    this.listeners = new Set();

    // Setup online/offline listeners
    window.addEventListener('online', () => this.handleOnline());
    window.addEventListener('offline', () => this.handleOffline());
  }

  async init() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);

      request.onerror = () => reject(request.error);
      request.onsuccess = () => {
        this.db = request.result;
        resolve();
      };

      request.onupgradeneeded = (e) => {
        const db = e.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
          store.createIndex('action', 'action', { unique: false });
          store.createIndex('status', 'status', { unique: false });
          store.createIndex('timestamp', 'timestamp', { unique: false });
        }
      };
    });
  }

  /**
   * Add item to queue
   */
  async enqueue(action, endpoint, payload) {
    const item = {
      action,
      endpoint,
      payload,
      timestamp: Date.now(),
      retries: 0,
      status: 'pending'
    };

    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.add(item);

      request.onsuccess = () => {
        const id = request.result;
        this.notifyListeners('enqueue', { ...item, id });
        this.trySync();
        resolve(id);
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Get all pending items
   */
  async getPending() {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('status');
      const request = index.getAll('pending');

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Get queue status
   */
  async getStatus() {
    const pending = await this.getPending();
    const failed = await this.getFailed();
    return {
      pending: pending.length,
      failed: failed.length,
      isOnline: this.isOnline
    };
  }

  /**
   * Get failed items
   */
  async getFailed() {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readonly');
      const store = tx.objectStore(STORE_NAME);
      const index = store.index('status');
      const request = index.getAll('failed');

      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Remove item from queue
   */
  async remove(id) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.delete(id);

      request.onsuccess = () => {
        this.notifyListeners('remove', { id });
        resolve();
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Update item status
   */
  async updateStatus(id, status) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const getRequest = store.get(id);

      getRequest.onsuccess = () => {
        const item = getRequest.result;
        if (item) {
          item.status = status;
          const putRequest = store.put(item);
          putRequest.onsuccess = () => resolve();
          putRequest.onerror = () => reject(putRequest.error);
        } else {
          resolve();
        }
      };
      getRequest.onerror = () => reject(getRequest.error);
    });
  }

  /**
   * Increment retry count
   */
  async incrementRetry(id) {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const getRequest = store.get(id);

      getRequest.onsuccess = () => {
        const item = getRequest.result;
        if (item) {
          item.retries++;
          if (item.retries >= MAX_RETRIES) {
            item.status = 'failed';
          }
          const putRequest = store.put(item);
          putRequest.onsuccess = () => resolve(item);
          putRequest.onerror = () => reject(putRequest.error);
        } else {
          resolve(null);
        }
      };
      getRequest.onerror = () => reject(getRequest.error);
    });
  }

  /**
   * Process sync
   */
  async sync() {
    if (!this.isOnline) {
      console.log('[OfflineQueue] Skipping sync - offline');
      return { synced: 0, failed: 0 };
    }

    const pending = await this.getPending();
    if (pending.length === 0) {
      return { synced: 0, failed: 0 };
    }

    let synced = 0;
    let failed = 0;

    for (const item of pending) {
      try {
        await this.syncItem(item);
        await this.remove(item.id);
        synced++;
      } catch (err) {
        console.error('[OfflineQueue] Sync failed for item', item.id, err);
        const updated = await this.incrementRetry(item.id);
        if (updated && updated.status === 'failed') {
          failed++;
        }
      }
    }

    this.notifyListeners('sync', { synced, failed });
    return { synced, failed };
  }

  /**
   * Sync single item
   */
  async syncItem(item) {
    const response = await fetch(item.endpoint, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(item.payload)
    });

    if (!response.ok) {
      throw new Error(`Sync failed: ${response.status}`);
    }

    return response.json();
  }

  /**
   * Try to sync (called after enqueue or when coming online)
   */
  trySync() {
    if (this.isOnline) {
      // Use Background Sync API if available
      if ('sync' in navigator.serviceWorker) {
        navigator.serviceWorker.ready.then(registration => {
          registration.sync.register('offline-sync');
        });
      } else {
        // Fallback to immediate sync
        this.sync();
      }
    }
  }

  /**
   * Handle going online
   */
  handleOnline() {
    console.log('[OfflineQueue] Online');
    this.isOnline = true;
    this.notifyListeners('online');
    this.sync();
  }

  /**
   * Handle going offline
   */
  handleOffline() {
    console.log('[OfflineQueue] Offline');
    this.isOnline = false;
    this.notifyListeners('offline');
  }

  /**
   * Add event listener
   */
  addListener(callback) {
    this.listeners.add(callback);
    return () => this.listeners.delete(callback);
  }

  /**
   * Notify listeners
   */
  notifyListeners(event, data) {
    this.listeners.forEach(cb => {
      try {
        cb(event, data);
      } catch (err) {
        console.error('[OfflineQueue] Listener error:', err);
      }
    });
  }

  /**
   * Clear all items
   */
  async clear() {
    return new Promise((resolve, reject) => {
      const tx = this.db.transaction(STORE_NAME, 'readwrite');
      const store = tx.objectStore(STORE_NAME);
      const request = store.clear();

      request.onsuccess = () => {
        this.notifyListeners('clear');
        resolve();
      };
      request.onerror = () => reject(request.error);
    });
  }

  /**
   * Retry failed items
   */
  async retryFailed() {
    const failed = await this.getFailed();
    for (const item of failed) {
      await this.updateStatus(item.id, 'pending');
    }
    this.trySync();
    return failed.length;
  }
}

// Export singleton
window.TNTTOfflineQueue = new OfflineQueue();
```

- [ ] **Step 2: Create tests for offline queue**

```javascript
// tests/unit/OfflineQueueTest.js
// Note: This would need a test environment with IndexedDB mock
// For now, manual testing is recommended
```

- [ ] **Step 3: Commit**

```bash
git add public/assets/js/offline-queue.js
git commit -m "feat(pwa): add offline queue for write operations

- IndexedDB-based persistent queue
- FIFO sync when online
- Exponential backoff for retries
- Event listeners for UI updates
- Works with Background Sync API

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 10: Update Client JavaScript for New API Format

**Files:**
- Update: `public/assets/js/modules/core.js`
- Update: `public/assets/js/modules/shell.js`

**Interfaces:**
- Consumes: New API response format
- Produces: Updated data handling with offline support

- [ ] **Step 1: Update core.js API handling**

Add to the API fetch functions to handle new response format:

```javascript
// In core.js - update fetchData function
async fetchData(part = 'all', options = {}) {
  const url = new URL('/api/data.php', window.location.origin);
  url.searchParams.set('part', part);
  if (options.page) {
    url.searchParams.set('page', options.page);
    url.searchParams.set('limit', options.limit || 100);
  }

  const response = await fetch(url.toString(), {
    credentials: 'include',
    headers: this.getHeaders()
  });

  // Handle new response format
  const data = await response.json();

  if (!data.ok) {
    // New error format
    if (data.error) {
      throw new ApiError(data.error.code, data.error.message, data.error.details);
    }
    throw new Error(data.error || 'Unknown error');
  }

  // Extract data (new format) or use old format (backward compatible)
  return data.data || data;
}
```

Add ApiError class:

```javascript
class ApiError extends Error {
  constructor(code, message, details = {}) {
    super(message);
    this.code = code;
    this.details = details;
    this.name = 'ApiError';
  }
}
```

- [ ] **Step 2: Update shell.js for offline support**

```javascript
// In shell.js - add offline queue initialization
initOfflineSupport() {
  // Initialize offline queue
  window.TNTTOfflineQueue.init().catch(err => {
    console.error('Failed to initialize offline queue:', err);
  });

  // Listen for sync events
  window.TNTTOfflineQueue.addListener((event, data) => {
    if (event === 'sync') {
      this.showToast(`Đã đồng bộ ${data.synced} mục${data.failed ? `, ${data.failed} thất bại` : ''}`);
      this.refreshData();
    } else if (event === 'failed') {
      this.showToast('Một số thao tác offline chưa đồng bộ được', 'warning');
    }
  });
}
```

- [ ] **Step 3: Update attendance marking for offline**

```javascript
// In attendance module - mark offline-capable
async markAttendance(studentId, programId, date, status) {
  const payload = {
    studentId,
    programId,
    date,
    status
  };

  try {
    const response = await fetch('/api/attendance.php', {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.getCsrfToken()
      },
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    if (data.ok) {
      return data.data;
    }
    throw new Error(data.error?.message || 'Failed to mark attendance');
  } catch (err) {
    // Offline - queue it
    if (!navigator.onLine) {
      await window.TNTTOfflineQueue.enqueue(
        'attendance_mark',
        '/api/attendance.php',
        payload
      );
      // Update local state optimistically
      this.updateLocalAttendance(studentId, programId, date, status);
      return { queued: true };
    }
    throw err;
  }
}
```

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/modules/core.js public/assets/js/modules/shell.js
git commit -m "feat(client): update for new API format and offline support

- Handle new {ok, data, error, meta} response format
- ApiError class with error codes
- Initialize OfflineQueue in shell
- Update attendance marking for offline queue

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 11: Update Config for Rate Limiting

**Files:**
- Update: `config/config.php`

- [ ] **Step 1: Add rate limit configuration**

Add to `config.php`:

```php
/**
 * RATE LIMITING CONFIGURATION
 *
 * Per-endpoint and per-user rate limits.
 */
$config['rate_limits'] = [
    // Per-user limits (requests per minute)
    'per_user' => [
        'attendance_create' => 60,    // 60/min for marking attendance
        'data' => 30,                 // 30/min for data requests
        'default' => 120,              // 120/min default
    ],

    // Per-endpoint limits (requests per minute)
    'per_endpoint' => [
        'data' => 300,                // 300/min for data endpoint
        'sync' => 60,                 // 60/min for sync
        'default' => 600,              // 600/min default
    ],

    // Whitelist (IPs or patterns that skip rate limiting)
    'whitelist' => [
        '127.0.0.1',
        '::1',
    ],
];
```

- [ ] **Step 2: Commit**

```bash
git add config/config.php
git commit -m "feat(config): add rate limiting configuration

- Per-user rate limits
- Per-endpoint rate limits
- Whitelist for local development

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Task 12: Final Integration and Testing

**Files:**
- Update: `public/assets/asset_manifest.php` (add new JS modules)
- Update: Service worker registration

- [ ] **Step 1: Update asset manifest**

Add new modules to `asset_manifest.php`:

```php
$jsModules = [
    // ... existing modules ...
    'offline-queue' => 'assets/js/offline-queue.js',
    'sw-utils' => 'assets/js/sw/utils/index.js',
    'sw-cache-manager' => 'assets/js/sw/core/cache-manager.js',
    'sw-network-manager' => 'assets/js/sw/core/network-manager.js',
    'sw-sync-manager' => 'assets/js/sw/core/sync-manager.js',
    'sw-cache-first' => 'assets/js/sw/strategies/cache-first.js',
    'sw-network-first' => 'assets/js/sw/strategies/network-first.js',
    'sw-swr' => 'assets/js/sw/strategies/stale-while-revalidate.js',
];
```

- [ ] **Step 2: Update service worker registration in index.php**

```javascript
// In index.php or main JS file
if ('serviceWorker' in navigator) {
  window.addEventListener('load', async () => {
    try {
      const version = window.TNTT_BUILD_VERSION || Date.now();
      const registration = await navigator.serviceWorker.register(`/sw.js?v=${version}`);
      console.log('SW registered:', registration.scope);

      // Handle updates
      registration.addEventListener('updatefound', () => {
        const newWorker = registration.installing;
        newWorker.addEventListener('statechange', () => {
          if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
            // New version available
            console.log('New SW version available');
          }
        });
      });
    } catch (err) {
      console.error('SW registration failed:', err);
    }
  });
}
```

- [ ] **Step 3: Run all tests**

```bash
php phpunit.phar --testsuite "TNTT Unit Tests" -v
```

- [ ] **Step 4: Run linting**

```bash
npx --yes eslint@9.39.5 public/assets/js/ public/sw.js
```

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: complete API + PWA refactor implementation

Phase 1 - API Foundation:
- Error code registry
- Validation layer with CSRF
- Standardized response helpers

Phase 2 - PWA:
- Modular service worker
- Cache strategies (cache-first, network-first, SWR)
- Offline queue with IndexedDB

Phase 3 - Client:
- New API format handling
- Offline-capable operations
- Updated config

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
```

---

## Verification Checklist

After each task:
- [ ] Run unit tests: `php phpunit.phar tests/unit/ErrorCodesTest.php -v`
- [ ] Run linting: `npx eslint public/assets/js/**/*.js`
- [ ] Test in browser (manual)

Final verification:
- [ ] All unit tests pass
- [ ] ESLint passes
- [ ] Service worker updates correctly
- [ ] Offline attendance works
- [ ] API responses match new format

---

## Rollback Plan

If issues arise:
```bash
git tag snapshot-before-api-pwa-refactor
git revert HEAD --no-commit
# Or: git reset --hard HEAD~1
```

---

*Plan created from spec: docs/superpowers/specs/2025-01-20-api-pwa-refactor-design.md*
