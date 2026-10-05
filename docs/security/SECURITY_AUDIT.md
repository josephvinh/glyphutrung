# Báo Cáo Rà Soát Bảo Mật — GĐGL Phú Trung

> **Ngày rà soát:** 05/10/2026
> **Phạm vi:** Toàn bộ mã web-reachable (`public/`), lớp cấu hình (`config/`),
> thư viện vendor, lịch sử git, và ứng dụng chạy thật (dựng DB + trình duyệt).
> **Người rà:** Claude Code (agent) — đọc tĩnh + kiểm chứng động trên bản cài thử.
> **Commit nền:** `ab5bd8b` (master).

Tài liệu này CHỈ nói về bảo mật. Các lỗi chức năng (điều hướng #194, mất
`normalizeText`…) nằm ở báo cáo review giao diện riêng, không lặp ở đây trừ khi
có hệ quả bảo mật.

> ⚠️ Repo đang **công khai (public)** — mọi thứ trong mã nguồn và **toàn bộ lịch
> sử git** coi như ai cũng đọc được, làm tăng mức nghiêm trọng của **S1** và
> **S2**.

## Tổng quan & việc làm ngay

| Mức | Số | Mã |
|-----|----|----|
| 🔴 Nghiêm trọng | 2 | S1, S2 |
| 🟠 Cao | 3 | S3, S4, S5 |
| 🟡 Trung bình | 4 | S6, S7, S8, S9 |
| 🔵 Thấp / Thông tin | 3 | S10, S11, S12 |

**5 việc nên làm trong 24 giờ:**

1. Đưa cache ra khỏi `public/` + xóa `public/cache/*.json` trên host (**S1**).
2. Tạo khóa VAPID mới, bỏ `default_password` cứng, coi secret đã-commit là đã lộ (**S2**).
3. Bắt cổng đặt/hủy đơn đổi quà công khai xác thực bằng mã **+ ngày sinh** (**S4**).
4. Chặn/bỏ tham số `classId`/`programId` không kiểm phạm vi trong `data.php` (**S3**).
5. Bật Secret scanning + Push protection trên GitHub; cân nhắc chuyển repo private
   (xem `docs/process/GITHUB_SETUP.md`).

**Phương pháp:** đọc từng dòng mọi file web-reachable (`public/**`), `config/*.php`,
`src/*.php`, `sw.js`, `.htaccess`, CI, và toàn bộ lịch sử git (chỉ đối chiếu tên
file + độ dài giá trị, **không in secret**). Kiểm chứng động trên PHP 8.3 +
MariaDB 10.11 + Chromium với dữ liệu `install.php`/`ci_seed.php` (421 test
PHPUnit qua). Mỗi mục ghi **✅ đã kiểm chứng động** hay **📖 từ đọc code**.

**Giới hạn:** không rà cấu hình máy chủ thật (LiteSpeed/AZDIGI, quyền thư mục,
`config.local.php`), không pentest hạ tầng, không thử tải. Một lần rà **không**
đảm bảo "hết lỗ hổng" — đây là ảnh chụp tại `ab5bd8b`; quy trình ở `docs/process/`
để giữ lỗi mới không lọt vào.

## Cách đọc mức độ

| Mức | Nghĩa | Hạn xử lý |
|-----|-------|-----------|
| 🔴 **Nghiêm trọng** | Khai thác được từ bên ngoài, lộ dữ liệu cá nhân hàng loạt hoặc chiếm quyền | Vá NGAY, trước mọi tính năng mới |
| 🟠 **Cao** | Rò rỉ/leo thang có điều kiện (cần biết mã em, cần đăng nhập…) | Trong đợt vá kế tiếp |
| 🟡 **Trung bình** | Làm yếu lớp phòng thủ, lộ thông tin hạn chế | Lên lịch xử lý |
| 🔵 **Thấp / Thông tin** | Rủi ro nhỏ, cần nhiều điều kiện, hoặc chỉ ảnh hưởng chất lượng | Dọn dần |

Mỗi mục ghi: **bằng chứng** (đã kiểm chứng động, hay chỉ đọc code), kịch bản
khai thác, và cách sửa đề xuất.

---

