# Task 1 Brief: Security - CSRF Protection

## Task Description
Implement CSRF token protection for all POST requests in the TNTT Super App.

## Files to Create/Modify

### Create: `public/api/csrf.php`
```php
<?php
/**
 * CSRF Protection - Token generation and verification
 */

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
```

### Modify: `public/api/_bootstrap.php`
Add at the end (before the last closing brace or before `require_login()` if exists):
```php
function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!verify_csrf($token)) {
        json_fail('Invalid CSRF token.', 403);
    }
}
```

### Modify: `public/api/_bootstrap_page.php`
Add to `page_bootstrap()` function - generate and include CSRF token in boot data:
```php
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
// ... in the returned array:
'csrfToken' => $_SESSION['csrf_token'],
```

### Modify: `public/assets/js/modules/core.js`
Add in `Alpine.data('tnttApp', ...)` initialization:
```javascript
window.TNTT = window.TNTT || {};
window.TNTT.csrfToken = window.TNTT_BOOT?.csrfToken || '';

window.TNTT.csrfFetch = async (url, options = {}) => {
    return fetch(url, {
        ...options,
        method: options.method || 'GET',
        headers: {
            ...options.headers,
            'X-CSRF-TOKEN': window.TNTT.csrfToken,
            'Content-Type': 'application/json',
        },
    });
};
```

### Modify: All PHP view files that contain forms
Add hidden CSRF input to all forms:
```html
<input type="hidden" name="_csrf" :value="csrfToken">
```

Focus files: `views/module_students.php`, `views/module_attendance.php`, `views/module_settings.php`, `views/module_announcements.php`

### Create: `tests/unit/CSRFTest.php`
```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../public/api/csrf.php';

class CSRFTest extends PHPUnit_Framework_TestCase {
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
```

### Create: `tests/bootstrap.php`
```php
<?php
// Bootstrap file for PHPUnit tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load application config
require_once __DIR__ . '/../config/db.php';
```

### Create: `phpunit.xml`
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true"
         stopOnFailure="false">
    <testsuites>
        <testsuite name="TNTT Unit Tests">
            <directory>tests/unit</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

## Requirements
- CSRF token: 32 bytes, hex-encoded (64 characters)
- Token stored in session
- `hash_equals()` used for timing-safe comparison
- CSRF check function: `require_csrf()`
- JavaScript helper: `window.TNTT.csrfFetch()`
- Hidden input field in all forms: `name="_csrf"`
- Tests must pass

## Acceptance Criteria
1. All POST requests without valid CSRF token are rejected with 403
2. CSRF token is included in `window.TNTT_BOOT.csrfToken`
3. JavaScript `csrfFetch()` automatically sends CSRF token
4. All forms include hidden CSRF input
5. Unit tests cover token generation and verification
