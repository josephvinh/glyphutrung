# Code Review Report: Xuất Báo Cáo Điểm Danh CSV

**Reviewer:** Claude Opus 5.5
**Date:** 2026-09-26
**Task:** Review tính năng xuất CSV điểm danh chi tiết
**Files Reviewed:** 5 files
**Test Status:** 43 tests passed

---

## Summary

| Metric | Count |
|--------|-------|
| Files reviewed | 5 |
| Critical issues | 1 |
| Important issues | 3 |
| Minor issues | 2 |

---

## Findings

### [Critical] Bug: Leave Request Query Không Filter Theo programId

**File:** `public/api/export.php:280-283`

**Issue:**
Khi user truyền `programId` (lọc theo buổi sinh hoạt cụ thể), query attendance có filter `programId` (lines 242-245), nhưng query leave_requests KHÔNG có filter tương ứng.

```php
// Query attendance - CÓ filter programId
if ($programId !== null) {
    $dk .= ' AND a.program_id = ?';
    $params[] = $programId;
}

// Query leave_requests - THIẾU filter programId
if ($programId !== null) {
    $lrDk .= ' AND lr.program_id = ?';  // ← DÒNG NÀY THIẾU!
    $lrParams[] = $programId;
}
```

**Impact:** Export với filter buổi cụ thể sẽ trả về tất cả leave_requests của mọi buổi, không chỉ buổi được chọn.

**Recommendation:**
Thêm filter programId vào query leave_requests:
```php
if ($programId !== null) {
    $lrDk .= ' AND lr.program_id = ?';
    $lrParams[] = $programId;
}
```

---

### [Important] Bug: Default Date Year Logic Sai

**File:** `public/api/export.php:201`

**Issue:**
Code lấy `fromDate` mặc định sai logic:

```php
if ($fromDate === '') $fromDate = $yearStart ?: date('Y-01-01');
```

Khi `$yearStart` tồn tại, nó gán đúng. Nhưng khi `$yearStart` null, nó fallback về `date('Y-01-01')` (1/1 năm hiện tại), không phải đầu năm học.

**Impact:**
- Nếu không tìm thấy school_year (edge case), fromDate mặc định thành 1/1/[năm hiện tại]
- Vấn đề này có thể xảy ra nếu bảng `school_years` rỗng hoặc không có `is_current = 1`

**Recommendation:**
```php
// Fallback về ngày đầu tháng thay vì đầu năm
if ($fromDate === '') {
    $today = date('Y-m-d');
    $fromDate = $yearStart ?: substr($today, 0, 8) . '01';
}
```

---

### [Important] Quality: Thiếu Xử Lý Special Characters Trong Filename

**File:** `public/api/export.php:374`

**Issue:**
Code replace spaces nhưng không xử lý các ký tự đặc biệt khác có thể gây lỗi filesystem:

```php
$className = preg_replace('/\s+/', '_', $cls['name']) . '_';
// Ví dụ: "5 Thánh Phaolô" → "5_Thánh_Phaolô_" (OK)
// Nhưng: "Lớp: 5A/1" → "Lớp:_5A/1" (Có dấu / - không an toàn!)
```

**Impact:**
- Tên lớp chứa `/`, `\`, `:`, `*`, `?`, `"`, `<`, `>`, `|` sẽ gây lỗi trên một số filesystem
- Có thể tạo filename không hợp lệ

**Recommendation:**
```php
// Chỉ giữ alphanumeric, dấu cách, và một số ký tự an toàn
$unsafeClassName = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $cls['name'] ?? '');
$className = preg_replace('/\s+/', '_', $unsafeClassName) . '_';
```

---

### [Important] Quality: Thiếu Program Name Filter Cho Enrolled Students

**File:** `public/api/export.php:248-266`

**Issue:**
Khi có `programId`, attendance records được filter đúng, nhưng logic tính "vắng mặt" không xem xét enrollment của student vào buổi đó.

**Code hiện tại:**
```php
// Index để skip leave_requests đã có attendance
$attendedKeys[$key] = true;
// key = student_code | session_date | program_name
```

**Impact:**
Nếu student được enrolled nhưng buổi đó không gắn với enrollment của student, họ sẽ không xuất hiện trong danh sách vắng mặt đúng.

