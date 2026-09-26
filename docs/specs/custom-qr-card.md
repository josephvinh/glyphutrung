# SPEC: Custom QR Card - Thẻ Học Sinh Tùy Chỉnh

**Phiên bản:** 1.0
**Ngày:** 2026-09-20
**Trạng thái:** Draft
**Tác giả:** Claude Opus 5.5

---

## 1. Overview

### 1.1 Mô tả Feature
Module **Custom QR Card** cho phép giáo viên (GLV) tạo và xuất thẻ QR cá nhân hóa cho học sinh trong lớp mình phụ trách. Thẻ QR chứa `student_code` để check-in/điểm danh, có thể tùy chỉnh về hình ảnh, màu sắc, kích thước, text và xuất ra nhiều định dạng (PNG, SVG, PDF).

### 1.2 Mục tiêu
- Giáo viên tạo thẻ QR cho học sinh trong lớp được phân công
- Hỗ trợ nhiều template có sẵn và tùy chỉnh cao
- Preview trực quan trước khi xuất
- Xuất đa định dạng: PNG, SVG, PDF, in trực tiếp

### 1.3 Phạm vi người dùng
- **GLV (Giáo Lớp Viên)**: Tạo thẻ cho học sinh trong lớp được phân công
- **Trưởng Khối**: Tạo thẻ cho học sinh trong khối
- **BĐH (Ban Điều Hành)**: Tạo thẻ cho toàn đoàn
- **Admin**: Full access

---

## 2. Requirements

### 2.1 Core Features

#### 2.1.1 Chọn Học Sinh
- [ ] Chọn theo lớp (mặc định)
- [ ] Chọn theo khối
- [ ] Chọn tất cả
- [ ] Checkbox chọn/bỏ chọn từng em
- [ ] Checkbox "Chọn tất cả" với counter

#### 2.1.2 Custom Options

##### Hình Ảnh/Logo
- [ ] Upload logo/tổ chức image (JPG, PNG, SVG)
- [ ] Kích thước logo: nhỏ (15mm), trung bình (20mm), lớn (25mm)
- [ ] Vị trí logo: trên QR, dưới QR, trong QR (logo ở giữa QR code)

##### Templates Có Sẵn
- [ ] **Basic**: QR + tên + mã số
- [ ] **Classic**: Viền trang trí, logo đoàn
- [ ] **Badge**: Thẻ đeo (móc treo)
- [ ] **Compact**: Nhỏ gọn (in cắt dán)
- [ ] **Minimal**: Tối giản, chỉ QR + tên

##### Màu Sắc
- [ ] Màu QR: đen (default), xanh navy, xanh lá, nâu
- [ ] Màu nền thẻ: trắng (default), vàng nhạt, xanh nhạt, hồng nhạt
- [ ] Màu text: đen, xám đậm, xanh navy
- [ ] Color picker để chọn màu tùy ý (hex)

##### Kích Thước
- [ ] QR size: 20mm, 25mm, 30mm, 35mm
- [ ] Font size text: nhỏ, trung bình, lớn

##### Text Trên Thẻ
- [ ] Tên thánh (toggle)
- [ ] Họ tên (toggle)
- [ ] Mã số (toggle, luôn bật)
- [ ] Lớp (toggle)
- [ ] Khối (toggle)
- [ ] Ngày sinh (toggle)
- [ ] Niên khóa (auto)
- [ ] Tiêu đề đoàn (custom text)

##### Xuất
- [ ] Xuất PNG (single/multiple)
- [ ] Xuất SVG (vector, scalable)
- [ ] Xuất PDF (một trang nhiều thẻ)
- [ ] In trực tiếp (print)

### 2.2 Preview System
- [ ] Real-time preview khi thay đổi options
- [ ] Hiển thị tối đa 6 thẻ preview
- [ ] Zoom in/out preview
- [ ] Toggle dark/light preview

