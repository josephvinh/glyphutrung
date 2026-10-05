# Prompt cho Session Mới - Sprint 2

---

## Context

### Đã hoàn thành (Sprint 1)
- ✅ S-NEW-1: Double compression fix (`_bootstrap.php`)
- ✅ S1: Cache → `storage/cache/`
- ✅ S2: Secrets removed (`config.php`, `org.php`)
- ✅ DEP-1: SheetJS 0.20.3

**Tiến độ:** 9.5% (4/42)

---

## Nhiệm vụ: Sprint 2 - Security Hardening

Implement 10 security fixes. **Chỉ sửa code, không commit/push.**

### 1. S3 — IDOR data.php classId
**File:** `public/api/data.php:339`

Thêm scope check cho `classId`:
```php
// Tìm dòng có "elseif ($filterClassId !== null)"
// Thêm: kiểm tra classId thuộc allowed_class_ids($me)
// Nếu không thuộc phạm vi → http_response_code(403); exit;
```

### 2. S4 — Xác thực đổi quà
**File:** `public/api/somoc_order.php`

Thêm `tracuu_auth()` cho place/cancel:
```php
// Thêm vào đầu place/cancel:
// require __DIR__ . '/_tracuu.php';
// $auth = tracuu_auth($code, $dob);
// if (!$auth) http_response_code(403);
```

### 3. S5 — XFF spoof rate-limit
**File:** `src/RateLimiter.php:39-46`

Sửa `getClientIp()` - chỉ tin XFF khi proxy tin cậy:
```php
// Đảo điều kiện: chỉ tin XFF khi REMOTE_ADDR là trusted proxy
// VD: $trusted = ['127.0.0.1', '::1'];
```

### 4. S-NEW-2 — Timing attack
**File:** `public/api/auth.php`

Thêm timing equalization:
```php
// Sau dòng "$m = db_one..."
// Thêm: password_verify dummy call khi user không tồn tại
if (!$m) {
    password_verify($pass, '$2y$10$dummy.hash.for.timing.equalization.123456789012345678901234');
    json_fail('...');
}
```

### 5. S6 — XSS bible API
**File:** `views/layout_landing.php:251`

Đổi innerHTML thành textContent:
```php
// Từ: verseArea.innerHTML = verse + ref
// Thành:
verseArea.textContent = verse;
refArea.textContent = ref;
// Hoặc dùng DOMPurify sanitize trước
```

### 6. S7 — Lộ lỗi gốc
**File:** `src/ExceptionHandler.php:42`

Sửa output:
```php
// Từ: echo "\n<!-- Exception: " . $e->getMessage() . " -->";
// Thành:
if ($this->debug) {
    echo "\n<!-- Exception: " . $e->getMessage() . " -->";
} else {
    echo "\n<!-- error -->";
}
```

### 7. S8 — CSRF logout
**File:** `public/api/auth.php` case 'logout'

Thêm CSRF:
```php
// Thêm vào logout case:
// require_csrf();
```

### 8. S9 — Temp password entropy
**File:** `public/api/org.php:163`

Sửa entropy:
```php
// Từ: $temp = 'tntt' . random_int(1000, 9999);
// Thành: $temp = bin2hex(random_bytes(4)); // 8 hex = 65536 combos
```

### 9. INF-2 — install.php cleanup
**File:** `public/api/install.php`

Đảm bảo có `setup_key` check:
```php
// Kiểm tra có guard CLI/setup_key không
// Nếu chạy web → kiểm setup_key trong config
```

### 10. S10 — bible_daily retention
**File:** `public/api/bible.php`

Thêm cleanup:
```php
// Thêm vào cuối các action:
// DELETE FROM bible_daily WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
```

---

## Sau khi sửa xong
1. Chạy tests nếu có
2. Review từng file đã sửa

## Tracking
- `docs/audit/tracking/PLAN.md` - cập nhật checkbox
- `docs/audit/tracking/PROGRESS.md` - cập nhật status

---

## Lưu ý
- Không commit/push
- Không sửa file ngoài 10 files trên
- Mỗi fix = 1 mục đích rõ
