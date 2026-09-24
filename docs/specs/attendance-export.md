# Xuất Báo Cáo Điểm Danh CSV — Technical Specification

**Version:** 1.0
**Module:** attendance
**Created:** 2026-09-24
**Status:** Draft

---

## 1. Overview

### Mục tiêu
Cho phép giáo viên/học sinh xuất báo cáo điểm danh ra file CSV với các bộ lọc theo lớp và khoảng thời gian.

### Tính năng chính
- Xuất danh sách điểm danh theo lớp hoặc toàn đoàn
- Lọc theo ngày bắt đầu / ngày kết thúc
- Xuất CSV với định dạng: Họ tên | Ngày | Trạng thái | Ghi chú
- Hỗ trợ download trực tiếp từ trình duyệt

### So sánh với export hiện tại
| Khía cạnh | Export hiện tại | Feature mới |
|-----------|-----------------|-------------|
| Dạng dữ liệu | Ma trận ngang (mỗi cột là 1 buổi) | Danh sách dòng (mỗi dòng là 1 bản ghi) |
| Lọc ngày | Không | Từ ngày - Đến ngày |
| Lọc lớp | Có | Có |
| Ghi chú | Không | Có (thêm cột) |
| Trạng thái | P/L/E/A | Có mặt / Đi trễ / Vắng mặt (tiếng Việt) |

---

## 2. Requirements

### 2.1 User Stories
```
LÀM: Giáo viên chủ nhiệm
TAO MONG: Xuất danh sách điểm danh của lớp tôi phụ trách
DE: Gửi cho phụ huynh hoặc nộp về Văn phòng Giáo xứ

LÀM: Ban Điều Hành
TAO MONG: Theo dõi tình hình điểm danh toàn đoàn
DE: Lọc theo khối/lớp trong một tháng cụ thể

LÀM: Giáo viên
TAO MONG: Ghi chú khi điểm danh (VD: "vắng có phép - xin nghỉ ốm")
DE: Xuất kèm ghi chú trong CSV
```

### 2.2 Functional Requirements

#### FR-001: Xuất CSV theo lớp
- **Input:** `classId` (optional - nếu null thì xuất toàn đoàn)
- **Output:** File CSV chứa tất cả bản ghi điểm danh của lớp đó
- **Phân quyền:** Chỉ người có quyền `attendance` view trên lớp đó

#### FR-002: Lọc theo khoảng thời gian
- **Input:** `fromDate`, `toDate` (định dạng YYYY-MM-DD)
- **Validation:**
  - `fromDate` <= `toDate`
  - Khoảng thời gian không quá 365 ngày
- **Default:** Từ đầu năm học đến hiện tại

#### FR-003: Nội dung CSV
| Cột | Mô tả | Ví dụ |
|-----|--------|-------|
| STT | Số thứ tự | 1 |
| Mã số | Mã học sinh | TN001 |
| Họ tên | Họ và tên đầy đủ | Gioan Baotixita Phạm Văn A |
| Lớp | Tên lớp | 5 Thánh Phaolô |
| Ngày | Ngày điểm danh (DD/MM/YYYY) | 15/09/2026 |
| Buổi | Tên chương trình | Sinh hoạt Chúa Nhật |
| Trạng thái | Có mặt / Đi trễ / Vắng mặt | Có mặt |
| Ghi chú | Ghi chú từ bản ghi | Xin nghỉ ốm |
| Người ghi | Người thực hiện điểm danh | Trần Văn B |

#### FR-004: Tính Vắng mặt
- Hệ thống **tự động tính Vắng mặt** cho các em:
  - Có enrollment trong niên khoá
  - Được ghi danh vào lớp/buổi
  - **KHÔNG** có bản ghi trong `attendances`
- Hàng "Vắng mặt" xuất hiện với `status = 'Vắng mặt'` và `ghi chú = 'Tự động'`

### 2.3 Non-Functional Requirements

#### NFR-001: Performance
- Query không quá 5 giây với 10,000 bản ghi
- Sử dụng prepared statements cho mọi query

#### NFR-002: Security
- CSV injection protection (prefix `'` cho giá trị bắt đầu bằng `=`, `+`, `-`, `@`)
- Authorization check trước khi xuất
- CSRF protection (export là GET, không cần CSRF vì không ghi dữ liệu)

#### NFR-003: CSV Format
- Encoding: UTF-8 with BOM (để Excel đọc tiếng Việt đúng)
- Line ending: CRLF (`\r\n`)
- Delimiter: Dấu phẩy (`,`)
- Text qualifier: Dấu ngoặc kép (`"`)

---

## 3. API Design

### 3.1 Endpoint

```
GET /api/export.php?action=attendance-detail
```

