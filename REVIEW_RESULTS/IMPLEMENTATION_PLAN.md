# KẾ HOẠCH TRIỂN KHAI CẢI THIỆN TNTT VOLUTE

**Ngày tạo:** Tháng 9/2026  
**Phạm vi:** Toàn bộ codebase (778 files: PHP + JS)  
**Nguồn:** Tổng hợp từ 4 agents review (Security, Code Quality, Performance, Frontend)

---

## TÓNG QUAN FINDINGS

| Category | CRITICAL | HIGH | MEDIUM | LOW |
|----------|----------|------|--------|-----|
| **Security** | 0 | 2 | 4 | 5 |
| **Code Quality** | 3 | 5 | 8 | 6 |
| **Performance** | 3 | 5 | 6 | 4 |
| **Frontend** | 0 | 2 | 5 | 6 |
| **TỔNG** | **6** | **14** | **23** | **21** |

---

## GIAI ĐOẠN 1: BẢO MẬT KHẨN CẤP (Tuần 1-2) ✅ ĐÃ HOÀN THÀNH

### 1.1 Rate Limiting cho Passkey Login ✅
**File:** `public/api/passkey.php`
**Độ ưu tiên:** 🔴 CRITICAL
**Effort:** Low (1-2 giờ)
**Status:** DONE

```php
// Thêm vào processLogin case
case 'processLogin':
    require_post();
    $credentialId = $in['id'];
    login_throttle_by_credential($credentialId); // Cần tạo hàm mới
    // ... existing code
```

### 1.2 Thêm Rate Limiting cho Registration
**File:** `public/api/auth.php`
**Độ ưu tiên:** 🔴 CRITICAL
**Effort:** Low (1 giờ)

```php
function register_throttle(): void {
    $ip = client_ip();
    $recent = db_one('SELECT COUNT(*) n FROM members 
                     WHERE registered_at > ? AND ip = ?',
                     [date('Y-m-d H:i:s', time() - 3600), $ip]);
    if ($recent['n'] >= 3) {
        json_fail('Bạn đã đăng ký quá nhiều lần trong giờ qua.', 429);
    }
}
```

### 1.3 Move Secrets ra khỏi Git
**Files:** `config/config.example.php`
**Độ ưu tiên:** 🔴 CRITICAL
**Effort:** Low (30 phút)

- Xóa `setup_key` và VAPID keys thực từ git history
- Thêm `.gitignore` entry cho `config.local.php`
- Thêm pre-commit hook kiểm tra secrets

### 1.4 Thêm JSON Body Size Limit
**File:** `public/api/_bootstrap.php`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Low (15 phút)

```php
function json_input(): array {
    $raw = file_get_contents('php://input');
    $maxSize = 1 * 1024 * 1024; // 1MB
    if (strlen($raw) > $maxSize) {
        json_fail('Request body too large.', 413);
    }
    // ...
}
```

---

## GIAI ĐOẠN 2: PERFORMANCE CRITICAL (Tuần 2-4)

### 2.1 Pagination cho Attendance trong data.php
**File:** `public/api/data.php:176-197`
**Độ ưu tiên:** 🔴 CRITICAL
**Effort:** Medium (4-6 giờ)

**Vấn đề:** Load 50,000+ rows vào memory

**Giải pháp:**
```php
// Thay vì load tất cả
$attRows = db_all('SELECT * FROM attendances WHERE year_id = ?', [$yid]);

// Chỉ load 30 ngày gần nhất
$attRows = db_all(
    'SELECT a.*, m.full_name AS marked_by_name
       FROM attendances a
       LEFT JOIN members m ON m.id = a.marked_by
      WHERE a.year_id = ?
        AND a.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
      ORDER BY a.session_date DESC',
    [$yid]
);
```

### 2.2 Cache Invalidation Strategy
**Files:** Nhiều file (`announcements.php`, `attendance.php`, etc.)
**Độ ưu tiên:** 🔴 CRITICAL
**Effort:** Medium (6-8 giờ)

**Vấn đề:** `Cache::flush()` xóa toàn bộ cache

**Giải pháp:**
```php
// Thay vì Cache::flush()
// Dùng Cache::del($specificKey) hoặc cache tags

// Trong announcements.php, thay:
Cache::flush();
// Bằng:
Cache::del("announcements_{$yid}");
Cache::del("data_{$yid}_{$me['id']}_*"); // Pattern delete
```

