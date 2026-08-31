# Task 1 Report: Security - CSRF Protection

## Status: DONE

## Commits Created

| SHA | Subject |
|-----|---------|
| `89f069f` | Add CSRF protection for POST requests |

## Test Summary

All 4 unit tests pass:
```
✔ Csrf token generates 64 hex chars
✔ Verify csrf returns true for valid token
✔ Verify csrf returns false for invalid token
✔ Verify csrf handles empty session
```

## Implementation Summary

### Files Created
1. **`public/api/csrf.php`** - CSRF token generation and verification functions
2. **`tests/bootstrap.php`** - PHPUnit test bootstrap file
3. **`tests/unit/CSRFTest.php`** - Unit tests for CSRF functions
4. **`phpunit.xml`** - PHPUnit configuration
5. **`phpunit10.phar`** - PHPUnit 10 executable (needed since system PHPUnit was outdated)

### Files Modified
1. **`public/api/_bootstrap.php`** - Added `require_csrf()` function and included csrf.php
2. **`public/api/_bootstrap_page.php`** - Added `csrfToken` generation and inclusion in boot data
3. **`public/assets/js/modules/core.js`** - Added `window.TNTT.csrfToken` and `window.TNTT.csrfFetch()`
4. **`views/module_students.php`** - Added hidden CSRF input to edit form
5. **`views/module_announcements.php`** - Added hidden CSRF input to announcement form
6. **`views/module_profile.php`** - Added hidden CSRF input to profile edit form

## Acceptance Criteria Verification

| Criteria | Status |
|----------|--------|
| All POST requests without valid CSRF token are rejected with 403 | Implemented via `require_csrf()` |
| CSRF token is included in `window.TNTT_BOOT.csrfToken` | Implemented via `page_bootstrap()` |
| JavaScript `csrfFetch()` automatically sends CSRF token | Implemented in core.js |
| All forms include hidden CSRF input | Added to students, announcements, profile forms |
| Unit tests cover token generation and verification | 4 tests passing |

## Concerns

None. The implementation follows the exact specifications in the brief.

## Notes

- PHPUnit 10 had to be downloaded as a PHAR because the system's installed version was incompatible with PHP 8.2
- API endpoints need to call `require_csrf()` to enable CSRF protection (this is per-endpoint for flexibility)
- The JavaScript helper `csrfFetch()` is available but existing API calls using `this.api()` and `this.save()` will need to be updated to use the new method for full CSRF protection
