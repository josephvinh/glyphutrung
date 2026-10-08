# SPEC: Mở Rộng Bộ Kiểm Giao Diện Đa Thiết Bị

**Ngày:** 2026-10-07  
**Issue:** #231  
**Status:** Approved by stakeholder

---

## 1. Mục Tiêu

Mở rộng bộ kiểm giao diện tự động để bắt lỗi hiển thị trên nhiều thiết bị và màn hình hơn, trước khi tới tay người dùng.

---

## 2. Các Quyết Định Đã Duyệt

| # | Câu hỏi | Quyết định |
|---|---------|------------|
| 1 | Ưu tiên modules | Attendance → Students → Scores → Announcements → Sổ Mộc |
| 2 | Thiết bị | Thêm iPhone SE + tablet + màn thấp |
| 3 | So ảnh | Có, tích hợp Percy/Loki vào CI |
| 4 | Axe accessibility | Tích hợp vào CI job |
| 5 | Máy thật | Không, chỉ Playwright + checklist thử tay |
| 6 | Chặn merge | Chỉ khi thay đổi views/ hoặc CSS |
| 7 | Kiểm tra in | Có, thêm print.php |

---

## 3. Phases & PRs

### Phase 1: PR-1 - Modules + Thiết Bị Mới

**Mục tiêu:** Mở rộng bộ test từ 6 trang công khai sang 5 modules đã đăng nhập, thêm 3 viewport mới.

#### 3.1.1 Viewports Mới

| Tên | Kích thước | Engine | Lý do |
|-----|-------------|--------|-------|
| iPhone SE | 375×667 | WebKit | iPhone phổ biến nhỏ hơn |
| Tablet | 768×1024 | Chromium | iPad, tablet Android |
| Màn thấp | 320×480 | Chromium | Android cũ, low-end |

#### 3.1.2 Modules Cần Kiểm Tra

Theo thứ tự ưu tiên:
1. **attendance** - Module điểm danh
2. **students** - Module quản lý thiếu nhi  
3. **scores** - Module điểm số
4. **announcements** - Module thông báo
5. **somoc** - Module Sổ Mộc

#### 3.1.3 Test Matrix (Phase 1)

| Thành phần | Số lượng |
|------------|----------|
| Viewports | 6 (3 cũ + 3 mới) |
| Modes | 3 (bình thường, giảm trong suốt, chữ lớn) |
| Pages (public) | 6 |
| Modules (private) | 5 |
| Roles | 3 |
| **Tổng combinations** | ~360 |

#### 3.1.4 Popup Testing

Mỗi module kiểm tra các popup:
- Modal dialog
- Toast notifications
- Dropdown menus
- Drawer panels

Kiểm tra: nút chính không bị nav đè, popup không bị lệch khung.

#### 3.1.5 Files Cần Sửa

```
tests/e2e/ui_matrix.js
├── Thêm viewports: iPhone SE, Tablet, Màn thấp
├── Thêm modules: attendance, students, scores, announcements, somoc
├── Thêm popup checks cho mỗi module
└── Cập nhật report format
```

#### 3.1.6 CI Impact (Phase 1)

- **Thời gian:** ~8-10 phút (thay vì 2.5 phút hiện tại)
- **Artifacts:** Screenshots + JSON report

---

### Phase 2: PR-2 - Axe + Block Merge

**Mục tiêu:** Tích hợp kiểm tra trợ năng vào CI, bỏ `continue-on-error` cho các changes liên quan.

#### 3.2.1 Tích Hợp Axe

```yaml
# .github/workflows/ci.yml
ui-a11y:
  steps:
    - run: node tests/e2e/axe.js
    - upload: axe_results.json
```

**Cấu hình axe:**
- Tags: `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`
- Viewports: mobile (390×844) + desktop (1366×768)
- Violations đánh dấu theo mức: critical, serious, moderate, minor

#### 3.2.2 Block Merge Conditions

