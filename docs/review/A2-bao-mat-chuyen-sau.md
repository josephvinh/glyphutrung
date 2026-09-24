# Báo cáo rà sâu bảo mật — WebAuthn · IDOR/Scope · Upload

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`
(Bổ sung cho `A-bao-mat.md`, thực hiện theo yêu cầu làm sâu các mục để ngỏ.)

## 1. Upload thư viện — **An toàn (không có lỗ hổng)**
`_library.php` + `library_file.php`:
- Kiểm `is_uploaded_file()` + `UPLOAD_ERR_OK`; chặn file rỗng và vượt `max_size_mb` (mặc định 15MB).
- **MIME thật bằng `finfo`** (không tin đuôi client); chỉ nhận loại trong whitelist. ZIP
  (docx/pptx) chỉ được suy từ đuôi cho loại **tải-về**, không bao giờ chạy inline.
- Tên lưu = `bin2hex(random_bytes(16))` → **không path traversal, không tên do người dùng đặt**.
  Tên gốc chỉ dùng để hiển thị, đã `basename()` + lọc ký tự điều khiển.
- File nằm **ngoài webroot** (`storage/library`); phục vụ qua `library_file.php` có
  `require_login()` + kiểm quyền (tài liệu chưa duyệt chỉ người đăng/`edit` xem được).
- Khi trả file: `X-Content-Type-Options: nosniff` + Content-Type do server đặt +
  `Content-Disposition` inline chỉ cho loại xem được, còn lại `attachment` → **không chạy như mã**.

→ Đây là cách xử lý upload theo đúng best practice. Không cần sửa.

## 2. IDOR / Phạm vi (Scope) — **Chắc chắn (không có IDOR)**
- Phạm vi được tính **phía server từ `$me`** (người đăng nhập) qua `member_scopes()` /
  `responsible_class_ids()` / `allowed_class_ids()` / `scan_class_ids()`. `admin`/`bdh`/
  vai "toàn đoàn" → `null` (toàn quyền); còn lại giới hạn theo khối/lớp được phân công.
  **Không bao giờ lấy phạm vi từ tham số request.**
- Áp dụng cho **cả danh sách lẫn thao tác đơn lẻ**: `students.php` chặn `403` nếu lớp
  đích không thuộc `allowed_class_ids($me)` (dòng 140–143, 188, 246) — "Bạn chỉ ghi được
  vào: …". Đây chính là lớp chặn IDOR cho thao tác theo id.
- Có bộ test `ScopeTest`/`PermissionHardeningTest` bảo vệ hồi quy.

→ Không phát hiện IDOR. Mô hình phân quyền + scope nhất quán và trưởng thành.

## 3. WebAuthn / Passkey — **Vững, 2 điểm hardening nhẹ**
Dùng thư viện `lbuchs/WebAuthn`; challenge lưu trong session (chuỗi nhị phân), verify
bằng `processCreate`/`processGet`. rpId tách cổng từ `HTTP_HOST` (đúng chuẩn).

### ✅ A2-1 — Challenge dùng một lần — ĐÃ SỬA
Trước đây `$_SESSION['webauthn_challenge']` **không bị xoá** sau khi verify → về lý thuyết
có thể tái dùng challenge cũ cho tới khi bị ghi đè. → **Đã thêm `unset()` ngay sau khi
đọc** trong cả luồng đăng ký và đăng nhập passkey (dùng một lần, bất kể thành/bại).

### 🟢 A2-2 — Không so sánh sign counter (Thấp — chỉ ghi nhận)
Code tăng `sign_count = sign_count + 1` cục bộ nhưng **không so sánh** counter do
authenticator trả về (`newCounter <= stored` ⇒ nghi ngờ credential bị nhân bản).
- Rủi ro **thấp**: passkey đồng bộ hiện đại (iCloud/Google) thường báo counter = 0 luôn,
  nên nhiều hệ thống chủ động bỏ kiểm để tránh khoá nhầm.
- **Đề xuất (không sửa ở đây vì đụng luồng xác thực, cần test kỹ)**: nếu muốn siết, đọc
  counter từ `authenticatorData`, chỉ từ chối khi `stored > 0 && new > 0 && new <= stored`.

## Kết luận
Ba mảng rủi ro cao nhất của một web quản lý (upload, phân quyền dữ liệu, xác thực sinh
trắc học) đều được xử lý **đúng và cẩn thận**. Chỉ có 1 hardening nhỏ đã vá (challenge
dùng một lần) và 1 ghi chú tuỳ chọn (sign counter). **Không phát hiện lỗ hổng cao/trung.**
