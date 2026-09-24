# Code Quality Review - PHP Backend

**Project:** TNTT Volute
**Reviewer:** Claude Code
**Date:** 2026-09-02
**Scope:** `public/api/` + `config/`

---

## Executive Summary

| Category | Count |
|----------|-------|
| CRITICAL | 3 |
| HIGH | 5 |
| MEDIUM | 8 |
| LOW | 6 |

Overall: Code is well-structured with good security practices. Main concerns are hardcoded credentials in config, missing type hints, and some architectural inconsistencies.

---

## CRITICAL Issues

### [CRITICAL-1] Hardcoded credentials in config.php:34-38
**File:** `config/config.php:33-38`
**Description:** Push notification VAPID keys and setup key are hardcoded in the example config file.

```php
'setup_key' => '123456789012120937867508',
'push' => [
    'public'  => 'BKwJPh2CRLonC6WHGRXHifm1SUuwOhHOSgy6ZmkiAe3X8aLhNNIuJ58dgsu9yTlx2XuCPy_eHK60KDF68F9NDB8',
    'private' => 't1m_pTScHFjS8Z2CQcNXUYOqJGKUw5vyTru_mT2UQac',
```

**Suggestion:** These are example values, but the setup_key looks suspiciously sequential. Ensure production config always uses `config.local.php` and never commits real credentials. Consider adding a CI check or pre-commit hook.

---

### [CRITICAL-2] SQL injection via dynamic table/column names in db_has_column()
**File:** `config/db.php:162,181`
**Description:** `db_has_table()` and `db_has_column()` build SQL using string interpolation for table/column names. While names are filtered to `[A-Za-z0-9_]`, this pattern is risky.

```php
$exists = db_one("SHOW TABLES LIKE '" . $name . "'") !== null;
// ...
$exists = db_one("SHOW COLUMNS FROM `" . $table . "` LIKE '" . $col . "'") !== null;
```

**Suggestion:** Use whitelist validation instead of regex replacement:
```php
if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
    return false;
}
```

---

### [CRITICAL-3] `@unlink()` with `@` error suppressor on line 168, 224, 238 in library.php
**File:** `public/api/library.php:168,224,238`
**Description:** `@unlink()` silently fails if file deletion fails. No logging or error handling.

```php
@unlink(library_storage_dir() . '/' . $oldStored);
```

**Suggestion:** Log failures instead of suppressing them:
```php
if (!@unlink($path)) {
    error_log("Failed to delete library file: $path");
}
```

---

## HIGH Issues

### [HIGH-1] Missing return statements in switch/case in library.php
**File:** `public/api/library.php:30-261`
**Description:** Cases like `categories`, `list`, `mine`, `pending`, `upload`, etc. are missing `break` statements. Since they call `json_out()` which exits, it's functionally equivalent but inconsistent with other endpoints.

**Suggestion:** Add explicit `break` statements for consistency, or use a pattern that makes the control flow clearer.

---

### [HIGH-2] Type coercion inconsistency for return types
**File:** Multiple files
**Description:** Functions have inconsistent return type hints. Some have proper `: array`, `: int`, `: bool`, others have no type hints at all.

Example - `public/api/_library.php:42`:
```php
function library_config(string $key)  // Missing return type
```

**Suggestion:** Add strict return type hints throughout:
```php
function library_config(string $key): mixed  // PHP 8.0+
```

---

### [HIGH-3] Global state in password.php:26-32
**File:** `config/password.php:26-32`
**Description:** `password_verify_upgrade()` checks `$GLOBALS['db_run_override']` for test mocking. This is a global side channel.

```php
if (isset($GLOBALS['db_run_override'])) {
    $GLOBALS['db_run_override'](...);
}
```

**Suggestion:** Pass the db_run function as a dependency or use a proper testing adapter pattern.

---

### [HIGH-4] Magic number: 31556952 seconds per year
**File:** `public/api/auth.php:161`
**Description:** Age calculation uses hardcoded seconds value instead of a constant.

```php
$tuoi = (int) ((time() - strtotime($birth)) / 31556952);
```

**Suggestion:** Define as constant:
```php
const SECONDS_PER_YEAR = 31556952;  // Average, accounting for leap years
```

---

### [HIGH-5] No input validation for JSON body limits
**File:** `public/api/_bootstrap.php:75-83`
**Description:** `json_input()` reads entire request body without size limit. Malicious client could send large payloads.

```php
function json_input(): array
{
    $raw = file_get_contents('php://input');
    // No size check
}
```

**Suggestion:** Add max size check:
```php
$maxSize = 1 * 1024 * 1024; // 1MB
if (strlen($raw) > $maxSize) {
    json_fail('Request body too large.', 413);
}
```

---

## MEDIUM Issues

### [MEDIUM-1] Function duplication: member_payload() exists in auth.php and passkey.php
**Files:** `public/api/auth.php:33-51`, `public/api/passkey.php:14-31`
**Description:** Nearly identical `member_payload()` function duplicated across two files.

**Suggestion:** Move to a shared location like `_common.php` or a `MemberHelper` class.

---

### [MEDIUM-2] No pagination on activity_logs in data.php
**File:** `public/api/data.php:378`
**Description:** Hardcoded 50-row limit without pagination. For high-activity sites, this may be insufficient.

```php
db_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50')
```

**Suggestion:** Add pagination or configurable limit, especially since logs.php already has pagination.

---

### [MEDIUM-3] Inconsistent use of prepared statements
**File:** `public/api/org.php:174-177`
**Description:** `require_permission()` is called after `$STAFF_ACTIONS` check but before the switch statement. The permission check pattern varies.

