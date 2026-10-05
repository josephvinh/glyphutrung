# CLAUDE.md — Hướng dẫn cho Claude Code trên repo này

> Claude Code (và mọi agent) đọc file này trước khi làm việc. Giữ **ngắn và
> đúng**. Mục lục toàn bộ quy trình + quy ước: **`docs/process/README.md`**.
> Quy định đầy đủ: `docs/process/AGENT_RULES.md`. Lỗ hổng đang mở:
> `docs/security/SECURITY_AUDIT.md`. Mười loại audit (bảo mật, chức năng, design,
> a11y, hiệu năng, riêng tư, toàn vẹn dữ liệu, hạ tầng, phụ thuộc, chất lượng
> code) — xem README.

## Dự án là gì

Ứng dụng web PHP (không framework) + Alpine.js quản lý **Gia Đình Giáo Lý Phú
Trung**: thiếu nhi, điểm danh, điểm số, phiếu liên lạc, Sổ Mộc/đổi quà, thông
báo, phân quyền theo vai (admin, BĐH, trưởng khối, GLV chủ nhiệm, GLV, dự bị,
thủ thư). **Dữ liệu là hồ sơ trẻ em** (tên, ngày sinh, địa chỉ, SĐT phụ huynh) →
bảo mật và phân quyền là ưu tiên số một.

- Web root: `public/`. API: `public/api/*.php`. Giao diện: `views/*.php` +
  `public/assets/js/modules/*.js` (gộp vào component Alpine `tnttApp`).
- Cấu hình: `config/config.php` (mặc định, lên git) bị `config/config.local.php`
  (KHÔNG lên git, chứa secret thật) ghi đè; biến `TNTT_DB_*` ghi đè tiếp.
- Nền chung API: `public/api/_bootstrap.php`; quyền ở `_bootstrap.php` + `_common.php`.
- Trang công khai (không đăng nhập): `tracuu.php`, `somoc.php`, `bxh.php`,
  `api/somoc_order.php`, `api/push.php`, `api/bible.php` → **mọi input không tin cậy**.

## Chạy & kiểm (xem TESTING.md để đầy đủ)

```bash
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/install.php && php tests/fixtures/ci_seed.php     # dựng DB test
php phpunit.phar --testsuite "TNTT Unit Tests"               # unit
npx --yes eslint@9.39.5 public/assets/js/ public/sw.js       # lint JS
php -S 127.0.0.1:8080 -t public                              # chạy app, rồi node tests/e2e/smoke.js
```

## LUẬT CỨNG (không vi phạm)

1. **Không commit/push/mở PR trừ khi người dùng yêu cầu rõ.** Mặc định: chỉ sửa
   file trong cây làm việc và báo cáo.
2. **"Xong" = đã chạy test + mở app thật và dán kết quả.** Không đoán, không nói
   "chắc chạy được". (skill `verification-before-completion`)
3. **Xóa code phải `grep` mọi nơi gọi trước**, và nói rõ trong báo cáo/PR. Một
   lần xóa nhầm `normalizeText` (#194) làm vỡ cả tìm kiếm + nhân sự.
4. **Một thay đổi = một mục đích.** Không gộp nhiều việc vào một PR.
5. **Không nuốt lỗi dữ liệu quan trọng**: tránh `INSERT IGNORE`/`catch{}` cho
   seed quyền, migration, ràng buộc khóa ngoại (xem lỗi quyền `thu_vien`).
6. **Không làm yếu phân quyền.** Endpoint ghi: `require_write()` +
   `require_permission(module,'edit')` + **kiểm phạm vi** theo-đối-tượng
   (`can_access_class`/`can_manage_*`). Thêm endpoint đụng dữ liệu lớp → thêm
   ngay test "lớp khác → 403".
7. **Secret chỉ nằm ở `config/config.local.php`.** Không commit khóa VAPID, mật
   khẩu, token. Không đưa **định danh model** (tên/ID model) vào commit, code, PR.
8. **Không chạy `seed_demo.php`/`install.php` trên DB thật.** Chỉ DB test/dev.
9. **Không lưu dữ liệu cá nhân vào `public/cache/`** (web root — xem S1). Cache
   phải ra ngoài web root.
10. **Không tự spawn subagent** trừ khi người dùng yêu cầu dùng subagent.
11. Mỗi mảnh JS **không đặt trùng tên** thuộc tính/hàm với mảnh khác (bị đè âm
    thầm khi gộp). Thêm module mới chỉ khai ở `public/assets/asset_manifest.php`.
12. Bài đăng GitHub (comment/review): **tiết chế**, và kết bằng footer ghi nguồn
    Claude Code (xem AGENT_RULES.md).

## Khi đụng việc nhạy cảm

- Sửa auth/phiên/quyền/dữ liệu thiếu nhi/endpoint công khai/upload/cache → đọc
  `docs/security/SECURITY_AUDIT.md` trước, và chạy test phân quyền.
- Đổi schema → migration `config/migrations/NNN_*.sql` + cập nhật `install.php`.
- Không chắc yêu cầu → hỏi, đừng đoán.
