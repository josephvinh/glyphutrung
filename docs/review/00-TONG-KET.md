# TỔNG KẾT REVIEW TOÀN DIỆN — Web TNTT

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`
Kế hoạch gốc: [`docs/KE-HOACH-REVIEW-TOAN-DIEN.md`](../KE-HOACH-REVIEW-TOAN-DIEN.md)

## Kết luận chung
Dự án **chất lượng tốt và trưởng thành**. Backend/CSDL là phần mạnh nhất (phân quyền
nhất quán, không SQLi, ràng buộc DB chặt). Không phát hiện lỗ hổng bảo mật nghiêm trọng.
Rủi ro cao nhất **không nằm ở code mà ở vận hành**: secret bị commit trong lịch sử git.

## Đã SỬA trong đợt review này (đã push)
| # | Việc | Đợt | File |
|---|------|-----|------|
| 1 | Gỡ 6.442 file rác khỏi git (scratch/, profile Chromium, log, phar…), siết `.gitignore` | B | `.gitignore` |
| 2 | Gỡ 2 endpoint debug không xác thực | A | `test_timezone.php`, `test_webauthn.php` |
| 3 | Escape thêm vài trường phiếu liên lạc (defense-in-depth) | A | `export.php`, `print.php` |
| 4 | **Sửa bug parse** làm crash migration runner | F | `config/migrations/index.php:115` |
| 5 | Siết CI `php -l` thành chặn lỗi (bỏ `\|\| true`) | F | `.github/workflows/ci.yml` |
| 6 | **CI chạy PHPUnit với MySQL + seed qua install.php** (test thực sự chạy) | F | `.github/workflows/ci.yml` |
| 7 | WebAuthn: challenge dùng một lần (`unset` sau khi đọc) | A | `passkey.php` |

## Đã RÀ SÂU thêm (theo yêu cầu, tài liệu mới)
- **Bảo mật chuyên sâu** (`review/A2-bao-mat-chuyen-sau.md`): upload thư viện & IDOR/scope
  **an toàn, không lỗ hổng**; WebAuthn vững (còn 1 ghi chú tuỳ chọn: so sánh sign counter).
- **Spec hợp nhất migration** (`../HE-THONG-MIGRATION.md`): bảng ánh xạ 12 script →
  numbered migration + quy trình thực hiện có kiểm chứng trên staging.

## CÒN ĐỂ NGỎ — theo mức ưu tiên

### 🔴 CAO (làm trước khi deploy)
- **[B] Xoay secret bị lộ**: khoá **VAPID private**, `setup_key`, `default_password` nằm
  trong `config/config.php` (và lịch sử git). Tạo khoá mới → `config/config.local.php`;
  cân nhắc `git filter-repo` dọn lịch sử. (Cần bạn thực hiện; sau khi bạn xác nhận có
  `config.local.php` trên máy chủ, tôi có thể blank secret trong `config.php`.)

### 🟠 TRUNG BÌNH
- **[E] Hợp nhất hệ migration**: schema.sql vs `migrations/` (gần như trống) vs 12
  `migrate_*.php` rời rạc → chọn một nguồn sự thật, tránh lệch schema.
- **[F] Cho PHPUnit chạy được trên CI**: thêm service MySQL + nạp schema, hoặc cho
  `db.php` kết nối lazy để test logic không cần DB.
- **[A] Rà sâu (cần đọc kỹ/chạy)**: luồng WebAuthn (verify challenge/origin/replay),
  IDOR/scope trên từng handler, kiểm upload thư viện (MIME/dung lượng/traversal).

### 🟢 THẤP (cải thiện dần)
- [F] Thêm `eslint.config.mjs` rồi bỏ `|| true` ở bước ESLint; mở rộng độ phủ test.
- [C] Tách `data.php`/`export.php`/`core.js`; gom `date_default_timezone_set` về bootstrap.
- [G] Chủ động xoá cache sau thao tác ghi; lazy-load module JS; cache-control asset.
- [E] Index `scores(student_id)` nếu có màn hình điểm xuyên kỳ.
- [D] Xác nhận camera tắt trên mọi đường thoát; đảm bảo mọi `fetch` có xử lý lỗi.
- [H] Kiểm thử thủ công trên thiết bị thật; [I] đồng bộ tài liệu sau khi hợp nhất migration.

## Báo cáo chi tiết theo đợt
- Đợt A — Bảo mật: [`A-bao-mat.md`](A-bao-mat.md)
- Đợt B — Vệ sinh repo & secret: [`B-ve-sinh-repo-secret.md`](B-ve-sinh-repo-secret.md)
- Đợt C+E — Backend & CSDL: [`C-E-backend-csdl.md`](C-E-backend-csdl.md)
- Đợt F+G — Kiểm thử/CI & Hiệu năng: [`F-G-kiemthu-ci-hieunang.md`](F-G-kiemthu-ci-hieunang.md)
- Đợt D+H+I — Frontend/UX/Tài liệu: [`D-H-I-frontend-ux-docs.md`](D-H-I-frontend-ux-docs.md)
