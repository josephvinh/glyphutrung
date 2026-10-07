# Báo Cáo Audit Chất Lượng Code — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 3 | CQ-1, CQ-2, CQ-3 |
| 🟡 Trung bình | 5 | CQ-4, CQ-5, CQ-6, CQ-7, CQ-NEW-1 |
| 🔵 Thấp / Thông tin | 3 | CQ-8, CQ-9, CQ-NEW-2 |

---

## Nợ kỹ thuật đã biết

### 🟠 CQ-1 — Gộp module JS trùng tên

- **Vị trí:** `public/assets/js/modules/*.js` (~28 mảnh)
- **Mô tả:** Các mảnh JS gộp bằng `Object.defineProperties` → **trùng tên đè nhau âm thầm**.
  Đã gây lỗi #194 (mất `normalizeText`).
- **Cách sửa:** Kiểm `check_module_merge` (TESTING.md §5) phải nằm trong CI.

---

### 🟠 CQ-2 — Ba nguồn lược đồ

- **Vị trí:**
  - `config/schema.sql`
  - `config/migrations/*.sql`
  - `config/install.php` ($migrations array)
- **Mô tả:** Ba nơi định nghĩa schema → dễ trôi lệch. Đã gây lỗi quyền `thu_vien`.
- **Cách sửa:**
  1. Schema mới chỉ thêm ở migrations
  2. Schema.sql phải phản ánh migrations
  3. Install.php $migrations phải đồng bộ

---

### 🟠 CQ-3 — File quá dài cần tách

- **Vị trí:**
  - `public/api/data.php` (625 dòng)
  - `public/api/_rewards.php` (643 dòng)
  - `public/api/somoc.php` (873 dòng)
  - `public/assets/js/modules/core.js` (1084 dòng)
  - `public/assets/js/modules/students.js` (1002 dòng)
- **Cách sửa:** Ưu tiên tách `data.php` và `core.js`.

---

## Nợ kỹ thuật mới

### 🟡 CQ-4 — INSERT IGNORE sử dụng không nhất quán

- **Vị trí:**
  - `public/api/attendance.php:32,183,193`
  - `public/api/announcements.php:205,212`
  - `public/api/programs.php:120`
- **Mô tả:** `INSERT IGNORE` có thể **nuốt lỗi thật** (trigger, constraint).
- **Cách sửa:** Dùng `ON DUPLICATE KEY UPDATE` thay vì INSERT IGNORE.

---

### 🟡 CQ-5 — Logic `client_ip()` không nhất quán

- **Vị trí:**
  - `public/api/_http_util.php:23-26` — tin `X-Forwarded-For` khi `REMOTE_ADDR` không phải loopback
  - `src/RateLimiter.php:39-46` — chỉ tin `REMOTE_ADDR`
- **Mô tả:** `_bootstrap.php` nạp `_http_util.php` trước → rate-limit có thể bị bypass.
- **Cách sửa:** Đồng bộ logic hoặc dùng tên hàm khác nhau.

---

### 🟡 CQ-6 — TODO/FIXME tồn đọng

- **Vị trí:**
  - `public/api/data.php:552` — `// TODO: Chuyển sang API riêng có pagination khi có màn xem nhật ký`
  - `public/api/OrgService.php:8` — `* TODO: Chuyển sang class-based endpoints khi có thời gian refactor đầy đủ`
- **Cách sửa:** Chuyển thành GitHub issues, xóa comments.

---

### 🟡 CQ-7 — package.json có dependencies không cần thiết

- **Vị trí:** `package.json:43-52`
- **Mô tả:** `dependencies` chứa phụ thuộc kéo theo của `sharp` (color, semver, etc.)
- **Cách sửa:** Chuyển `sharp` về devDeps, xóa dependencies thừa:
  ```bash
  npm install --save-dev sharp && npm prune
  ```

---

### 🟡 CQ-NEW-1 — Comment lỗi thời về Vue Router

- **Vị trí:** `public/.htaccess:19-20`
- **Mô tả:**
  ```apache
  # Cho phép truy cập /students, /attendance thay vì /#/students
  # Dùng cho Vue Router history mode.
  ```
  App dùng Alpine.js SPA, không phải Vue Router.
- **Cách sửa:** Cập nhật comment.

---

### 🔵 CQ-8 — Code chết: migrate-passkeys.php

- **Vị trí:** `public/api/migrate-passkeys.php`
- **Mô tả:** Require path sai + `$me` chưa gán → không chạy được.
- **Cách sửa:** Xóa file.

---

### 🔵 CQ-9 — src/Router.php không dùng

- **Vị trí:** `src/Router.php`
- **Mô tả:** Chỉ còn test dùng.
- **Cách sửa:** Xóa file.

---

### 🔵 CQ-NEW-2 — Magic numbers rải rác

- **Vị trí:** Nhiều file
- **Mô tả:** Các số "magic" không có ý nghĩa rõ ràng:
  - `30` phút cutoff
  - `100` default page limit
  - `500` max page limit
- **Cách sửa:** Định nghĩa constants:
  ```php
  const CUTOFF_MINUTES = 30;
  const DEFAULT_PAGE_LIMIT = 100;
  const MAX_PAGE_LIMIT = 500;
  ```

---

## Checklist

### Rõ ràng
- [x] Đặt tên + comment theo lối repo
- [ ] **CQ-3:** Tách file quá dài
- [ ] **CQ-NEW-2:** Định nghĩa magic numbers

### Không bẫy ngầm
- [ ] **CQ-1:** Kiểm trùng tên module JS trong CI
- [ ] **CQ-2:** Đồng bộ 3 nguồn lược đồ
- [ ] **CQ-5:** Đồng bộ client_ip()

### Sạch
- [ ] **CQ-4:** Thay INSERT IGNORE bằng ON DUPLICATE KEY UPDATE
- [ ] **CQ-6:** Chuyển TODO thành issues
- [ ] **CQ-7:** Dọn package.json dependencies
- [ ] **CQ-8:** Xóa migrate-passkeys.php
- [ ] **CQ-9:** Xóa Router.php
- [ ] **CQ-NEW-1:** Cập nhật comment .htaccess

---

## Khuyến nghị thứ tự

1. **CQ-1** — Thêm check_module_merge vào CI
2. **CQ-2** — Đồng bộ lược đồ
3. **CQ-3** — Tách file lớn
4. **CQ-4** — Thay INSERT IGNORE
5. **CQ-5** — Đồng bộ client_ip()
6. **CQ-6, CQ-7, CQ-NEW-1** — Cleanup
7. **CQ-8, CQ-9** — Xóa code chết
8. **CQ-NEW-2** — Constants
