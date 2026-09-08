# Deploy lên AZDIGI qua GitHub — hướng dẫn từng bước

> App PHP thuần (không cần Composer/Node). Yêu cầu **PHP 8.2+**, MySQL/MariaDB.
> Repo: `https://github.com/josephvinh/glyphutrung.git` (private).
> Điểm quan trọng: **document root phải trỏ vào thư mục `public/`**, và tạo
> **`config/config.local.php`** (không lên git) chứa thông tin DB của AZDIGI —
> KHÔNG sửa `config/config.php`. (Xem Bước 5.)

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

## Bước 5 — Cấu hình DB thật bằng `config.local.php` (QUAN TRỌNG)

**KHÔNG sửa `config/config.php`** trên máy chủ (nó bị git theo dõi → sửa là xung
đột mỗi lần `git pull`). Thay vào đó tạo **`config/config.local.php`** — file này
**đã .gitignore**, `config.php` tự trộn đè lên khi có, nên `git pull` không bao giờ
đụng tới. (Cơ chế đã dựng sẵn: `config.php` đọc `config.local.php` rồi
`array_replace_recursive`.)

Trên hosting (cPanel File Manager hoặc SSH), trong **thư mục APP** (`~/tntt-app/config/`
nếu dùng `.cpanel.yml`, hoặc `~/tntt/config/` nếu docroot trỏ thẳng thư mục clone):

```bash
cp config/config.local.example.php config/config.local.php
```
Rồi sửa `config/config.local.php` cho đúng cPanel (chỉ khai khoá cần đổi):
```php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'cpaneluser_tntt',   // DB tạo ở Bước 4
        'user' => 'cpaneluser_tntt',
        'pass' => '<mật-khẩu-DB>',
    ],
    'default_password' => '<đổi khác tntt@2026>',
    'setup_key'        => '<chuỗi bí mật mới>',
    // 'push' => ['public'=>'...','private'=>'...','subject'=>'mailto:...'], // khoá VAPID riêng
    'production' => true,
];
```

> `config.php` mặc định (trên git) trỏ DB thật `ylcqukhi_glyphutrung` + `production=true`;
> máy nhà đã có `config.local.php` riêng trỏ `tntt_demo`. Nhờ vậy KHÔNG còn phải
> "đổi lại DB" thủ công. Env `TNTT_DB_*` (nếu đặt) vẫn ghi đè tiếp lên trên cùng.

---

## Bước 6 — Chạy migration + kiểm tra (SSH)

Mở **Terminal** trong cPanel (hoặc SSH), vào thư mục code:
```bash
cd ~/tntt
php -v                                   # phải 8.2+
php config/install.php                   # chỉ khi cài DB rỗng (bỏ nếu đã import)
php config/migrate_roles_du_bi.php        # thêm vai Dự Bị + quyền
php config/migrate_modules_sync.php       # thêm module Báo cáo/Lịch trình
php config/migrate_bdh_view.php           # BĐH chỉ XEM điểm số + phiếu liên lạc
php config/migrate_lich_hop.php           # Lịch cá nhân + thông báo họp (RSVP)
php config/migrate_guide.php              # module Hướng dẫn sử dụng
php config/migrate_student_codes.php      # chuẩn hoá mã (nếu chưa chạy)
php phpunit10.phar --no-coverage          # 33/33 là OK
```

> Các script migrate đều **idempotent** — chạy lại vô hại. Sau khi có
> `migrate_lich_hop.php`, nhớ đặt **cron nhắc lịch** ở **Phụ lục C** để có
> thông báo đẩy khi đóng app.

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

**Nén (hiệu năng — nên bật):** API đã **tự nén gzip** ở tầng PHP (`ob_gzhandler`
trong `api/_bootstrap.php`) nên `api/data.php` truyền rất nhẹ (vd toàn đoàn
~4.8MB → ~140KB). Để nén luôn **JS/CSS tĩnh**, thêm vào `public/.htaccess` (nếu
host bật `mod_deflate`):
```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml
</IfModule>
```
Kiểm chứng: DevTools → Network → `data.php` có `Content-Encoding: gzip`.

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

## Bước 9 — (Tuỳ chọn) Tự động deploy khi push (GitHub Actions + SSH)

Repo đã có sẵn workflow `.github/workflows/deploy.yml`: mỗi lần **push lên
master**, GitHub SSH vào AZDIGI, `git pull` ở thư mục clone rồi chạy
`scripts/deploy.sh` (rsync sang thư mục app, giữ `config.php`/`backup`).

**Điều kiện:** host cho phép **SSH** (hỏi AZDIGI nếu chưa chắc). Không có SSH →
xem Phụ lục B.