### 2.3 Streaming Export thay vì Memory Load
**File:** `public/api/export.php:269-275`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Medium (4-6 giờ)

```php
// Thay vì load all vào memory
$attendance = db_all("SELECT * FROM attendances WHERE year_id = ?", [$year['id']]);

// Dùng cursor-based streaming
function build_attendance_csv_streaming(int $yearId, $outputFile) {
    $handle = fopen($outputFile, 'w');
    $lastId = 0;
    $batchSize = 1000;
    
    while ($batch = db_all(
        "SELECT * FROM attendances WHERE year_id = ? AND id > ? 
         ORDER BY id LIMIT ?",
        [$yearId, $lastId, $batchSize]
    )) {
        foreach ($batch as $row) {
            fputcsv($handle, [...]);
            $lastId = $row['id'];
        }
    }
    fclose($handle);
}
```

### 2.4 N+1 Permission Query Fix
**File:** `public/api/_common.php`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Medium (3-4 giờ)

```php
// Thêm static cache trong request
function can_access_class(array $me, string $moduleKey, int $classId, string $need = 'view'): bool {
    static $cachedScopes = null;
    static $cachedPermissions = [];
    
    if ($cachedScopes === null) {
        $cachedScopes = member_scopes($me);
        // Pre-load permissions
        foreach ($cachedScopes as $s) {
            $perm = permission_of_role($s['role_code'], $moduleKey);
            $cachedPermissions[$s['role_code']] = $perm;
        }
    }
    // Sử dụng cached data
}
```

### 2.5 SELECT * → Chỉ lấy cột cần thiết
**Files:** `public/api/data.php:40-82`, `public/api/export.php`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Low (2-3 giờ)

```php
// Thay:
return db_all("SELECT s.*, e.status, c.name AS class_name...");

// Bằng:
return db_all(
    "SELECT s.id, s.code, s.full_name, s.birth_date,
            e.status, c.name AS class_name, b.name AS block_name..."
);
```

---

## GIAI ĐOẠN 3: CODE QUALITY (Tuần 4-8)

### 3.1 Fix @unlink() Error Suppression
**File:** `public/api/library.php:168,224,238`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Low (15 phút)

```php
// Thay:
@unlink(library_storage_dir() . '/' . $oldStored);

// Bằng:
$deleted = @unlink(library_storage_dir() . '/' . $oldStored);
if (!$deleted) {
    error_log("Failed to delete library file: " . library_storage_dir() . '/' . $oldStored);
}
```

### 3.2 Add Type Hints to Functions
**Files:** Nhiều file
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Medium (8-10 giờ)

```php
// Trước:
function library_config(string $key)

// Sau:
function library_config(string $key): mixed
```

### 3.3 Consolidate Duplicate member_payload()
**Files:** `public/api/auth.php:33-51`, `public/api/passkey.php:14-31`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (30 phút)

```php
// Di chuyển vào _common.php
function member_payload(array $member): array {
    // Code từ auth.php
}
```

### 3.4 Fix curl_error() Ordering
**File:** `config/push.php:139-142`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (5 phút)

```php
// Thay:
$body = curl_exec($ch);
$ma   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$loi  = curl_error($ch);  // Sai: gọi sau curl_close
curl_close($ch);

// Bằng:
$body = curl_exec($ch);
$loi  = curl_error($ch);  // Đúng: gọi TRƯỚC curl_close
$ma   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
```

### 3.5 Add Transactions cho notes.php
**File:** `public/api/notes.php:78-90`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (15 phút)

```php
trong_giao_dich(function() use ($in, $me) {
    // notes save logic
});
```

---

## GIAI ĐOẠN 4: FRONTEND IMPROVEMENTS (Tuần 6-10)

### 4.1 CSRF Token Rotation
**File:** `public/assets/js/modules/core.js`
**Độ ưu tiên:** 🟠 HIGH
**Effort:** Medium (3-4 giờ)

```javascript
// Thêm token rotation
window.TNTT.rotateCsrfToken = async () => {
    const res = await fetch('api/csrf.php?action=refresh', { 
        method: 'POST',
        credentials: 'include'
    });
    const data = await res.json();
    window.TNTT.csrfToken = data.token;
};
```

### 4.2 Add Request Timeouts
**File:** `public/assets/js/modules/core.js`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (1 giờ)

