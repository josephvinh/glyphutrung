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
- **Hồ sơ tổng hợp từng em** (`module_student_profile.php`, `student_profile.js`):
  trang riêng với 5 tab (Thông tin, Điểm số, Phiếu LC, Điểm danh, QR Card).
  Nút "Xem hồ sơ" trên card thiếu nhi trong Danh sách. Quay lại = về Danh sách.
- **Gộp danh sách module về MỘT nguồn** (`public/assets/asset_manifest.php`):
  `app.js` đọc `window.TNTT_MODULES` (index.php nhúng từ manifest) thay cho mảng
  cứng → **thêm module JS mới chỉ khai ở `asset_manifest.php`**, cả nạp/bundle/gộp
  component đều theo, hết cảnh "nạp mà quên gộp vào `app.js`" (đã từng làm vỡ
  student_profile, calendar, analytics).
- **Sửa bug quyền lớn** (`public/api/_bootstrap.php` → `permission_of`): quyền nay =
  **hợp vai gốc + vai kiêm nhiệm**, so theo **hạng** (none<view<edit) thay vì
  `MAX()` chuỗi (trước bị tụt 'edit'→'view'). Đây là lý do admin xoá mình khỏi
  lớp không lưu được.
- **Thêm vai "Dự Bị"** theo cơ cấu đoàn (`config/migrate_roles_du_bi.php`,
  `config/install.php`): điểm danh=sửa, còn lại=xem. Đồng thời GVCN được sửa hồ sơ
  + gửi thông báo lớp mình; Trưởng khối sửa hồ sơ trong khối. Thêm/gỡ GLV & Dự Bị
  vào lớp ngay ở màn **Khối & Lớp** (`org.js`, `views/module_org.php`).
  ⚠️ **Máy chủ thật phải chạy 1 lần:** `php config/migrate_roles_du_bi.php`.
- **Chuyển Nhân sự + Niên khoá sang khu Ban điều hành** (`core.js` `moduleDefs`,
  `area:'bdh'`, icon `text-white`).
- **Cải tổ bố cục Trang chủ** (`docs/superpowers/plans/2026-09-06-cai-to-bo-cuc.md`):
  - Việc cần làm ra Trang chủ (partial `partial_my_tasks.php`, dùng chung Cá nhân)
  - Sinh nhật ẩn khỏi lưới (giữ ở thẻ Hero)
  - Lưới nút chia cụm có tiêu đề: Hằng ngày / Theo dõi / Quản lý (GLV) và Chương trình / Điều hành (BĐH)
  - Thống kê + Phân tích gộp thành hub **Báo cáo** (tab), quyền tab riêng
  - Thanh dưới 5 tab: Trang chủ · Thiếu Nhi · Điểm danh · Thông báo · Cá nhân

---

## 9. Trạng thái hiện tại & tham chiếu cho phiên sau

### 9.1 ✅ ĐÃ XONG — Cải tổ bố cục Trang chủ
Plan `docs/superpowers/plans/2026-09-06-cai-to-bo-cuc.md` đã hoàn thành & push (HEAD `ea63350`):
- Task 1 — Việc cần làm ra Trang chủ (`582e49e`)
- Task 2 — Sinh nhật ẩn khỏi lưới, giữ thẻ Hero (`e6f621e`)
- Task 3 — Lưới nút chia cụm (`ce2373f`)
- Task 4 — Hub "Báo cáo" gộp Thống kê + Phân tích + quyền tab (`d963aa3`, `20aecec`, `d936c7d`)
- Task 5 — Thanh dưới 5 tab (`4651dcc`)
- Trước đó: Nhân sự + Niên khoá sang khu BĐH (`2c67597`); vai Dự Bị + picker (`1f1895a`, `b99b94b`)

Kiểm chứng nhanh: `grep reporthub core.js` (có), thanh dưới 5 tab, `moduleGroups` chạy.

**Ghi chú theo dõi (tuỳ chọn, không ảnh hưởng):**
- Sidebar (`layout_sidebar.php`) vẫn dùng `visibleModules` trực tiếp, chưa nhóm theo cụm.

### 9.2 Mô hình tư duy kiến trúc (nắm cái này là code đúng)
- SPA Alpine.js, **một component khổng lồ `tnttApp`** ghép từ ~23 mảnh
  `window.TNTT.*`. `app.js` gộp bằng `gopManh()` (dùng `Object.defineProperties`
  để **giữ getter**). Danh sách mảnh = `window.TNTT_MODULES` (nhúng từ
  `asset_manifest.php`). → **Thêm mảnh JS mới: khai ở `asset_manifest.php` là đủ.**
- Mỗi màn = `<div data-module="key" class="module-panel">` trong
  `public/index.php`, bật/tắt bằng `currentModule`. Điều hướng: `openModule(key)`
  (có gate quyền + bảo trì) hoặc `changeModule(key)`.
- Lưới nút Trang chủ **sinh từ `moduleDefs`** (`core.js`) qua `moduleGroups(area)`
  → thêm/ẩn/đổi khu một module = sửa `moduleDefs`, **không** sửa tay
  `module_menu.php`.
- **Hai trục quyền:** *scope* (vai `toàn đoàn`/`khối`/`lớp` → lớp nào truy cập
  được, qua `can_access_class`/`accessible_class_ids`) × *quyền module* (bảng
  `permissions`: module × vai → `none`/`view`/`edit`). Kiêm nhiệm = bảng
  `member_assignments`. Frontend đọc quyền từ boot (`window.TNTT_BOOT`).

### 9.3 Tư duy code (bắt buộc tuân theo)
1. **Viết như code xung quanh:** cùng phong cách, cùng độ dày comment, comment +
   nhãn hiển thị **bằng tiếng Việt**.