### 2.3 Lưu Preset
- [ ] Lưu preset tùy chỉnh (name, options)
- [ ] Load preset đã lưu
- [ ] Delete preset
- [ ] Preset mặc định cho từng người dùng

---

## 3. API Design

### 3.1 Endpoints

#### `POST /api/custom-qrcard.php`

##### Actions

**`preview`**
- **Purpose**: Generate preview HTML for selected students
- **Request:**
```json
{
  "action": "preview",
  "student_ids": [1, 2, 3],
  "options": {
    "template": "classic",
    "logo": "org_default",
    "logo_size": "medium",
    "logo_position": "top",
    "qr_color": "#000000",
    "bg_color": "#ffffff",
    "text_color": "#1e293b",
    "qr_size": "25mm",
    "font_size": "medium",
    "fields": ["code", "name", "className"],
    "header_text": "Thiếu Nhi Thánh Thể"
  }
}
```
- **Response:**
```json
{
  "ok": true,
  "html": "<style>...</style><div class='luoi'>...</div>",
  "count": 3
}
```

**`export_png`**
- **Purpose**: Export QR cards as PNG
- **Request:**
```json
{
  "action": "export_png",
  "student_ids": [1, 2, 3],
  "options": { ... },
  "format": "single" | "combined"
}
```
- **Response:** Binary PNG file

**`export_svg`**
- **Purpose**: Export QR cards as SVG
- **Response:** Binary SVG file or ZIP of SVGs

**`export_pdf`**
- **Purpose**: Export QR cards as PDF
- **Request:**
```json
{
  "action": "export_pdf",
  "student_ids": [1, 2, 3],
  "options": { ... },
  "page_size": "A4",
  "cards_per_page": 6
}
```
- **Response:** Binary PDF file

**`save_preset`**
- **Purpose**: Save custom preset
- **Request:**
```json
{
  "action": "save_preset",
  "name": "Thẻ lớp 3TN",
  "options": { ... },
  "is_default": false
}
```
- **Response:**
```json
{
  "ok": true,
  "id": 5
}
```

**`list_presets`**
- **Purpose**: Get user's presets
- **Response:**
```json
{
  "ok": true,
  "presets": [
    {
      "id": 1,
      "name": "Thẻ đeo",
      "options": { "template": "badge", ... },
      "is_default": false,
      "created_at": "2026-09-15"
    }
  ]
}
```

**`delete_preset`**
- **Purpose**: Delete a preset
- **Request:**
```json
{
  "action": "delete_preset",
  "id": 5
}
```

**`upload_logo`**
- **Purpose**: Upload custom logo
- **Request:** multipart/form-data with `logo` file
- **Response:**
```json
{
  "ok": true,
  "logo_id": "custom_abc123",
  "url": "/assets/logos/custom_abc123.png"
}
```

### 3.2 Permission Check
```php
// User must have permission to 'qrcard' module (view or edit)
// AND must cover the class(es) of selected students
$chophep = allowed_class_ids($me);
if ($chophep !== null) {
    foreach ($studentIds as $sid) {
        $student = db_one("SELECT class_id FROM enrollments WHERE student_id = ?", [$sid]);
        if (!in_array($student['class_id'], $chophep)) {
            json_fail('Bạn không có quyền tạo thẻ cho em này.', 403);
        }
    }
}
```

---

## 4. Database Schema

### 4.1 New Tables

#### `qr_card_templates`
Lưu các template có sẵn (system-defined).

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| code | VARCHAR(50) | Template code (basic, classic, badge, compact, minimal) |
| name | VARCHAR(100) | Display name |
| options | JSON | Default options |
| is_active | TINYINT | 1 = active |

#### `qr_card_presets`
Lưu presets của người dùng.

| Column | Type | Description |
|--------|------|-------------|
| id | INT PK | Auto-increment |
| member_id | INT FK | Người tạo preset |
| name | VARCHAR(100) | Tên preset |
| options | JSON | Các tùy chọn đã lưu |
| is_default | TINYINT | 1 = default preset |
| created_at | DATETIME | Ngày tạo |
| updated_at | DATETIME | Ngày cập nhật |

