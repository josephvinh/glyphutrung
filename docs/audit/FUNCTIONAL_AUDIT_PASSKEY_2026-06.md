# Báo Cáo Audit Chức Năng — Đăng Nhập Sinh Trắc Học (FaceID / Vân Tay / Passkey)

**Ngày:** 2026-06-04
**Phạm vi:** Module Passkey/WebAuthn — đăng nhập bằng FaceID, vân tay
**Mục tiêu:** theo yêu cầu — audit chức năng tính năng "chức năng đăng nhập bằng faceid vân tay"
**Phương pháp:** Đọc code + kiểm chứng động bằng test hiện có
**Giới hạn:** Không chạy app thật trong session này; dựa vào `tests/e2e/hw.js` và `tests/e2e/p1_regress.py` để kiểm chứng động.

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 1 | F-PK-1 |
| 🟡 Trung bình | 2 | F-PK-2, F-PK-3 |
| 🔵 Thông tin | 4 | F-PK-4 → F-PK-7 |
| ✅ Đúng | 5 | F-PK-8 → F-PK-12 |

---

## 1. Happy Path (theo vai)

### ✅ Happy Path — Mọi vai

**Ca:** GLV chủ nhiệm, GLV, Trưởng khối, BĐH, Admin đăng ký và đăng nhập bằng Passkey.

| Bước | Kỳ vọng | Kết quả |
|------|---------|---------|
| Bật Passkey ở Hồ sơ | Gọi `register()` → browser prompt → lưu `member_passkeys` | ✅ Code đúng (`passkey.php:100`) |
| Đăng nhập bằng nút FaceID | Browser prompt → xác minh → `login_ok()` + session | ✅ Code đúng (`passkey.php:220-227`) |
| Gỡ Passkey | Xoá dòng `member_passkeys`, toggle gạt về tắt | ✅ Code đúng (`passkey.php:125`) |
| Kiểm tra trạng thái | Toggle gạt hiển thị đúng BậT/TẮT | ✅ Code đúng (`passkey.php:117`) |

**Bằng chứng:**
- `tests/e2e/hw.js:68-70` — PK-01: register thành công
- `tests/e2e/hw.js:80` — PK-02: row lưu vào `member_passkeys`
- `tests/e2e/hw.js:72` — PK-03: `status()` báo đúng
- `tests/e2e/hw.js:95` — PK-06: gỡ xoá row
- `tests/e2e/p1_regress.py:481` — AUTH-09a: register với attestation=none

---

## 2. Ca Biên

### 🟠 F-PK-1 — Challenge không có expiration time

- **Mức:** 🟠 Cao
- **Vị trí:** `public/api/passkey.php:70`, `:80`, `:142`, `:156`
- **Mô tả:** Challenge WebAuthn được lưu vào `$_SESSION['webauthn_challenge']` nhưng **không có thời gian hết hạn** và **không kiểm tra reuse**. Một kẻ tấn công có thể:
  1. Gây lỗi đăng nhập cho nạn nhân (social engineering)
  2. Thu intercept challenge cũ (man-in-the-middle)
  3. Replay challenge đã dùng để xác minh
- **Kỳ vọng:** Challenge phải có thời gian hết hạn (ví dụ `$_SESSION['webauthn_challenge_expire'] = time() + 240`) và `processLogin` phải từ chối challenge đã dùng hoặc hết hạn.
- **Thực tế:** Code dùng `$_SESSION['webauthn_challenge'] ?? ''` không kèm expire — trống coi như invalid nhưng không chặn replay.
- **Cách sửa:** Thêm `$_SESSION['webauthn_challenge_expire'] = time() + PASSKEY_TIMEOUT_SECONDS;` ở `getLoginArgs`/`getRegisterArgs`; ở `processLogin`/`processRegister` kiểm `time() > $_SESSION['webauthn_challenge_expire']` → từ chối; sau khi dùng xong unset challenge. Hoặc dùng nonce/one-time token.
- **Trạng thái:** 📖 từ đọc code, **chưa kiểm chứng động** (cần server thật)

---

### 🟡 F-PK-2 — userVerification='preferred' không bắt buộc sinh trắc

