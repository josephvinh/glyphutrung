# Performance Review Results - TNTT Backend

**Date:** September 2026
**Reviewer:** Claude Code Performance Analysis
**Scope:** PHP Backend (`public/api/`, `config/`)

---

## Tổng quan

| Mức độ | Số lượng |
|---------|----------|
| CRITICAL | 3 |
| HIGH | 5 |
| MEDIUM | 6 |
| LOW | 4 |

---

## CRITICAL Issues

### [CRITICAL-1] data.php - Tải TOÀN BỘ attendance cho cả năm vào memory

**File:** `public/api/data.php:176-197`

```php
if ($attScopeIds === null) {
    $attRows = db_all(
        'SELECT a.*, m.full_name AS marked_by_name
           FROM attendances a
           LEFT JOIN members m ON m.id = a.marked_by
          WHERE a.year_id = ?', [$yid]);
}
```

**Impact:** Với 500 em x 50 buổi x 2 trạng thái = 50,000+ records được load vào memory mỗi lần ai đó gọi `data.php?part=all`. Không có LIMIT, không có pagination.

**Suggestion:**
- Thêm pagination cho attendance (ví dụ: chỉ load 30 ngày gần nhất)
- Hoặc tách attendance thành API riêng có streaming/pagination
- Hoặc dùng cursor-based pagination với `WHERE session_date > ?`

---

### [CRITICAL-2] data.php - Cache key không hợp lệ khi dữ liệu thay đổi

**File:** `public/api/data.php:32-35`

```php
$cacheKey = "data_{$yid}_{$me['id']}_{$part}";
if ($cached = Cache::get($cacheKey)) {
    json_out($cached);
}
```

**Impact:** Cache chỉ key theo user_id và part, KHÔNG key theo thời điểm dữ liệu thay đổi. Khi:
- Thêm/sửa/xóa student
- Thêm attendance
- Thêm announcement
- Thay đổi enrollment

→ Cache vẫn trả dữ liệu cũ cho đến khi TTL (60s) hết.

**Suggestion:**
- Sử dụng cache versioning: khi data thay đổi, tăng version number
- Hoặc dùng cache key có timestamp: `data_{$yid}_{$part}_{$version}`
- Hoặc invalidate cache per-entity thay vì flush toàn bộ

---

### [CRITICAL-3] Cache::flush() xóa TOÀN BỘ cache khi BẤT KỲ write nào

**Files:** `announcements.php:161`, `attendance.php:195`, `auth.php:194`, `students.php:174`, `programs.php:124`, `org.php:135,147,281`, etc.

```php
Cache::flush();
```

**Impact:**
1. Khi user A save student, user B và user C đang có cache cũ → cache miss ngay
2. Disk I/O nhiều: xóa hàng trăm file cache cùng lúc
3. "Thundering herd problem": nhiều user cùng ghi → tất cả cache bị xóa → tất cả phải query lại DB

**Suggestion:**
- Thay `flush()` bằng `del($specificKey)` khi biết key cần xóa
- Hoặc dùng cache tags để xóa theo nhóm (students, attendance, etc.)
- Hoặc implement cache versioning thay vì xóa file

---

## HIGH Issues

### [HIGH-1] export.php - Load TẤT CẢ attendance vào memory để export

**File:** `public/api/export.php:269-275`

```php
function build_attendance_csv(array $students, array $sessions, array $year): string
{
    $attendance = db_all(
        "SELECT student_id, program_id, session_date, status FROM attendances
         WHERE year_id = ?",
        [$year['id']]);
    // ...
}
```

**Impact:** Export attendance sheet cho cả năm = load 50,000+ rows vào memory. Memory spike, potential OOM.

**Suggestion:**
- Dùng cursor/streaming: `SELECT ... WHERE id > ? ORDER BY id LIMIT 1000`
- Hoặc dùng chunked processing
- Hoặc export trực tiếp ra file (fputcsv) thay vì build string trong memory

---

### [HIGH-2] data.php - Load ALL students cho mọi request, không phân biệt permission

**File:** `public/api/data.php:40-82`

```php
return db_all(
    "SELECT s.*, e.status, c.name AS class_name, b.name AS block_name
       FROM enrollments e
       JOIN students s ON s.id = e.student_id
       JOIN classes  c ON c.id = e.class_id
       JOIN blocks   b ON b.id = c.block_id
      WHERE e.year_id = ?{$dk}
      ORDER BY s.code", $tham);
```