#### `qr_card_logos`
Lưu logo tùy chỉnh.

| Column | Type | Description |
|--------|------|-------------|
| id | VARCHAR(50) PK | UUID/unique code |
| member_id | INT FK | Người upload |
| filename | VARCHAR(255) | Tên file |
| original_name | VARCHAR(255) | Tên gốc |
| mime_type | VARCHAR(50) | MIME type |
| size | INT | File size bytes |
| uploaded_at | DATETIME | Ngày upload |

### 4.2 Indexes
```sql
-- qr_card_presets
INDEX idx_member_id (member_id)

-- qr_card_logos
INDEX idx_member_id (member_id)
```

### 4.3 Foreign Keys
```sql
ALTER TABLE qr_card_presets
ADD FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE;

ALTER TABLE qr_card_logos
ADD FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE;
```

---

## 5. Frontend Design

### 5.1 Module Entry Point
Màn hình Custom QR Card được mở từ:
1. Menu chính -> Module "Thẻ QR" -> Tab "Tùy chỉnh"
2. Từ module Attendance -> Nút "Tạo thẻ QR"

### 5.2 Layout Structure
```
┌─────────────────────────────────────────────────────────────┐
│  ← Quay lại                    Tạo Thẻ QR Tùy Chỉnh       │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────────┐  ┌──────────────────────────────┐ │
│  │ TUỲ CHỌN            │  │ PREVIEW                     │ │
│  │ ─────────────────── │  │ ──────────────────────────── │ │
│  │ [Chọn học sinh    ▼]│  │                              │ │
│  │  ☐ Tất cả em (25)   │  │   ┌──────┐ ┌──────┐        │ │
│  │  ☑ Em 1             │  │   │ QR   │ │ QR   │        │ │
│  │  ☑ Em 2             │  │   │ Name │ │ Name │        │ │
│  │  ☐ Em 3             │  │   └──────┘ └──────┘        │ │
│  │                      │  │                              │ │
│  │ Template:            │  │   ┌──────┐ ┌──────┐        │ │
│  │ [Basic][Classic]...  │  │   │ QR   │ │ QR   │        │ │
│  │                      │  │   │ Name │ │ Name │        │ │
│  │ Logo: [Upload]       │  │   └──────┘ └──────┘        │ │
│  │                      │  │                              │ │
│  │ Màu QR: [████]      │  │  Hiển thị 4/25 thẻ        │ │
│  │ Màu nền: [████]     │  │                              │ │
│  │                      │  │  [Zoom -][Zoom +]           │ │
│  │ Kích thước: [▼ 25mm]│  │                              │ │
│  │                      │  └──────────────────────────────┘ │
│  │ Thông tin hiển thị: │                                    │
│  │ ☐ Tên thánh         │  ┌──────────────────────────────┐ │
│  │ ☑ Họ tên            │  │ PRESETS                      │ │
│  │ ☑ Mã số             │  │ [preset 1] [preset 2] [+Save]│ │
│  │ ☐ Ngày sinh         │  └──────────────────────────────┘ │
│  │                      │                                    │
│  │ Tiêu đề: [________]  │  ┌──────────────────────────────┐ │
│  │                      │  │ XUẤT THẺ                     │ │
│  │ ─────────────────── │  │ [PNG] [SVG] [PDF] [In]        │ │
│  │ [Lưu Preset]         │  └──────────────────────────────┘ │
│  └─────────────────────┘                                   │
└─────────────────────────────────────────────────────────────┘
```

