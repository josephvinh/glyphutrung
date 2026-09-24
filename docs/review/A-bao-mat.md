# Báo cáo Đợt A — Bảo mật ứng dụng

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

## Đánh giá tổng quan
Nền tảng bảo mật của web **khá tốt**. Điểm mạnh xác nhận được:

- **SQL Injection**: hầu hết truy vấn dùng prepared statement (`?`). Các mệnh đề `IN (...)` dựng placeholder bằng `implode(',', array_fill(0, n, '?'))` (an toàn), không nội suy giá trị người dùng vào chuỗi SQL. `LIMIT/OFFSET` trong `library.php` được ép `(int)` trong `library_page()`.
- **CSRF**: có `require_csrf()` (kiểm token cho mọi POST) + helper gộp `require_post()+require_csrf()` dùng ở hàng loạt endpoint (`_bootstrap.php`).
- **Đăng nhập**: có `login_throttle($phone)` chống dò mật khẩu và `session_regenerate_id(true)` chống session fixation.
- **XSS phía JS**: gần như không dùng `innerHTML` với dữ liệu người dùng (chỉ `toast.js` với SVG tĩnh). Alpine chủ yếu dùng `x-text` (tự escape).
- **Header bảo mật**: CSP/HSTS/X-Frame-Options/nosniff đặt ở tầng PHP (`_common.php`).
- **PII in phiếu liên lạc**: tên/mã số/lớp/nhận xét đã `htmlspecialchars` (trong `export.php` và `print.php`).

## Phát hiện & xử lý

### 🟠 A1 — Endpoint debug lộ trên production (Trung bình) — ĐÃ SỬA
`public/api/test_timezone.php` và `public/api/test_webauthn.php`: endpoint gỡ lỗi,
**không xác thực**, tự ghi chú "xoá sau khi xong". `test_webauthn.php` khởi tạo
WebAuthn và in `createArgs`. Không nơi nào trong app tham chiếu chúng.
→ **Đã gỡ** cả hai file khỏi repo.

### 🟡 A2 — Vài trường trong phiếu liên lạc chưa escape đồng nhất (Thấp, defense-in-depth) — ĐÃ SỬA
Trong khi tên/mã số/nhận xét đã escape, các trường sau echo thô:
- `export.php`: `$termName`, `$termFrom`, `$termTo`, `$createdBy` (tên người lập — do nhân sự nhập).
- `print.php`: `$termName`.

Giá trị do nhân sự nhập nên rủi ro thấp, nhưng render trong trang HTML in được → nên escape cho nhất quán.
→ **Đã bọc `htmlspecialchars()`** cho các trường trên.

### 🟢 A3 — Điểm cần theo dõi (chưa thấy lỗ hổng, nên xác minh)
- **`x-html` trong view** (`module_qrcard.php`, `module_student_profile.php`, `partial_student_profile_header.php`): bind vào `qrSvg(...)`/`qrPreviewHtml`. Nguồn là mã học sinh do hệ thống sinh → rủi ro thấp. Nên xác nhận `qrSvg()` không chèn dữ liệu ngoài vào SVG.
- **CSRF cho login/passkey**: `auth.php` (login) và `passkey.php` gọi `require_post()` nhưng không `require_csrf()` trực tiếp — thường là **cố ý** (token CSRF chưa có trước khi có phiên). Login đã có throttle. Nên xác nhận luồng passkey (register/authenticate) có chống replay/verify origin đầy đủ trong `public/api/webauthn/`.
- **`years.php` `FROM $bang`**: `$bang` là tham số closure, được gọi nội bộ bằng tên bảng cố định (không phải input) → an toàn; nên whitelist tường minh để chắc chắn.
- **CSP phải mở `unsafe-inline`/`unsafe-eval`**: do Alpine.js + `<script>` nhúng dữ liệu boot. Cải thiện dài hạn: dùng nonce cho script nhúng để bỏ `unsafe-inline`.

## Việc còn để ngỏ (đề xuất, chưa làm — cần xem sâu hơn)
- [ ] Rà kỹ luồng WebAuthn (`public/api/webauthn/*`): verify challenge/origin/rpId, chống replay, lưu credential id/counter.
- [ ] Rà **IDOR/scope**: xác nhận mọi endbir đọc/ghi dữ liệu học sinh/lớp đều kiểm `scope` theo vai (đối chiếu `ScopeTest`, `PermissionHardeningTest`) — cần đọc từng handler, chưa bao phủ hết trong đợt này.
- [ ] Rà upload thư viện (`library.php`/`_library.php`): kiểm loại MIME, chống path traversal ở `stored_name`, giới hạn dung lượng, phục vụ file có kiểm quyền.
- [ ] Cân nhắc CSRF cho passkey nếu luồng cho phép.

## Ghi chú môi trường
Unit test **không chạy được trong container này** vì thiếu MySQL ("Không kết nối được
cơ sở dữ liệu"). Đây là vấn đề hạ tầng test — chuyển sang **Đợt F** (CI cần dịch vụ DB
hoặc test dùng SQLite/in-memory). Các sửa đổi ở đợt này đã qua `php -l` sạch.
