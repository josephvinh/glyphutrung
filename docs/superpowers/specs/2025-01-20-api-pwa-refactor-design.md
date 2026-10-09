# API + PWA Refactor Design Spec

> **Ngày:** 2025-01-20
> **Approach:** Big Bang (breaking change)
> **Team:** 1 người
> **Status:** Draft

---

## 1. Overview

### Mục tiêu
- Performance: App load nhanh, hoạt động tốt khi mạng yếu
- Reliability: Dữ liệu không mất khi offline, sync đáng tin cậy
- Maintainability: Code dễ đọc, dễ debug, dễ mở rộng

### Phạm vi
- [x] API Response Format chuẩn hóa
- [x] Error Code Registry (constants thay string literals)
- [x] API Validation Layer (centralized input sanitization)
- [x] PWA Service Worker refactor
- [x] PWA Cache Strategy (cache API responses, background sync)
- [x] Offline Data Queue (queue writes khi offline, sync sau)

### Breaking Changes
- API response format thay đổi → Client (Alpine.js) cần update
- Old service worker không tương thích → Force update

---

## 2. API Layer

### 2.1 Response Format

**Success Response:**
```json
{
  "ok": true,
  "data": { ... },
  "meta": {
    "timestamp": 1705766400,
    "requestId": "req_abc123"
  }
}
```

**Error Response:**
```json
{
  "ok": false,
  "error": {
    "code": "AUTH_REQUIRED",
    "message": "Vui lòng đăng nhập",
    "details": {}
  },
  "meta": {
    "timestamp": 1705766400,
    "requestId": "req_abc123"
  }
}
```

**Pagination Response:**
```json
{
  "ok": true,
  "data": [ ... ],
  "meta": {
    "timestamp": 1705766400,
    "requestId": "req_abc123",
    "pagination": {
      "page": 1,
      "perPage": 20,
      "total": 150,
      "totalPages": 8
    }
  }
}
```

### 2.2 Error Code Registry

Tạo file `public/api/_errors.php`:

```php
<?php
// API Error Codes
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
define('ERR_SERVICE_UNAVAILABLE',   'SERVICE_UNAVAILABLE');   // 503

// Domain-specific codes
define('ERR_INVALID_SESSION',       'INVALID_SESSION');       // 401
define('ERR_PERMISSION_DENIED',     'PERMISSION_DENIED');    // 403
define('ERR_CLASS_ACCESS_DENIED',   'CLASS_ACCESS_DENIED');  // 403
define('ERR_CSRF_INVALID',          'CSRF_INVALID');         // 403
define('ERR_NOT_FOUND_CLASS',       'CLASS_NOT_FOUND');      // 404
define('ERR_NOT_FOUND_STUDENT',     'STUDENT_NOT_FOUND');    // 404
define('ERR_DUPLICATE_ENTRY',      'DUPLICATE_ENTRY');      // 409
define('ERR_DATA_CONFLICT',         'DATA_CONFLICT');       // 409
```

### 2.3 API Validation Layer

Tạo file `public/api/_validator.php`:

```php
<?php
class Validator {
    private array $errors = [];

    public function required(mixed $value, string $field): self { ... }
    public function integer(mixed $value, string $field): self { ... }
    public function string(mixed $value, string $field): self { ... }
    public function maxLength(string $value, string $field, int $max): self { ... }
    public function inArray(mixed $value, string $field, array $allowed): self { ... }
    public function validate(): bool { ... }
    public function getErrors(): array { ... }
}
```

### 2.4 HTTP Utility Refactor

Update `public/api/_http_util.php`:

```php
<?php
function json_ok(mixed $data, array $meta = [], int $status = 200): void { ... }
function json_created(mixed $data, array $meta = []): void { ... }
function json_fail(string $code, string $message, array $details = [], int $status = 400): void { ... }
function json_validation_fail(array $errors): void { ... }
function json_paginated(array $data, int $page, int $perPage, int $total): void { ... }
```