## 🔴 S1 — Tệp cache chứa dữ liệu cá nhân toàn đoàn, tải được KHÔNG cần đăng nhập

- **Vị trí:** `public/api/cache.php:7` (`$dir = __DIR__ . '/../cache'` → `public/cache/`),
  ghi bởi `public/api/data.php:131,429,624`.
- **Bằng chứng (đã kiểm chứng động):** Trên bản chạy thật, đăng nhập admin một
  lần để sinh cache, rồi **không gửi cookie** tải thẳng:
  ```
  GET /cache/<md5>.json  →  HTTP 200, 5921 bytes
  keys: ...,members,logs,students,...
  member lộ ra: {"fullName":"Nguyễn Văn A","phone":"0901000001"}
  ```
- **Vì sao khai thác được:**
  1. Cache ghi vào thư mục **nằm trong web root** (`public/cache/`).
  2. Tên tệp = `md5("data_" . "v2" . "_{yearId}_{memberId}_{part}_{classId}_{programId}")`
     — mọi thành phần đều đoán được: `v2` cố định trong mã, `yearId`/`memberId`
     là số nguyên nhỏ (1, 2, 3…), `part` ∈ {core, heavy, all}. Kẻ tấn công tự
     tính được **chính xác** tên tệp cache của admin (`md5("data_v2_1_1_all__")`).
  3. `.htaccess` chặn `.sql/.log/.md…` nhưng **không chặn `.json`**; luật rewrite
     bỏ qua tệp đã tồn tại nên máy chủ phục vụ thẳng tệp tĩnh.
  4. Repo **công khai** (`private:false`) nên cấu trúc khóa cache + tên DB đều lộ.
- **Hậu quả:** Lộ toàn bộ hồ sơ thiếu nhi (địa chỉ, SĐT cha mẹ, ngày sinh), danh
  bạ nhân sự (SĐT), và nhật ký hệ thống — cho bất kỳ ai trên Internet.
- **Cách sửa (ưu tiên 1):**
  - Chuyển thư mục cache ra **ngoài** `public/` (vd `storage/cache/`, giống
    `storage/library`). Sửa `Cache::$dir`.
  - Phòng thủ chiều sâu: thêm `public/cache/.htaccess` với `Require all denied`,
    và thêm `.json` vào `FilesMatch` chặn tải trong `.htaccess` gốc.
  - Cân nhắc đổi khóa cache sang có thành phần bí mật (vd HMAC với khóa server)
    để tên tệp không đoán được, hoặc bỏ cache tệp, chuyển sang APCu (bộ nhớ,
    không chạm đĩa web).

## 🔴 S2 — Rò rỉ secret trong mã nguồn & lịch sử git của repo CÔNG KHAI

- **Vị trí:** `config/config.php:41` (`'private' => 'E0wu…'` — khóa riêng VAPID thật,
  dù comment ngay trên ghi "KHÔNG để khoá thật ở đây"); `'default_password' =>
  'tntt@2026'`. Trong **lịch sử git** còn: `cookies.txt` (commit `1cbf9a0`),
  `error_log`/`public/error_log`/`public/api/error_log` (lộ đường dẫn
  `/home/ylcqukhi…`, câu SQL, tên bảng `members`), `setup_key` từng có giá trị
  24 ký tự trong `config.php`.
- **Bằng chứng:** `git log --all -p -- config/config.php` cho thấy private key;
  `git show <commit>:public/api/error_log` lộ SQLSTATE + đường dẫn tuyệt đối.
  Repo là public (xác nhận qua GitHub API).
- **Hậu quả:**
  - Khóa riêng VAPID: kẻ khác có thể ký JWT VAPID và gửi "chuông" đẩy tới thiết
    bị đã đăng ký (nội dung vẫn do SW tự lấy nên không đọc được dữ liệu, nhưng
    là spam/phiền và lộ danh tính endpoint).
  - `default_password` công khai: tài khoản vừa tạo (chưa đăng nhập lần đầu,
    `must_change_pw=1`) có thể bị chiếm nếu biết số điện thoại — chỉ cần đăng
    nhập trước chủ nhân rồi đổi mật khẩu.
  - Lịch sử lộ đường dẫn máy chủ + cấu trúc SQL: hỗ trợ tấn công có chủ đích.