### 5.3 Alpine.js State
```javascript
window.TNTT = window.TNTT || {};
window.TNTT.customQrcard = {
    // State
    selectedStudentIds: [],
    availableStudents: [],
    scopeType: 'class',       // 'class' | 'block' | 'all'
    scopeValue: '',

    // Options
    options: {
        template: 'basic',
        logo: null,            // logo_id or null
        logoSize: 'medium',    // 'small' | 'medium' | 'large'
        logoPosition: 'top',   // 'top' | 'bottom' | 'center'
        qrColor: '#000000',
        bgColor: '#ffffff',
        textColor: '#1e293b',
        qrSize: '25mm',
        fontSize: 'medium',
        fields: ['code', 'name'],
        headerText: ''
    },

    // Presets
    presets: [],
    selectedPresetId: null,
    newPresetName: '',

    // UI State
    isLoading: false,
    previewZoom: 100,

    // Computed
    get previewStudents() {
        return this.availableStudents
            .filter(s => this.selectedStudentIds.includes(s.id))
            .slice(0, 6);
    },

    get canExport() {
        return this.selectedStudentIds.length > 0 && !this.isLoading;
    },

    // Methods
    async loadStudents() { ... },
    async generatePreview() { ... },
    async exportPNG() { ... },
    async exportSVG() { ... },
    async exportPDF() { ... },
    async print() { ... },
    async savePreset() { ... },
    async loadPresets() { ... },
    applyPreset(preset) { ... },
    updateOption(key, value) { ... },
};
```

### 5.4 Component Styling
Tuân thủ design system hiện có:
- Border radius: `rounded-card` (12px) hoặc `rounded-xl` (16px)
- Shadows: `shadow-sm`
- Spacing: Tailwind utilities
- Colors: Tailwind color palette (slate, blue, emerald, amber, rose)
- Font: Be Vietnam Pro

---

## 6. Template System

### 6.1 Built-in Templates

#### Basic
```html
┌─────────────────────┐
│ [Logo - optional]   │
│                     │
│    ┌─────────┐      │
│    │  QR     │      │
│    └─────────┘      │
│                     │
│  Mã: GDGLPT260001   │
│  Nguyễn Văn A       │
│  Lớp: 3TN           │
└─────────────────────┘
```

#### Classic
```html
┌─────────────────────┐
│ ╔═══════════════╗   │
│ ║  Logo/Emblem  ║   │
│ ╚═══════════════╝   │
│                     │
│    ┌─────────┐      │
│    │  QR     │      │
│    └─────────┘      │
│                     │
│  Thiếu Nhi Thánh   │
│  Thể - Phú Trung   │
│                     │
│  Nguyễn Văn A       │
│  Lớp 3TN · Mã 001  │
└─────────────────────┘
```

#### Badge (Thẻ đeo)
```html
┌─────────────────────┐
│    ┌─────────┐      │
│    │  QR     │      │
│    └─────────┘      │
│                     │
│  Nguyễn Văn A       │
│  3TN · 001          │
└─────────────────────┘
│  [Lỗ treo]          │
└─────────────────────┘
```

#### Compact (In cắt dán)
```html
┌───┬───┬───┬───┐
│QR │QR │QR │QR │
│   │   │   │   │
│Nam│Nam│Nam│Nam│
└───┴───┴───┴───┘
```
- Grid layout: 2, 3, hoặc 4 cột
- Không có viền đẹp, chỉ viền cắt để tách

#### Minimal
```html
┌───────┐
│  QR   │
│       │
│ Name  │
└───────┘
```
- Tối giản nhất
- Không logo, không viền trang trí

