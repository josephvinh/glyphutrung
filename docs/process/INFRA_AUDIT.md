# Quy Ước Audit Hạ Tầng / Triển Khai / Sao Lưu

> `SECURITY_AUDIT.md` cố ý **không** rà cấu hình máy chủ thật. Tài liệu này lấp
> chỗ đó: máy chủ (LiteSpeed/AZDIGI shared hosting), cấu hình PHP, quyền thư mục,
> HTTPS, **sao lưu + khôi phục**, và quy trình triển khai. Mất DB = mất nhiều năm
> hồ sơ giáo lý → **backup là mục quan trọng nhất ở đây**.

Đa phần việc này cần truy cập máy chủ thật (cPanel/SSH) — agent **không** tự làm,
chỉ hướng dẫn và kiểm phần trong repo.

---

## 1. Khi nào

- Trước khi đưa lên host lần đầu; sau mỗi thay đổi hạ tầng; định kỳ (quý).
- Xuất báo cáo: `docs/audit/INFRA_AUDIT_<YYYY-MM>.md`.

## 2. Cấu hình web & PHP

- **`.htaccess`** (đã khá chắc): chặn tải `.sql/.log/.md/.env…`, `-Indexes`,
  security headers, cache asset. ⚠️ **Bổ sung:** chặn `.json` (cache — S1).
  Mọi chỉ thị bọc `<IfModule>` để không sập toàn site trên host thiếu module.
- **Thư mục ngoài web root:** `config/`, `views/`, `storage/library` đã ngoài
  `public/`. ⚠️ **Chuyển `public/cache/` ra ngoài** (S1).
- **PHP ini (kiểm trên host):** `display_errors=0` (đã set ở `_bootstrap`),
  `log_errors=1`, `expose_php=Off`, `session.cookie_httponly/secure/samesite`
  (đã set ở code), giới hạn `upload_max_filesize`/`post_max_size` hợp lý.
- **Quyền thư mục:** `storage/`, `logs/`, `public/cache/` ghi được bởi PHP; mã
  nguồn **chỉ đọc**; `config/config.local.php` quyền chặt (600).

## 3. HTTPS & secret

- HTTPS bắt buộc; **HSTS** đã set khi `$_SERVER['HTTPS']` (max-age 2 năm) +
  Alt-Svc HTTP/3. Kiểm chứng chỉ còn hạn, redirect http→https.
- **Secret chỉ ở `config/config.local.php`** (ngoài git): DB thật, VAPID, setup_key.
  `config.php` để giá trị rỗng/mặc định. Xem S2 — xoay secret đã lộ.
- `setup_key` chỉ đặt khi cần chạy `install.php` qua web; để rỗng thì chỉ chạy
  được bằng CLI (`guard_setup`). Sau cài xong nên gỡ `install.php`/`seed_demo.php`
  khỏi host.

## 4. Sao lưu & khôi phục (QUAN TRỌNG NHẤT)

- [ ] **Backup DB tự động** hằng ngày: `mysqldump` (hoặc cPanel backup) → lưu
      **ngoài máy chủ** (tải về / cloud), giữ nhiều bản (vd 7 ngày + 4 tuần).
- [ ] **Backup `storage/library`** (tệp tài liệu — không nằm trong DB).
- [ ] **Backup `config/config.local.php`** (secret — không nằm trong git) ở nơi
      an toàn, riêng với backup mã nguồn.
- [ ] **Diễn tập khôi phục**: định kỳ dựng lại DB + file từ backup trên máy test,
      xác nhận app chạy. Backup **chưa thử khôi phục** = coi như không có.
- [ ] Ghi rõ **RPO/RTO** mong muốn (mất tối đa bao nhiêu dữ liệu / bao lâu phục hồi).

Lệnh mẫu (chạy trên host, không phải repo):
```bash
mysqldump --single-transaction --default-character-set=utf8mb4 ylcqukhi_glyphutrung \
  | gzip > backup_$(date +%F).sql.gz        # rồi tải về ngoài máy chủ
tar czf library_$(date +%F).tgz storage/library
```

## 5. Triển khai (deploy)

- Mã lên host bằng `git pull` hoặc `rsync --exclude` (loại `tests/ docs/ build/
  .github/ node_modules/ *.md package*.json` — xem `GITHUB_SETUP.md` §10).
- Sau deploy có đổi schema: chạy `php config/install.php` (idempotent) / migration
  trên host; **sao lưu DB TRƯỚC** khi migrate.
- `sw.js` phải `no-cache` (đã set) để cập nhật app không kẹt bản cũ.
- Sau deploy: mở app thật một lượt (đăng nhập, đổi màn, một thao tác ghi) +
  xác nhận `/cache/*.json` **không** tải được từ ngoài.

## 6. Checklist

- [ ] `.htaccess` chặn `.json`; cache ra ngoài web root.
- [ ] `display_errors=0`, `expose_php=Off` trên host; không lộ lỗi/đường dẫn.
- [ ] HTTPS + HSTS hoạt động; chứng chỉ còn hạn.
- [ ] Secret chỉ ở `config.local.php`; `install.php`/`seed_demo.php` đã gỡ/khóa.
- [ ] **Backup DB + storage + config.local tự động, đã thử khôi phục.**
- [ ] Quy trình deploy có bước backup-trước-migrate + kiểm sau deploy.
- [ ] Giám sát: có nơi đọc được `logs/` + `error_log`; cân nhắc báo lỗi runtime
      client (`window.onerror`) về một endpoint.

## 7. Công cụ

- Header/HTTPS: `curl -sI https://<host>/` (xem HSTS, headers, server).
- Chứng chỉ: `echo | openssl s_client -connect <host>:443 2>/dev/null | openssl x509 -noout -dates`.
- `.read_documentation` của môi trường cloud cho phần container/secret/network
  khi làm trong phiên Claude Code.

## 8. Báo cáo & quy trình

Finding: **mức** (🔴 mất dữ liệu/lộ qua cấu hình / 🟠 thiếu backup-thử / 🟡 hardening
thiếu / 🔵 ghi chú), **chỗ**, **rủi ro**, **cách sửa**. Toàn-app:
`docs/audit/INFRA_AUDIT_<YYYY-MM>.md`. Phần chồng với bảo mật → `SECURITY_AUDIT.md`.

---
_Phần lớn cần truy cập host; agent hướng dẫn và kiểm phần repo, không tự đụng máy chủ._