- **Cách sửa:**
  1. **Tạo lại khóa VAPID mới** (`php -r 'require "config/push.php"; print_r(push_tao_khoa());'`),
     chỉ đặt ở `config/config.local.php` (đã gitignore). Để `config.php` khóa rỗng.
  2. Đổi `default_password` sang sinh ngẫu nhiên mỗi tài khoản (giống
     `resetPassword`), không dùng hằng số.
  3. Coi mọi secret đã từng commit là **đã lộ** → xoay hết (khóa VAPID, bất kỳ
     mật khẩu DB/thật nào từng xuất hiện).
  4. Cân nhắc dọn lịch sử (`git filter-repo`) để gỡ `cookies.txt`/`error_log` —
     nhưng vì repo đã public, **xoay secret quan trọng hơn** dọn lịch sử.
  5. Thêm quét secret vào CI (xem `docs/process/GITHUB_SETUP.md` → gitleaks).

## 🟠 S3 — IDOR: `data.php?classId=` bỏ qua kiểm tra phạm vi lớp

- **Vị trí:** `public/api/data.php:339` (nhánh `elseif ($filterClassId !== null)`).
- **Vì sao khai thác được:** `data.php` chỉ gọi `require_login()` (không
  `require_permission`). Nhánh lọc theo `classId` truy vấn điểm danh theo
  `enrollments.class_id = ?` mà **không** giao với `allowed_class_ids($me)`.
  Bất kỳ thành viên đăng nhập nào (kể cả Dự Bị chưa được phân lớp) thêm
  `?classId=<id lớp khác>` là đọc được điểm danh của lớp đó.
- **Liên quan:** Nhánh `?programId=` (dòng ~310): khi `allowed_class_ids` trả `[]`
  (người chưa có phạm vi), luồng rơi vào `else` trả **toàn bộ** điểm danh của
  chương trình đó trên cả đoàn.
- **Bằng chứng:** Đọc mã (chưa dựng kịch bản động vì cần tạo nhiều tài khoản phạm
  vi hẹp; logic rõ ràng khi đọc).
- **Cách sửa:** Trong cả hai nhánh, **giao** `classId`/kết quả với
  `allowed_class_ids($me)`; nếu `classId` không thuộc phạm vi → 403. Vì frontend
  hiện **không dùng** `classId`/`programId` (xem review giao diện), phương án
  gọn nhất là **xóa hẳn** hai tham số lọc này khỏi `data.php` và `cacheKey`.

## 🟠 S4 — Cổng đổi quà công khai: đặt/giữ Mộc thay bất kỳ em nào (chỉ cần biết mã)

- **Vị trí:** `public/api/somoc_order.php` (case `place`), gọi
  `rewards_place_order()` trong `public/api/_rewards.php`.
- **Vì sao khai thác được:** `place` chỉ cần `code` (mã thiếu nhi) + `items` +
  `password` — trong đó `password` là **mật mã do CHÍNH người đặt tự đặt**, không
  phải bằng chứng danh tính. Khác với trang tra cứu (`tracuu.php` cần mã **+ ngày
  sinh**), `place`/`cancel` **không** đòi ngày sinh. Mã thiếu nhi in trên thẻ
  (`GDGLPT` + 6 số, tuần tự, dễ đoán/nhìn thấy).
- **Hậu quả:** Kẻ biết mã một em có thể:
  - Đặt đơn giữ (`held_balance`) hết Mộc khả dụng của em → em không đổi quà được
    (quấy phá), và không tự hủy được vì không biết mật mã kẻ kia đặt.
  - Tự đặt mật mã lấy quà → tới quầy đọc mật mã để **lấy quà vật lý** bằng Mộc
    của em khác.
  Có rate-limit theo IP (30 lượt/10 phút) và Thủ thư hủy hộ được, nhưng rào chắn
  danh tính thì thiếu.
- **Bằng chứng:** Đọc mã (`place` không có bước kiểm ngày sinh/định danh).
- **Cách sửa:** Bắt `place`/`cancel` xác thực như `tracuu`: yêu cầu **mã + ngày
  sinh** khớp (`tracuu_auth()`) trước khi cho đặt/hủy. Mật mã đổi quà chỉ dùng
  cho khâu nhận quà, không thay cho định danh.

