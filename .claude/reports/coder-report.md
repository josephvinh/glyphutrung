# Báo Cáo Implement: Xuất Báo Cáo Điểm Danh CSV

**Ngày:** 2026-09-26
**Agent:** TNTT Coder
**Task:** Implement tính năng xuất báo cáo điểm danh chi tiết ra CSV

---

## 1. Files Changed

| File | Action | Mô tả |
|------|--------|--------|
| `docs/nang-cap-attendance-note.sql` | Already existed | Migration script |
| `public/api/export.php` | Modified | Thêm case `attendance-detail` |
| `public/assets/js/modules/export.js` | Modified | Thêm method `attendanceDetail()` |
| `public/assets/js/modules/attendance.js` | Modified | Thêm state và handler `exportAttendanceCSV()` |
| `views/module_attendance.php` | Modified | Thêm UI button và modal xuất CSV |

---

## 2. Implementation Details

### 2.1 Database Migration
- **Đã chạy:** Thêm cột `note VARCHAR(255) NULL` vào bảng `attendances`
- **Đã chạy:** Thêm index `idx_att_date_prog (session_date, program_id)`
- **Verify:** Cột và index đã tồn tại trong database

### 2.2 API Endpoint (`export.php`)
- **Action:** `attendance-detail`
- **Method:** GET
- **Parameters:**
  - `classId` (optional): Lọc theo lớp
  - `fromDate` (optional): Ngày bắt đầu (YYYY-MM-DD)
  - `toDate` (optional): Ngày kết thúc (YYYY-MM-DD)
  - `programId` (optional): Lọc theo chương trình

**Validation:**
- Kiểm tra định dạng ngày (YYYY-MM-DD)
- Kiểm tra `fromDate <= toDate`
- Giới hạn khoảng thời gian: max 365 ngày
- Authorization check qua `accessible_class_ids()`

**Logic xuất CSV:**
1. Query attendance records với student + program info
2. Query approved leave_requests
3. Kết hợp: attendance records + leave requests chưa có trong attendance
4. Sort theo ngày (desc), lớp, mã số
5. Build CSV với UTF-8 BOM

**Security:**
- CSV injection protection qua `csv_escape()` function (prefix `'` cho values bắt đầu bằng `=`, `+`, `-`, `@`)
- Authorization check trước khi export

### 2.3 Frontend UI (`module_attendance.php`)
- Thêm button "Xuất CSV" (emerald color) trong section chọn ngày
- Modal với bộ lọc:
  - Chọn lớp (dropdown)
  - Từ ngày (date picker)
  - Đến ngày (date picker)
- Validation: fromDate phải <= toDate

### 2.4 JavaScript Handler (`attendance.js`)
- State: `showExportCSVModal`, `exportCSV` (classId, fromDate, toDate, loading, error)
- Method: `openExportCSVModal()` - mở modal với default values
- Method: `exportAttendanceCSV()` - gọi API và trigger download

### 2.5 Export Function (`export.js`)
- Method: `attendanceDetail(classId, fromDate, toDate, programId)` - gọi API và download file

---

## 3. CSV Format

```
STT,Mã số,Họ tên,Lớp,Ngày,Buổi,Trạng thái,Ghi chú,Người ghi
1,TN001,Gioan Baotixita Phạm Văn A,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Có mặt,,Trần Văn B
2,TN002,Matthêu Nguyễn Văn B,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Đi trễ,,Trần Văn B
...
```

**Encoding:** UTF-8 with BOM (hiển thị tiếng Việt đúng trong Excel)

---

## 4. Self-Review Checklist

- [x] CSV mở được trong Excel với tiếng Việt đúng (UTF-8 BOM)
- [x] CSV injection protection đã áp dụng (csv_escape function)
- [x] Authorization check đúng (accessible_class_ids)
- [x] Edge cases xử lý:
  - Empty data: API trả về CSV rỗng với header
  - Invalid dates: Trả lỗi 400
  - fromDate > toDate: Trả lỗi validation
  - No permission: Trả lỗi 403
- [x] Không có debug code
- [x] Date sort dùng DateTime::createFromFormat để so sánh đúng

---

## 5. Test Cases đã Verify

### TC-001: API Validation
```
fromDate > toDate → 400 "Ngày bắt đầu phải trước ngày kết thúc."
```

### TC-002: Authorization
```
GLV lớp 5A export lớp 5B → 403 "Bạn không phụ trách lớp này."
```

### TC-003: CSV Format
```
Output: UTF-8 BOM + headers + data rows
Header: STT,Mã số,Họ tên,Lớp,Ngày,Buổi,Trạng thái,Ghi chú,Người ghi
```

---

## 6. Notes

- **Backward Compatibility:** Export hiện tại (`action=attendance`) vẫn hoạt động
- **Cột note:** Nullable, không ảnh hưởng dữ liệu cũ
- **Performance:** Sử dụng index `idx_att_date_prog` cho truy vấn nhanh
- **Filename format:** `Diem_Danh_[Lớp_]YYYY-MM-DD_YYYY-MM-DD.csv`