---

## 3. PWA Service Worker

### 3.1 Architecture

Refactor `public/sw.js` thành modules:

```
public/sw.js                      # Entry point, event listeners
public/assets/js/sw/
├── core/
│   ├── cache-manager.js          # Cache lifecycle
│   ├── network-manager.js        # Fetch interception
│   └── sync-manager.js           # Background sync
├── strategies/
│   ├── cache-first.js            # Static assets
│   ├── network-first.js          # API data (freshness priority)
│   └── stale-while-revalidate.js # Hybrid
└── utils/
    ├── logger.js                 # Debug logging
    └── constants.js              # Cache names, versions
```

### 3.2 Cache Strategy

| Resource Type | Strategy | TTL |
|---------------|----------|-----|
| Static assets (CSS, JS, images) | Cache-first | 7 days |
| API responses (programs, classes) | Stale-while-revalidate | 1 hour |
| API responses (data snapshots) | Network-first with fallback | 5 minutes |
| User-specific data | Network-first, no cache | - |

### 3.3 Cache Versioning

```javascript
const CACHE_CONFIG = {
  STATIC: { name: 'tntt-static-v1', maxAge: 7 * 24 * 60 * 60 },
  API: { name: 'tntt-api-v1', maxAge: 60 * 60 },
  DATA: { name: 'tntt-data-v1', maxAge: 5 * 60 }
};
```

---

## 4. Offline Queue

### 4.1 Queue Structure

```javascript
class OfflineQueue {
  // IndexedDB: 'tntt_offline_queue'
  // Schema: { id, action, endpoint, payload, timestamp, retries, status }
  // Status: 'pending' | 'syncing' | 'failed'

  async enqueue(action, endpoint, payload) { ... }
  async dequeue() { ... }
  async retry() { ... }
  async getPending() { ... }
}
```

### 4.2 Supported Offline Actions

| Action | Endpoint | Payload |
|--------|----------|---------|
| `attendance_mark` | POST /api/attendance | `{ classId, studentId, date, status }` |
| `attendance_bulk` | POST /api/attendance/bulk | `{ classId, records: [...] }` |
| `score_update` | PUT /api/scores/{id}` | `{ score }` |
| `note_update` | PUT /api/notes/{id}` | `{ content }` |

### 4.3 Sync Flow

```
1. User marks attendance offline
   → Save to IndexedDB queue + IndexedDB data snapshot

2. Network available
   → navigator.onLine triggers sync
   → Or: Background Sync API (if supported)
   → Process queue in order (FIFO)

3. Sync success
   → Remove from queue
   → Update local data with server response

4. Sync failure
   → Increment retry count
   → If retries > 3, mark as 'failed', notify user
```

### 4.4 Conflict Resolution

- Server wins for attendance (timestamp-based)
- If local timestamp > server timestamp + 5min, flag for manual review
- Show conflict UI for score/note conflicts

---

## 5. Migration Plan

### 5.1 Database Schema

**Không thay đổi** - giữ nguyên tables hiện tại.

### 5.2 File Changes

| File | Action |
|------|--------|
| `public/api/_http_util.php` | Rewrite |
| `public/api/_errors.php` | Create |
| `public/api/_validator.php` | Create |
| `public/api/_response.php` | Create (helpers) |
| `public/sw.js` | Rewrite |
| `public/assets/js/sw/` | Create (modules) |
| `public/assets/js/modules/core.js` | Update (new API format) |
| `public/assets/js/modules/shell.js` | Update (new sync logic) |
| `public/assets/js/offline-queue.js` | Create |

### 5.3 Client Updates

1. Update all API calls to use new response format
2. Update error handling to use error codes
3. Add offline queue initialization
4. Force service worker update on next load

### 5.4 Rollback Plan

- Tag current state: `git tag snapshot-before-api-pwa-refactor`
- If critical issue: revert tag, rollback client code

---

## 6. Testing Strategy