**Impact:** Query `SELECT s.*` lấy TẤT CẢ cột của students (FULL_NAME, FATHER_PHONE, MOTHER_PHONE, ADDRESS...). Dữ liệu nhạy cảm + bandwidth waste.

**Suggestion:**
- Chỉ SELECT các cột cần thiết: `SELECT s.id, s.code, s.full_name, ...`
- Thêm pagination cho danh sách học sinh
- Hoặc tách endpoint riêng cho attendance lookup (chỉ cần code + name + class)

---

### [HIGH-3] students.php import - Load TẤT CẢ students vào memory

**File:** `public/api/students.php:207-216`

```php
foreach (db_all(
    "SELECT s.code, s.id, e.class_id FROM students s
       LEFT JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?",
    [$yid]
) as $r) {
```

**Impact:** LEFT JOIN lấy tất cả students (có thể hàng nghìn) chỉ để kiểm tra trùng mã. Nếu chỉ cần đếm hoặc lookup thì không cần load tất cả.

**Suggestion:**
- Nếu chỉ cần kiểm tra mã tồn tại: `SELECT code FROM students WHERE code IN (...)`
- Hoặc dùng temporary table / INSERT IGNORE thay vì pre-check
- Hoặc batch check: mỗi batch 500 codes

---

### [HIGH-4] Permission checks - N+1 query tiềm ẩn

**Files:** `_common.php:202-206`, `_bootstrap.php:331-356`

```php
function can_access_class(array $me, string $moduleKey, int $classId, string $need = 'view'): bool
{
    $needRank = level_rank($need);
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        if (assignment_covers_class($a, $classId)) return true;
    }
    return false;
}
```

**Impact:** Mỗi lần gọi `can_access_class()`:
1. Gọi `member_scopes()` → query member_assignments
2. Gọi `permission_of_role()` → query permissions

Nếu trong một request có nhiều permission checks (attendance, scores, reports...), đây là N+1.

**Suggestion:**
- Cache kết quả permission trong request (static variable)
- Hoặc pre-load tất cả permissions + assignments một lần vào request-scoped cache

---

### [HIGH-5] data.php - Class counts query không có index optimal

**File:** `public/api/data.php:93-101`

```php
$classCounts = [];
foreach (db_all(
    "SELECT c.name, COUNT(*) AS n
       FROM enrollments e
       JOIN classes c ON c.id = e.class_id
      WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'
      GROUP BY c.id", [$yid]) as $r) {
```

**Impact:** Query này OK nhưng có thể nhanh hơn với composite index `(year_id, status, class_id)`.

**Suggestion:** Index đã có `idx_enr_year_status` nhưng thiếu class_id trong index.

---

## MEDIUM Issues

### [MEDIUM-1] Cache implementation - O(n) disk operations trong flush()

**File:** `public/api/cache.php:41-48`

```php
public static function flush(): void {
    foreach (glob(self::$dir . '/*.json') as $file) {
        unlink($file);
    }
    // ...
}
```

**Impact:** Với 1000+ cached files, `glob()` + `unlink()` = nhiều disk I/O operations.

**Suggestion:**
- Dùng `glob()` một lần rồi `unlink()` trong batch
- Hoặc xóa parent directory và recreate
- Hoặc dùng database-backed cache với single DELETE query

---

### [MEDIUM-2] attendance.php - Lookup action không có cache

**File:** `public/api/attendance.php:80-109`

```php
if (($_GET['action'] ?? '') === 'lookup') {
    $ids = scan_class_ids($me);
    // ... query không cache
}
```

**Impact:** Lookup được gọi thường xuyên (mỗi lần quét QR) nhưng không cache kết quả.

**Suggestion:**
- Cache lookup với TTL ngắn (30s)
- Hoặc cache theo class changes

---

### [MEDIUM-3] _common.php - effective_assignments() không cache

**File:** `public/api/_common.php:88-101`

```php
function effective_assignments(int $memberId): array
{
    return db_all(
        "SELECT a.*, r.label AS role_label, ...
           FROM member_assignments a ...
          WHERE a.member_id = ? AND a.to_date IS NULL ...",
        [$memberId]
    );
}
```

**Impact:** Được gọi NHIỀU lần trong mỗi request (permission checks, scope resolution, etc.). Mỗi lần = 1 DB query.

**Suggestion:**
- Cache kết quả trong request với static variable
- Hoặc dùng request-scoped container/cache

---

### [MEDIUM-4] reports.php - Multiple SELECT queries không batch

**File:** `public/api/reports.php:27-33`

```php
if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) { ... }
$st = db_one('SELECT s.full_name, e.class_id FROM enrollments ...'); 
if (!can_access_class(...)) { ... }
```