### 3.2 Request Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| classId | int | No | null | Lọc theo lớp cụ thể |
| fromDate | string | No | Đầu năm học | Ngày bắt đầu (YYYY-MM-DD) |
| toDate | string | No | Hôm nay | Ngày kết thúc (YYYY-MM-DD) |
| programId | int | No | null | Lọc theo buổi sinh hoạt cụ thể |

### 3.3 Response

**Success (200):**
```json
{
  "ok": true,
  "url": "data:text/csv;charset=utf-8;base64,...",
  "filename": "Diem_Danh_5A_2026-09-01_2026-09-30.csv",
  "count": 150
}
```

**Error (4xx/5xx):**
```json
{
  "ok": false,
  "error": "Thông báo lỗi"
}
```

### 3.4 HTTP Method
- **GET** — export là thao tác đọc, không ghi dữ liệu
- Không yêu cầu CSRF token
- Response là data URL (base64) để trình duyệt tự download

---

## 4. Data Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT                                    │
│  module_attendance.php                                           │
│  - User chọn "Xuất CSV" button                                   │
│  - Chọn lớp, fromDate, toDate                                   │
│  - Gọi GET /api/export.php?action=attendance-detail             │
│  - Nhận data URL → trigger browser download                     │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                     BACKEND                                      │
│  public/api/export.php (case attendance-detail)                  │
│  ┌─────────────────────────────────────────────────────────────┐ │
│  │ 1. require_login() → Lấy thông tin member hiện tại          │ │
│  │ 2. current_year() → Lấy niên khoá đang mở                   │ │
│  │ 3. accessible_class_ids() → Lấy danh sách lớp được phép    │ │
│  │ 4. Validate classId (nếu có)                                │ │
│  │ 5. Validate date range                                      │ │
│  │ 6. Query attendance records + leave_requests                 │ │
│  │ 7. Query enrolled students (cho tính Vắng mặt)              │ │
│  │ 8. Build CSV string                                         │ │
│  │ 9. Return data URL                                          │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────┬───────────────────────────────────────────┘
                      │
                      ▼
┌─────────────────────────────────────────────────────────────────┐
│                    DATABASE                                      │
│                                                                  │
│  Tables:                                                         │
│  - enrollments (year_id, student_id, class_id, status)           │
│  - students (id, code, full_name, holy_name)                    │
│  - classes (id, name)                                            │
│  - programs (id, name, year_id)                                  │
│  - program_classes (program_id, class_id) ← gắn lớp với buổi    │
│  - attendances (student_id, program_id, session_date, status,    │
│                 marked_by, note) ← THÊM note column             │
│  - leave_requests (student_id, program_id, session_date, reason)  │
│  - members (id, full_name)                                      │
└─────────────────────────────────────────────────────────────────┘
```

### 4.1 Logic Tính Vắng Mặt

```
Với mỗi student trong enrollment (của lớp được lọc):
  VỚI MỖI program trong khoảng thời gian:
    NẾU tồn tại attendance record:
      Thêm dòng với status = attendance.status
    NGƯỢC LẠI NẾU tồn tại leave_request với status='đã duyệt':
      Thêm dòng với status = 'Vắng mặt', ghi chú = reason
    NGƯỢC LẠI:
      Thêm dòng với status = 'Vắng mặt', ghi chú = 'Tự động'
```

---

## 5. Database Changes

### 5.1 Migration: Thêm cột `note` vào `attendances`

**File:** `docs/nang-cap-attendance-note.sql`

```sql
-- ============================================================
-- Migration: Thêm cột ghi chú cho điểm danh
-- ============================================================

-- Thêm cột note vào bảng attendances
ALTER TABLE attendances
ADD COLUMN note VARCHAR(255) NULL
AFTER status;

-- Thêm INDEX cho truy vấn theo ngày + chương trình
ALTER TABLE attendances
ADD INDEX idx_att_date_prog (session_date, program_id);
```

### 5.2 Schema Changes Summary

| Bảng | Thay đổi | Kiểu |
|------|----------|------|
| `attendances` | Thêm `note VARCHAR(255) NULL` | Migration |
| `attendances` | Thêm `INDEX idx_att_date_prog` | Migration |

### 5.3 Queries Used

```sql
-- Lấy attendance records + student info + program info
SELECT
    a.session_date,
    s.code AS student_code,
    CONCAT(COALESCE(s.holy_name, ''), ' ', s.full_name) AS full_name,
    c.name AS class_name,
    p.name AS program_name,
    a.status,
    a.note,
    m.full_name AS marked_by_name
