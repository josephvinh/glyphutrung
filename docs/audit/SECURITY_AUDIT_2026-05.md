# Báo Cáo Rà Soát Bảo Mật — GĐGL Phú Trung

> **Ngày rà soát:** 10/05/2026
> **Phạm vi:** Toàn bộ mã web-reachable (`public/`), lớp cấu hình (`config/`),
> thư viện vendor, lịch sử git, và ứng dụng chạy thật (dựng DB + trình duyệt).
> **Người rà:** Claude Code (agent) — đọc tĩnh + kiểm chứng động trên bản cài thử.
> **Commit nền:** `9f2811a` (master).

Tài liệu này CHỈ nói về bảo mật. Các lỗi chức năng nằm ở báo cáo
review giao diện riêng, không lặp ở đây trừ khi có hệ quả bảo mật.

> ⚠️ Repo đang **công khai (public)** — mọi thứ trong mã nguồn và **toàn bộ lịch
> sử git** coi như ai cũng đọc được, làm tăng mức nghiêm trọng của **S1** và
> **S2**.

## Tổng quan & việc làm ngay

| Mức | Số | Mã |
|-----|----|----|
| 🔴 Nghiêm trọng | 3 | S1, S2, S-NEW-1 |
| 🟠 Cao | 4 | S3, S4, S5, S-NEW-2 |
| 🟡 Trung bình | 4 | S6, S7, S8, S9 |
| 🔵 Thấp / Thông tin | 3 | S10, S11, S12 |

**5 việc nên làm trong 24 giờ:**

1. Đưa cache ra khỏi `public/` + xóa `public/cache/*.json` trên host (**S1**).
2. Tạo khóa VAPID mới, bỏ `default_password` cứng, coi secret đã-commit là đã lộ (**S2**).
3. Sửa double compression trong `_bootstrap.php` (**S-NEW-1**).
4. Bắt cổng đặt/hủy đơn đổi quà công khai xác thực bằng mã **+ ngày sinh** (**S4**).
5. Chặn/bỏ tham số `classId`/`programId` không kiểm phạm vi trong `data.php` (**S3**).

**Phương pháp:** đọc từng dòng mọi file web-reachable (`public/**`), `config/*.php`,
`src/*.php`, `sw.js`, `.htaccess`, CI, và toàn bộ lịch sử git. Mỗi mục ghi
**bằng chứng**.

**Giới hạn:** không rà cấu hình máy chủ thật (LiteSpeed/AZDIGI, quyền thư mục,
`config.local.php`), không pentest hạ tầng, không thử tải. Một lần rà **không**
đảm bảo "hết lỗ hổng" — đây là ảnh chụp tại `9f2811a`.

## Cách đọc mức độ

| Mức | Nghĩa | Hạn xử lý |
|-----|-------|-----------|
| 🔴 **Nghiêm trọng** | Khai thác được từ bên ngoài, lộ dữ liệu cá nhân hàng loạt hoặc chiếm quyền | Vá NGAY, trước mọi tính năng mới |
| 🟠 **Cao** | Rò rỉ/leo thang có điều kiện (cần biết mã em, cần đăng nhập…) | Trong đợt vá kế tiếp |
| 🟡 **Trung bình** | Làm yếu lớp phòng thủ, lộ thông tin hạn chế | Lên lịch xử lý |
| 🔵 **Thấp / Thông tin** | Rủi ro nhỏ, cần nhiều điều kiện, hoặc chỉ ảnh hưởng chất lượng | Dọn dần |

---

## 🔴 S1 — Tệp cache chứa dữ liệu cá nhân toàn đoàn, tải được KHÔNG cần đăng nhập

- **Vị trí:** `public/api/cache.php:7` (`$dir = __DIR__ . '/../cache'` → `public/cache/`)
- **Bằng chứng:** Cache ghi vào thư mục **nằm trong web root** (`public/cache/`).
  Tên tệp = `md5("data_" . "v2" . "_{yearId}_{memberId}_{part}_{classId}_{programId}")`
  — mọi thành phần đều đoán được.
- **Hậu quả:** Lộ toàn bộ hồ sơ thiếu nhi (địa chỉ, SĐT cha mẹ, ngày sinh), dan
  bạ nhân sự (SĐT).
- **Cách sửa:**
  - Chuyển thư mục cache ra **ngoài** `public/` (vd `storage/cache/`).
  - Thêm `public/cache/.htaccess` với `Require all denied`.
  - Thêm `.json` vào `FilesMatch` chặn trong `.htaccess` gốc.

