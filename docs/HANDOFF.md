# Bàn giao & Cài đặt dự án — TNTT Super App

Tài liệu này để **chuyển dự án sang một máy / tài khoản Claude khác** và dựng
lại từ đầu. Đọc hết một lượt trước khi làm.

---

## 1. Dự án là gì (ngữ cảnh cho Claude mới)

**TNTT Super App** — web app quản lý Đoàn Thiếu Nhi Thánh Thể (giáo lý viên +
thiếu nhi) của giáo xứ Phú Trung. Chức năng: danh sách thiếu nhi, điểm danh
(chạm tay + quét QR camera), xin phép, điểm số, sổ liên lạc, thống kê/phân
tích, lịch trình, thông báo, phân quyền theo vai trò/phạm vi/**kiêm nhiệm**,
lên lớp cuối năm, PWA (thông báo đẩy).

**Stack:**
- PHP 8.2 + PDO/MySQL (InnoDB, utf8mb4). Không dùng framework.
- Frontend: Alpine.js + Tailwind (biên dịch sẵn). SPA nhẹ: nạp dữ liệu **một
  lần** lúc đăng nhập rồi mọi thao tác chạy ở máy khách.
- Test: PHPUnit 10 (`php phpunit10.phar`).

**Cấu trúc thư mục:**
- `public/` — web root. `index.php` là điểm vào; `api/*.php` các endpoint;
  `assets/js/modules/*.js` các mảnh component (gộp bởi `app.js`).
- `views/` — các màn HTML (nằm NGOÀI web root).
- `config/` — kết nối DB, cài đặt, schema, sinh mã, mật khẩu…
- `docs/` — tài liệu (gồm file này và `docs/features/kiem-nhiem.md`).
- `tests/` — PHPUnit.

**Quy ước quan trọng:**
- `members` vừa là nhân sự vừa là **tài khoản đăng nhập** (đăng nhập bằng **số
  điện thoại**).
- Kiêm nhiệm: bảng `member_assignments` (một người nhiều vai trò/lớp/khối).
- Mã thiếu nhi: `GDGLPT` + 2 số năm nhập + 4 số thứ tự (VD `GDGLPT260001`),
  **sinh ở máy chủ**, bền suốt đời em. Xem `config/student_code.php`.

---

## 2. Chuyển code sang tài khoản mới — chọn 1 trong 2

### Cách A — Qua GitHub (khuyến nghị, giữ lịch sử)
Repo: `https://github.com/josephvinh/glyphutrung.git` (**private**).
Trên máy mới:
```bash
cd /path/to/xampp/htdocs
git clone https://github.com/josephvinh/glyphutrung.git tntt
```
> Repo private → cần đăng nhập GitHub có quyền: dùng cùng tài khoản GitHub,
> hoặc thêm collaborator, hoặc dùng Personal Access Token khi clone.

### Cách B — Chép nguyên thư mục
Nén cả thư mục `tntt/` (gồm cả `.git/` và `config/config.php`) rồi giải nén vào
`htdocs/` máy mới. Cách này mang theo luôn cấu hình, khỏi lo quyền GitHub.

> **Dữ liệu (thành viên, thiếu nhi, điểm danh…) KHÔNG nằm trong code** — nó ở
> MySQL. Xem mục 5 để mang dữ liệu theo.

---

## 3. Yêu cầu môi trường

- **XAMPP** (hoặc bất kỳ Apache + PHP 8.2+ + MySQL/MariaDB). Tải xampp, cài mặc
  định. Bật **Apache** + **MySQL** trong XAMPP Control Panel.
- Windows/Mac/Linux đều được. Ví dụ dưới theo Windows + XAMPP.

---

## 4. config/config.php

File này **đã có sẵn trong repo** (đã theo git). Mặc định:
- DB: host `127.0.0.1`, tên `ylcqukhi_glyphutrung`, user `root`, mật khẩu rỗng
  (khớp XAMPP mặc định).
- `default_password` = `tntt@2026` (mật khẩu tạm của admin khi cài mới).
- `setup_key` = chuỗi để chạy trình cài đặt qua web.
- `production` = true.

Nếu MySQL máy mới khác (có mật khẩu root, hoặc tên DB khác), **sửa lại khối `db`
trong `config/config.php`**. Có `config/config.example.php` làm mẫu.

> ⚠️ Bảo mật: `config.php` chứa `setup_key` + `default_password` và **đang nằm
> trong repo**. Chấp nhận được khi repo private giữa các tài khoản của bạn.
> Nếu sau này công khai repo, phải gỡ file này khỏi git và đổi các khoá.

---

## 5. Dựng cơ sở dữ liệu — chọn 1 trong 2

Trước tiên tạo DB rỗng (phpMyAdmin của XAMPP → New → tên `ylcqukhi_glyphutrung`,
collation `utf8mb4_unicode_ci`), hoặc:
```bash
# trong thư mục xampp/mysql/bin
mysql -u root -e "CREATE DATABASE ylcqukhi_glyphutrung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### Cách 1 — Mang theo DỮ LIỆU hiện có (khuyến nghị để làm tiếp)
Trên máy CŨ, xuất:
```bash
mysqldump -u root ylcqukhi_glyphutrung > tntt_dump.sql
```
Chép `tntt_dump.sql` sang máy mới, nhập:
```bash
mysql -u root ylcqukhi_glyphutrung < tntt_dump.sql
```
→ Giữ nguyên mọi thành viên, thiếu nhi, mã GDGLPT, phân công… Đăng nhập bằng
tài khoản admin **thật** đang có (số điện thoại + mật khẩu bạn đang dùng).

### Cách 2 — Cài mới, dữ liệu rỗng
```bash
php config/install.php
```
Lệnh này dựng bảng từ `schema.sql`, seed vai trò/phân quyền, và tạo **admin đầu
tiên**: đăng nhập `0901000001` / `tntt@2026` (bị buộc đổi mật khẩu lần đầu).
> Không có terminal? Mở trình duyệt: `http://localhost/tntt/public/cai-dat.php?key=<setup_key>`.

---

## 6. Chạy app

Đặt dự án ở `xampp/htdocs/tntt`, bật Apache, mở:
```
http://localhost/tntt/public/
```
Đăng nhập bằng số điện thoại + mật khẩu (mục 5).

> **Tải tài nguyên (dev vs production) — đã tối ưu:**
> - **localhost** (dev): nạp file JS/CSS **lẻ** + `?v` đổi mỗi lần tải → sửa
>   code là thấy ngay, không cần Unregister service worker thủ công như trước.
>   Mở F12 sẽ thấy **nhiều request** — đúng thiết kế cho dev.
> - **Server thật** (tên miền giáo xứ): nạp **1 bundle JS + 1 bundle CSS** (qua
>   `assets/js/bundle.php` và `assets/css/bundle.php`) → ~5 request, nhẹ cho
>   điện thoại. Service worker cache bundle nên vẫn chạy offline.
> - Chia theo tên miền trong `public/index.php` (`$__dev` = localhost/127.0.0.1);
>   thứ tự file khai ở `public/assets/asset_manifest.php`.
>
> **Vẫn nên dùng Apache của XAMPP**, KHÔNG dùng `php -S` (đơn luồng, tải nhiều
> file dễ rớt request → màn trắng). Nếu hiếm khi thấy bản cũ trên trình duyệt:
> DevTools → Application → Service Workers → Unregister + Clear storage →
> Ctrl+Shift+R. (Khi deploy bản mới, bump `PHIEN_BAN` trong `sw.js` là kho cũ
> tự dọn.)

---

## 7. Chạy test

```bash
php phpunit10.phar --no-coverage
```
Kỳ vọng: `OK (25 tests, ...)`. Chạy sau mỗi thay đổi backend.

---

## 8. Bàn giao ngữ cảnh cho Claude ở tài khoản mới

Mở dự án trong Claude Code (thư mục `htdocs/tntt`) rồi bảo nó đọc theo thứ tự:
1. `docs/HANDOFF.md` (file này) — bức tranh tổng.
2. `~/.claude/CLAUDE.md` nếu có (chỉ dẫn cá nhân) + bất kỳ `CLAUDE.md` trong repo.
3. `docs/features/kiem-nhiem.md` — luồng kiêm nhiệm + phạm vi quyền.
4. `git log --oneline -20` — các thay đổi gần đây.

**Đã làm gần đây (để Claude mới không làm lại):**
- Phân quyền theo **từng phân công** (chống leo thang): `public/api/_common.php`
  (`can_access_class`, `accessible_class_ids`), gate ở attendance/leave/export/
  scores/reports; UI lọc theo `myClasses`/`myBlocks` (`access.js`).
- Kiêm nhiệm cho announcements + quét QR; quản lý phân công chuyển sang màn
  **Khối & Lớp** (thêm/gỡ GLV, chủ nhiệm/trưởng khối), BĐH kiêm nhiệm được.
- Mã thiếu nhi **GDGLPT + năm + số**, sinh ở máy chủ (`config/student_code.php`),
  đã chuyển đổi mã cũ (script `config/migrate_student_codes.php`).
- Gom **Danh sách + Điểm số + Phiếu liên lạc + In thẻ QR** vào một module
  **Thiếu Nhi** (thanh thẻ `views/partial_children_tabs.php`, 4 thẻ).
- **In thẻ QR** thành thẻ riêng (`views/module_qrcard.php`, `qrcard.js`): chọn
  phạm vi (lớp/khối/tất cả), bật/tắt field (mã, tên thánh, họ tên, lớp, khối,
  ngày sinh), 2 kiểu thẻ (gọn / thẻ đeo), xem trước rồi in. QR luôn chỉ chứa
  mã số. Đã bỏ nút "Thẻ QR" cũ trong Danh sách.
- **Tối ưu tải:** production gộp JS/CSS thành bundle (`public/assets/asset_manifest.php`,
  `assets/js/bundle.php`, `assets/css/bundle.php`), dev nạp lẻ. Fix cache dev
  (SW bỏ qua kho ở localhost + `?v` duy nhất mỗi lần tải). Thêm icon
  `moon`/`sun`/`hash`/`refresh-cw` vào bản lucide rút gọn (`assets/js/vendor/lucide-icons.js`).
- **Nút Làm mới/đồng bộ** trên header (cạnh nút Sáng/Tối): `refreshApp()` gọi
  `loadData()` — thay cho thao tác kéo-xuống mà PWA cài ra màn hình chính không
  có. `loadData()` nay trả về true/false.
- Sửa bug: trang Cá nhân mồ côi; module Lịch trình/Phân tích chưa gộp vào
  `app.js`.

**Việc còn để ngỏ (nếu muốn làm tiếp):** hồ sơ tổng hợp từng em (bấm 1 em xem
info + điểm + phiếu + điểm danh) — "bước 2" của việc gom module Thiếu Nhi.

---

## 9. Cheat-sheet lệnh

```bash
# Lấy code
git clone https://github.com/josephvinh/glyphutrung.git tntt

# DB: tạo + nhập dữ liệu
mysql -u root -e "CREATE DATABASE ylcqukhi_glyphutrung CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root ylcqukhi_glyphutrung < tntt_dump.sql     # (bản dump từ máy cũ)
# hoặc cài rỗng:
php config/install.php

# Chạy: bật Apache của XAMPP -> http://localhost/tntt/public/

# Test
php phpunit10.phar --no-coverage
```