- **Mức:** 🟡 Trung bình
- **Vị trí:** `public/api/passkey.php:135` — `getGetArgs(..., true, true, true, true, true)` và `passkey.js:164` — `navigator.credentials.get({publicKey: args})`
- **Mô tả:** Tham số thứ 5 trong `getGetArgs` là `requireUserVerification=false` (xem `WebAuthn.php:275`). Browser/authenticator có thể bỏ qua kiểm tra sinh trắc (biometric) và chỉ dùng mã PIN thiết bị.
- **Kỳ vọng:** Đối với app GĐGL chứa dữ liệu trẻ em, nên yêu cầu userVerification='required' để đảm bảo dùng sinh trắc thật (FaceID/vân tay), không phải chỉ PIN.
- **Thực tế:** Trên Android/iOS, nhiều browser triển khai WebAuthn yêu cầu biometric/PIN bất kể setting. Tuy nhiên, `requireUserVerification=false` không đảm bảo điều này trên mọi nền tảng.
- **Cách sửa:** Đổi thành `requireUserVerification=true` ở `passkey.php:135` và kiểm kỹ trên Android + iOS + Desktop trước khi deploy.
- **Trạng thái:** 📖 từ đọc code

---

### 🟡 F-PK-3 — Registration endpoint không có rate limiting

- **Mức:** 🟡 Trung bình
- **Vị trí:** `public/api/passkey.php:56-72` (`getRegisterArgs`) và `:74-112` (`processRegister`)
- **Mô tả:** Rate limiting (throttle/failed) chỉ áp dụng cho `processLogin`. Hai endpoint registration **không bị giới hạn**.
- **Kỳ vọng:** Attacker có thể bruteforce đăng ký passkey cho nhiều tài khoản để xác định ai có tài khoản trong hệ thống (credential enumeration).
- **Thực tế:** `processRegister` gọi `require_login()` — attacker cần đã đăng nhập trước. Rủi ro giảm nhưng vẫn có attack surface: người dùng đã đăng nhập có thể spam đăng ký.
- **Cách sửa:** Thêm rate limit cho `processRegister` (ví dụ: tối đa 3 lần/thành viên/giờ) hoặc throttle theo IP cho cả `getRegisterArgs`.
- **Trạng thái:** 📖 từ đọc code

---

## 3. Xử Lý Lỗi

### ✅ F-PK-8 — Clone detection bằng sign_count

- **Vị trí:** `public/api/passkey.php:182-193`
- **Mô tả:** Sau mỗi login thành công, `sign_count` được cập nhật. Nếu bước nhảy > 10, ghi log cảnh báo.
- **Kỳ vọng:** Attacker sao chép credential → sign_count tăng đột ngột → bị phát hiện.
- **Kiểm chứng:** ✅ `tests/e2e/hw.js:83-89` — PK-05: đặt sign_count +1000 → đăng nhập bị từ chối.

### ✅ F-PK-9 — must_change_pw chặn tạo session

- **Vị trí:** `public/api/passkey.php:215-218`
- **Mô tả:** Khi `must_change_pw=1`, trả về HTTP 403 + `code: 'must_change_pw'` — **không** tạo phiên đăng nhập.
- **Kiểm chứng:** ✅ `tests/e2e/p1_regress.py:498-501` — AUTH-09: đăng nhập passkey khi must=1 → bị chặn 403.

### ✅ F-PK-10 — Duplicate credential được phát hiện

- **Vị trí:** `public/api/passkey.php:106-109`
- **Mô tả:** INSERT với `UNIQUE KEY uq_credential` → exception "Duplicate entry" → bắt và trả lỗi thân thiện.
- **Kỳ vọng:** Đăng ký cùng thiết bị 2 lần → báo "Thiết bị này đã được đăng ký trước đó."
- **Kiểm chứng:** ✅ `tests/e2e/p1_regress.py:481`

---

## 4. Bất Biến Nghiệp Vụ

### ✅ F-PK-11 — Credential gắn đúng thành viên

- `member_passkeys.member_id` có **FOREIGN KEY** đến `members(id) ON DELETE CASCADE` — ✅ schema.sql:181
- Mỗi thành viên có thể đăng ký **nhiều** credential (nhiều thiết bị) — ✅ `passkey.php:100` INSERT không có limit
- Xoá thành viên → cascade xoá passkeys — ✅ schema đúng
- Xoá passkey chỉ ảnh hưởng tài khoản đó — ✅ `passkey.php:125` filter `member_id = me`

### ✅ F-PK-12 — HTTPS enforcement

- `passkey.php:25-32` kiểm `HTTPS` trên production (non-localhost)
- WebAuthn **bắt buộc** secure context — trình duyệt từ chối trên HTTP
- ✅ Phòng thua trên production

---

## 5. State Machine

### ✅ Trạng thái tài khoản → đăng nhập Passkey

| Trạng thái | Passkey login | Hành vi |
|------------|--------------|---------|
| `đang phục vụ` | ✅ Cho phép | `passkey.php:208` kiểm |
| `chờ duyệt` | ✅ **Sai** | `require_login()` chặn trước khi đến Passkey |
| `đã nghỉ` | ❌ Chặn | `passkey.php:208` trả lỗi |
| `must_change_pw=1` | ❌ Chặn (403) | `passkey.php:215-218` |