## 🟠 S5 — Giả mạo IP qua `X-Forwarded-For` làm yếu rate-limit (RateLimiter)

- **Vị trí:** `src/RateLimiter.php:39-46` (hàm `getClientIp`), fallback session
  tại `:108`.
- **Vì sao:** Điều kiện tin `X-Forwarded-For` bị **ngược**: chỉ dùng XFF khi
  `REMOTE_ADDR` **không** phải loopback — tức là với **mọi client ngoài** (bình
  thường) thì tin header do client gửi. Kẻ tấn công đặt `X-Forwarded-For` tùy ý
  để xoay danh tính, vượt các giới hạn dựa trên `RateLimiter`
  (`enforce_api_read_limit`, `enforce_api_write_limit`, `enforce_register_rate_limit`).
  Khi host không có APCu, bộ đếm lưu theo **session** → không gửi cookie là không
  bị đếm.
- **Giảm nhẹ:** Các chốt xác thực quan trọng (`login_throttle`, `register_throttle`,
  `tracuu_throttle`) dùng `client_ip()` ở `_http_util.php` — chỉ đọc
  `REMOTE_ADDR`, **không** spoof được. Đăng ký có cả chốt bảng (an toàn) lẫn
  RateLimiter. Nên tác động chính là né giới hạn tần suất API (DoS), không phải
  vượt khóa đăng nhập. **Đã kiểm chứng động** rằng khóa đăng nhập per-phone/IP
  giữ vững kể cả với biến thể Unicode số điện thoại (collation gộp đúng bucket).
- **Cách sửa:** Chỉ tin `X-Forwarded-For` khi `REMOTE_ADDR` thuộc danh sách
  **proxy tin cậy** đã cấu hình (đảo lại điều kiện), và lấy IP thật từ bên phải
  chuỗi XFF. Nếu dùng Cloudflare, dùng `CF-Connecting-IP`. Khi không có APCu, nên
  log cảnh báo rõ (đã có) và không coi rate-limit là lớp bảo vệ duy nhất.

## 🟡 S6 — XSS tiềm ẩn ở trang chủ công khai qua API Kinh Thánh bên thứ ba

- **Vị trí:** `views/layout_landing.php:251` — `verseArea.innerHTML = '…' + verse + '…' + ref`.
- **Vì sao:** `verse`/`ref` lấy từ `bible-api.com` (bên thứ ba) rồi chèn bằng
  `innerHTML` **không escape**, và còn lưu `localStorage` để render lại. CSP của
  trang cho `script-src 'unsafe-inline'` và `img-src https: data:`, nên nếu phản
  hồi chứa `<img src=x onerror=…>` thì chạy được.
- **Điều kiện:** Cần kiểm soát phản hồi của `bible-api.com` (MITM đã khó vì HTTPS
  verify; hoặc chính API bị chiếm/đổi). Khả năng thấp nhưng hậu quả là XSS trên
  trang public.
- **Cách sửa:** Dùng `textContent` cho câu Kinh Thánh và tham chiếu (tạo phần tử
  rồi gán `.textContent`), hoặc escape trước khi chèn. Không bao giờ `innerHTML`
  dữ liệu từ nguồn ngoài. Cân nhắc tự chứa danh sách câu (đã có `FALLBACK_VERSES`)
  thay vì gọi API ngoài.

## 🟡 S7 — Lộ thông điệp lỗi gốc khi header đã gửi

- **Vị trí:** `src/ExceptionHandler.php:42` —
  `echo "\n<!-- Exception: " . $e->getMessage() . " -->";`
- **Vì sao:** Nếu ngoại lệ xảy ra sau khi đã bắt đầu xuất (hay gặp ở trang HTML
  `index.php`/`print.php`/`somoc.php`/`bxh.php` dùng output buffering), thông điệp
  lỗi gốc (có thể chứa SQL, tên bảng, đường dẫn) bị in vào comment HTML — **bất kể**
  `production`/debug. Các endpoint API đặt header sớm nên ít dính, nhưng trang HTML
  thì có.
- **Cách sửa:** Khi `!$this->debug`, in comment chung chung (vd `<!-- error -->`)
  và chỉ ghi chi tiết vào log. Không đưa `getMessage()` ra output trong production.