**Thiết lập một lần:**
1. Tạo một cặp khoá SSH RIÊNG cho GitHub Actions (khác khoá deploy ở Bước 1):
   - Trên máy bạn: `ssh-keygen -t ed25519 -f deploy_actions -N ""` → được
     `deploy_actions` (private) và `deploy_actions.pub` (public).
   - Dán nội dung `deploy_actions.pub` vào host: cPanel → **SSH Access → Manage
     SSH Keys → Import Key** (dán vào ô public) → **Authorize**.
2. Thêm 4 **secret** trong GitHub repo (**Settings → Secrets and variables →
   Actions → New repository secret**):
   | Secret | Giá trị |
   |---|---|
   | `AZDIGI_HOST` | hostname/IP máy chủ SSH (AZDIGI cấp) |
   | `AZDIGI_USER` | user cPanel |
   | `AZDIGI_PORT` | cổng SSH (AZDIGI thường KHÔNG phải 22 — xem trong cPanel) |
   | `AZDIGI_SSH_KEY` | **toàn bộ nội dung file `deploy_actions`** (private key) |
3. Đảm bảo thư mục clone `~/repositories/tntt` đã tồn tại (Bước 2) và pull được
   (nó dùng deploy key GitHub ở Bước 1).

**Xong.** Từ giờ chỉ cần `git push` → sau ~1 phút host tự cập nhật. Xem log ở
tab **Actions** của repo. Chạy tay: tab Actions → **Deploy AZDIGI → Run workflow**.

> ⚠️ Migration KHÔNG tự chạy. Bản cập nhật nào có migration (thêm module/vai) thì
> vẫn SSH chạy tay một lần (Bước 6).
>
> Muốn "chỉ deploy khi CI xanh": đổi trigger trong `deploy.yml` sang
> `workflow_run` theo workflow CI — nhắn mình nếu cần.

---

## Phụ lục A — Lỗi thường gặp
- **500 Internal Server Error**: gần như luôn do **chưa tạo `config/config.local.php`**
  (hoặc sai thông tin DB trong đó), hoặc PHP < 8.2. Xem log: cPanel → **Errors**.
- **Trang trắng / thiếu CSS-JS**: docroot chưa trỏ đúng `public/`.
- **"Access denied for user"**: sai `user`/`pass`/`name` DB trong `config.local.php`
  (Bước 5), hoặc chưa Add User To Database.
- **Kết nối nhầm DB (vẫn ra `ylcqukhi_glyphutrung`/không có bảng)**: chưa tạo
  `config/config.local.php` ở đúng **thư mục APP** đang chạy (không phải thư mục clone).
- **Không thấy module Báo cáo/Lịch trình**: chưa chạy `php config/migrate_modules_sync.php`.
- **Không thấy "Lịch của tôi" / không tạo được buổi họp**: chưa chạy
  `php config/migrate_lich_hop.php`.
- **Không nhận thông báo nhắc khi đóng app**: chưa đặt **cron** (Phụ lục C),
  chưa khai khoá VAPID, hoặc người dùng chưa bật chuông thông báo.

## Phụ lục B — Không có SSH/Git trên hosting
- **GitHub Actions → FTP**: tạo workflow đẩy thư mục lên hosting qua FTP mỗi lần
  push (dùng secret FTP host/user/pass). Nhắn mình để soạn workflow.
- **Thủ công**: tải ZIP repo từ GitHub → giải nén → upload qua **File Manager**;
  cập nhật thì upload đè. (Chậm và dễ sót, chỉ dùng khi bí.)

## Phụ lục C — Cron nhắc lịch (thông báo đẩy khi đóng app)
Lịch cá nhân + buổi họp cần một cron chạy nền để gửi push khi tới giờ. Trên
cPanel: **Cron Jobs** → thêm job **mỗi 5 phút** (Common Settings: "Once per five
minutes") với lệnh (đổi đường dẫn php + thư mục cho đúng máy chủ):

    /usr/local/bin/php /home/<user>/public_html/config/nhac_lich.php >/dev/null 2>&1

Biểu thức thời gian 5 phút: đặt phút = `*/5`, các ô còn lại = `*`.

Kiểm tra tay (SSH): `php /home/<user>/public_html/config/nhac_lich.php` — in ra
số mục đã nhắc. Script chỉ chạy bằng CLI (gọi qua trình duyệt bị chặn 403).

Điều kiện: đã khai **khoá VAPID** trong `config/config.php` và người dùng đã
**bật thông báo** trên máy họ (nút chuông trong app). Không có cron thì lịch vẫn
chạy, chỉ mất phần nhắc khi app đóng (trong app vẫn nhắc ở "Việc cần làm").