### 6.2 Template Options Schema
```javascript
const templateDefaults = {
    basic: {
        showLogo: false,
        logoPosition: 'top',
        qrSize: '25mm',
        border: 'solid',
        borderRadius: '4mm',
        padding: '3mm',
        fields: ['code', 'name', 'className'],
    },
    classic: {
        showLogo: true,
        logoPosition: 'top',
        qrSize: '25mm',
        border: 'double',
        borderRadius: '6mm',
        padding: '4mm',
        fields: ['holyName', 'name', 'className', 'code'],
        showEmblem: true,
    },
    badge: {
        showLogo: false,
        qrSize: '30mm',
        border: 'solid',
        borderRadius: '50%', // Round badge
        padding: '2mm',
        fields: ['name', 'className'],
        holePunch: true,
    },
    compact: {
        showLogo: false,
        qrSize: '20mm',
        border: 'dashed',
        borderRadius: '2mm',
        padding: '2mm',
        fields: ['code', 'name'],
        gridColumns: 3,
    },
    minimal: {
        showLogo: false,
        qrSize: '25mm',
        border: 'none',
        borderRadius: '0',
        padding: '1mm',
        fields: ['name'],
    },
};
```

---

## 7. Integration

### 7.1 Với module_qrcard.php Hiện Tại

Module hiện tại (`module_qrcard.php`) giữ nguyên chức năng "In thẻ QR nhanh" với các template cố định. Custom QR Card là module mới, mở rộng thêm.

```
┌─────────────────────────────────────────┐
│  Thẻ QR                                │
│  ├── Tab: In nhanh (hiện tại)          │
│  │   └── module_qrcard.php             │
│  └── Tab: Tùy chỉnh (mới)             │
│      └── module_custom_qrcard.php      │
└─────────────────────────────────────────┘
```

### 7.2 Với Module Attendance

QR code từ Custom Card vẫn tương thích 100% với module Attendance:
- QR chứa `student_code`
- Scan QR = check-in bình thường

### 7.3 File Structure
```
public/
├── api/
│   ├── custom-qrcard.php      # API endpoint mới
│   └── custom-qrcard-logo.php # Upload logo handler

views/
├── module_custom_qrcard.php   # Main view
└── partial_custom_qrcard/
    ├── _student_selector.php   # Component chọn học sinh
    ├── _options_panel.php      # Component tùy chọn
    ├── _template_picker.php    # Component chọn template
    ├── _color_picker.php       # Component chọn màu
    ├── _preset_manager.php     # Component quản lý preset
    └── _export_panel.php      # Component xuất file

public/assets/
├── css/
│   └── custom-qrcard.css     # Styles riêng
└── js/
    └── modules/
        └── custom-qrcard.js   # Logic Alpine.js

public/uploads/logos/         # Logo uploaded files
```

### 7.4 Dependencies
- **QR Library**: qrcode.min.js (đã có)
- **Canvas-to-Blob**: for PNG export (polyfill if needed)
- **jsPDF**: for PDF export (cdn hoặc vendor)
- **FileSaver.js**: for download handling

---

## 8. Acceptance Criteria

### 8.1 Functional Requirements

| ID | Criteria | Test Method |
|----|----------|-------------|
| AC-01 | Người dùng có thể chọn học sinh từ lớp được phân công | Manual test |
| AC-02 | QR preview cập nhật real-time khi thay đổi options | Manual test |
| AC-03 | Export PNG tạo file hình ảnh đúng kích thước | Visual inspection |
| AC-04 | Export SVG tạo file vector không mất chất lượng | Zoom test |
| AC-05 | Export PDF xếp đúng số thẻ/trang | Print preview |
| AC-06 | Print mở dialog in đúng nội dung | Print test |
| AC-07 | Preset lưu và load đúng options | Save/load test |
| AC-08 | Logo upload và hiển thị đúng vị trí | Upload test |
| AC-09 | Phân quyền chặn đúng user không có quyền | Role test |
| AC-10 | QR scan vẫn hoạt động với thẻ tùy chỉnh | Scan test |

### 8.2 Non-Functional Requirements

| ID | Criteria | Target |
|----|----------|--------|
| NF-01 | Preview load < 500ms với 25 students | Performance |
| NF-02 | Export 25 cards PNG < 3s | Performance |
| NF-03 | Giao diện responsive trên mobile | UI/UX |
| NF-04 | Logo upload max 2MB, định dạng jpg/png/svg | Validation |
| NF-05 | QR vẫn đọc được dù có logo center | Compatibility |

