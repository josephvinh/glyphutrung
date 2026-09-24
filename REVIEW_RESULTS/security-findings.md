# BÁO CÁO REVIEW BẢO MẬT - TNTT Backend PHP

**Ngày review:** Tháng 9/2026
**Phạm vi:** `public/api/*.php`, `config/*.php`
**Số file đã đọc:** 35+

---

## TÓM TẮT

| Mức độ | Số lượng |
|---------|----------|
| CRITICAL | 0 |
| HIGH | 2 |
| MEDIUM | 4 |
| LOW | 5 |
| INFO | 3 |

---

## CHI TIẾT VULNERABILITIES

---

### [HIGH] Thiếu Rate Limiting trên Passkey Login

**File:** `public/api/passkey.php:97-145`
**Dòng:** `case 'processLogin':`

**Mô tả:**
Endpoint `passkey.php?action=processLogin` không có cơ chế giới hạn số lần thử như `login_throttle()` trong `auth.php`. Kẻ tấn công có thể brute-force passkey authentication mà không bị chặn.

```php
case 'processLogin':
    require_post();
    // THIẾU: login_throttle($someId);
    $passkey = db_one("SELECT * FROM member_passkeys WHERE credential_id = ?", [$credentialId]);
    // ... xác thực passkey
```

**Fix suggestion:**
Thêm rate limiting tương tự như `auth.php`:
```php
case 'processLogin':
    require_post();
    $credentialId = $in['id'];
    login_throttle_by_credential($credentialId); // cần tạo hàm mới
    // ... xác thực
```

---

### [HIGH] Thiếu CSRF Protection cho Passkey Operations

**File:** `public/api/passkey.php`
**Dòng:** `case 'processRegister':` (dòng 46), `case 'delete':` (dòng 79)

**Mô tả:**
Hai action `processRegister` và `delete` gọi `require_write()` nhưng không truyền CSRF token. Phân tích cho thấy `require_write()` bắt CSRF, nhưng endpoint này nhận dữ liệu từ `json_input()` trong khi CSRF token thường ở header `X-CSRF-TOKEN`.

```php
case 'processRegister':
    $me = require_login();
    require_write(); // CSRF được check
    $clientDataJSON = base64_decode($in['clientDataJSON']); // Lấy từ body JSON
```

**Lưu ý:** CSRF token có thể đã được gửi qua header `X-CSRF-TOKEN`, nhưng cần verify phía client gửi đúng cách.

**Fix suggestion:**
Đảm bảo client gửi CSRF token qua header `X-CSRF-TOKEN` cho tất cả passkey operations.

---

### [MEDIUM] Default Password Yếu trong Config

**File:** `config/config.php:28`
**Dòng:** `'default_password' => 'tntt@2026',`

**Mô tả:**
Mật khẩu mặc định cho tài khoản mới được hardcode. Nếu attacker biết logic này và config không được thay đổi, họ có thể đăng nhập.

```php
// config/password.php:197
$defaultPw = app_config('default_password') ?: 'tntt@2026';
```

**Fix suggestion:**
- Bắt buộc thay đổi mật khẩu ngay sau khi tạo (`must_change_pw = 1`)
- Yêu cầu mật khẩu phức tạp hơn (hiện tại chỉ 6 ký tự tối thiểu)
- Không lưu default password trong config example

---

### [MEDIUM] Potential IDOR trong File Library Access

**File:** `public/api/library_file.php:14-29`
**Dòng:** 24-29

**Mô tả:**
File được phục vụ dựa trên `id` từ query string. Kiểm tra quyền chỉ dựa trên trạng thái item và người upload:

```php
if ($item['status'] !== 'da_duyet'
    && (int) $item['uploaded_by'] !== (int) $me['id']
    && !$canEdit) {
    http_response_code(403); exit;
}
```

Người dùng có thể đoán/burp các ID khác để truy cập file "chờ duyệt" hoặc "bị từ chối" của người khác nếu họ biết ID.

**Fix suggestion:**
- Thêm audit log cho mỗi lần truy cập file
- Sử dụng token tạm thời thay vì ID cố định
- Hash ID với secret server-side

---

### [MEDIUM] Không Giới Hạn Số Lần Đăng Ký

**File:** `public/api/auth.php:143-196`
**Dòng:** `case 'register':`

**Mô tả:**
Không có cơ chế giới hạn số lần đăng ký tài khoản mới từ cùng một IP hoặc device. Kẻ tấn công có thể tạo nhiều tài khoản rác.

**Fix suggestion:**
```php
function register_throttle(): void {
    $ip = client_ip();
    $recent = db_one('SELECT COUNT(*) n FROM members WHERE registered_at > ? AND ip = ?',
                     [date('Y-m-d H:i:s', time() - 3600), $ip]);
    if ($recent['n'] >= 3) {
        json_fail('Bạn đã đăng ký quá nhiều lần trong giờ qua.', 429);
    }
}
```

---

### [MEDIUM] Setup Key Hardcoded trong Config

**File:** `config/config.php:33`
**Dòng:** `'setup_key' => '123456789012120937867508',`

**Mô tả:**
Setup key để chạy cài đặt qua trình duyệt được hardcode trong config example. Key này nên được generate ngẫu nhiên và yêu cầu thay đổi khi deploy.

