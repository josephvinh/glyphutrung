# Sprint Context - Audit Implementation

## Đã hoàn thành (Sprint 1)

✅ S-NEW-1: Double compression fix (`_bootstrap.php`)
✅ S1: Cache → `storage/cache/`
✅ S2: Secrets removed (`config.php`, `org.php`)
✅ DEP-1: SheetJS 0.20.3

**Tiến độ:** 9.5% (4/42)

## Cần làm tiếp

### Sprint 2: Security (10 items)
- S3: IDOR data.php classId
- S4: Xác thực đổi quà (thêm tracuu_auth)
- S5: XFF spoof rate-limit
- S-NEW-2: Timing attack (auth.php)
- S6: XSS bible API (layout_landing.php)
- S7: Lộ lỗi gốc (ExceptionHandler.php)
- S8: CSRF logout
- S9: Temp password entropy
- INF-2: install.php cleanup
- S10: bible_daily retention

### Sprint 3: Code Quality (11 items)
- CQ-1 đến CQ-9, CQ-NEW-1, CQ-NEW-2

## Files đã sửa
- `public/api/_bootstrap.php` (compression)
- `public/api/cache.php` (path)
- `public/.htaccess` (+ .json)
- `public/cache/.htaccess` (deny all)
- `config/config.php` (secrets removed)
- `public/api/org.php` (password entropy)

## Tracking
- `docs/audit/tracking/PLAN.md`
- `docs/audit/tracking/PROGRESS.md`

## Báo cáo gốc
- `docs/audit/SECURITY_AUDIT_2026-05.md`
- `docs/audit/CODE_QUALITY_AUDIT_2026-05.md`
- `docs/audit/PRIVACY_AUDIT_2026-05.md`
- etc. trong `docs/audit/`