FROM attendances a
JOIN students s ON s.id = a.student_id
JOIN enrollments e ON e.student_id = s.id AND e.year_id = a.year_id
JOIN classes c ON c.id = e.class_id
JOIN programs p ON p.id = a.program_id
LEFT JOIN members m ON m.id = a.marked_by
WHERE a.year_id = ?
  AND a.session_date BETWEEN ? AND ?
  [AND e.class_id = ?]
ORDER BY a.session_date DESC, c.name, s.code;

-- Lấy approved leave requests
SELECT
    lr.session_date,
    s.code AS student_code,
    CONCAT(COALESCE(s.holy_name, ''), ' ', s.full_name) AS full_name,
    c.name AS class_name,
    p.name AS program_name,
    'Vắng mặt' AS status,
    lr.reason AS note,
    NULL AS marked_by_name
FROM leave_requests lr
JOIN students s ON s.id = lr.student_id
JOIN enrollments e ON e.student_id = s.id AND e.year_id = lr.year_id
JOIN classes c ON c.id = e.class_id
JOIN programs p ON p.id = lr.program_id
WHERE lr.year_id = ?
  AND lr.session_date BETWEEN ? AND ?
  AND lr.status = 'đã duyệt'
ORDER BY lr.session_date DESC, c.name, s.code;
```

---

## 6. Frontend Changes

### 6.1 UI Components

**Nút "Xuất CSV"** trong `module_attendance.php`:

```html
<!-- Sau phần chọn ngày, thêm bộ lọc xuất -->
<div class="bg-white rounded-card p-4 shadow-sm border border-slate-100 mb-5">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-bold text-slate-700">Xuất Báo Cáo CSV</h3>
        <button @click="showExportModal = true" type="button"
                class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-bold text-xs flex items-center gap-2">
            <i data-lucide="download" class="w-4 h-4"></i> Xuất CSV
        </button>
    </div>
    <p class="text-xs text-slate-500">Tải danh sách điểm danh theo lớp và khoảng thời gian</p>
</div>

<!-- Modal chọn bộ lọc -->
<div x-show="showExportModal" style="display: none;"
     class="fixed inset-0 z-[200] bg-black/50 flex items-center justify-center p-4">
    <!-- Modal content with:
         - Class selector (dropdown)
         - From date picker
         - To date picker
         - Export button
    -->
</div>
```

### 6.2 JavaScript Functions

```javascript
// Trong AttendanceController hoặc Alpine.js data
{
    showExportModal: false,
    exportFilters: {
        classId: '',
        fromDate: '',
        toDate: ''
    },

    async exportAttendanceCSV() {
        const params = new URLSearchParams({
            action: 'attendance-detail',
            classId: this.exportFilters.classId || '',
            fromDate: this.exportFilters.fromDate,
            toDate: this.exportFilters.toDate
        });

        const resp = await fetch(`/api/export.php?${params}`);
        const data = await resp.json();

        if (data.ok) {
            // Trigger download
            const a = document.createElement('a');
            a.href = data.url;
            a.download = data.filename;
            a.click();
            this.showExportModal = false;
        } else {
            toast.error(data.error);
        }
    }
}
```

---

## 7. Acceptance Criteria

### AC-001: Xuất CSV cơ bản
- [ ] Gọi API với date range hợp lệ → nhận file CSV
- [ ] File CSV mở được trong Excel với tiếng Việt đúng
- [ ] File có đúng header theo mẫu

### AC-002: Lọc theo lớp
- [ ] Không chọn lớp → xuất toàn đoàn
- [ ] Chọn lớp cụ thể → chỉ xuất học sinh lớp đó
- [ ] Không có quyền xem lớp khác → API trả 403

### AC-003: Lọc theo ngày
- [ ] fromDate mặc định = đầu năm học
- [ ] toDate mặc định = hôm nay
- [ ] Chỉ có 1 ngày → xuất đúng 1 ngày đó
- [ ] fromDate > toDate → API trả lỗi validation

### AC-004: Tính Vắng mặt
- [ ] Em không có attendance record → xuất dòng "Vắng mặt"
- [ ] Em có leave_request đã duyệt → xuất dòng "Vắng mặt" + lý do
- [ ] Tổng số dòng = present + late + absent

### AC-005: Security
- [ ] Không đăng nhập → API trả 401
- [ ] CSV injection với tên bắt đầu bằng `=` → prefix `'` được thêm
- [ ] Không có quyền attendance view → API trả 403

### AC-006: Performance
- [ ] 500 học sinh × 30 ngày × 2 buổi → response < 3 giây
- [ ] Không timeout khi dữ liệu lớn

---

## 8. Test Cases