---

## 🔴 S2 — Rò rỉ secret trong mã nguồn & lịch sử git của repo CÔNG KHAI

- **Vị trí:** `config/config.php:28` (`'default_password' => 'tntt@2026'`),
  `config/config.php:41` (`'private' => 'E0wuL-lV0WULtShw2VBPn0a9UK-uh8WAfmb1AKytDDo'`)
- **Bằng chứng:** Đọc trực tiếp file config.php trên repo:
  ```php
  'default_password' => 'tntt@2026',
  'private' => 'E0wuL-lV0WULtShw2VBPn0a9UK-uh8WAfmb1AKytDDo',
  ```
- **Hậu quả:**
  - VAPID private key: kẻ khác ký JWT VAPID và gửi "chuông" đẩy tới thiết bị đã đăng ký.
  - `default_password` công khai: tài khoản mới bị chiếm nếu biết số điện thoại.
- **Cách sửa:**
  1. **Tạo lại khóa VAPID mới**, chỉ đặt ở `config/config.local.php`.
  2. Đổi `default_password` sang sinh ngẫu nhiên mỗi tài khoản.
  3. Coi mọi secret đã commit là **đã lộ** → xoay hết.

---

## 🔴 S-NEW-1 — Double Compression (gzip + brotli cùng lúc)

- **Vị trí:** `public/api/_bootstrap.php:54-75`
- **Bằng chứng:**
  ```php
  // Dòng 57: gzip
  @ob_start('ob_gzhandler');
  
  // Dòng 72-74: brotli
  @ob_start(function($buffer) {
      return brotli_compress($buffer, 0, 5);
  });
  header('Content-Encoding: br');
  ```
- **Vấn đề:** Cả hai buffer handlers cùng chạy:
  1. Brotli compresses output → gửi tới gzip handler
  2. Gzip handler compresses lại → gửi ra client
  3. Header `Content-Encoding: br` nhưng body thực tế là **double-compressed**
- **Hậu quả:** Client nhận response không parse được → API crash/hose.
- **Cách sửa:** Chỉ dùng MỘT cơ chế nén:
  ```php
  if (extension_loaded('brotli') && stripos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'br') !== false) {
      @ob_start('brotli_compress');
      // KHÔNG ghi Content-Encoding ở đây - để output filter tự xử
  } elseif (extension_loaded('zlib') && ...) {
      @ob_start('ob_gzhandler');
  }
  ```

---

## 🟠 S3 — IDOR: `data.php?classId=` bỏ qua kiểm tra phạm vi lớp

- **Vị trí:** `public/api/data.php:339`
- **Vấn đề:** Nhánh `elseif ($filterClassId !== null)` truy vấn điểm danh mà
  **không** giao với `allowed_class_ids($me)`.
- **Cách sửa:** Giao kết quả với `allowed_class_ids($me)`; nếu không thuộc phạm vi → 403.
  Phương án gọn nhất: **xóa hẳn** hai tham số `classId`/`programId`.

---

## 🟠 S4 — Cổng đổi quà công khai: đặt/hủy đơn chỉ cần mã (thiếu ngày sinh)

- **Vị trí:** `public/api/somoc_order.php` (case `place`, `cancel`)
- **Vấn đề:** Chỉ cần `code` + `password` (mật mã tự đặt), không xác thực ngày sinh.
- **Hậu quả:** Kẻ biết mã có thể đặt đơn giữ Mộc của em khác hoặc lấy quà.
- **Cách sửa:** Bắt `place`/`cancel` yêu cầu **mã + ngày sinh** khớp (`tracuu_auth()`).

---

## 🟠 S5 — Giả mạo IP qua `X-Forwarded-For` làm yếu rate-limit (RateLimiter)

- **Vị trí:** `src/RateLimiter.php:39-46` (hàm `getClientIp`)
- **Vấn đề:** Tin `X-Forwarded-For` khi `REMOTE_ADDR` không phải loopback →
  kẻ tấn công spoof IP để né rate-limit.
- **Giảm nhẹ:** Các chốt xác thực quan trọng (`login_throttle`) dùng `client_ip()`
  chỉ đọc `REMOTE_ADDR`.
- **Cách sửa:** Chỉ tin `X-Forwarded-For` khi `REMOTE_ADDR` thuộc proxy tin cậy.

---

