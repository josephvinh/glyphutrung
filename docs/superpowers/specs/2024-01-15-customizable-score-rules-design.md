# Spec: Hệ Thống Quy Tắc Điểm Tuỳ Chỉnh

> **Ngày:** 2024-01-15
> **Tác giả:** Claude Code
> **Trạng thái:** Approved

## 1. Tổng Quan

### 1.1 Mục tiêu

Cho phép Admin và BĐH cấu hình toàn hệ thống:
- **Điểm học tập**: thêm loại đầu điểm, đặt trọng số, giới hạn số bài kiểm tra mỗi loại
- **Điểm chuyên cần**: chọn chương trình tính theo từng học kỳ, đặt các mốc xếp loại

GLV tự thêm các bài kiểm tra cụ thể trong phạm vi cho phép.

### 1.2 Phạm vi

- **Phạm vi rules:** Toàn hệ thống (một bộ quy tắc dùng chung)
- **Phân quyền:** Admin + BĐH sửa rules; ai cũng xem

---

## 2. Mô Hình Dữ Liệu (Schema)

### 2.1 Bảng `score_types` — Cập nhật

Thêm cột:

```sql
ALTER TABLE score_types 
    ADD COLUMN category VARCHAR(32) NOT NULL DEFAULT 'homework'
    COMMENT 'homework=điểm BT, exam=điểm thi, attendance=chuyên cần';

ALTER TABLE score_types 
    ADD COLUMN max_exams TINYINT NOT NULL DEFAULT 1 
    COMMENT 'Số bài kiểm tra tối đa cho loại này (1-20)';
```

**Dữ liệu mặc định:**

| code | label | short_label | weight | max_exams | category |
|------|-------|-------------|--------|-----------|----------|
| mieng | Miệng | M | 1 | 3 | homework |
| p15 | 15 phút | 15p | 1 | 4 | homework |
| p45 | 1 tiết | 1T | 2 | 2 | homework |
| giuaky | Giữa kỳ | GK | 2 | 1 | exam |
| cuoiky | Cuối kỳ | CK | 3 | 1 | exam |

### 2.2 Bảng `scores` — Cập nhật

Thêm cột `exam_num`:

```sql
ALTER TABLE scores 
    ADD COLUMN exam_num TINYINT NOT NULL DEFAULT 1 
    COMMENT 'Số thứ tự bài KT trong loại (1, 2, 3...)';

ALTER TABLE scores DROP INDEX uq_score;
ALTER TABLE scores ADD UNIQUE KEY uq_score (term_id, student_id, type_code, exam_num);
```

### 2.3 Bảng `score_rules` — MỚI

```sql
CREATE TABLE IF NOT EXISTS score_rules (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    rule_key      VARCHAR(64) NOT NULL UNIQUE,
    rule_label    VARCHAR(128) NOT NULL,
    rule_type     ENUM('weight','threshold','formula') NOT NULL,
    rule_value    TEXT NOT NULL,
    description   VARCHAR(255) NULL,
    updated_by    INT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sr_by FOREIGN KEY (updated_by) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Dữ liệu mặc định:**

| rule_key | rule_label | rule_type | rule_value |
|----------|-----------|-----------|------------|
| `rank_thresholds` | Ngưỡng xếp loại | threshold | `{"gioi":8,"kha":6.5,"tb":5,"yeu":0}` |
| `passing_min_avg` | Điểm TB tối thiểu | threshold | `{"min":5}` |

### 2.4 Bảng `term_programs` — MỚI

```sql
CREATE TABLE IF NOT EXISTS term_programs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    term_id         INT NOT NULL,
    program_id      INT NOT NULL,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_tp (term_id, program_id),
    CONSTRAINT fk_tp_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_prog FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.5 Bảng `attendance_thresholds` — MỚI

```sql
CREATE TABLE IF NOT EXISTS attendance_thresholds (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sort_order      INT NOT NULL,
    label           VARCHAR(32) NOT NULL,
    min_percent     DECIMAL(5,2) NOT NULL,
    description     VARCHAR(128) NULL,
    CONSTRAINT chk_at_min CHECK (min_percent >= 0 AND min_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Dữ liệu mặc định:**

| sort_order | label | min_percent | description |
|-----------|-------|-------------|-------------|
| 1 | A | 90.00 | Xuất sắc |
| 2 | B | 80.00 | Tốt |
| 3 | C | 70.00 | Khá |
| 4 | Đạt | 60.00 | Tối thiểu |

---

## 3. API Endpoints

### 3.1 `api/rules.php`

```
GET    api/rules.php?type=score          → lấy rules điểm học tập
GET    api/rules.php?type=attendance     → lấy rules chuyên cần
POST   api/rules.php                      → tạo/sửa rule
```

**Phân quyền:** `require_login()` (GET), `require_admin_or_bdh()` (POST)

### 3.2 `api/score_types.php` — CRUD loại đầu điểm

```
GET    api/score_types.php                → lấy danh sách
POST   api/score_types.php                → thêm mới
PUT    api/score_types.php?id=xxx        → sửa
DELETE api/score_types.php?id=xxx        → xóa
```

### 3.3 `api/scores.php` — Cập nhật

```
POST api/scores.php?action=set 
  { studentId, termId, type, exam_num, value }