**Impact:** 3 sequential SELECT queries có thể gộp thành 1 JOIN query.

**Suggestion:**
```sql
SELECT t.id, s.full_name, e.class_id 
FROM terms t
JOIN enrollments e ON ...
WHERE t.id = ? AND t.year_id = ? AND e.student_id = ?
```

---

### [MEDIUM-5] export.php scores - Query không có index optimal

**File:** `public/api/export.php:170`

```php
$scores = db_all('SELECT * FROM scores WHERE term_id = ?', [$termId]);
```

**Impact:** Bảng scores có `uq_score (term_id, student_id, type_code)` nhưng không có index chỉ `(term_id)`. EXPLAIN sẽ show range scan thay vì index-only.

**Suggestion:**
- Index `(term_id, student_id)` cho query này
- Hoặc dùng `SELECT student_id, type_code, value` thay vì `SELECT *`

---

### [MEDIUM-6] library.php - Pagination nhưng count query riêng

**File:** `public/api/library.php:42-53`

```php
$total = library_count($where, $params);
$rows  = db_all(
    'SELECT i.*, c.name AS category_name, m.full_name AS uploader_name ...');
```

**Impact:** 2 queries cho mỗi page load (COUNT + SELECT). Có thể dùng SQL_CALC_FOUND_ROWS hoặc fetch thêm 1 row để check hasMore.

**Suggestion:**
- Dùng `SELECT COUNT(*) OVER()` window function (MySQL 8+)
- Hoặc query thêm 1 row để check `hasMore = count($rows) > $limit`

---

## LOW Issues

### [LOW-1] No database connection pooling

**File:** `config/db.php`

**Impact:** Mỗi request tạo connection mới. Với high traffic, connection overhead đáng kể.

**Suggestion:**
- Dùng persistent connections (`PDO::ATTR_PERSISTENT`)
- Hoặc connection pool (proxySQL, PgBouncer for MySQL)

---

### [LOW-2] No response compression beyond gzip

**File:** `public/api/_bootstrap.php:28-49`

**Impact:** Brotli compression có thể giảm ~20% bandwidth nhưng chỉ active nếu server có mod_brotli.

**Suggestion:**
- Đã có code cho brotli nhưng nên verify server config
- Consider HTTP/2 server push

---

### [LOW-3] activity_logs không có pagination trong data.php

**File:** `public/api/data.php:367-378`

```php
$logs = array_map(fn($l) => [...],
    db_all('SELECT * FROM activity_logs ORDER BY id DESC LIMIT 50'));
```

**Impact:** Luôn load 50 logs dù user không cần. Có thể có logs.php riêng đã có pagination, nhưng data.php vẫn load 50.

**Suggestion:**
- Bỏ logs khỏi data.php response
- Hoặc chỉ load logs khi có `?include_logs=1`

---

### [LOW-4] No ETag/Last-Modified headers cho cache validation

**File:** `public/api/data.php`

**Impact:** Client không thể validate cache mà phải rely vào TTL.

**Suggestion:**
- Thêm `ETag` header dựa trên data version
- Hoặc `Last-Modified` header
- Client có thể gửi `If-None-Match` / `If-Modified-Since`

---

## Recommendations Summary

### Ưu tiên cao (Fix trong 1-2 sprint):
1. **Cache versioning** thay vì flush() toàn bộ
2. **Pagination cho attendance** trong data.php
3. **Cache permission results** trong request scope
4. **Chỉ SELECT cần thiết** thay vì `SELECT *`

### Ưu tiên trung bình (Fix trong 2-4 sprint):
5. **Streaming export** thay vì load all vào memory
6. **Index optimization** cho các query phổ biến
7. **Request-scoped cache** cho assignments và permissions

### Ưu tiên thấp (Technical debt):
8. Connection pooling
9. ETag/Last-Modified headers
10. Database-backed cache thay vì file-based

---

## Database Index Analysis

| Table | Query Pattern | Current Index | Suggestion |
|-------|--------------|---------------|------------|
| attendances | `year_id` only | `(year_id, session_date)` | Thêm `(year_id, program_id, session_date)` |
| students | `code` lookup | `UNIQUE(code)` | OK |
| members | `phone` login | `idx_member_phone` | OK |
| enrollments | `year_id, status` | `idx_enr_year_status` | Thêm class_id vào |
| scores | `term_id` | `uq_score (term_id, student_id, type_code)` | OK |

---

**Generated by:** Claude Code Performance Review