## 🟡 S8 — CSRF đăng xuất

- **Vị trí:** `public/api/auth.php` case `logout` — chỉ `require_post()`, không
  `require_csrf()`.
- **Vì sao:** Trang ngoài có thể tự submit form POST tới `auth.php?action=logout`
  khiến người dùng bị đăng xuất (phiền, không phá dữ liệu). Các action ghi khác
  đều qua `require_write()` (có CSRF) — chỉ logout là ngoại lệ.
- **Cách sửa:** Thêm `require_csrf()` cho logout (token đã có sau khi đăng nhập),
  hoặc chấp nhận rủi ro và ghi chú rõ. Ưu tiên thêm CSRF cho nhất quán.

## 🟡 S9 — Mật khẩu tạm entropy thấp khi cấp lại

- **Vị trí:** `public/api/org.php:163` — `$temp = 'tntt' . random_int(1000, 9999)`.
- **Vì sao:** Chỉ 9000 khả năng (~13 bit). Dù `must_change_pw=1` buộc đổi khi đăng
  nhập và login bị throttle (5/số/15 phút), kẻ biết số điện thoại của người vừa
  được cấp lại (chưa kịp đăng nhập) có thể dò. Rủi ro thấp nhờ throttle nhưng
  entropy nên cao hơn.
- **Cách sửa:** Sinh mật khẩu tạm ngẫu nhiên mạnh hơn, vd
  `bin2hex(random_bytes(4))` (8 hex) hoặc 6–8 ký tự an toàn từ `random_int`.

## 🔵 S10 — `bible.php`: lưu IP khách vô thời hạn + `CREATE TABLE` mỗi request

- **Vị trí:** `public/api/bible.php:16` (`ensure_bible_table` chạy mỗi lượt
  `random`), bảng `bible_daily` lưu `ip_address` không giới hạn thời gian.
- **Vì sao:** Endpoint công khai lưu IP mọi khách (dữ liệu cá nhân theo nghĩa rộng)
  vô thời hạn; `CREATE TABLE IF NOT EXISTS` mỗi request là lãng phí. Admin xem được
  danh sách IP (`action=list`).
- **Cách sửa:** Tạo bảng qua migration một lần (bỏ `ensure_bible_table` khỏi đường
  nóng). Dọn `bible_daily` cũ định kỳ (chỉ cần giữ trong cửa sổ rate-limit 1 giờ).
  Cân nhắc lưu **băm** IP thay vì IP thô nếu chỉ dùng cho rate-limit.

## 🔵 S11 — `migrate-passkeys.php` hỏng (đường dẫn require sai + `$me` chưa gán)

- **Vị trí:** `public/api/migrate-passkeys.php:17,24`.
- **Vì sao:** `require __DIR__ . '/../public/api/_bootstrap.php'` từ `public/api/`
  trỏ tới `public/public/api/…` (không tồn tại) → fatal; và `$role = $me['role_code']`
  dùng `$me` chưa được gán (`require_login()` gọi mà không hứng kết quả). Endpoint
  thực tế không chạy. Không phải lỗ hổng, nhưng là mã quản trị chết, cần sửa hoặc
  xóa để không gây hiểu nhầm/che lỗi.
- **Cách sửa:** Sửa `require __DIR__ . '/_bootstrap.php';` và `$me = require_login();`,
  hoặc xóa endpoint nếu việc migrate đã xong (để trong `config/` có guard CLI thay vì
  endpoint web).

## 🔵 S12 — `data.php` khóa cache thiếu `page`/`limit`

- **Vị trí:** `public/api/data.php:131`.
- **Vì sao:** Khóa cache gồm `classId/programId` nhưng **không** gồm `page`/`limit`.
  Cùng một người xem trang 2 có thể nhận lại thân phản hồi đã cache của trang 1.
  Chỉ ảnh hưởng chính người đó (khóa có `memberId`), không rò chéo — nên là lỗi
  đúng-đắn dữ liệu, không phải bảo mật.
- **Cách sửa:** Thêm `page`/`limit`/`isPaginated` vào khóa cache, hoặc không cache
  các request phân trang.

---