```yaml
on:
  push:
    branches: [master]
  pull_request:
    branches: [master]
    paths:
      - 'views/**'
      - 'public/assets/css/**'
      - 'public/assets/js/**'
      - 'public/**/*.php'  # nếu output HTML
```

**Khi có thay đổi trong paths trên:**
- CI phải xanh hoàn toàn
- Không dùng `continue-on-error`

**Khi không có thay đổi:**
- Vẫn chạy CI nhưng không block merge
- Có thể dùng `continue-on-error`

#### 3.2.3 CI Impact (Phase 2)

- **Thời gian:** +2 phút

---

### Phase 3: PR-3 - Visual Regression + Print Testing

**Mục tiêu:** Thêm so ảnh để bắt thay đổi phong cách, kiểm tra print.php.

#### 3.3.1 Visual Regression (Percy/Loki)

```yaml
visual-regression:
  if: changes-in(['views/', 'public/assets/css/'])
  steps:
    - percy snapshot ./tests/e2e/ui_matrix.js
```

**Cấu hình:**
- Chỉ chạy khi có thay đổi views/ hoặc CSS
- Ignore dynamic content (timestamps, random IDs)
- Baseline stored trong Percy project

#### 3.3.2 Print Testing

```
tests/e2e/print_check.js
├── Desktop viewport (1366×768)
├── Kiểm tra @media print CSS
├── Nội dung không bị cắt
└── Screenshot @media print
```

**Elements cần kiểm:**
- Phiếu điểm danh (attendance)
- Thẻ thiếu nhi (students)
- Báo cáo PDF export

#### 3.3.3 CI Impact (Phase 3)

- **Thời gian:** +3 phút

---

## 4. Tổng Kết CI Pipeline

| Phase | Nội dung | Thời gian CI |
|-------|----------|--------------|
| Hiện tại | 6 pages, 3 viewports, 60 tests | ~2.5 phút |
| Phase 1 | +5 modules, +3 viewports | ~8-10 phút |
| Phase 2 | +Axe integration | +2 phút |
| Phase 3 | +Percy + Print check | +3 phút |
| **Tổng** | | **~13-15 phút** |

---

## 5. Acceptance Criteria

### Phase 1
- [ ] Tất cả 5 modules mới được kiểm tra
- [ ] 3 viewports mới (iPhone SE, Tablet, Màn thấp) hoạt động
- [ ] Popup testing cho mỗi module
- [ ] Screenshot artifacts được upload
- [ ] JSON report đầy đủ thông tin

### Phase 2
- [ ] Axe chạy trong CI job chính
- [ ] Violations được export vào axe_results.json
- [ ] CI block merge khi có violations liên quan đến views/CSS
- [ ] CI không block khi không có changes liên quan

### Phase 3
- [ ] Percy snapshots được capture cho mỗi PR
- [ ] Visual diff được báo cáo (nếu có)
- [ ] print.php được kiểm tra
- [ ] @media print CSS không có lỗi

---

## 6. Checklist Thử Tay (Trước Release)

Sau khi merge mỗi phase, test trên 3 máy thật:
- [ ] iPhone (Safari) - báo cáo kết quả
- [ ] Android (Chrome) - báo cáo kết quả  
- [ ] Desktop (Chrome/Firefox) - báo cáo kết quả

---

## 7. Phụ Lục: Cấu Hình Playwright Hiện Tại

```javascript
// Viewports hiện tại
const viewports = {
  mobile: { width: 390, height: 844 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1366, height: 768 }
};

// Seed accounts
const roles = {
  admin: ['0901000001', 'tntt@2026'],
  glv: ['0911000004', 'Test@1234'],
  thu_thu: ['0911000006', 'Test@1234']
};

// Audit checks
const checks = ['bi-cat', 'bi-che', 'tuong-phan', 'tran-ngang'];
```

---

## 8. Ghi Chú

- Issue gốc: #231
- PR hiện tại: #228 (bộ test cơ bản)
- Các lỗi đã fix: #222, #226, #227