POST api/scores.php?action=delete_exam
  { studentId, termId, type, exam_num }  → xoá & renumber
```

### 3.4 `api/term_programs.php`

```
GET    api/term_programs.php?term_id=1   → lấy danh sách programs
POST   api/term_programs.php             → cập nhật is_active
```

### 3.5 `api/attendance_thresholds.php`

```
GET    api/attendance_thresholds.php     → lấy danh sách mốc
POST   api/attendance_thresholds.php     → thêm mốc
PUT    api/attendance_thresholds.php?id=x → sửa mốc
DELETE api/attendance_thresholds.php?id=x → xóa mốc
```

### 3.6 `api/attendance_scores.php`

```
GET    api/attendance_scores.php?term_id=1&student_id=101
```

### 3.7 `api/scores.php?action=export` — Mở rộng

```
GET    api/scores.php?action=export&term_id=1&class_id=5
```

Trả file Excel với cột động theo `score_types`.

---

## 4. Logic Tính Điểm

### 4.1 Điểm TB mỗi loại

```javascript
termScoreOfType(studentId, termId, typeCode) {
    // Lấy tất cả exam_num của typeCode cho student/term
    // Trả về mảng điểm đã có
    // TB = sum / count (chỉ tính bài đã chấm)
}
```

### 4.2 Điểm TB học kỳ (Cách A)

```
1. Với mỗi score_type: tính TB loại = avg(các bài đã chấm)
2. ĐTB = Σ(TB_loại × hệ_số_loại) / Σ(hệ_số_loại)
   (chỉ tính loại có ít nhất 1 bài đã chấm)
```

### 4.3 Xếp loại học lực

```javascript
academicRank(avg, thresholds) {
    // thresholds: [{label:"Giỏi", min:8}, {label:"Khá", min:6.5}, ...]
    // Duyệt từ cao → thấp, trả về label đầu tiên có avg >= min
}
```

### 4.4 Điểm chuyên cần

```
1. Lấy term_programs đang active cho term_id
2. Với mỗi program:
   - expected = COUNT DISTINCT session_date trong kỳ (đã điểm danh)
   - present = COUNT WHERE status IN ('có mặt','đi trễ')