### 8.3 Security Requirements

| ID | Criteria |
|----|----------|
| SEC-01 | CSRF protection trên mọi POST |
| SEC-02 | Chỉ user có quyền mới tạo thẻ cho học sinh |
| SEC-03 | Logo upload validate MIME type |
| SEC-04 | File upload size limit enforced |
| SEC-05 | Path traversal prevention trong file handling |

---

## 9. Test Cases

### 9.1 Unit Tests

#### TC-01: QR Generation
```javascript
test('QR chứa đúng student_code', () => {
    const qr = generateQR('GDGLPT260001');
    expect(qr).toContain('GDGLPT260001');
    expect(qr.length).toBeLessThan(1000); // Size limit
});
```

#### TC-02: Color Validation
```javascript
test('Color picker chấp nhận hex hợp lệ', () => {
    expect(isValidHex('#FF5733')).toBe(true);
    expect(isValidHex('#fff')).toBe(true);
    expect(isValidHex('#GGG')).toBe(false);
    expect(isValidHex('red')).toBe(false);
});
```

#### TC-03: Options Serialization
```javascript
test('Options serialize đúng format', () => {
    const opts = { template: 'basic', qrColor: '#000' };
    const json = JSON.stringify(opts);
    expect(() => JSON.parse(json)).not.toThrow();
});
```

### 9.2 Integration Tests

#### TC-10: Export Workflow
```
1. Chọn 3 học sinh
2. Chọn template "Classic"
3. Upload logo
4. Click "Xuất PNG"
5. Verify: Download started, file size > 0
6. Verify: File mở được, có 3 QR codes
```

#### TC-11: Preset Save/Load
```
1. Set options: template=badge, qrSize=30mm
2. Click "Lưu Preset"
3. Đặt tên "Thẻ đeo lớp 3"
4. Reload page
5. Load preset "Thẻ đeo lớp 3"
6. Verify: template=badge, qrSize=30mm
```

### 9.3 Permission Tests

#### TC-20: GLV Scope
```
1. Login as GLV_lop3
2. Mở Custom QR Card
3. Verify: Chỉ thấy lớp 3
4. Attempt tạo thẻ cho student lớp 4
5. Verify: Error 403
```

### 9.4 Edge Cases

| ID | Case | Expected |
|----|------|----------|
| EC-01 | Chọn 0 học sinh + click Export | Error message |
| EC-02 | Upload logo 3MB | Error: max 2MB |
| EC-03 | Logo định dạng .exe | Error: invalid type |
| EC-04 | QR với student code dài 20 chars | Vẫn tạo được |
| EC-05 | Network fail khi export | Error message + retry |

---

## 10. Implementation Phases

### Phase 1: Core (MVP)
- Chọn học sinh + basic preview
- 3 templates: Basic, Classic, Badge
- Export PNG + Print
- Basic color customization

### Phase 2: Advanced
- Thêm templates Compact, Minimal
- Full color customization
- Export SVG + PDF
- Preset management

### Phase 3: Polish
- Logo upload system
- Advanced positioning
- Batch export optimization
- Mobile optimization

---

## 11. References

- Existing module: `views/module_qrcard.php`
- JS Logic: `public/assets/js/modules/qrcard.js`
- API Pattern: `public/api/_bootstrap.php`
- Permission System: `public/api/_common.php`
- Attendance QR: `views/module_attendance.php`

---

## 12. Open Questions

1. Logo center overlay có che QR modules không? Cần dùng error correction level cao hơn (H thay vì M)?
2. PDF export dùng thư viện gì? jsPDF hay PHP-based (TCPDF/Dompdf)?
3. Có cần watermark "Generated by TNTT" không?
4. Preset có chia sẻ giữa các user được không?
5. Logo mặc định (logo đoàn) lấy từ đâu?