2. **DRY:** dùng lại thì tách `views/partial_*.php`, đừng lặp markup.
3. **IA/bố cục thì KHÔNG đụng backend/quyền** — chỉ view + JS. Mọi nút vẫn qua
   `canAccess()`/`isUnderMaintenance()`.
4. **Kiểm chứng mọi thay đổi:** `php -l <view>` + `node --check <js>` +
   `php phpunit10.phar --no-coverage` (phải **25/25**) + mở preview soi mắt
   (Browser pane / preview_start).
5. **Commit theo từng Task**, message tiếng Việt, kết:
   `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Push khi người dùng
   yêu cầu (remote `https://github.com/josephvinh/glyphutrung.git`, private).
6. **Dọn dữ liệu test:** tài khoản thử luôn prefix `ZZ`, sđt `0900000009`; xong
   phải xoá (xoá `member_assignments` phụ thuộc trước vì FK `fk_assign_by`).
7. **Không commit** `config/backup/` (tên thật thiếu nhi — đã gitignore).
   `config/config.php` có secrets nhưng repo private nên chấp nhận; nếu công khai
   phải gỡ.

### 9.4 Nhớ nhắc người dùng
- Chạy `php config/migrate_roles_du_bi.php` trên máy chủ thật (vai Dự Bị + quyền
  GVCN/Trưởng khối).
- Thư mục lạ `NGOC VINH/` chưa track — hỏi trước khi làm gì với nó.

---

## 10. Cheat-sheet lệnh

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

---

## 11. Bảo mật khi lên máy chủ thật

### 11.1 ✅ Đã có sẵn trong code (không phải làm lại)
- **SQL injection**: PDO prepared + `ATTR_EMULATE_PREPARES=false` (`config/db.php`); mọi truy vấn dùng `?`.
- **Brute-force đăng nhập**: `login_throttle()` chặn theo SĐT + IP, quá ngưỡng → 429 (bảng `login_attempts`), `_bootstrap.php`.
- **Session**: cookie `HttpOnly` + `SameSite=Lax` + `Secure` khi HTTPS; `session_regenerate_id(true)` khi đăng nhập (`_common.php`, `auth.php`).
- **CSRF**: `require_csrf()` kiểm token qua header `X-CSRF-TOKEN` (`_bootstrap.php` + `csrf.php`).
- **Header bảo mật**: `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `HSTS` (khi HTTPS) — đặt SONG SONG ở `.htaccess` VÀ `_common.php`.
- **CSP**: có ở `public/.htaccess` VÀ `_common.php` (khớp nhau). Cho `'unsafe-inline'`+`'unsafe-eval'` vì Alpine.js + script nhúng boot; `frame-ancestors 'none'`, `object`/`base-uri`/`form-action` siết.
- **`.htaccess`**: `Options -Indexes`, chặn tải `.sql/.bak/.env/.log/.md/...` và dotfiles, `RedirectMatch 404` cho `config|backup|views`.
- **Mật khẩu**: `password_hash` bcrypt.
- **CSV/Excel formula injection**: `csv_escape()` (`export.php`) thêm `'` vào ô bắt đầu `= + - @` (tránh `=HYPERLINK/=cmd`).
- Đã **xoá** file debug `public/kiem-tra.php`, `kiem-tra-qr.php` (khôi phục từ git nếu cần chẩn đoán 500: `git show <commit>:public/kiem-tra.php > public/kiem-tra.php`).

### 11.2 ⚠️ Việc PHẢI làm trên máy chủ (thao tác hạ tầng, không phải code)
Ưu tiên cao → thấp:
1. **Bật HTTPS** (Let's Encrypt / SSL của AZDIGI). HSTS + cookie Secure chỉ bật khi có HTTPS.
2. **Đổi tài khoản DB `root` không mật khẩu** → tạo user riêng chỉ có quyền trên đúng 1 database:
   ```sql
   CREATE USER 'tntt_app'@'localhost' IDENTIFIED BY '<mật-khẩu-mạnh>';
   GRANT SELECT, INSERT, UPDATE, DELETE ON ylcqukhi_glyphutrung.* TO 'tntt_app'@'localhost';
   FLUSH PRIVILEGES;
   ```
   Rồi sửa `config/config.php`: `user`/`pass` sang tài khoản mới. (Giới hạn thiệt hại nếu bị chọc thủng.)
3. **Đổi mật khẩu mặc định** `tntt@2026` và ép đổi lần đầu (`must_change_pw`).
4. **Đặt document root = thư mục `public/`** (để `config/`, `views/`, `backup/` nằm ngoài web). Đây là điều kiện tiên quyết cho các lớp phòng thủ khác.
5. **Tắt hiện lỗi** production: `display_errors=Off` (đã có cờ `production=true` trong config — kiểm tra nó thực sự tắt lỗi).
6. **Sao lưu DB định kỳ** ra nơi khác + mã hoá. KHÔNG để lộ `config/backup/` (chứa tên thật thiếu nhi — đã gitignore).

### 11.3 🛡️ Chống DDoS — ở tầng hạ tầng, PHP không tự chống được
- **Đặt sau Cloudflare (gói miễn phí)** — giải pháp chính: chống DDoS thể tích, WAF, rate-limit, che IP gốc, cache. Chỉ cần trỏ DNS qua Cloudflare.
- Web server: `mod_evasive` (Apache) / `limit_req` (nginx); **fail2ban** chặn IP spam.
- (Tuỳ chọn) thêm throttle cho API ghi dữ liệu, không chỉ login.

> Đây là rà soát thực dụng theo code, KHÔNG phải kiểm định an ninh đầy đủ. Với dữ liệu trẻ em, nếu có điều kiện nên nhờ pentest chuyên sâu một lần.
