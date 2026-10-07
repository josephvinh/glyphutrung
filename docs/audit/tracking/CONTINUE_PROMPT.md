# Claude Code Session - Continue TNTT Audit

## Context
Đang làm việc trên repo GĐGL Phú Trung (PHP + Alpine.js). Không commit/push - chỉ sửa file.

## Đã hoàn thành: 36/44 items (81.8%)

### Sprint 1-5 Security & Code Quality ✅
- S1-S12, S-NEW-1, S-NEW-2: Security fixes
- CQ-1, CQ-4-CQ-9, CQ-NEW-1, CQ-NEW-2: Code quality
- PR-1-PR-3, PR-5, PR-6: Privacy
- DES-1-DES-3, A11Y-1: Design/A11y
- PERF-1, PERF-2, PERF-4: Performance
- DATA-2: Data integrity

### Key Files Changed
```
public/api/_bootstrap.php     - S-NEW-2, PERF-2 memoization
public/api/data.php          - S3 IDOR, S12 cache, PR-2 filter
public/api/auth.php          - S-NEW-2, S8 CSRF
public/api/somoc_order.php  - S4 tracuu_auth
public/api/bible.php        - S10 retention, PR-6 hash IP
public/api/announcements.php - INSERT IGNORE catch
src/RateLimiter.php         - S5 XFF spoof
src/ExceptionHandler.php     - S7 exception leak
public/bxh.php              - PR-5 tên rút gọn
public/index.php             - DES-1 theme-color
config/cron_cleanup.php      - PR-3, PR-2 student retention
config/migrations/007_*.sql  - PR-2 student retention
public/api/migrate-passkeys.php - DELETED
src/Router.php               - DELETED
tests/e2e/check_module_merge.js - NEW (CQ-1)
```

## Còn lại: 8 items

### 1. CQ-2: Đồng bộ 3 nguồn schema
**Files:** `config/schema.sql`, `config/migrations/*.sql`, `config/install.php`
**Cần:** Chạy DB để verify, so sánh 3 nguồn

### 2. CQ-3: Tách file quá dài (refactor lớn)
**Files:** `data.php` (625 lines), `core.js` (1084), `students.js` (1002)
**Cần:** Tái cấu trúc - có thể tách thành module nhỏ hơn

### 3. PR-4: Tối thiểu payload data.php
**Vấn đề:** Gửi `fatherPhone`, `motherPhone` cho mọi request
**Cần:**
1. Thêm param `?includeContacts=1` vào data.php
2. Frontend thay đổi cách gọi - chỉ request khi cần

### 4. PERF-3: Đo payload data.php
**Cần:** Server để curl và đo kích thước response

### 5. DATA-1: Verify schema sync
**Cần:** Chạy DB queries so sánh schema

### 6. DATA-3: Đối soát ví Mộc
**SQL:**
```sql
SELECT ss.student_id, ss.current_balance, ss.held_balance,
       (SELECT COALESCE(SUM(amount),0) FROM stamp_transactions st
         WHERE st.student_id=ss.student_id AND st.year_id=ss.year_id) AS tong_gd
  FROM student_stamps ss
 HAVING ss.current_balance < 0 OR ss.held_balance < 0
     OR ss.current_balance <> tong_gd;
```

### 7. DATA-4: Orphan check
**SQL:**
```sql
SELECT COUNT(*) FROM enrollments e LEFT JOIN students s ON s.id=e.student_id WHERE s.id IS NULL;
SELECT COUNT(*) FROM enrollments e LEFT JOIN classes c ON c.id=e.class_id WHERE c.id IS NULL;
SELECT COUNT(*) FROM members m LEFT JOIN roles r ON r.code=m.role_code WHERE r.code IS NULL;
```

### 8. DEP-NEW-1: Verify qrcode.js
**File:** `public/assets/js/vendor/qrcode.min.js`

## Để tiếp tục

1. **Chạy app:** `php -S 127.0.0.1:8080 -t public`
2. **Setup DB test:** `php config/install.php && php tests/fixtures/ci_seed.php`
3. **Run tests:** `composer install && php vendor/bin/phpunit --testsuite "TNTT Unit Tests"`

## Lệnh hữu ích
```bash
# Check syntax
php -l file.php

# Xem thay đổi
git diff --stat

# Chạy migration
mysql -u tntt -p tntt_test < config/migrations/007_student_retention.sql

# Chạy cron cleanup
php config/cron_cleanup.php
```

## Tracking Files
- `docs/audit/tracking/PROGRESS.md` - Progress hiện tại
- `docs/audit/tracking/PLAN.md` - Chi tiết từng item

## Lưu ý
- KHÔNG commit/push - chỉ sửa local
- Kiểm tra syntax trước khi chuyển session
- Items cần DB phải setup test DB trước
