# Deploy lên AZDIGI qua GitHub — hướng dẫn từng bước

> App PHP thuần (không cần Composer/Node). Yêu cầu **PHP 8.2+**, MySQL/MariaDB.
> Repo: `https://github.com/josephvinh/glyphutrung.git` (private).
> Điểm quan trọng: **document root phải trỏ vào thư mục `public/`**, và
> `config/config.php` phải dùng thông tin DB của AZDIGI (không phải bản local).

---

## Điều kiện cần
- Gói AZDIGI có **cPanel** + đã bật **SSH access** và **Git™ Version Control**
  (nếu chưa thấy 2 mục này trong cPanel, mở ticket nhờ AZDIGI bật SSH).
- Một tên miền/subdomain đã trỏ về hosting.

Nếu gói KHÔNG có SSH/Git → xem **Phụ lục B** (đẩy bằng FTP/GitHub Actions).

---

## Bước 1 — Cho AZDIGI quyền đọc repo private (SSH Deploy Key)

Repo private nên cPanel cần một khoá để clone.

1. cPanel → **SSH Access** → **Manage SSH Keys** → **Generate a New Key**
   (đặt tên ví dụ `github_deploy`, để trống passphrase cho đơn giản).
2. Bấm **View/Download** khoá **public** (`.pub`) → copy toàn bộ nội dung.
3. Vào GitHub repo → **Settings → Deploy keys → Add deploy key** → dán khoá
   public vào. **KHÔNG** tick "Allow write access" (chỉ cần đọc). Lưu.
4. Về cPanel → **Manage SSH Keys** → **Authorize** khoá vừa tạo.

> Từ giờ hosting kéo code bằng URL dạng SSH: `git@github.com:josephvinh/glyphutrung.git`

---

## Bước 2 — Clone code về hosting (cPanel Git Version Control)

1. cPanel → **Git™ Version Control** → **Create**.
2. **Clone URL**: `git@github.com:josephvinh/glyphutrung.git`
3. **Repository Path**: `/home/<cpaneluser>/tntt` (một thư mục NGOÀI `public_html`).
4. Create → cPanel tự clone. (Lần sau muốn cập nhật: vào đây bấm **Update from
   Remote** → **Pull or Deploy → Update**.)

> Đặt code ngoài `public_html` để `config/`, `views/`, `backup/` không bị lộ ra web.

---

## Bước 3 — Trỏ document root vào `public/`

Chọn 1 trong 2 cách:

**Cách A — Đổi docroot của tên miền (khuyến nghị):**
- cPanel → **Domains** → tên miền → **Manage** → sửa **Document Root** thành
  `/home/<cpaneluser>/tntt/public` → Save.

**Cách B — Nếu không đổi được docroot** (bắt buộc chạy trong `public_html`):
- Clone thẳng vào `public_html` ở Bước 2, rồi tạo file `public_html/.htaccess`
  chuyển mọi request vào `public/` và chặn truy cập `config`/`views`:
  ```apache
  RewriteEngine On
  RewriteRule ^$ public/ [L]
  RewriteRule (.*) public/$1 [L]
  ```
  (Kém an toàn hơn Cách A — ưu tiên Cách A.)

---

## Bước 4 — Tạo cơ sở dữ liệu trên AZDIGI

1. cPanel → **MySQL® Databases**:
   - **Create New Database**: ví dụ `cpaneluser_tntt`.
   - **Add New User**: ví dụ `cpaneluser_tntt` + mật khẩu mạnh.
   - **Add User To Database** → cấp **ALL PRIVILEGES**.
2. Nạp dữ liệu — chọn 1:
   - **Mang theo dữ liệu cũ**: cPanel → **phpMyAdmin** → chọn DB → **Import**
     file `.sql` (bản dump từ máy cũ).
   - **Cài mới rỗng** (chạy qua SSH, xem Bước 6): `php config/install.php`.

---

## Bước 5 — Cấu hình `config.php` cho production (QUAN TRỌNG)

`config/config.php` trong repo đang là cấu hình **máy local** (`host=127.0.0.1`,
`user=root`, `pass=''`). Trên AZDIGI phải đổi thành thông tin DB ở Bước 4.

**Vấn đề:** file này bị git theo dõi → mỗi lần `git pull` sẽ đòi ghi đè/ xung đột.

**Cách xử lý sạch (khuyến nghị — mình có thể set sẵn cho bạn):**
tách cấu hình riêng-máy-chủ ra file **không** đưa lên git:
- `config/config.php` (trong repo) đọc thêm `config/config.local.php` nếu có và
  ghi đè các khoá nhạy cảm.
- Trên hosting tạo `config/config.local.php` chứa DB thật → `git pull` không bao
  giờ đụng tới nó.

> Muốn dùng cách này, nhắn mình "làm config.local" — mình sửa `config.php` +
> thêm `.gitignore` + file mẫu, rồi bạn chỉ việc tạo `config.local.php` trên server.

