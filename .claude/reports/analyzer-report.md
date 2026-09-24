# Báo Cáo Phân Tích Tính Năng Xuất Báo Cáo Điểm Danh CSV

**Agent:** tntt-analyzer
**Date:** 2026-09-24
**Project:** TNTT Glyphutrung

---

## 1. Tóm Tắt Điều Hành

Tính năng xuất báo cáo điểm danh CSV cho phép giáo viên/học sinh tải danh sách điểm danh với bộ lọc theo lớp và khoảng thời gian. Báo cáo này phân tích codebase hiện tại và thiết kế chi tiết cho feature mới.

**Đánh giá:**
- **Độ phức tạp:** Trung bình
- **Effort ước tính:** 8-12 giờ
- **Rủi ro:** Thấp (không ảnh hưởng feature hiện tại)

---

## 2. Phân Tích Codebase Hiện Tại

### 2.1 Kiến Trúc Attendance Module

**Backend (`public/api/attendance.php`):**
- Endpoint chính: `POST /api/attendance.php?action=toggle`
- Actions: `toggle`, `lookup`, `scan`
- Logic:
  - Toggle: Bật/tắt điểm danh cho 1 em
  - Scan: Quét QR hàng loạt (tối ưu N+1 queries)
  - Lookup: Bảng tra cho máy quét

**Frontend (`views/module_attendance.php`):**
- Alpine.js với các state: `activeSession`, `programsOnDate`, `sessionStudents`
- Giao diện: Chọn buổi → Danh sách → Quét QR / Điểm tay
- Modal QR scanner với camera API

**Export hiện tại (`public/api/export.php`):**
- Action `attendance`: Xuất ma trận điểm danh (mỗi cột là 1 buổi)
- Không có bộ lọc ngày
- Không có ghi chú

### 2.2 Database Schema

```sql
-- attendances table (hiện tại)
attendances (
    id, year_id, program_id, session_date,
    student_id, status, method, marked_by, marked_at
)

-- Cần thêm: note VARCHAR(255) NULL
```

**Leave requests (cho tính "vắng có phép"):**
```sql
leave_requests (
    id, year_id, student_id, program_id,
    session_date, reason, status, created_by, ...
)
```

### 2.3 Patterns Quan Trọng

**Authorization:**
```php
$allow = accessible_class_ids($me, 'attendance', 'view');
if ($allow !== null && !in_array($classId, $allow, true)) {
    json_fail('Bạn không phụ trách lớp này.', 403);
}
```

**CSV Escape (chống injection):**
```php
function csv_escape(string $value): string {
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
        $value = "'" . $value;
    }
    // ... handle commas, quotes, newlines
}
```

**Data URL Response:**
```php
$url = 'data:text/csv;charset=utf-8;base64,' . base64_encode($csv);
json_out(['ok' => true, 'url' => $url, 'filename' => '...csv']);
```

---

## 3. Thiết Kế Kỹ Thuật

### 3.1 API Changes

**Thêm endpoint:**
```
GET /api/export.php?action=attendance-detail
    &classId=5        (optional)
    &fromDate=2026-09-01  (optional, default: đầu năm)
    &toDate=2026-09-30    (optional, default: hôm nay)
    &programId=3     (optional)
```

**Response mẫu:**
```json
{
  "ok": true,
  "url": "data:text/csv;charset=utf-8;base64,UVNQ+...",
  "filename": "Diem_Danh_5A_2026-09-01_2026-09-30.csv",
  "count": 150
}
```

### 3.2 Logic Tính Vắng Mặt

Điểm khác biệt quan trọng với export hiện tại:

| Export hiện tại | Feature mới |
|-----------------|-------------|
| Chỉ export các bản ghi điểm danh | Tính cả "Vắng mặt" cho em không có record |
| Ma trận ngang (cột = buổi) | Danh sách dọc (dòng = bản ghi) |
| Không có ghi chú | Có cột ghi chú |