**Recommendation:**
Kiểm tra enrollment với `program_classes` table:
```php
// Cần verify student enrolled vào class gắn với program
```

---

### [Minor] Security: CSV Escape Chưa Check Newline Prefix

**File:** `public/api/export.php:593`

**Issue:**
`csv_escape()` check cho `\t` (tab) và `\r` (carriage return) nhưng thiếu `\n` (newline):

```php
if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
    $value = "'" . $value;
}
```

**Impact:**
Giá trị bắt đầu bằng newline (`\n`) sẽ không được prefix, tuy nhiên sẽ được wrap trong quotes ở bước tiếp theo. Rủi ro thấp.

**Recommendation:**
Thêm `\n` vào danh sách:
```php
if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
    $value = "'" . $value;
}
```

---

### [Minor] Quality: Code Duplication Trong Query Building

**File:** `public/api/export.php:230-245` và `269-283`

**Issue:**
Logic xây dựng WHERE clause cho attendance và leave_requests gần như giống hệt nhau, được lặp lại 2 lần với biến `$dk` và `$lrDk`.

**Recommendation:**
Tái sử dụng helper function:
```php
function buildAttendanceWhereClause(array $params, $classId, $programId, $allow): string {
    // ... shared logic
}
```

---

## Positive Observations

1. **CSV Injection Protection** - Hàm `csv_escape()` được implement đúng cách, prefix `'` cho values bắt đầu bằng `=`, `+`, `-`, `@`

2. **Authorization Checks** - Logic kiểm tra quyền truy cập (`accessible_class_ids()`) được áp dụng nhất quán cho cả attendance records và leave requests

3. **Date Validation** - Validation đầy đủ: format YYYY-MM-DD, range check (max 365 days), fromDate <= toDate

4. **UTF-8 BOM** - CSV output có BOM `\xEF\xBB\xBF` đúng cho Excel đọc tiếng Việt

5. **Prepared Statements** - Tất cả queries đều dùng prepared statements, không có SQL injection risk

6. **Unit Tests** - 43 tests cover đầy đủ: security, validation, status mapping, authorization

7. **UI/UX** - Modal với Alpine.js transitions, validation error display, loading state

8. **Performance** - Sử dụng index `idx_att_date_prog`, không có N+1 queries

---

## Recommendations

### Must Fix (Before Merge)

1. **Thêm programId filter vào leave_requests query** (Critical bug)
   - File: `public/api/export.php:280-283`

### Should Fix (Recommended)

2. **Cải thiện default date fallback**
   - File: `public/api/export.php:201`

3. **Sanitize filename cho special characters**
   - File: `public/api/export.php:374`

4. **Verify enrollment logic cho "vắng mặt"**
   - Kiểm tra xem student có enrolled vào buổi cụ thể không

### Nice to Have

5. **Extract duplicated WHERE clause logic** thành helper function
6. **Thêm `\n` vào csv_escape prefix check**

---

## Test Coverage Assessment

| Category | Coverage | Notes |
|----------|---------|-------|
| CSV Escape Security | ✅ Good | Tests đầy đủ cho `=`, `+`, `-`, `@`, tab |
| Date Validation | ✅ Good | Format, range, from/to comparison |
| Authorization | ✅ Good | Admin, GLV assigned, GLV unassigned |
| Status Mapping | ✅ Good | All 4 status values + unknown |
| Leave Request Logic | ✅ Good | Attendance优先, LR fallback |
| Filename Generation | ⚠️ Partial | Missing special char tests |
| Frontend Validation | ⚠️ Partial | Server-side OK, client-side minimal |

---

## Approved?

**NO** - Cần fix Critical bug #1 (programId filter missing) trước khi merge.

Sau khi fix Critical bug, code đủ chất lượng để merge với các Should Fix recommendations.

---

## Files Reviewed

| File | LoC | Issues |
|------|-----|--------|
| `public/api/export.php` | 625 | 3 (1 Critical, 2 Important) |
| `public/assets/js/modules/attendance.js` | 493 | 0 |
| `public/assets/js/modules/export.js` | 140 | 0 |
| `views/module_attendance.php` | 463 | 0 |
| `tests/unit/ExportTest.php` | 531 | 0 |

---

**Reviewer:** Claude Opus 5.5
**Review Type:** Standard (15-30 min)
**Recommendation:** Fix Critical bug, then APPROVE
