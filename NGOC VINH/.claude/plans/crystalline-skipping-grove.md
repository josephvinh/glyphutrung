# Plan: Hồ sơ tổng hợp từng em Thiếu Nhi

## ✅ Người dùng đã xác nhận
- **Dạng hiển thị:** Trang riêng (SPA page) với nút ← Quay lại
- **Phạm vi:** Làm tất cả 5 tab cùng lúc

---

## Context

Theo HANDOFF.md, "bước 2" của việc gom module Thiếu Nhi chưa xong: **hồ sơ tổng hợp từng em** — bấm vào 1 em xem thông tin + điểm + phiếu liên lạc + điểm danh.

Hiện tại, click vào card em chỉ mở popup sửa hồ sơ. Cần thêm một trang xem chi tiết đầy đủ.

---

## Tổng quan cấu trúc hiện tại

| Thành phần | File |
|------------|------|
| Thanh tab | `views/partial_children_tabs.php` |
| Module students | `public/assets/js/modules/students.js` |
| Module scores | `public/assets/js/modules/scores.js` |
| Module reports | `public/assets/js/modules/reports.js` |
| Module attendance | `public/assets/js/modules/attendance.js` |
| Data API | `public/api/data.php` |

---

## 1. Tạo view hồ sơ tổng hợp

**File mới:** `views/module_student_profile.php`

### Giao diện

```
┌─────────────────────────────────────────────────────────────┐
│  ← Quay lại    HỒ SƠ THIẾU NHI                            │
├─────────────────────────────────────────────────────────────┤
│  ┌──────────┐  Tên Thánh Họ Tên                           │
│  │  QR Code │  Mã: GDGLPT260001 • Lớp: 5A • Khối: 5       │
│  │  (nhỏ)   │  📅 01/01/2013 • 👤 Nam                    │
│  └──────────┘  📍 123 Đường ABC, Quận...                  │
│                 👨 Cha: Nguyễn Văn A (0901...) [Gọi]      │
│                 👩 Mẹ: Trần Thị B (0902...) [Gọi]          │
├─────────────────────────────────────────────────────────────┤
│  [Thông tin] [Điểm số] [Phiếu LC] [Điểm danh] [QR Card]   │
├─────────────────────────────────────────────────────────────┤
│  Nội dung tab được chọn                                    │
└─────────────────────────────────────────────────────────────┘
```

### Các tab con

1. **Thông tin** — hồ sơ đầy đủ (giống popup sửa nhưng xem được hết)
2. **Điểm số** — bảng điểm theo học kỳ, xếp loại
3. **Phiếu liên lạc** — danh sách phiếu, xem/in
4. **Điểm danh** — lịch sử điểm danh (từ `attendanceRecord()`)
5. **QR Card** — in thẻ QR của em này

### Header profile (giống partial_children_tabs.php nhưng cho profile)

**File mới:** `views/partial_student_profile_header.php`

---

## 2. Sửa `students.js` — thêm state và method mở profile

Thêm vào `window.TNTT.students`:

```javascript
// State
profileStudent: null,
profileTab: 'info', // info | scores | report | attendance | qrcard

// Method mở profile
openStudentProfile(student) {
    this.profileStudent = student;
    this.profileTab = 'info';
    this.changeModule('student_profile');
},
```

---

## 3. Cập nhật `partial_children_tabs.php`

Thêm điều kiện hiển thị: khi `currentModule === 'student_profile'`, hiện header profile thay vì tabs Thiếu Nhi thông thường.

---

## 4. Tạo view module_student_profile.php

**File:** `views/module_student_profile.php`

Pattern giống `module_scores.php`:
- Include header `partial_student_profile_header.php`
- 5 tab sub-navigation
- Nội dung mỗi tab dùng chung logic từ các module hiện có

### Tab Thông tin
- Hiển thị đầy đủ thông tin: mã, tên, ngày sinh, giới tính, địa chỉ, cha mẹ
- Nút Sửa hồ sơ (mở popup edit từ students.js)

### Tab Điểm số
- Lặp qua `terms`, hiển thị bảng điểm từ `scoreOf(studentId, type, termId)`
- ĐTB học kỳ từ `termAverage(studentId, termId)`
- ĐTB cả năm từ `yearAverage(studentId)`

### Tab Phiếu liên lạc
- Lọc `reports` theo `studentId`
- Danh sách phiếu với học kỳ, trạng thái, nút Xem/In

### Tab Điểm danh
- Lọc `attendances` theo `studentId`
- Bảng ngày, trạng thái, phương thức, người điểm danh
- Số buổi có mặt / vắng / trễ

### Tab QR Card
- Hiển thị thẻ QR của em (reuse từ qrcard.js)
- Nút In

---

## 5. Đăng ký module mới

**File:** `public/index.php`

Thêm `<script src="assets/js/modules/student_profile.js">` vào danh sách modules.

---

## 6. Xử lý nút Quay lại

Trong `module_student_profile.php`, nút ← Quay lại gọi `changeModule('students')`.

---

## Tổng kết files

### Tạo mới

| File | Mô tả |
|------|--------|
| `views/module_student_profile.php` | View chính cho trang hồ sơ em |
| `views/partial_student_profile_header.php` | Header với thông tin em + QR code nhỏ |
| `public/assets/js/modules/student_profile.js` | JS module (state + methods cho profile) |

### Sửa đổi

| File | Thay đổi |
|------|-----------|
| `public/assets/js/modules/students.js` | Thêm `profileStudent`, `profileTab`, method `openStudentProfile()` |
| `views/partial_children_tabs.php` | Thêm điều kiện hiển thị header profile khi ở module student_profile |
| `public/index.php` | Thêm script tag cho `student_profile.js` |

---

## Files cần tạo mới

1. `views/module_student_profile.php`
2. `public/assets/js/modules/student_profile.js`

## Files cần sửa

1. `public/assets/js/app.js` — thêm state, đăng ký module
2. `public/assets/js/modules/students.js` — thêm hàm mở profile
3. `views/partial_children_tabs.php` — thêm logic hiển thị

---

## Verification

1. Chạy app → vào module Thiếu Nhi → click vào tên 1 em
2. Kiểm tra tab Thông tin hiển thị đúng thông tin em
3. Chuyển tab Điểm số → xem bảng điểm
4. Chuyển tab Phiếu LC → xem danh sách phiếu
5. Chuyển tab Điểm danh → xem lịch sử điểm danh
6. Chuyển tab QR Card → xem/in thẻ QR
7. Nút Quay lại → về danh sách
8. Chạy `php phpunit10.phar --no-coverage` để test
