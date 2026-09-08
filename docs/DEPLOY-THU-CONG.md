# Deploy THỦ CÔNG lên AZDIGI (không cần SSH/Git)

> Chỉ dùng **cPanel**: **File Manager** (tải code) + **phpMyAdmin** (nạp DB).
> Không gõ lệnh. Đưa lên **2 database**: chính thức (dữ liệu thật) + demo (600 em).
> Yêu cầu host: **PHP 8.2+**.

Ký hiệu: `cpxxx` = tên user cPanel của bạn (thấy ở góc trên phải cPanel).

---

## A. Chuẩn bị trên máy bạn (XAMPP)

**A1. Tải mã nguồn:** vào GitHub repo → nút xanh **Code** → **Download ZIP**
→ được `glyphutrung-master.zip`.

**A2. Xuất 2 database ra file `.sql`** (mở `http://localhost/phpmyadmin`):
- Chọn DB **`ylcqukhi_glyphutrung`** (thật) → tab **Export** → **Export** (Go)
  → lưu `ylcqukhi_glyphutrung.sql`.
- Chọn DB **`tntt_demo`** (demo) → **Export** → **Export** → lưu `tntt_demo.sql`.
- File lớn thì Export → chọn nén **gzip** cho nhẹ.

---

## B. Tạo & nạp database trên cPanel

**B1. Tạo DB + user** — cPanel → **MySQL® Databases**:
- **Create New Database**: tạo `cpxxx_tntt` (thật) và `cpxxx_demo` (demo) → 2 DB.
- **Add New User**: `cpxxx_app` + mật khẩu mạnh (ghi lại).
- **Add User To Database**: gán user `cpxxx_app` vào **cả 2** DB → **ALL PRIVILEGES**.

**B2. Nạp dữ liệu** — cPanel → **phpMyAdmin**:
- Chọn `cpxxx_tntt` (cột trái) → tab **Import** → chọn `ylcqukhi_glyphutrung.sql` → **Import**.
- Chọn `cpxxx_demo` → **Import** → chọn `tntt_demo.sql` → **Import**.

> DB nhập vào đã **đầy đủ bảng + mọi migration + dữ liệu** (vì export từ máy đã
> chạy đủ) → **KHÔNG cần** chạy `install.php` hay các `migrate_*.php`.

---

## C. Tải mã nguồn lên (File Manager)

**C1.** cPanel → **File Manager** → tạo thư mục **`tntt`** NGOÀI `public_html`
(ví dụ `/home/cpxxx/tntt`). *(Không đổi được docroot? xem mục G.)*

**C2.** Vào `tntt` → **Upload** → chọn `glyphutrung-master.zip`. Xong quay lại,
chọn file ZIP → **Extract**.
- Nếu giải nén ra thư mục con `glyphutrung-master/`: mở nó → **Select All** →
  **Move** ra `/home/cpxxx/tntt` (để `public/`, `config/`, `views/`… nằm NGAY
  trong `tntt`). Xoá thư mục con rỗng + file ZIP.

---

## D. Trỏ tên miền vào `public/`

cPanel → **Domains** → tên miền → **Manage** → **Document Root** =
`/home/cpxxx/tntt/public` → **Save**.

---

## E. Kết nối DB — tạo `config/config.local.php` (QUAN TRỌNG)

**KHÔNG sửa `config/config.php`.** Tạo file riêng (đã .gitignore, cập nhật code
sau không bị đè):

File Manager → `/home/cpxxx/tntt/config/` → **+ File** → đặt tên
`config.local.php` → chọn nó → **Edit** → dán và sửa cho đúng:
```php
<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'cpxxx_tntt',   // DB THẬT
        'user' => 'cpxxx_app',
        'pass' => 'MẬT_KHẨU_DB',
    ],
    'default_password' => 'đổi-khác-tntt@2026',
    'setup_key'        => 'chuỗi-bí-mật-ngẫu-nhiên-dài',
    'production'       => true,
];
```
→ **Save Changes**. *(Có mẫu sẵn: `config/config.local.example.php`.)*

---

## F. HTTPS + Cron + kiểm tra

**F1. HTTPS:** cPanel → **SSL/TLS Status** → **Run AutoSSL** (đợi ổ khoá xanh).
Ép HTTPS: sửa `public/.htaccess`, thêm vào ĐẦU file:
```apache
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

**F2. Cron nhắc lịch:** cPanel → **Cron Jobs** → Common Settings **Once per five
minutes** (`*/5 * * * *`) → Command:
```
/usr/local/bin/php /home/cpxxx/tntt/config/nhac_lich.php >/dev/null 2>&1
```

**F3. Kiểm tra:** mở `https://tenmien/` → đăng nhập (tài khoản trong DB thật của
bạn) → thấy đủ module, icon không trống. Xong.

---

## Chạy kèm bản DEMO (dữ liệu 600 em)

**Cách 1 — tạm thời (1 site):** sửa 1 dòng trong `config.local.php`:
`'name' => 'cpxxx_demo'` → cả site chạy demo; xong đổi lại `cpxxx_tntt`.

**Cách 2 — chạy đồng thời (khuyến nghị):** tạo **subdomain** riêng cho demo:
1. **Domains → Create A New Domain**: `demo.tenmien`.
2. Lặp lại mục C với thư mục khác: `/home/cpxxx/tntt-demo` (upload + extract).
3. Docroot của `demo.tenmien` → `/home/cpxxx/tntt-demo/public`.
4. Tạo `/home/cpxxx/tntt-demo/config/config.local.php` với `'name' => 'cpxxx_demo'`.

→ `tenmien` = dữ liệu thật, `demo.tenmien` = demo. Hai bản độc lập.

---

## Cập nhật mã nguồn về sau (thủ công)

1. Tải ZIP mới từ GitHub → **Upload** vào `tntt` → **Extract** đè.
2. **ĐỪNG đè** `config/config.local.php` và thư mục `config/backup/`
   (chúng KHÔNG có trong ZIP nên extract sẽ không xoá — cứ để yên).
3. Nếu bản mới có **migration** (thêm bảng/cột — VD Lịch cá nhân, Hướng dẫn):
   vì không có SSH, **nhắn mình đưa câu lệnh SQL** để bạn dán vào phpMyAdmin →
   tab **SQL** → chạy. (Các migration đều là `CREATE TABLE`/`ALTER`/`INSERT`
   đơn giản, chạy một lần.)

---

## G. Nếu KHÔNG đổi được Document Root

Bắt buộc chạy trong `public_html`: giải nén code vào chính `public_html`, rồi
tạo `public_html/.htaccess`:
```apache
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/$1 [L]
```
(Kém tách bạch hơn cách D — ưu tiên đổi Document Root nếu được.)

---

## Lỗi thường gặp
- **500 / trang lỗi**: chưa tạo `config/config.local.php` hoặc sai `name/user/pass` DB.
- **Trang trắng, mất CSS/JS**: docroot chưa trỏ vào `public/`.
- **"Access denied for user"**: user chưa được **Add To Database** hoặc sai mật khẩu.
- **Đăng nhập báo sai**: dùng tài khoản có trong DB vừa nhập (demo: `0901000001`
  / `tntt@2026`; bản thật: theo dữ liệu của bạn).
- **Không nhận thông báo khi đóng app**: chưa đặt Cron (F2) hoặc chưa bật chuông.
