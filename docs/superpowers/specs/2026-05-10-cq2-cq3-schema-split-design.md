# CQ-2 & CQ-3 Implementation Design

**Date:** 2026-05-10
**Status:** Approved

---

## CQ-2: Đồng bộ 3 nguồn Schema

### Approach: Schema làm template + Migrations bổ sung

**Quy tắc:**
1. `config/schema.sql` = nguồn tin duy nhất cho DB mới (snapshot mới nhất)
2. `config/migrations/*.sql` = delta để nâng cấp DB cũ
3. `config/install.php` = gọi schema.sql + migrations còn thiếu

### Actions

1. **So sánh 3 nguồn** → báo diff
2. **Thêm vào schema.sql:**
   - `students.hidden_at` (từ migration 007)
   - `students.deleted_at` (từ migration 007)
3. **Cập nhật install.php:**
   - Xóa inline ALTERs (dòng 98-155)
   - Thay bằng gọi migrations/*.sql

---

## CQ-3: Tách file quá dài

### Approach: Tách theo domain

**Nguyên tắc:**
- Mỗi domain = 1 file riêng
- Main file import/include các sub-files
- Giữ backward compatibility (API không đổi)

### data.php Structure (634 → 5 files)

```
public/api/
├── data.php              # Entry, routing, cache (150 lines)
├── data_students.php     # Students + enrollments + stamps (200 lines)
├── data_programs.php    # Programs + program_classes (100 lines)
├── data_attendance.php  # Attendances + leaves (150 lines)
└── data_scores.php      # Scores (100 lines)
```

### core.js Structure (1084 → 5 files)

```
public/assets/js/modules/
├── core.js              # TNTTApp, routing, init (250 lines)
├── core_auth.js         # Login, logout, session (150 lines)
├── core_state.js        # App state management (200 lines)
├── core_helpers.js      # Utilities (200 lines)
└── core_components.js   # Alpine components (250 lines)
```

### students.js Structure (1002 → 4 files)

```
public/assets/js/modules/
├── students.js          # Main entry (200 lines)
├── students_list.js     # List view (250 lines)
├── students_form.js     # Add/edit form (300 lines)
└── students_card.js     # Card/detail (200 lines)
```

---

## Implementation Order

1. **CQ-2** (Schema sync) — trước vì foundation
2. **CQ-3** (File split) — sau, dùng schema đã sync

---

## Verification

- PHP syntax check trên tất cả files
- Unit tests pass
- Manual smoke test các flows chính
