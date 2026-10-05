# Báo Cáo Audit Hạ Tầng — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Repo + Cấu hình web (không truy cập host thật)
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🔴 Nghiêm trọng | 1 | INF-1 (S1) |
| 🟡 Trung bình | 2 | INF-2, INF-NEW-1 |
| 🔵 Thấp / Thông tin | 1 | INF-NEW-2 |

> ⚠️ **Giới hạn:** Phần lớn audit hạ tầng cần truy cập máy chủ thật (SSH/cPanel).
> Phần này chỉ kiểm được **phần trong repo**.

---

## .htaccess

### ✅ Đã kiểm và thấy TỐT

| Điểm | Trạng thái |
|-------|------------|
| Chặn tải .sql/.log/.md/.env | ✅ |
| -Indexes | ✅ |
| Security headers (X-Content-Type-Options, X-Frame-Options, etc.) | ✅ |
| CSP header | ✅ |
| HTTPS redirect (HSTS) | ✅ |
| Cache headers cho asset | ✅ |
| Brotli/Gzip compression | ✅ |

### ⚠️ Cần bổ sung

- [ ] **Thêm `.json`** vào `FilesMatch` — bảo vệ cache files
- [ ] **Kiểm trùng lặp compression** — có cả gzip (line 157) và brotli (_bootstrap.php)

---

## Cấu hình web

### ✅ Đã kiểm và thấy tốt (trong code)

| Điểm | Vị trí | Trạng thái |
|-------|--------|------------|
| display_errors=0 | `_bootstrap.php:28` | ✅ |
| HTTPS headers | `_bootstrap.php:80-85` | ✅ |
| Session security | `auth.php` | ✅ |

### ⚠️ Cần kiểm trên host

| Điểm | Cần kiểm |
|-------|----------|
| SSL/TLS | HTTPS hoạt động |
| display_errors php.ini | =0 trên production |
| expose_php | =Off |
| PHP version | >=8.0 |

---

## Backup & Recovery

> ⚠️ **Không thể kiểm từ repo** — cần kiểm trên host.

### Cần xác nhận trên host

| Điểm | Cần kiểm |
|-------|----------|
| Backup DB tự động hằng ngày | mysqldump hoặc cPanel backup |
| Backup ra ngoài máy chủ | Cloud/local backup |
| Backup storage/library | Tài liệu không trong DB |
| Backup config.local.php | Secret không trong git |
| Diễn tập khôi phục | Test restore trên dev |

---

## Vấn đề

### 🔴 INF-1 — Cache vẫn trong public/ (S1)

- **Vị trí:** `public/api/cache.php:7`
- **Mô tả:** Cache được ghi vào `public/cache/` (trong web root)
- **Rủi ro:** Cache có thể chứa PII, tải được công khai nếu không có .htaccess chặn
- **Cách sửa:**
  1. Di chuyển cache ra ngoài web root (vd `storage/cache/`)
  2. Thêm `.htaccess` trong `public/cache/` với `Require all denied`
  3. Thêm `.json` vào `FilesMatch` chặn trong `.htaccess` gốc

---

### 🟡 INF-2 — install.php và seed_demo.php vẫn trên host

- **Vị trí:** `public/api/install.php`, `public/api/seed_demo.php`
- **Mô tả:** Các file cài đặt nên được gỡ/khóa sau khi cài đặt thành công
- **Cách sửa:**
  1. Đặt `setup_key` ngẫu nhiên trong `config.local.php`
  2. Hoặc xóa/gỡ các file này sau khi cài xong

---

### 🟡 INF-NEW-1 — Double compression

- **Vị trí:**
  - `.htaccess:154-160` — Brotli filter (`SetOutputFilter DEFLATE`)
  - `_bootstrap.php:54-75` — PHP gzip + brotli
- **Mô tả:** Cả web server và PHP cùng nén → lãng phí CPU, có thể gây lỗi
- **Cách sửa:**
  - Hoặc bỏ compression khỏi PHP, để `.htaccess` xử
  - Hoặc bỏ compression khỏi `.htaccess`, để PHP xử

---

### 🔵 INF-NEW-2 — Không có giám sát lỗi runtime

- **Mô tả:** Không thấy endpoint/logic gửi `window.onerror` về server
- **Cách sửa:** Cân nhắc thêm client-side error tracking

---

## Checklist (cần xác nhận trên host)

### Cấu hình PHP
- [ ] display_errors=0
- [ ] log_errors=1
- [ ] expose_php=Off
- [ ] session.cookie_httponly
- [ ] session.cookie_secure
- [ ] session.cookie_samesite

### HTTPS & Security
- [ ] HTTPS hoạt động
- [ ] HSTS header
- [ ] Certificate còn hạn

### Backup
- [ ] DB backup tự động
- [ ] Backup ra ngoài host
- [ ] Đã test restore

### Deployment
- [ ] Deploy script exclude tests/docs
- [ ] Backup trước migrate
- [ ] Kiểm sau deploy

---

## Khuyến nghị

### Ưu tiên cao
1. **INF-1:** Di chuyển cache ra ngoài web root

### Ưu tiên trung bình
2. **INF-2:** Xóa/gỡ install.php sau cài
3. **INF-NEW-1:** Sửa double compression

### Cần kiểm trên host
- Backup tự động
- HTTPS/HSTS
- PHP settings