## Những điểm đã KIỂM và thấy ỔN (để khỏi rà lại)

Ghi lại để lần sau khỏi nghi oan — các chốt sau đã xác minh là chắc:

- **Khóa chống dò đăng nhập/tra cứu KHÔNG bị vượt bằng biến thể Unicode.** Đã thử
  trực tiếp: số `０901000001` (full-width) và mã `ＨＳ００１` khớp đúng bản ghi nhờ
  collation `utf8mb4_unicode_ci`, **và** câu `COUNT(*)` đếm throttle cũng khớp cùng
  bucket (collation áp dụng cả hai phía) → fullwidth vẫn nhận 429. Không phải lỗ hổng.
- **Web Push chống SSRF kỹ** (`config/push.php`): whitelist host cứng, chặn IP nội
  bộ/metadata, `CURLOPT_RESOLVE` ghim IP, cấm redirect, chỉ HTTPS. Tốt.
- **Không chuyển quyền sở hữu push endpoint của người khác** (#107) — token băm
  SHA-256, xoay khi đăng ký lại. Tốt.
- **IDOR hồ sơ thiếu nhi (save/import/bulk_move/bulk_delete)** chặn fail-closed:
  phải phủ được **lớp nguồn** (`current_enrollment_class` + `can_access_class`),
  không chỉ lớp đích (`students.php`). Tốt.
- **Chống leo thang phân quyền:** `can_access_class`/`accessible_class_ids` xét
  **từng phân công** (không "lai" quyền vai này với phạm vi vai kia); `responsible_*`
  loại vai `thu_thu` scope toàn đoàn. Nhất quán ở attendance/scores/reports/leave.
- **Bảo vệ admin/bdh (#141, F9):** `guardTarget`/`isProtected` ẩn admin (404) với
  người không phải admin; không cho phân công/hạ vai admin-bdh qua mọi đường
  (`org.php`, `assignments.php`, `StaffService`).
- **SQL:** Toàn bộ dùng prepared statements; chỗ nội suy tên bảng/cột được lọc
  `[A-Za-z0-9_]` (`db_has_table/column`). Không thấy SQL injection.
- **Thư viện tài liệu:** kiểm MIME thật bằng `finfo`, tên lưu ngẫu nhiên, phục vụ
  qua `library_file.php` có kiểm quyền + `nosniff` + `Content-Disposition`. Lưu
  **ngoài** web root (`storage/library`). Tốt.
- **Trang in/xuất (`print.php`, `export.php`):** biến hiển thị đều qua
  `htmlspecialchars`. Không thấy XSS.
- **Trang công khai `bxh.php`/`somoc.php`/`tracuu.php`:** chỉ đọc, prepared, escape
  `e_()`, `noindex`, chỉ lộ tên+lớp+điểm/Mộc; tra cứu cần mã **+ ngày sinh**.
- **Vendor JS** đều bản đã vá: SheetJS `0.20.3`, Alpine `3.15.0`, jsQR `1.4.0`,
  qrcode-generator `1.4.4`. Không có CDN ngoài (self-host).
- **Script cài đặt** (`install.php`, `seed_demo.php`) có guard CLI/`setup_key`/
  `production`; `seed_demo` còn chặn trừ khi DB tên chứa `demo`.

---

## Thứ tự vá đề xuất

1. **S1** (cache lộ PII) — chặn ngay, một dòng đổi đường dẫn + `.htaccess`.
2. **S2** (xoay secret, bỏ default_password cứng).
3. **S3, S4** (IDOR data.php + định danh cổng đổi quà).
4. **S5, S6, S7, S8** (rate-limit XFF, XSS landing, lỗi lộ, CSRF logout).
5. **S9–S12** dọn dần.

Mỗi mục nên đi kèm **một test hồi quy** (xem `docs/process/TESTING.md` → mục "Test
bảo mật / phân quyền") để không tái phát. Ví dụ: test tải `/cache/<md5>.json`
không cookie phải trả 403/404; test `data.php?classId=` của lớp ngoài phạm vi phải
403; test `somoc_order place` sai ngày sinh phải bị từ chối.

---
_Tài liệu rà soát; cập nhật khi vá xong từng mục (đánh dấu ✅ + commit/PR)._
