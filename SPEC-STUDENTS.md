# SPEC: Nâng cấp Module Thiếu Nhi

> **Issue:** [#25](https://github.com/josephvinh/glyphutrung/issues/25)  
> **Status:** Draft  
> **Target:** Dễ sử dụng - Tiện lợi - Mượt

---

## 1. Overview

Module Thiếu Nhi hiện tại đã có các tính năng cơ bản (CRUD, tìm kiếm, lọc, import/export CSV). Spec này mở rộng để nâng cao trải nghiệm người dùng với các tính năng mới.

---

## 2. User Stories

### 2.1 Người dùng cơ bản (Giáo lý viên)

| # | Story | Acceptance Criteria |
|---|-------|-------------------|
| US-01 | Tôi muốn xem danh sách em dưới dạng lưới (grid) để dễ nhìn hơn | Có nút chuyển đổi Grid/List view |
| US-02 | Tôi muốn lọc nhanh theo tuổi, giới tính, địa chỉ | Bộ lọc nâng cao hiển thị trong panel filter |
| US-03 | Tôi muốn copy số điện thoại cha/mẹ chỉ 1 click | Nút copy xuất hiện khi hover vào số điện thoại |

### 2.2 Người quản lý (Ban Điều Hành)

| # | Story | Acceptance Criteria |
|---|-------|-------------------|
| US-04 | Tôi muốn chọn nhiều em để xóa/chuyển lớp hàng loạt | Checkbox selection + bulk action bar |
| US-05 | Tôi muốn xuất danh sách ra PDF để in | Nút Export PDF, file có header thông tin lớp/niên khoá |
| US-06 | Tôi muốn đánh dấu em cần theo dõi đặc biệt | Icon star/favorite trên card em |

### 2.3 Người dùng nâng cao

| # | Story | Acceptance Criteria |
|---|-------|-------------------|
| US-07 | Tôi muốn sửa nhanh thông tin trực tiếp trên danh sách | Double-click để edit inline |
| US-08 | Tôi muốn dùng phím tắt để điều hướng nhanh | J/K để di chuyển, E để edit |

---

## 3. Features

### 3.1 Grid/List Toggle View

**Mô tả:** Cho phép chuyển đổi giữa view dạng lưới (card) và danh sách (table).

**UI:**
```
┌─────────────────────────────────────────────────┐
│ [Grid] [List]  ← Toggle buttons                 │
├─────────────────────────────────────────────────┤
│ Grid:  Card layout (hiện tại)                   │
│ List:  Table layout với columns có thể sort     │
└─────────────────────────────────────────────────┘
```

**Implementation:**
- Thêm button group toggle trong toolbar
- CSS: `.view-grid` / `.view-list`
- State: `localStorage.setItem('studentsViewMode', 'grid'|'list')`

**Priority:** Cao

---

### 3.2 Bulk Selection & Actions

**Mô tả:** Cho phép chọn nhiều em để thực hiện hành động hàng loạt.

**UI:**
```
┌─────────────────────────────────────────────────┐
│ ☐ Select all  │  Đã chọn: 5 em  │ [Chuyển lớp] [Xóa] │
├─────────────────────────────────────────────────┤
│ ☐ Card 1                                        │
│ ☐ Card 2                                        │
└─────────────────────────────────────────────────┘
```

**Actions:**
| Action | API Endpoint | Notes |
|--------|--------------|-------|
| Chuyển lớp | `POST /api/students.php?action=bulk_move` | Chọn lớp đích |
| Xóa | `POST /api/students.php?action=bulk_delete` | Confirm dialog |

**API Changes:**
```php
// POST api/students.php?action=bulk_move
{ student_ids: [1,2,3], target_class: "A1" }

// POST api/students.php?action=bulk_delete  
{ student_ids: [1,2,3] }
```

**Priority:** Cao

---

### 3.3 Enhanced Filters

**Mô tả:** Bổ sung bộ lọc theo tuổi, giới tính, địa chỉ.

**UI - Mở rộng panel filter hiện tại:**
```
┌─────────────────────────────────────────────────┐
│ Khối: [Tất cả      ▼]                          │
│ Lớp:  [Tất cả      ▼]                          │
│ Tình trạng: [Tất cả ▼]                         │
│ ─────────────────────────────────────────────── │
│ Tuổi:  [Từ ▼] - [Đến ▼]                       │
│ Giới tính: [Tất cả ▼] [Nam] [Nữ]               │
│ Địa chỉ: [________________]                    │
│                            [Áp dụng] [Xóa lọc]  │
└─────────────────────────────────────────────────┘
```

**Filter Options:**
| Field | Type | Values |
|-------|------|--------|
| age | range | Tính từ birthDate |
| gender | select | Nam / Nữ |
| address | text | Partial match |

**Priority:** Cao

---

### 3.4 Quick Actions

**Mô tả:** Các action nhanh trên mỗi card em.

**UI:**
```
┌──────────────────────────┐
│ ☎️ [📋] [✏️] [⭐]        │  ← Hover actions
│ Tên Em                    │
│ Lớp A1 • Đang sinh hoạt   │
└──────────────────────────┘
```

**Actions:**
| Icon | Action | Behavior |
|------|--------|----------|
| ☎️ | Gọi điện | `tel:` link |
| 📋 | Copy phone | Toast "Đã copy số điện thoại" |
| ✏️ | Sửa nhanh | Open edit modal |
| ⭐ | Đánh dấu | Toggle favorite (localStorage + API) |

**Priority:** Cao

---

### 3.5 Inline Quick Edit

**Mô tả:** Sửa nhanh thông tin trực tiếp trên danh sách.

**UI:**
```
┌─────────────────────────────────────────────────┐
│ Double-click vào field để edit:                │
│ Tên: [Lm. Gioan Baotixita          ▼]          │
│ Lớp:  [A1 ▼]                                  │
│ Địa chỉ: [123 Nguyễn Trãi           ]          │
└─────────────────────────────────────────────────┘
```

**Fields có thể inline edit:**
- `holyName`, `name`, `className`, `status`

**Validation:** Same như form modal hiện tại

**Priority:** Trung

---

### 3.6 Auto-save Form

**Mô tả:** Tự động lưu form khi nhập liệu.

**UI:**
```
┌─────────────────────────────────────────────────┐
│ [Draft saved ✓]  ← Auto-save indicator          │
│ Tên: [________________]                         │
│ ...                                             │
└─────────────────────────────────────────────────┘
```

**Behavior:**
- Debounce 2s sau khi gõ
- Lưu vào `localStorage` với key `student_draft_{id}`
- Xóa draft khi submit thành công
- Restore draft khi mở lại form

**Priority:** Trung

---

### 3.7 Export PDF

**Mô tả:** Xuất danh sách ra PDF.

**UI:**
```
┌─────────────────────────────────────────────────┐
│ [📄 PDF] ▼  ← Dropdown options                 │
│   ├─ Danh sách lớp (1 trang)                   │
│   ├─ Danh sách có ảnh (nhiều trang)            │
│   └─ Thẻ QR từng em                            │
└─────────────────────────────────────────────────┘
```

**Template:**
```html
<!-- Header -->
Logo • Tên Giáo xứ • Niên khoá 2025-2026
Danh sách lớp A1 - Thiếu nhi Thánh Thể
Ngày in: 25/09/2026 • Tổng: 30 em

<!-- Table -->
STT | Mã số | Tên thánh | Họ tên | GT | Tuổi | Ghi chú
```

**Implementation:**
- Dùng jsPDF hoặc print CSS
- Tái sử dụng template print hiện có

**Priority:** Trung

---

### 3.8 Favorites/Star

**Mô tả:** Đánh dấu em cần theo dõi đặc biệt.

**UI:**
```
⭐ Card nổi bật với border vàng
```

**Implementation:**
```php
// Thêm bảng favorites
CREATE TABLE student_favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    user_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**API:**
```php
// GET/POST/DELETE api/students.php?action=favorites
```

**Priority:** Trung

---

### 3.9 Virtual Scrolling (Performance)

**Mô tả:** Lazy load khi danh sách > 100 em.

**Implementation:**
- Intersection Observer API
- Render chunk 20 items
- Infinite scroll pagination

**Priority:** Thấp

---

### 3.10 Keyboard Shortcuts

**Mô tả:** Phím tắt cho thao tác nhanh.

| Key | Action |
|-----|--------|
| `/` | Focus search |
| `J` | Select next |
| `K` | Select previous |
| `E` | Edit selected |
| `Delete` | Delete selected |
| `Esc` | Clear selection |

**Priority:** Thấp

---

## 4. UI/UX Design

### 4.1 Layout

```
┌────────────────────────────────────────────────────────────────┐
│ [Tabs: Thiếu Nhi | Lớp Giáo Lý | Điểm Danh | Lịch Sử | QR]   │
├────────────────────────────────────────────────────────────────┤
│ [🔍 Tìm tên...] [⚙️ Filter]    [📊 Stats] [Grid][List] [➕]  │
├────────────────────────────────────────────────────────────────┤
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐             │
│ │ ☐ ⭐ Tên em │ │ ☐ ⭐ Tên em │ │ ☐ ⭐ Tên em │             │
│ │   Lớp A1    │ │   Lớp A1    │ │   Lớp A1    │             │
│ │   📞 ☎️ ✏️  │ │   📞 ☎️ ✏️  │ │   📞 ☎️ ✏️  │             │
│ └─────────────┘ └─────────────┘ └─────────────┘             │
├────────────────────────────────────────────────────────────────┤
│ ◄ Trang 1/5 ►                    Tổng: 150 em                 │
└────────────────────────────────────────────────────────────────┘
```

### 4.2 Component States

| State | Visual |
|-------|--------|
| Default | White card, subtle shadow |
| Hover | Light blue background, show action buttons |
| Selected | Blue border, checkbox checked |
| Favorite | Yellow star, subtle gold border |
| Disabled | Gray out |

### 4.3 Responsive

| Breakpoint | Layout |
|------------|--------|
| Mobile (<640px) | 1 column, swipe navigation |
| Tablet (640-1024px) | 2 columns grid |
| Desktop (>1024px) | 3-4 columns grid |

---

## 5. API Changes

### 5.1 New Endpoints

| Action | Method | Request | Response |
|--------|--------|---------|----------|
| `bulk_move` | POST | `{ student_ids: [], target_class: "" }` | `{ success: true, moved: 5 }` |
| `bulk_delete` | POST | `{ student_ids: [] }` | `{ success: true, deleted: 3 }` |
| `favorites` | GET | - | `{ favorites: [1,2,3] }` |
| `favorites` | POST | `{ student_id: 1 }` | `{ success: true }` |
| `favorites` | DELETE | `{ student_id: 1 }` | `{ success: true }` |

### 5.2 Enhanced List Response

```json
{
  "students": [...],
  "meta": {
    "total": 150,
    "filters": { "block": "A", "class": "A1" }
  }
}
```

---

## 6. Technical Approach

### 6.1 Stack

| Layer | Technology |
|-------|------------|
| Backend | PHP 8+ (existing) |
| Frontend | Alpine.js (existing) |
| Styling | Tailwind CSS (existing) |
| PDF | jsPDF / print CSS |
| Icons | Lucide Icons (existing) |

### 6.2 File Changes

```
Modified:
├── views/module_students.php      # Add grid/list toggle, bulk actions
├── public/api/students.php        # Add bulk & favorites endpoints
└── public/assets/js/modules/students.js  # Add view switching, bulk logic

New:
├── public/api/students_export.php  # PDF generation
└── components/student_card_grid.php # Grid layout component
```

### 6.3 Database Changes

```sql
CREATE TABLE student_favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (student_id, user_id)
);
```

---

## 7. Implementation Order

### Phase 1: Core UX (1-2 days)
1. Grid/List Toggle View
2. Enhanced Filters (age, gender, address)
3. Quick Actions (copy phone, edit)

### Phase 2: Bulk Operations (1 day)
4. Bulk Selection UI
5. Bulk Move/Delete API
6. Confirmation dialogs

### Phase 3: Polish (1 day)
7. Favorites
8. Auto-save Draft
9. Keyboard Shortcuts

### Phase 4: Advanced (Optional)
10. Export PDF
11. Virtual Scrolling
12. Dark Mode

---

## 8. Open Questions

- [ ] Có cần favorites per-user hay global?
- [ ] PDF template có cần approval từ Ban?
- [ ] Thứ tự ưu tiên chính xác là gì?

---

*Document created for Issue #25*