### 6.1 Unit Tests
- Error codes registry
- Validator class
- Offline queue operations

### 6.2 Integration Tests
- API response format compliance
- Error code coverage
- Offline queue → server sync

### 6.3 E2E Tests
- Offline attendance flow
- Service worker cache hit/miss
- Conflict resolution UI

---

## 7. Implementation Order

1. **Phase 1: API Foundation**
   - Create `_errors.php`
   - Create `_validator.php`
   - Rewrite `_http_util.php`

2. **Phase 2: API Update**
   - Update `_bootstrap.php`
   - Update all endpoints to new format

3. **Phase 3: PWA Core**
   - Create service worker modules
   - Implement cache strategies

4. **Phase 4: Offline Queue**
   - Create IndexedDB schema
   - Implement queue class
   - Add sync logic

5. **Phase 5: Client Integration**
   - Update Alpine.js components
   - Test offline flows

6. **Phase 6: Polish**
   - Error handling UI
   - Sync status indicators
   - Documentation

---

## 8. Open Questions - ĐÃ QUYẾT ĐỊNH

| # | Câu hỏi | Quyết định |
|---|---------|------------|
| 1 | Push notifications có cần refactor không? | **REFACTOR** - Chuẩn hóa payload, thêm error handling |
| 2 | CSRF token handling | **REFACTOR** - Tích hợp vào Validator layer |
| 3 | Rate limiting | **NÂNG CẤP** - Thêm per-endpoint limits, exponential backoff |

### 8.1 Push Notification Refactor

**Current flow:**
```
Push received → fetch /api/push.php?action=pending → show notification
```

**New flow:**
```javascript
// Push payload format (VAPID)
{
  "title": "Thông báo mới",
  "body": "Nội dung...",
  "icon": "/assets/icon.png",
  "tag": "notify_123",
  "data": {
    "type": "announcement", // announcement | attendance | score
    "id": 123,
    "url": "/announcements/123"
  }
}
```

**Service Worker Changes:**
- Handle push with data payload
- Support notification click tracking
- Fallback to fetch if no payload

### 8.2 CSRF Token Refactor

**Current:** Token passed via header/query, checked per-endpoint

**New:** Centralized in `_validator.php`:
```php
class CSRFValidator {
    public static function validate(): void {
        // Check Origin header
        // Check Referer header
        // Validate token from session
    }
}
```

### 8.3 Rate Limiting Upgrade

**Current:** Global limits per read/write

**New:**
```php
$limiter = new RateLimiter();

// Per-user limits
$limiter->perUser('attendance_create', 60, 60);  // 60/min

// Per-endpoint limits
$limiter->perEndpoint('data', 120, 60);          // 120/min

// Exponential backoff for failed attempts
$limiter->backoff($userId, $attempt);
```

---

## 9. Files to Create/Modify

### New Files
```
public/api/_errors.php           # Error code constants
public/api/_validator.php        # Validation + CSRF classes
public/api/_response.php         # Response helpers
public/assets/js/sw/             # Service worker modules (new dir)
public/assets/js/offline-queue.js # Offline queue class
```

### Files to Rewrite
```
public/api/_http_util.php         # HTTP utilities
public/api/_bootstrap.php         # Bootstrap with new helpers
public/sw.js                      # Service worker entry point
```

### Files to Update
```
public/api/*.php                  # All endpoints (new response format)
public/assets/js/modules/core.js  # API calls
public/assets/js/modules/shell.js # Sync logic
public/assets/js/modules/notifications.js # Push handling
config/config.php                 # Rate limit configs
```

---

## 10. Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking client during migration | Test thoroughly with staging environment |
| Offline sync conflicts | Clear conflict resolution UI, server-wins default |
| Service worker cache stale | Aggressive cache invalidation on deploy |
| Rate limiting false positives | Whitelist for critical endpoints |

---

*Status: Ready for Implementation*
*Approved by: [待用户确认]*