```php
$STAFF_ACTIONS = ['saveMember', 'deleteMember', ...];
$me   = in_array($action, $STAFF_ACTIONS, true)
    ? require_permission('staff', 'edit')
    : require_permission('org', 'edit');
```

**Suggestion:** This is actually fine for action-based routing, but consider using a service layer for clearer permission mapping.

---

### [MEDIUM-4] Missing type hints in OrgService and StaffService
**Files:** `public/api/OrgService.php`, `public/api/StaffService.php`
**Description:** Service classes use `private array $me`, `private int $yid`, etc. but methods lack return type hints.

**Suggestion:** Add strict typing throughout:
```php
public function saveBlock(): array
```

---

### [MEDIUM-5] Duplicate DB queries in attendance.php scan action
**File:** `public/api/attendance.php:142-157`
**Description:** `scan_class_ids($me)` and `allowed_class_ids($me)` may query the same data twice if called in sequence.

**Suggestion:** Consider caching scope results in a request-scoped context.

---

### [MEDIUM-6] CSRF token generation uses `bin2hex(random_bytes(32))` but no validation
**File:** `public/api/csrf.php:6-11`
**Description:** Token generation is good, but no explicit lifetime/expiry check.

**Suggestion:** Add token creation timestamp to session for optional expiry:
```php
$_SESSION['csrf_token_created'] = time();
```

---

### [MEDIUM-7] No database transaction in notes.php save action
**File:** `public/api/notes.php:78-90`
**Description:** Single UPDATE/INSERT but no transaction wrapper. Minor issue but inconsistent with other endpoints.

**Suggestion:** Wrap in `trong_giao_dich()` for consistency.

---

### [MEDIUM-8] Missing error handling in push.php:push_gui()
**File:** `config/push.php:139-142`
**Description:** `curl_exec()` result is captured but `curl_error()` is checked after `curl_close()`. In PHP, `curl_error()` should be checked before `curl_close()`.

```php
$body = curl_exec($ch);
$ma   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loi  = curl_error($ch);  // Should be called before close
curl_close($ch);
```

**Suggestion:** Move `curl_error()` before `curl_close()`.

---

## LOW Issues

### [LOW-1] Naming inconsistency: snake_case vs camelCase
**Files:** Throughout codebase
**Description:** PHP functions use `snake_case` (e.g., `json_input`, `require_login`) but array keys in API responses use `camelCase` (e.g., `studentId`, `fullName`).

**Suggestion:** This is a frontend API contract issue. Document the convention and ensure consistency.

---

### [LOW-2] Unused variable in export.php:319
**File:** `public/api/export.php:318-319`
**Description:** `$total` variable calculated but never used in the output.

```php
$rate = $total > 0 ? round($present / $total * 100) : 0;
```

**Suggestion:** Include `$total` in the export or remove the unnecessary calculation.

---

### [LOW-3] TODO comment in data.php:368
**File:** `public/api/data.php:368`
**Description:** TODO about pagination is still outstanding.

```php
// TODO: Chuyển sang API riêng có pagination khi có màn xem nhật ký
```

**Suggestion:** Create a tracked issue or implement pagination.

---

### [LOW-4] Missing `@throws` documentation
**Files:** Multiple
**Description:** Functions that can throw exceptions lack `@throws` annotations, making it harder for developers to understand error flows.

**Suggestion:** Add `@throws` tags to function docblocks for all functions that throw.

---

### [LOW-5] Inconsistent cache key format
**File:** `public/api/announcements.php:234-236` vs other files
**Description:** Cache keys use different patterns: `data_' . $yid . '_' . (int) $me['id'] . $p` vs individual key construction.

**Suggestion:** Create a centralized `CacheKey` helper class with constants for all key patterns.

---

### [LOW-6] Hardcoded timezone in _bootstrap.php:11
**File:** `public/api/_bootstrap.php:11`
**Description:** Timezone hardcoded as `'Asia/Ho_Chi_Minh'` - not configurable.

```php
date_default_timezone_set('Asia/Ho_Chi_Minh');
```

**Suggestion:** Make timezone configurable via `app_config('timezone')` with a sensible default.

---

## Positive Findings

1. **Excellent security practices:** CSRF protection, prepared statements, input validation, proper error handling
2. **Good code organization:** Separation of concerns with `_bootstrap.php`, `_common.php`, services
3. **Comprehensive comments:** Well-documented functions and complex logic
4. **Proper use of transactions:** `trong_giao_dich()` wrapper used correctly
5. **Good naming conventions:** Functions are well-named in Vietnamese
6. **Consistent API response format:** `['ok' => true/false, ...]` pattern throughout
7. **Proper file upload handling:** MIME type validation, safe file storage
8. **Good permission system:** Modular, hierarchical, well-documented

---

## Recommendations Summary

| Priority | Action |
|----------|--------|
| CRITICAL | Move all secrets to `config.local.php`, never commit real credentials |
| CRITICAL | Improve whitelist validation in `db_has_table()` / `db_has_column()` |
| CRITICAL | Add logging to `@unlink()` failures instead of suppressing errors |
| HIGH | Add strict return type hints throughout the codebase |
| HIGH | Add JSON body size limits to prevent DoS |
| MEDIUM | Consolidate duplicate `member_payload()` functions |
| MEDIUM | Implement pagination for activity_logs in data.php |
| MEDIUM | Fix curl_error() ordering in push.php |

---

*End of Review*