```javascript
async api(file, action, body, timeoutMs = 30000) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), timeoutMs);
    
    try {
        const res = await fetch('api/' + file + '.php?action=' + action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.TNTT.csrfToken },
            body: JSON.stringify(body || {}),
            signal: controller.signal
        });
        clearTimeout(timeout);
        // ...
    } catch (e) {
        clearTimeout(timeout);
        if (e.name === 'AbortError') {
            return { ok: false, error: 'Yêu cầu hết thời gian. Vui lòng thử lại.' };
        }
        throw e;
    }
}
```

### 4.3 Error Logging Enhancement
**Files:** `public/assets/js/modules/*.js`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (2 giờ)

```javascript
// Thay:
} catch (e) {
    // Im lặng
}

// Bằng:
} catch (e) {
    console.warn('[TNTT] loadHeavy failed:', e);
    // Hoặc gửi lên error tracking service
}
```

### 4.4 Service Worker Retry Logic
**File:** `public/sw.js`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Medium (3 giờ)

---

## GIAI ĐOẠN 5: DATABASE OPTIMIZATION (Tuần 8-12)

### 5.1 Add Missing Indexes
**File:** `config/schema.sql`
**Độ ưu tiên:** 🟡 MEDIUM
**Effort:** Low (migration)

```sql
-- Thêm index cho attendance lookup
ALTER TABLE attendances 
ADD INDEX idx_att_year_program_date (year_id, program_id, session_date);

-- Thêm index cho enrollment
ALTER TABLE enrollments 
ADD INDEX idx_enr_year_status_class (year_id, status, class_id);
```

### 5.2 ETag/Last-Modified Headers
**File:** `public/api/data.php`
**Độ ưu tiên:** 🟢 LOW
**Effort:** Low (2 giờ)

---

## TIMELINE TỔNG HỢP

```
Tuần 1-2:  Security Critical
├── 1.1 Rate limiting passkey (2h)
├── 1.2 Rate limiting registration (1h)
├── 1.3 Move secrets out of git (30m)
└── 1.4 JSON body size limit (15m)

Tuần 2-4:  Performance Critical  
├── 2.1 Pagination attendance (6h)
├── 2.2 Cache invalidation (8h)
├── 2.3 Streaming export (6h)
└── 2.4 N+1 query fix (4h)

Tuần 4-8:  Code Quality
├── 3.1 @unlink fix (15m)
├── 3.2 Type hints (10h)
├── 3.3 Consolidate duplicate (30m)
├── 3.4 curl ordering (5m)
└── 3.5 Add transactions (15m)

Tuần 6-10: Frontend
├── 4.1 CSRF rotation (4h)
├── 4.2 Request timeouts (1h)
├── 4.3 Error logging (2h)
└── 4.4 SW retry logic (3h)

Tuần 8-12: Database
├── 5.1 Add indexes (2h)
└── 5.2 ETag headers (2h)
```

**Tổng effort ước tính:** ~50 giờ (~2 tuần dev full-time)

---

## CHECKLIST TRIỂN KHAI

### Trước khi deploy:
- [ ] Viết unit tests cho functions mới
- [ ] Test rate limiting hoạt động đúng
- [ ] Performance testing với data thực
- [ ] Security audit sau khi fix

### Monitoring sau deploy:
- [ ] Theo dõi cache hit rate
- [ ] Theo dõi memory usage export
- [ ] Theo dõi error rates
- [ ] User feedback về performance

---

## FILES ĐÃ REVIEW

| File | CRITICAL | HIGH | MEDIUM | LOW |
|------|----------|------|--------|-----|
| `public/api/data.php` | 2 | 2 | 2 | 1 |
| `public/api/cache.php` | 1 | 0 | 0 | 0 |
| `public/api/passkey.php` | 0 | 2 | 0 | 0 |
| `public/api/auth.php` | 0 | 1 | 2 | 1 |
| `public/api/attendance.php` | 0 | 0 | 2 | 0 |
| `public/api/export.php` | 0 | 2 | 1 | 0 |
| `public/api/library.php` | 0 | 1 | 0 | 0 |
| `public/api/_common.php` | 0 | 1 | 1 | 0 |
| `config/push.php` | 0 | 0 | 1 | 0 |
| `config/password.php` | 0 | 1 | 0 | 0 |
| Frontend JS | 0 | 2 | 5 | 6 |

---

*Báo cáo được tạo tự động bởi Claude Code*
*Date: 2026-09-02*