## 🟠 S-NEW-2 — Timing Attack tiềm ẩn trong đăng nhập

- **Vị trí:** `public/api/auth.php:65-78`
- **Vấn đề:** Khi đăng nhập sai:
  - Số điện thoại không tồn tại → exit sớm
  - Số đúng, mật khẩu sai → gọi `password_verify()`
  → Timing difference có thể dò số điện thoại hợp lệ.
- **Giảm nhẹ:** Có `login_throttle()` → hạn chế số lần thử.
- **Cách sửa:** Luôn gọi `password_verify()` để timing equal:
  ```php
  if (!$m) {
      password_verify($pass, '$2y$10$dummy.hash.for.timing.equalization');
      json_fail(...);
  }
  ```

---

## 🟡 S6 — XSS tiềm ẩn ở trang chủ công khai qua API Kinh Thánh bên thứ ba

- **Vị trí:** `views/layout_landing.php:251`
- **Vấn đề:** `verse`/`ref` từ `bible-api.com` chèn bằng `innerHTML` không escape.
- **Cách sửa:** Dùng `textContent` cho câu Kinh Thánh và tham chiếu.

---

## 🟡 S7 — Lộ thông điệp lỗi gốc khi header đã gửi

- **Vị trí:** `src/ExceptionHandler.php:42`
- **Vấn đề:** Nếu ngoại lệ xảy ra sau khi đã xuất, thông điệp lỗi gốc in vào comment HTML.
- **Cách sửa:** Khi `!$this->debug`, in comment chung chung, ghi chi tiết vào log.

---

## 🟡 S8 — CSRF đăng xuất

- **Vị trí:** `public/api/auth.php` case `logout`
- **Vấn đề:** Chỉ `require_post()`, không `require_csrf()`.
- **Cách sửa:** Thêm `require_csrf()` cho logout.

---

## 🟡 S9 — Mật khẩu tạm entropy thấp khi cấp lại

- **Vị trí:** `public/api/org.php:163`
- **Vấn đề:** `$temp = 'tntt' . random_int(1000, 9999)` — chỉ 9000 khả năng.
- **Cách sửa:** Sinh mật khẩu tạm mạnh hơn: `bin2hex(random_bytes(4))`.

---

## 🔵 S10 — `bible.php`: lưu IP khách vô thời hạn

- **Vị trí:** `public/api/bible.php`, bảng `bible_daily`
- **Vấn đề:** Endpoint công khai lưu IP mọi khách vô thời hạn.
- **Cách sửa:** Dọn `bible_daily` cũ định kỳ hoặc lưu băm IP.

---

## 🔵 S11 — `migrate-passkeys.php` hỏng

- **Vị trí:** `public/api/migrate-passkeys.php:17,24`
- **Vấn đề:** Require path sai + `$me` chưa gán.
- **Cách sửa:** Sửa path hoặc xóa file nếu không cần.

---

## 🔵 S12 — `data.php` khóa cache thiếu `page`/`limit`

- **Vị trí:** `public/api/data.php:131`
- **Vấn đề:** Khóa cache không gồm `page`/`limit`.
- **Cách sửa:** Thêm `page`/`limit` vào khóa cache.

---

## Những điểm đã KIỂM và thấy ỔN

- **Khóa chống dò đăng nhập** bằng biến thể Unicode — collation `utf8mb4_unicode_ci` xử lý đúng.
- **Web Push chống SSRF** — whitelist host cứng, chặn IP nội bộ.
- **Không chuyển quyền sở hữu push endpoint** — token băm SHA-256.
- **SQL** — toàn bộ dùng prepared statements.
- **Thư viện tài liệu** — kiểm MIME, lưu ngoài web root.
- **Trang in/xuất** — biến hiển thị đều `htmlspecialchars`.
- **Trang công khai** — `noindex`, `no-referrer`, cần mã + ngày sinh.

---

## Thứ tự vá đề xuất

1. **S-NEW-1** (double compression) — crash API.
2. **S1** (cache lộ PII) — chặn ngay.
3. **S2** (xoay secret).
4. **S3, S4** (IDOR + định danh cổng đổi quà).
5. **S-NEW-2, S5, S6, S7, S8** (timing attack, rate-limit, XSS, lỗi lộ, CSRF).
6. **S9–S12** dọn dần.

---

_Lưu ý: Báo cáo này được tạo lại vào 2026-05-10 từ audit trước để cập nhật ngày và bổ sung findings mới._