**Fix suggestion:**
- Không commit setup_key thực vào git
- Generate key ngẫu nhiên khi tạo config mới
- Yêu cầu đổi key ngay sau khi cài đặt xong

---

### [LOW] Sensitive Data Trong Activity Logs

**File:** Nhiều file (`auth.php`, `org.php`, `students.php`, etc.)
**Mô tả:**
Thông tin nhạy cảm được ghi vào activity_logs mà không có masking:

```php
// auth.php:100
log_action('tao', 'auth', 'Đăng nhập hệ thống', $m['phone']); // Phone logged

// org.php:146
log_action('tuchoi', 'org', 'Từ chối đăng ký của ' . $m['full_name'], $m['phone']); // Phone in detail
```

**Fix suggestion:**
- Mask số điện thoại: `0987xxxx654` thay vì `0987654321`
- Không log thông tin PII vào detail field
- Chỉ log actor_id, không log sensitive fields

---

### [LOW] Không Có Limit trên Registration Code Generation

**File:** `public/api/auth.php:173-175`
**Dòng:**
```php
$max  = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n
                  FROM members WHERE code LIKE 'GLV%'");
$code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);
```

**Mô tả:**
Mã GLV tăng tuần tự, dễ đoán. Attacker có thể brute-force các mã hợp lệ.

**Fix suggestion:**
Sử dụng format khó đoán hơn:
```php
$code = 'GLV' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
```

---

### [LOW] CSP Header Cho Phép unsafe-eval

**File:** `public/api/_bootstrap.php:58`, `public/api/_common.php:35-40`
**Dòng:**
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; ...");
```

**Mô tả:**
CSP cho phép `unsafe-eval` và `unsafe-inline` cho scripts, giảm hiệu quả bảo vệ XSS.

**Fix suggestion:**
- Loại bỏ `unsafe-eval` nếu có thể
- Chuyển sang nonces hoặc hashes thay vì `unsafe-inline`
- Sử dụng strict CSP

---

### [LOW] Vắng Mặt Rate Limit trên Log Viewing

**File:** `public/api/logs.php:21-24`
**Dòng:**
```php
$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
```

**Mô tả:**
Không có kiểm tra authentication/authorization đầy đủ. Phân tích cho thấy `require_login()` được gọi, nhưng chỉ admin/bdh được phép xem logs.

**Fix suggestion:**
Đã OK - kiểm tra quyền ở dòng 15-18 đúng cách.

---

### [INFO] SQL Injection - Tình trạng TỐT

**Kết luận:** KHÔNG CÓ lỗ hổng SQL Injection trong codebase.

**Giải thích:**
Tất cả queries đều sử dụng prepared statements:
```php
db_all("SELECT * FROM members WHERE phone = ?", [$phone]);
db_run("UPDATE members SET status = ? WHERE id = ?", [$status, $id]);
```

Hàm `db_has_table()` và `db_has_column()` sử dụng filtered input với `preg_replace('/[^A-Za-z0-9_]/', '', $name)`.

---

### [INFO] File Upload Security - Tình trạng TỐT

**Kết luận:** KHÔNG CÓ lỗ hổng upload nghiêm trọng.

**Các biện pháp bảo vệ:**
- MIME type validation qua `finfo` (không tin client)
- Random file names: `bin2hex(random_bytes(16))`
- Storage ngoài web root: `storage/library/`
- Content-Type do server quyết định
- Extension whitelist: chỉ pdf, jpg, png, webp, doc, docx, ppt, pptx

---

### [INFO] Password Security - Tình trạng TỐT

**Kết luận:** Mã hóa password rất tốt.

**Các biện pháp:**
- Argon2id (PASSWORD_ARGON2ID) với memory_cost=65536, time_cost=4, threads=3
- Auto-upgrade từ bcrypt sang Argon2id
- `password_needs_rehash()` kiểm tra định kỳ
- `session_regenerate_id(true)` chống session fixation

---

## BEST PRACTICES ĐƯỢC TUÂN THỦ

1. **Security Headers:** X-Content-Type-Options, X-Frame-Options, CSP, Permissions-Policy được set
2. **Session Security:** HttpOnly, SameSite=Lax cookies
3. **CSRF Protection:** Token-based với `hash_equals()` timing-safe
4. **Rate Limiting:** Login attempts được throttle trong `auth.php`
5. **IDOR Protection:** Phạm vi dữ liệu được kiểm tra qua `can_access_class()`
6. **Error Handling:** `safe_error()` không leak thông tin internal
7. **Transaction Safety:** Write operations dùng `trong_giao_dich()`
8. **Input Validation:** Regex patterns cho date, phone, etc.

---

## RECOMMENDATIONS PRIORITY

| Priority | Action | Effort |
|----------|--------|--------|
| 1 | Thêm rate limiting cho passkey login | Low |
| 2 | Generate random setup_key thay vì hardcode | Low |
| 3 | Thêm rate limiting cho registration | Low |
| 4 | Mask sensitive data trong logs | Medium |
| 5 | Review CSP và loại bỏ unsafe-eval | Medium |
| 6 | Thêm audit log cho library_file access | Medium |

---

*Báo cáo này chỉ mang tính chất tham khảo. Khuyến nghị test kỹ trước khi áp dụng.*