3. total_expected = SUM(expected)
4. total_present = SUM(present)
5. percent = (total_present / total_expected) × 100
6. rank = so với attendance_thresholds (từ cao → thấp)
```

### 4.5 Renumber khi xoá bài

```javascript
deleteExam(studentId, termId, type, examNum) {
    // 1. Xoá bài có exam_num = examNum
    // 2. UPDATE các bài có exam_num > examNum: exam_num -= 1
}
```

---

## 5. Frontend

### 5.1 Settings — Tab "Quy Tắc Điểm"

**Vị trí:** Trong màn Cài Đặt (settings), tab mới "Quy Tắc"

**Ai thấy:** Admin + BĐH (tab ẩn với vai trò khác)

**Giao diện:**

```
┌─────────────────────────────────────────────────────┐
│  QUY TẮC ĐIỂM HỌC TẬP                              │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ĐẦU ĐIỂM                                          │
│  ┌───────────────────────────────────────────────┐ │
│  │ Loại đầu điểm  │ Hệ số │ Tối đa │           │ │
│  │ ─────────────────────────────────────────────│ │
│  │ Miệng          │ [ 1 ] │ [ 3 ]  │ [🗑]       │ │
│  │ 15 phút        │ [ 1 ] │ [ 4 ]  │ [🗑]       │ │
│  │ 1 tiết         │ [ 2 ] │ [ 2 ]  │ [🗑]       │ │
│  │ Giữa kỳ       │ [ 2 ] │ [ 1 ]  │ [🗑]       │ │
│  │ Cuối kỳ       │ [ 3 ] │ [ 1 ]  │ [🗑]       │ │
│  ├───────────────────────────────────────────────┤ │
│  │ [+ Thêm loại đầu điểm]                        │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  XẾP LOẠI HỌC LỰC                                  │
│  ┌───────────────────────────────────────────────┐ │
│  │ Giỏi ≥ [8.0]    Khá ≥ [6.5]    TB ≥ [5.0]    │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  [ 💾 Lưu thay đổi ]                               │
└─────────────────────────────────────────────────────┘
```

**Popup thêm loại đầu điểm:**

```
┌─────────────────────────────────────┐
│  THÊM ĐẦU ĐIỂM MỚI                  │
├─────────────────────────────────────┤
│  Mã (key):  [________]             │
│  Tên hiển thị: [________]         │
│  Viết tắt:   [____]                │
│  Hệ số:     [__]                   │
│  Tối đa bài: [__] (1-20)           │
│                                     │
│  [ Hủy ]    [ Thêm ]               │
└─────────────────────────────────────┘
```

### 5.2 Settings — Tab "Quy Tắc Chuyên Cần"

```
┌─────────────────────────────────────────────────────┐
│  QUY TẮC CHUYÊN CẦN                                 │
├─────────────────────────────────────────────────────┤
│                                                     │
│  CHƯƠNG TRÌNH THEO HỌC KỲ                          │
│  ┌───────────────────────────────────────────────┐ │
│  │ Học kỳ: [HK1 2024-2025 ▼]                   │ │
│  │                                               │ │
│  │ ☑ Sinh hoạt T5      (12 buổi)             │ │
│  │ ☑ Lễ Chúa Nhật     (10 buổi)              │ │
│  │ ☐ Học Giáo Lý                          │ │
│  │ ☑ Thi Đua              (5 buổi)           │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  CÁC MỐC XẾP LOẠI                                  │
│  ┌───────────────────────────────────────────────┐ │
│  │ A   ≥ [90]%  Xuất sắc              [✎][🗑] │ │
│  │ B   ≥ [80]%  Tốt                    [✎][🗑] │ │
│  │ C   ≥ [70]%  Khá                    [✎][🗑] │ │
│  │ Đạt ≥ [60]%  Tối thiểu              [✎][🗑] │ │
│  ├───────────────────────────────────────────────┤ │
│  │ [+ Thêm mốc]                                  │ │
│  └───────────────────────────────────────────────┘ │
│                                                     │
│  [ 💾 Lưu thay đổi ]                               │
└─────────────────────────────────────────────────────┘
```

### 5.3 Màn Nhập Điểm — Bảng động

**Thay đổi:**
- Số cột = tổng `max_exams` của tất cả `score_types`
- Header: `Miệng 1 | Miệng 2 | Miệng 3 | 15p 1 | 15p 2 | ... | GK | CK | ĐTB`
- Nút `[+Thêm bài]` chỉ hiện khi `count < max_exams`
- Nút `[🗑]` xoá bài → gọi `delete_exam` → renumber tự động

---

## 6. Migration

### 6.1 File migration: `config/migrations/XXX_score_rules_configurable.sql`

1. ALTER `score_types`: thêm `category`, `max_exams`
2. ALTER `scores`: thêm `exam_num`, cập nhật unique key
3. CREATE `score_rules`
4. CREATE `term_programs`
5. CREATE `attendance_thresholds`
6. Seed dữ liệu mặc định

### 6.2 Cập nhật `config/install.php`

Thêm seed dữ liệu mặc định tương tự migration.

---

## 7. Test Cases

### 7.1 Điểm học tập

| Test | Input | Expected |
|------|-------|----------|
| TB 1 loại | Miệng: [8, 7] → hệ số 1 | TB = 7.5 |
| TB nhiều loại | Miệng:[8], 15p:[9,8], GK:[8] hệ số [1,1,2] | (8×1 + 8.5×1 + 8×2) / 4 = 8.125 |
| Renumber khi xoá | Miệng:[8(M1), 7(M2), 9(M3)] → xoá M2 | Miệng:[8(M1), 9(M2)] |
| max_exams | max=3, đang 3 bài | Không hiện nút [+Thêm] |
| Chỉ tính bài đã chấm | Miệng:[8, -, 9] | TB = (8+9)/2 = 8.5 |

### 7.2 Điểm chuyên cần

| Test | Input | Expected |
|------|-------|----------|
| Tính % | expected=22, present=19 | 86.36% → rank B |
| Xếp loại | 86.36% với thresholds [90,80,70,60] | B |
| is_active=0 | Program không tính | Không tính vào tổng |

### 7.3 Phân quyền

| Test | Vai trò | Action | Expected |
|------|---------|--------|----------|
| Đọc rules | GLV | GET rules | 200 OK |
| Sửa rules | GLV | POST rules | 403 |
| Sửa rules | Admin | POST rules | 200 OK |
| Sửa rules | BĐH | POST rules | 200 OK |

---

## 8. Rollout Plan

1. **Migration:** Thêm bảng mới, cột mới, seed dữ liệu mặc định
2. **API:** Các endpoint mới (`rules.php`, `score_types.php`, `term_programs.php`, `attendance_thresholds.php`, `attendance_scores.php`)
3. **Backend logic:** Cập nhật `scores.php`, `scores.js` — logic tính điểm động
4. **Frontend Settings:** Tab "Quy Tắc Điểm", "Quy Tắc Chuyên Cần"
5. **Frontend Nhập điểm:** Bảng động với [+Thêm bài], [🗑] xoá + renumber
6. **Test:** Unit tests cho logic tính điểm, E2E tests cho UI