> **⚠️ Note F-PK-13 (thiếu):** Không có test kiểm chứng động cho trường hợp member đổi trạng thái sang "đã nghỉ" sau khi đã đăng ký passkey → đăng nhập bằng passkey bị chặn đúng hay không.

---

## 6. Missing Test Coverage (gap)

| Ca còn thiếu | Tác động |
|--------------|---------|
| Credential enumeration: thử `processRegister` với nhiều `member_id` khác nhau | Phát hiện ai có tài khoản |
| Challenge replay: dùng lại challenge cũ → bị từ chối | Bảo mật |
| Concurrent registration: 2 request cùng lúc → không nhân đôi row | Toàn vẹn |
| Passkey login sau khi member → "đã nghỉ" | State machine |
| Device không hỗ trợ biometric → fallback đúng | UX |

---

## 7. Đánh giá rủi ro theo OWASP

| Lớp | Rủi ro | Ghi chú |
|-----|--------|---------|
| **A07:2021 – Security Misconfigurations** | 🟠 Cao | F-PK-1: challenge không expire |
| **A07:2021 – Security Misconfigurations** | 🟡 Trung bình | F-PK-2: userVerification không bắt buộc |
| **A04:2021 – Insecure Design** | 🟡 Trung bình | F-PK-3: registration không rate limit |
| **A01:2021 – Broken Access Control** | 🟢 Thấp | Passkey gắn đúng member_id; scope = toàn app |

---

## 8. Khuyến nghị

1. **Ưu tiên 1 (F-PK-1):** Thêm expiration cho challenge và chặn replay. Đây là lỗ hổng bảo mật nghiêm trọng nhất.
2. **Ưu tiên 2 (F-PK-2):** Cân nhắc `userVerification=true` nếu muốn bắt buộc biometric. Test kỹ trên Android/iOS/Desktop trước.
3. **Ưu tiên 3 (F-PK-3):** Thêm rate limit cho `processRegister`.
4. **Bổ sung tests:** Viết test cho F-PK-13 (đổi status → "đã nghỉ" → passkey bị chặn).
5. **Happy path tổng thể:** ✅ Đăng ký, đăng nhập, gỡ bỏ, toggle trạng thái, must_change_pw, clone detection — đều đúng và có test coverage.

---

## 9. Bảng Tổng Hợp

| Mã | Mức | Module | Kỳ vọng | Thực tế | Loại |
|----|-----|--------|---------|---------|------|
| F-PK-1 | 🟠 Cao | passkey.php | Challenge expire + anti-replay | Không có | Bảo mật |
| F-PK-2 | 🟡 TB | passkey.php | userVerification required | preferred | Thiết kế |
| F-PK-3 | 🟡 TB | passkey.php | Rate limit registration | Không có | Bảo mật |
| F-PK-4 | 🔵 Info | passkey.php | Attestation format 'none' acceptable | self-attestation | Thông tin |
| F-PK-5 | 🔵 Info | passkey.php | HTTPS enforced on production | ✅ Có check | Thông tin |
| F-PK-6 | 🔵 Info | passkey.php | Status endpoint safe | ✅ Chỉ trả bool | Thông tin |
| F-PK-7 | 🔵 Info | passkey.php | Credential ID base64 encode | ✅ Đúng | Thông tin |
| F-PK-8 | ✅ | passkey.php | Clone detection sign_count | ✅ Hoạt động | Đúng |
| F-PK-9 | ✅ | passkey.php | must_change_pw blocks | ✅ Hoạt động | Đúng |
| F-PK-10 | ✅ | passkey.php | Duplicate credential handled | ✅ Hoạt động | Đúng |
| F-PK-11 | ✅ | schema | FK + CASCADE đúng | ✅ Đúng | Đúng |
| F-PK-12 | ✅ | passkey.php | HTTPS enforcement | ✅ Hoạt động | Đúng |

---

## 10. File đã rà soát

| File | Mục đích |
|------|---------|
| `public/api/passkey.php` | API endpoints |
| `public/assets/js/modules/passkey.js` | JS client |
| `public/assets/js/login.js` | Login screen Alpine component |
| `views/layout_login.php` | Login page HTML |
| `views/module_profile.php` | Profile page với toggle Passkey |
| `config/schema.sql:170-182` | Bảng `member_passkeys` |
| `config/migrate_passkey.php` | Migration |
| `public/api/webauthn/WebAuthn.php` | Thư viện WebAuthn |
| `tests/e2e/hw.js` | Hardware tests (PK-01→PK-07) |
| `tests/e2e/p1_regress.py:475-501` | P1 regression (AUTH-09a/b) |

---

_Cập nhật khi vá F-PK-1, F-PK-2, F-PK-3 hoặc thêm test coverage._