### TC-001: Happy Path - Xuất theo lớp
```
Precondition:
  - Đăng nhập với tài khoản GLV lớp 5A
  - Có 20 học sinh trong lớp 5A
  - Có attendance records cho 5 ngày

Steps:
  1. Mở module Điểm Danh
  2. Bấm "Xuất CSV"
  3. Chọn lớp "5A"
  4. Để date range mặc định (01/09/2026 - hôm nay)
  5. Bấm "Tải về"

Expected:
  - File download thành công
  - Tên file: Diem_Danh_5A_2026-09-*.csv
  - CSV có 100 dòng (20 học sinh × 5 ngày)
  - Mỗi dòng có: STT, Mã, Họ tên, Lớp, Ngày, Buổi, Trạng thái, Ghi chú
```

### TC-002: Happy Path - Xuất toàn đoàn
```
Precondition:
  - Đăng nhập với tài khoản BĐH (quyền toàn đoàn)

Steps:
  1. Mở module Điểm Danh
  2. Bấm "Xuất CSV"
  3. Không chọn lớp (default = all)
  4. Chọn date range: 01/09/2026 - 30/09/2026
  5. Bấm "Tải về"

Expected:
  - File download thành công
  - Tên file: Diem_Danh_2026-09-01_2026-09-30.csv
  - CSV chứa học sinh mọi lớp
```

### TC-003: Validation - Ngày không hợp lệ
```
Steps:
  1. Mở modal xuất CSV
  2. Nhập fromDate = 30/09/2026
  3. Nhập toDate = 01/09/2026
  4. Bấm "Tải về"

Expected:
  - Toast error: "Ngày bắt đầu phải trước ngày kết thúc"
  - Không gọi API
```

### TC-004: Authorization - Không có quyền
```
Precondition:
  - Đăng nhập với tài khoản GLV lớp 5A
  - Cố gắng xuất lớp 5B (không phụ trách)

Steps:
  1. Mở modal xuất CSV
  2. Chọn lớp "5B"
  3. Bấm "Tải về"

Expected:
  - Toast error: "Bạn không phụ trách lớp này"
  - API không trả file
```

### TC-005: CSV Injection Protection
```
Precondition:
  - Có học sinh tên: "=CMD|'/C calc'!A0"

Steps:
  1. Xuất CSV bình thường
  2. Mở file trong Excel

Expected:
  - Tên hiển thị đúng: "=CMD|'/C calc'!A0" (với prefix dấu ')
  - Excel KHÔNG thực thi lệnh CMD
```

### TC-006: Performance - Dữ liệu lớn
```
Precondition:
  - 500 học sinh
  - 90 ngày sinh hoạt
  - 2 buổi/tuần = ~25 buổi

Steps:
  1. Xuất toàn đoàn với date range 1 tháng
  2. Đo thời gian response

Expected:
  - Response time < 5 giây
  - File CSV đầy đủ, không thiếu dòng
```

---

## 9. Implementation Notes

### 9.1 File Changes

| File | Action | Description |
|------|--------|-------------|
| `docs/nang-cap-attendance-note.sql` | **CREATE** | Migration thêm cột note |
| `public/api/export.php` | **MODIFY** | Thêm case `attendance-detail` |
| `views/module_attendance.php` | **MODIFY** | Thêm UI xuất CSV |
| `public/assets/js/app.js` hoặc inline | **MODIFY** | Thêm handler xuất CSV |

### 9.2 Backward Compatibility
- Cột `note` mới là `NULL` cho các bản ghi cũ → không ảnh hưởng
- Export hiện tại (`action=attendance`) vẫn hoạt động → không xóa
- Frontend có thể gọi cả 2 action tùy use case

### 9.3 Future Enhancements (Out of Scope)
- Xuất Excel (.xlsx) thay vì CSV
- Lọc theo trạng thái (chỉ vắng, chỉ có mặt)
- Gửi email trực tiếp cho phụ huynh
- Xuất PDF với template

---

## 10. Appendix: Sample Output

### Sample CSV (UTF-8 with BOM)
```csv
STT,Mã số,Họ tên,Lớp,Ngày,Buổi,Trạng thái,Ghi chú,Người ghi
1,TN001,Gioan Baotixita Phạm Văn A,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Có mặt,,Trần Văn B
2,TN002,Matthêu Nguyễn Văn B,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Đi trễ,,Trần Văn B
3,TN003,Luca Lê Thị C,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Vắng mặt,Xin nghỉ ốm có phép,
4,TN004,Maria Trần Văn D,5 Thánh Phaolô,15/09/2026,Sinh hoạt Chúa Nhật,Vắng mặt,Tự động,
5,TN001,Gioan Baotixita Phạm Văn A,5 Thánh Phaolô,22/09/2026,Sinh hoạt Chúa Nhật,Có mặt,,Hồ Thị X
```

---

**Prepared by:** Claude Code Analyzer Agent
**Date:** 2026-09-24