**Thuật toán:**
1. Lấy danh sách học sinh theo lớp/năm
2. Với mỗi học sinh, với mỗi buổi trong khoảng ngày:
   - Có attendance → dùng status thực
   - Có leave_request đã duyệt → "Vắng mặt" + lý do
   - Không có gì → "Vắng mặt" + "Tự động"

### 3.3 Frontend UI

**Vị trí:** Thêm trong `module_attendance.php` sau phần chọn ngày

**Components:**
1. **Button "Xuất CSV"** - màu emerald, icon download
2. **Modal filter:**
   - Dropdown chọn lớp (từ danh sách được phép)
   - Date picker "Từ ngày"
   - Date picker "Đến ngày"
   - Nút "Tải về" / "Hủy"

### 3.4 Database Migration

```sql
ALTER TABLE attendances
ADD COLUMN note VARCHAR(255) NULL AFTER status;

ALTER TABLE attendances
ADD INDEX idx_att_date_prog (session_date, program_id);
```

---

## 4. So Sánh Với Export Hiện Tại

| Tiêu chí | Export Matrix | Export Chi Tiết |
|----------|---------------|-----------------|
| **Use case** | Xem nhanh tổng quan | Báo cáo chi tiết |
| **Format** | Ma trận (hàng=hs, cột=buổi) | Danh sách (mỗi dòng=1 bản ghi) |
| **Bộ lọc** | Chỉ lớp | Lớp + ngày + buổi |
| **Vắng mặt** | Tính trong cell cuối | Từng dòng riêng |
| **Ghi chú** | Không | Có |
| **Lý do vắng** | Không | Có (từ leave_requests) |

**Cả hai đều cần giữ** vì phục vụ use cases khác nhau.

---

## 5. Rủi Ro Và Mitigation

| Rủi ro | Mức | Mitigation |
|---------|-----|------------|
| Query chậm với dữ liệu lớn | Trung bình | Thêm INDEX, giới hạn date range |
| CSV injection | Cao | Escape đúng chuẩn (đã có pattern) |
| Thiếu quyền nhưng vẫn export | Cao | Check `accessible_class_ids()` kỹ |
| Conflicting với export cũ | Thấp | Tên action khác, không sửa code cũ |

---

## 6. Effort Estimate

| Task | Hours |
|------|-------|
| Migration SQL | 1 |
| API endpoint (export.php) | 3 |
| Frontend UI (modal + handler) | 3 |
| Testing thủ công | 2 |
| **Total** | **8-12** |

---

## 7. Recommendations

### 7.1 Immediate (Nên làm)
1. **Thêm cột `note`** vào `attendances` - phục vụ cả use cases khác
2. **Index** trên `(session_date, program_id)` - tối ưu query

### 7.2 Future (Có thể làm sau)
1. Xuất Excel (.xlsx) thay vì CSV
2. Lọc theo trạng thái (chỉ vắng, chỉ muộn)
3. Preview trước khi download
4. Auto-email cho phụ huynh

---

## 8. Deliverables

| File | Status | Path |
|------|--------|------|
| Technical Specification | Hoàn thành | `docs/specs/attendance-export.md` |
| Database Migration | Cần tạo | `docs/nang-cap-attendance-note.sql` |
| API Implementation | Cần làm | `public/api/export.php` (modify) |
| Frontend UI | Cần làm | `views/module_attendance.php` (modify) |

---

## 9. Kết Luận

Tính năng xuất CSV điểm danh chi tiết là bổ sung hợp lý cho module attendance. Thiết kế:
- Tận dụng infrastructure sẵn có (export.php, csv_escape pattern)
- Bảo toàn backward compatibility
- Bổ sung cột `note` phục vụ nhiều use cases
- Rủi ro thấp, effort vừa phải

**Khuyến nghị:** Tiến hành implementation với thứ tự ưu tiên:
1. Database migration
2. API endpoint
3. Frontend UI
4. Testing

---

*Generated by Claude Code Analyzer Agent*
*Model: opus*