**Cách nhanh (tạm, nếu chưa tách):** sửa trực tiếp `config/config.php` trên hosting
(cPanel File Manager → Edit) các khoá:
```php
'db' => [
    'host' => 'localhost',
    'name' => 'cpaneluser_tntt',
    'user' => 'cpaneluser_tntt',
    'pass' => '<mật-khẩu-DB>',
],
'production'       => true,
'default_password' => '<đổi khác tntt@2026>',
'setup_key'        => '<đổi thành chuỗi bí mật mới>',
```
Khi `git pull` sau này nếu báo xung đột file này → giữ bản trên server.

---

## Bước 6 — Chạy migration + kiểm tra (SSH)

Mở **Terminal** trong cPanel (hoặc SSH), vào thư mục code:
```bash
cd ~/tntt
php -v                                   # phải 8.2+
php config/install.php                   # chỉ khi cài DB rỗng (bỏ nếu đã import)
php config/migrate_roles_du_bi.php        # thêm vai Dự Bị + quyền
php config/migrate_modules_sync.php       # thêm module Báo cáo/Lịch trình
php config/migrate_student_codes.php      # chuẩn hoá mã (nếu chưa chạy)
php phpunit10.phar --no-coverage          # 25/25 là OK
```

---

## Bước 7 — Bật HTTPS + bảo mật

1. cPanel → **SSL/TLS Status** → **Run AutoSSL** (Let's Encrypt miễn phí) cho
   tên miền. Đợi có ổ khoá xanh.
2. Ép HTTPS: thêm vào đầu `public/.htaccess`:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```
3. (Rất khuyến nghị) đưa tên miền qua **Cloudflare** (miễn phí) để chống DDoS +
   WAF. Xem `docs/HANDOFF.md` mục 11.3.

Kiểm tra cuối: mở `https://tenmien/` → đăng nhập → thấy đủ module (Báo cáo,
Lịch trình...) và icon không trống.

---

## Bước 8 — Cập nhật app về sau

Repo đã có sẵn **`.cpanel.yml`** để cPanel tự copy code sang thư mục app khi
deploy. Mô hình này dùng **2 thư mục**:
- **Thư mục CLONE** — nơi cPanel giữ repo (ví dụ `~/repositories/tntt`).
- **Thư mục APP** — nơi web chạy thật (`$DEPLOYPATH`, mặc định `~/tntt-app`).
  **Document root tên miền phải trỏ vào `$DEPLOYPATH/public`** (không phải thư
  mục clone).

**Thiết lập một lần:**
1. `.cpanel.yml` dùng `$HOME/tntt-app` nên **không cần sửa gì** (đổi tên thư mục
   app thì sửa `tntt-app` trong file). Deploy lần đầu để cPanel tạo `~/tntt-app`.
2. Trỏ Document Root của tên miền vào `/home/<cpaneluser>/tntt-app/public`.
3. Tạo `config/config.php` **trong thư mục APP** (`~/tntt-app/config/`) với thông
   tin DB AZDIGI (Bước 5). File này KHÔNG bị deploy đè (đã loại trừ trong `.cpanel.yml`).

**Mỗi lần cập nhật:**
- cPanel → **Git Version Control** → repo → **Manage** → **Pull or Deploy** →
  bấm **Update from Remote** rồi **Deploy HEAD Commit**.
- cPanel `git pull` về thư mục clone, rồi `.cpanel.yml` **rsync** sang thư mục
  APP, **giữ nguyên** `config/config.php`, `config/config.local.php`, `config/backup/`.
- Nếu bản cập nhật có migration (thêm module/vai) → chạy tay qua SSH (Bước 6);
  `.cpanel.yml` cố ý KHÔNG tự chạy migration.

> Muốn deploy tự động ngay khi push lên GitHub (không phải bấm tay): cần
> **webhook** gọi endpoint deploy của cPanel — nhiều gói shared chặn, nên mặc
> định là bấm **Deploy** thủ công (chỉ mất vài giây).

**Thủ công không dùng `.cpanel.yml`** (nếu bạn để docroot trỏ thẳng vào thư mục
clone `~/tntt/public`): chỉ cần **Update from Remote → Pull** là xong, không cần
bước Deploy. Đơn giản hơn nhưng thư mục web = thư mục git (kém tách bạch hơn).

---

## Phụ lục A — Lỗi thường gặp
- **500 Internal Server Error**: gần như luôn do `config.php` sai thông tin DB,
  hoặc PHP < 8.2. Xem log: cPanel → **Errors** / **Metrics → Errors**.
- **Trang trắng / thiếu CSS-JS**: docroot chưa trỏ đúng `public/`.
- **"Access denied for user"**: sai `user`/`pass`/`name` DB ở Bước 5, hoặc chưa
  Add User To Database.
- **Không thấy module mới**: chưa chạy `php config/migrate_modules_sync.php`.

## Phụ lục B — Không có SSH/Git trên hosting
- **GitHub Actions → FTP**: tạo workflow đẩy thư mục lên hosting qua FTP mỗi lần
  push (dùng secret FTP host/user/pass). Nhắn mình để soạn workflow.
- **Thủ công**: tải ZIP repo từ GitHub → giải nén → upload qua **File Manager**;
  cập nhật thì upload đè. (Chậm và dễ sót, chỉ dùng khi bí.)
