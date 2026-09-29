# BÁO CÁO KIỂM THỬ TOÀN DIỆN — Web "Gia Đình Giáo Lý Phú Trung" (TNTT Super App)

- **Ngày kiểm thử:** 29/09/2026
- **Mã nguồn:** nhánh hiện tại của `josephvinh/glyphutrung`, commit `8e979b1` ("fix(students): popup sửa hồ sơ hiện ngay & xóa thiếu nhi xóa hẳn (#77)")
- **Công nghệ:** PHP thuần (API JSON + trang HTML) · MySQL/MariaDB · Alpine.js + Tailwind (biên dịch sẵn) · PWA/Service Worker
- **Quy mô:** 170 file PHP (~32k dòng), 33 file JS (~10k dòng), 22 module giao diện, ~30 endpoint API, 22 file test

---

## 1. Tóm tắt điều hành

**Kết luận chung: nền tảng vững, nhưng KHÔNG nên coi là "đã kiểm thử xong" — có 4 lỗi mức Cao cần sửa ngay, và hệ thống CI hiện cho kết quả xanh giả.**

Điểm tốt (đã kiểm chứng bằng chạy thật): chống CSRF hoạt động, ghi dữ liệu bắt buộc POST, khoá đăng nhập sau 5 lần sai, cookie phiên `HttpOnly`+`SameSite`, phân quyền theo vai và chống IDOR theo lớp/khối đều đúng ở mọi thao tác ghi tôi thử (24/24), chặn upload file nguy hiểm, các quy tắc nghiệp vụ (xin phép, điểm số, niên khoá, quà) đúng. Giao diện hiển thị ổn ở 3 kích thước màn hình, **0 lỗi JavaScript / 0 lỗi console / 0 request lỗi** khi duyệt 138 lượt mở module.

Điểm cần xử lý:

| # | Mức | Vấn đề | Tác động |
|---|-----|--------|----------|
| F-01 | **Cao** | `api/data.php` gửi dữ liệu **ngoài phạm vi** cho mọi người dùng đã đăng nhập (điểm số 600 em, nhật ký hoạt động kèm SĐT, danh bạ nhân sự) | Lộ dữ liệu thiếu nhi/nhân sự, trái với ranh giới phân quyền mà chính code mô tả |
| F-02 | **Cao** | Nút **"Xuất Excel" điểm danh hỏng 100%** (HTTP 500); 3/4 action của `export.php` lỗi SQL | Tính năng không dùng được với mọi tài khoản |
| F-03 | **Cao** | **Không xoá được lớp** (kể cả lớp rỗng) — `OrgService.php:237` truy vấn cột `students.class_id` đã không còn | Quản trị không dọn được lớp |
| F-04 | **Cao** | File debug và nhật ký lỗi **tải được công khai** (`/error_log`, `/api/error_log`, `rebuild_login.php`, `test_webauthn.php`…) | Lộ đường dẫn máy chủ, tên CSDL, stack trace; có script ghi file không cần đăng nhập |
| F-05 | Trung bình | **CI xanh giả**: `phpunit` thoát mã 0 mà chạy 0 test; CI không có MySQL; lint có `\|\| true` | Lỗi lọt qua CI mà không ai biết |
| F-06 | Trung bình | `must_change_pw` chỉ chặn ở giao diện, API vẫn dùng được | Mật khẩu mặc định `tntt@2026` (nằm trong repo) dùng được vô thời hạn |
| F-07 | Trung bình | Giới hạn đăng ký công khai không có tác dụng với đăng ký **thành công** | Spam hàng đợi "chờ duyệt" |
| F-08 | Trung bình | Cho điểm danh trước cho buổi **tương lai** | Sai số liệu chuyên cần/Mộc |
| F-09 | Trung bình | Thiếu kiểm tra dữ liệu thiếu nhi (ngày sinh 2099/1800, tên 300 ký tự, ngày sai định dạng → HTTP 500 thông báo rỗng) | Dữ liệu rác / trải nghiệm lỗi kém |
| F-10 | Trung bình | Cài mới thiếu bảng QR-card; module `custom-qrcard` chưa nối vào giao diện; trang "Chưa cài đặt" hiện cho **mọi** lỗi thiếu bảng | Gây hiểu nhầm khi debug |
| F-11…F-14 | Thấp | logout bằng GET, a11y/tap-target, payload 5,7 MB, các lỗi nhỏ | Xem mục 4 |

**Số liệu kiểm thử**

| Tầng | Kết quả |
|------|---------|
| Cú pháp (PHP lint 170 file / `node --check` 33 file JS) | ✅ 0 lỗi |
| PHPUnit hiện có (164 test PHPUnit + 43 test kiểu script) | 157/164 đạt; **7 lỗi đều do test lỗi thời/thiếu dữ liệu mẫu, không phải lỗi ứng dụng** |
| Kịch bản API/bảo mật/nghiệp vụ mới viết (mục 5) | khoảng 150 kiểm tra (đã loại các ô tự kiểm sai), phát hiện F-01…F-04, F-06…F-09 |
| Giao diện tự động (Chromium, 3 vai × 3 kích thước, 138 lượt mở module + 15 trang) | 0 lỗi JS/console/HTTP, 0 tràn ngang; có lỗi a11y nhỏ |

---

## 2. Phạm vi, môi trường và giới hạn

**Môi trường dựng để kiểm thử** (không đụng dữ liệu thật): MariaDB 10.11 cục bộ, PHP 8.4.19, `php -S` làm web server, Chromium + Playwright. Nạp `config/install.php` + toàn bộ `config/migrate_*.php` + `seed_demo.php` (600 thiếu nhi, 20 lớp, ~35k dòng điểm danh, 2.400 dòng điểm). Chọn DB qua biến `TNTT_DB_*` nên **không sửa file cấu hình nào**. Tạo thêm 6 tài khoản thử, mỗi vai một người (`bdh`, `truong_khoi`, `glv_chu_nhiem`, `glv`, `du_bi`, `thu_thu`).

**Những gì CHƯA/KHÔNG kiểm thử được (cần làm thủ công hoặc môi trường thật):**

| Hạng mục | Lý do |
|----------|-------|
| Web Push (`push.php`, `sw.js`) | Cần khoá VAPID + trình duyệt đăng ký thật |
| Passkey/WebAuthn (đăng nhập sinh trắc) | Cần authenticator thật; chỉ xác nhận `getLoginArgs` công khai theo thiết kế |
| Quét QR bằng camera thật, tia sáng/độ nét thẻ | Kiểm thử ở mức API (`scan`) và PHPUnit, không có camera |
| Trình duyệt iOS Safari/Android thật, chế độ cài PWA/offline | Chỉ có Chromium desktop giả lập viewport |
| Lên lớp cuối năm (`promotion.run`), nhập Excel (`students.import`), xoá thiếu nhi hàng loạt trên dữ liệu thật | Thao tác phá huỷ/nhiều dữ liệu; chỉ mở màn hình, không chạy lệnh |
| Tải (load test), đồng thời (race condition) | Ngoài phạm vi |
| Cookie `Secure`/HSTS | `php -S` chạy HTTP; code có nhánh HTTPS nhưng không chạy |
| Bố cục in ấn (phiếu liên lạc, thẻ QR) bằng mắt | Chỉ kiểm escape HTML, không xem bản in |

**Lưu ý phương pháp:** hai lượt chạy đầu của tôi có một số kiểm tra dùng sai tên tham số (nên trả 400 "Thiếu…" và "đạt" oan). Tôi đã phát hiện, sửa và chạy lại; **các kết quả trong báo cáo này chỉ là kết quả đã được xác minh lại**. Kiểm tra giao diện là tự động, không thay thế mắt người.

---

## 3. Kết quả theo tầng

### 3.1 Code (tĩnh)
- ✅ `php -l` 170/170 file sạch; `node --check` 33/33 file JS sạch.
- ✅ Truy vấn dùng tham số hoá (PDO prepared) nhất quán; tham số ID đều ép `(int)`. Thử 8 payload SQLi trên 6 endpoint: không lỗi SQL rò rỉ, bảng `students` nguyên vẹn (600 dòng).
- ✅ XSS: front-end dùng `x-text`; các `x-html`/`innerHTML`/`document.write` còn lại đều đi qua hàm escape (`escapeHtml`, `_thoat`) hoặc dữ liệu sinh từ mã. `print.php` escape đúng (kiểm bằng tên `<img onerror>`, `<script>`).
- ⚠️ ESLint (bộ quy tắc lỗi logic): 5 cảnh báo, **1 thật** — khoá trùng `libItemIcon` (`library.js:51` và `library.js:324`, khoá sau ghi đè khoá trước). Các cảnh báo còn lại (`indexedDB`, `html2canvas`, `jspdf`) là dương tính giả (biến toàn cục trình duyệt / đã có `if (window.html2canvas)` bảo vệ).
- ⚠️ Đối chiếu tên bảng trong SQL với schema: tìm được `program_sessions` không tồn tại (F-02) và `qr_card_*` chỉ có sau migration riêng (F-10).
- ℹ️ Chưa chạy `npm run typecheck` (`tsc`) vì repo chưa cài `node_modules`.

### 3.2 Backend/API
Xem ma trận kiểm thử mục 5. Tóm tắt: xác thực, CSRF, throttle, RBAC, IDOR, upload, quy tắc nghiệp vụ đều đúng; lỗi tập trung ở **rò rỉ dữ liệu trong `data.php` (F-01)**, **export (F-02)**, **xoá lớp (F-03)**, **validate đầu vào (F-09)**.

### 3.3 Frontend + Layout + UI/UX (Chromium)
- 3 kích thước: mobile 390×844, tablet 768×1024, desktop 1366×768. 3 vai: admin (22 module), glv (18), thu_thu (6) → **138 lượt mở module** + 15 lượt mở trang công khai (landing, đăng nhập, tra cứu điểm, sổ mộc, bảng thi đua).
- ✅ Không có `pageerror`, không có `console.error`, không có response ≥ 400, không có module kẹt trạng thái loading, không tràn ngang; bố cục thích nghi đúng (thanh bên trên desktop, thanh điều hướng dưới trên mobile).
- ✅ Vai trò chỉ thấy đúng module được cấp (thu_thu chỉ thấy 6 module).
- ⚠️ Phát hiện nhỏ (chi tiết F-12, F-14): mỗi màn có 2 thẻ `<h1>`; trang đăng nhập 2/2 ô nhập chỉ có placeholder (không có `<label>`); danh sách thiếu nhi 23 ô và tab điểm số/phiếu liên lạc 31 ô nhập không có nhãn; nhiều nút < 32 px (Danh sách 22, Sổ liên lạc 32, Lịch 12, Bảng thi đua 4/5); biểu tượng kính lúp **đè lên chữ "T" của placeholder** ở ô tìm kiếm mobile (ảnh `tests/e2e/out/shots/admin-students-mobile.png`).
- ✅ Tái hiện F-02 trên giao diện: Điểm danh → "Xuất Excel" → "Tải về" ⇒ `500 export.php?action=attendance-detail` ⇒ toast "Đã xảy ra lỗi hệ thống" (ảnh `tests/e2e/out/shots/export-dialog.png`).

### 3.4 Hiệu năng (dữ liệu demo 600 em)
| Endpoint | Thời gian trung vị | Kích thước (chưa nén) |
|----------|-------------------|-----------------------|
| `/` (trang chính) | 10 ms | 582 KB |
| `/api/data.php?part=core` | 9 ms | 250 KB |
| `/api/data.php?part=heavy` | 158 ms | 5,5 MB |
| `/api/data.php` (toàn bộ, admin) | 196 ms | **5,7 MB** |
| `/api/auth.php?action=me`, `/api/sync.php` | 1–3 ms | < 1 KB |

Có nén gzip (~10×) nên chấp nhận được hôm nay, nhưng payload tăng tuyến tính theo số buổi × số em (34.868 dòng điểm danh + 2.400 dòng điểm gửi trọn cho admin/BĐH). Xem F-13.

---

## 4. Danh sách phát hiện chi tiết

### F-01 · CAO — `api/data.php` gửi dữ liệu ngoài phạm vi phân quyền
**Bằng chứng (chạy thật, DB 600 em):**

| Người dùng | `students` nhận | `scores` nhận | `members` (kèm SĐT, ngày sinh) | `logs` (50 dòng, chứa SĐT ở `detail`) |
|-----------|----------------|---------------|-------------------------------|--------------------------------------|
| GLV lớp A | 30 (đúng phạm vi) | **2.400 dòng = điểm của 600 em** (570 em ngoài lớp) | 6 | **có** |
| Dự bị lớp A | 30 | **2.400** | 6 | **có** |
| Thủ thư (không có quyền `students`/`scores`/`staff`) | 0 | **2.400** | **6 (kèm SĐT)** | **có** |

Mã nguồn: `data.php:311-323` (`scores` chỉ lọc theo niên khoá), `data.php:288-306` (`leaveRequests`, kèm `reason` — lý do xin phép có thể chứa thông tin sức khoẻ; chưa có dữ liệu để chạy thật nhưng truy vấn không lọc phạm vi), `data.php:411-441` (`members`), `data.php:445-454` (`logs`, trong khi `api/logs.php` đòi quyền `settings`). Trong khi đó `students`, `attendances`, `stampSummaries` **được lọc đúng** — nghĩa là nguyên tắc có sẵn, chỉ thiếu áp cho các khối còn lại.

**Khuyến nghị:** lọc `scores`/`leaveRequests`/`reports` theo `student_id IN (danh sách em trong phạm vi)` như đã làm với `attendances`; chỉ trả `logs` khi có quyền `settings`; chỉ trả `members` (đặc biệt SĐT, ngày sinh) khi có quyền `staff` ≥ view; **cần xác nhận nghiệp vụ** việc GLV thấy danh bạ toàn bộ nhân sự (hiện GLV có quyền `staff=view` nên là theo thiết kế, còn thủ thư thì không).

### F-02 · CAO — Xuất Excel hỏng (3/4 action của `export.php`)
| Action | Kết quả (admin) | Nguyên nhân |
|--------|----------------|-------------|
| `attendance-detail` (**nút "Xuất Excel" ở Điểm danh dùng cái này**) | HTTP 500 | `export.php:187` `SELECT from_date, to_date FROM school_years` — cột thật là `start_date`, `end_date` |
| `attendance` | HTTP 503 trang "Chưa cài đặt" | `export.php:112` `JOIN program_sessions` — bảng này không tồn tại ở bất kỳ schema/migration nào |
| `scores` | HTTP 500 (`Invalid parameter number`) | `export.php:155` truyền `[năm, termId, classId]` nhưng câu SQL chỉ có 1 dấu `?` + điều kiện lớp (Excel điểm số ở giao diện đã dùng đường client `scores.js:158` nên chưa lộ) |
| `report` | ✅ 200 | — |

Không test nào trong `ExportTest.php` phủ các action này (file chỉ kiểm hàm dựng CSV).
**Khuyến nghị:** sửa 3 câu SQL trên và thêm test tích hợp gọi thật từng action.

### F-03 · CAO — Không xoá được lớp
`OrgService.php:237`: `SELECT COUNT(*) n FROM students WHERE class_id=?`. Sau khi chuyển sang mô hình ghi danh theo niên khoá (`enrollments`), bảng `students` không còn `class_id` ⇒ `Unknown column 'class_id'` ⇒ **HTTP 500 với mọi lần xoá lớp**, kể cả lớp rỗng. (Hệ quả: khối còn lớp con không thể dọn vì phải xoá lớp trước.) Tái hiện: `tests/e2e/orgflow.py` (ORG-02, ORG-03).
**Khuyến nghị:** đếm theo `enrollments` (đúng niên khoá hiện tại **và** mọi niên khoá, tuỳ nghiệp vụ) thay cho `students.class_id`.

### F-04 · CAO — File debug/nhật ký lộ công khai
Truy cập không cần đăng nhập (đều trả HTTP 200):
- `/error_log` (8,9 KB) và `/api/error_log` (0,9 KB) — **đang nằm trong git**, chứa đường dẫn máy chủ `/home/ylcqukhi/tntt/...`, tên CSDL `ylcqukhi_glyphutrung`, stack trace. `.htaccess` chỉ chặn các đuôi `.sql|.log|.ini|...`, **tệp `error_log` không có đuôi nên lọt**.
- `/rebuild_login.php` — **ghi đè** `assets/js/login.min.js` mà không cần đăng nhập (nội dung lấy từ file trong repo nên không chèn được mã tuỳ ý, nhưng đây là điểm ghi file công khai; và bản production nạp chính file này ở trang đăng nhập — `layout_login.php:304`).
- `/api/test_webauthn.php`, `/api/test_timezone.php`, `/check_times.php`, `/read_step.php`, `/read_transcript.php` (chứa đường dẫn máy dev `C:/Users/...`), `/api/openapi.yaml` (tài liệu API đầy đủ).

**Khuyến nghị:** xoá toàn bộ file trên khỏi `public/` (và khỏi git), thêm `error_log` vào `.gitignore` + `FilesMatch` của `.htaccess`; đổi mật khẩu DB nếu nhật ký đã từng công khai.

### F-05 · TRUNG BÌNH — CI cho kết quả xanh giả
1. `php phpunit10.phar --testsuite "TNTT Unit Tests"` **thoát mã 0 mà không chạy test PHPUnit nào** (đã kiểm: 0 dòng banner PHPUnit). Nguyên nhân: `tests/unit/ExportTest.php` là script kiểu cũ, chạy code ngay lúc nạp và gọi `exit(0)` (dòng 529), giết tiến trình trước khi PHPUnit chạy.
2. Job `php-unit` không có dịch vụ MySQL, trong khi 21/22 file test cần DB đã nạp dữ liệu mẫu.
3. Job `php-syntax` kết thúc bằng `|| true`; `js-lint` cũng `|| true` ⇒ không bao giờ đỏ.
4. Khi chạy từng file riêng trên DB demo: **4/22 file lỗi thời** — `ThiDuaTest` (hàm `td_ty_le_co_mat` nay cần 4 tham số, test truyền 3); `RewardsSchemaTest` (quyền nay được seed dạng `none` cho mọi vai chứ không để trống); `SomocTest` (giả định học sinh mã `HS001`, mã nay là `GDGLPT26xxxx`; và `stamp_transactions` đã có khoá duy nhất `uq_sttx_earn` mà test vi phạm khi chèn 2 giao dịch cùng buổi); `PermissionTest` (giả định admin có dòng `member_assignments`, cài mới không tạo — xem F-14).

**Khuyến nghị:** chuyển `ExportTest` sang `TestCase`; dựng MySQL service + `install.php` + fixture cố định trong CI; bỏ `|| true`.

### F-06 · TRUNG BÌNH — `must_change_pw` không được API kiểm tra
Chỉ `index.php:25` và `login.js` ép đổi mật khẩu. `require_login()` (`_bootstrap.php:112`) không xét cờ này: tài khoản còn mật khẩu mặc định gọi được toàn bộ API (đã thử `data.php` → 200). Mật khẩu mặc định `tntt@2026` nằm ngay trong `config/config.php:28` (đã commit) và trong đầu ra của `install.php`.
**Khuyến nghị:** trong `require_login()` trả 403 khi `must_change_pw=1` (trừ `auth.php?action=password|logout|me`).

### F-07 · TRUNG BÌNH — Giới hạn đăng ký công khai vô hiệu
`auth.php:207` gọi `register_ok()` xoá bộ đếm khi đăng ký thành công, và bộ đếm chỉ tăng khi thất bại (`register_failed`). Thử 5 lần đăng ký hợp lệ liên tiếp từ 1 IP: cả 5 đều 200 (mong đợi tối đa 3/giờ). Tài khoản tạo ra ở trạng thái "chờ duyệt" nên không tự vào được, nhưng có thể làm ngập hàng đợi duyệt của BĐH.
**Khuyến nghị:** ghi 1 bản ghi mỗi lần đăng ký (cả thành công) và không xoá khi thành công.

### F-08 · TRUNG BÌNH — Điểm danh cho buổi tương lai
`attendance.php` (khoảng dòng 36–52) chỉ kiểm "buổi có diễn ra vào thứ đó", không kiểm `ngày ≤ hôm nay`. Đã ghi được trạng thái `có mặt` cho Chúa Nhật 11/10/2026 khi hôm nay là 29/09. Với chương trình tính Mộc/chuyên cần, có thể cộng Mộc trước.
**Khuyến nghị:** từ chối `date > CURDATE()` (hoặc chỉ cho trong ngày diễn ra).

### F-09 · TRUNG BÌNH — Thiếu kiểm tra đầu vào hồ sơ thiếu nhi
`students.php` `save`: ngày sinh `2099-01-01` và `1800-01-01` được lưu; ngày `abc` và tên 300 ký tự làm `INSERT` lỗi ⇒ **HTTP 500** với thông báo rỗng `"Không lưu được: "` (ở chế độ production `safe_error()` giấu nội dung lỗi nên người dùng không biết sai chỗ nào). Ngược lại, điểm số (0–10), quà (tồn kho ≥ 0, Mộc > 0), niên khoá (ngày, tên trùng) đều được kiểm chặt — hồ sơ thiếu nhi nên đồng bộ mức chặt tương tự.
**Khuyến nghị:** kiểm `birthDate` là ngày hợp lệ và trong khoảng hợp lý, giới hạn độ dài từng trường, trả 400 kèm thông báo tiếng Việt.

### F-10 · TRUNG BÌNH — Cài mới thiếu bảng, thông báo lỗi gây hiểu nhầm
- `install.php` chỉ chạy `schema.sql` + vài `ALTER`; bảng `qr_card_templates/presets/logos` chỉ có khi chạy riêng `php config/migrations/index.php` (đã kiểm: chạy runner này thì đủ 3 bảng). Cài mới xong, `custom-qrcard.php` trả 503.
- `assets/js/modules/custom-qrcard.js` (900 dòng) **không có trong `asset_manifest.php`** ⇒ tính năng chưa nối vào giao diện; `api/custom-qrcard.php` (896 dòng) là code chưa dùng tới.
- `db.php:71`: chuỗi `'doesn${q}t exist'` là literal (sai chính tả kiểu nội suy), và hàm `db_bao_chua_cai_dat` coi **mọi** lỗi `42S02` là "chưa dựng CSDL" ⇒ một bảng thiếu của riêng một tính năng cũng hiện trang "Chưa dựng cơ sở dữ liệu" (HTTP 503) — gây hiểu nhầm (gặp ở F-02 và `custom-qrcard`).
**Khuyến nghị:** gộp runner migration vào `install.php` (hoặc ghi rõ trong hướng dẫn cài), đăng ký/loại bỏ `custom-qrcard`, chỉ hiện trang "chưa cài đặt" khi bảng lõi (vd. `members`) thiếu.

### Mức THẤP
| ID | Vấn đề | Chi tiết |
|----|--------|----------|
| F-11 | `logout` nhận cả GET | `auth.php:105`: một thẻ `<img src="…/auth.php?action=logout">` đăng xuất được người dùng (CSRF-logout). Nên `require_post()`. |
| F-12 | Khả năng truy cập | 2 thẻ `<h1>`/màn; ô nhập thiếu `<label>` (đăng nhập, danh sách, phiếu liên lạc); nhiều nút/checkbox < 32 px (số liệu ở 3.3) |
| F-13 | Payload lớn | `data.php` toàn bộ 5,7 MB với 600 em; nên phân trang/lọc điểm danh theo tháng, gửi điểm theo lớp đang mở |
| F-14 | Lặt vặt | (a) khoá trùng `libItemIcon`; (b) icon tìm kiếm đè placeholder (mobile); (c) CSP có `'unsafe-inline' 'unsafe-eval'` (do Alpine bản thường — chấp nhận nhưng làm yếu lớp chống XSS); (d) `install.php` in tên DB từ config chứ không phải DB đang dùng khi có `TNTT_DB_NAME`, và `migrate_thu_vien.php` tra `information_schema` theo tên config ⇒ báo `Duplicate column 'item_type'` khi dùng biến môi trường; (e) admin cài mới không có dòng `member_assignments` (quyền vẫn đúng nhờ `permission_of` cộng vai gốc, nhưng dữ liệu không đồng nhất với các vai khác); (f) `_bootstrap.php` gọi `usleep()` 200–400 ms mỗi lần sai mật khẩu làm 1 worker PHP bận (chấp nhận được, chỉ lưu ý khi bị dò mật khẩu hàng loạt) |

---

## 5. Kịch bản kiểm thử theo từng module (kết quả)

Ký hiệu: ✅ đạt · ❌ lỗi (xem F-xx) · ⚠️ đạt một phần · ⏭ chưa kiểm (cần thủ công/môi trường thật).
"API" = gọi thật lên server đã dựng; "UI" = mở bằng Chromium; "UT" = PHPUnit.

### 5.1 Xác thực, phiên, bảo mật chung
| ID | Kịch bản | Kết quả |
|----|----------|---------|
| AUTH-01 | Đăng nhập sai mật khẩu → 401, thông báo không lộ số có tồn tại | ✅ |
| AUTH-02 | Sai > 5 lần / 15 phút → 429 | ✅ |
| AUTH-03 | Đăng nhập đúng, cấp phiên mới (`session_regenerate_id`) | ✅ |
| AUTH-04 | Tài khoản admin mới cài buộc đổi mật khẩu ở giao diện | ✅ API trả cờ `mustChangePw`, `index.php:25` + `login.js` xử lý (chưa chạy tay luồng đổi mật khẩu trên UI) |
| AUTH-05 | API bị chặn khi `must_change_pw=1` | ❌ F-06 |
| AUTH-06 | Cookie `HttpOnly` + `SameSite=Lax` (`Secure` khi HTTPS) | ✅ / ⏭ Secure |
| AUTH-07 | Logout không cho GET | ❌ F-11 |
| CSRF-01/02 | POST thiếu/sai token → 403 | ✅ |
| CSRF-03 | Hành động ghi bằng GET → 405 | ✅ |
| SEC-01 | Mọi endpoint `api/*` từ chối khi chưa đăng nhập (trừ `auth`, `sync`, `csrf`, `passkey.getLoginArgs` theo thiết kế) | ✅ |
| SEC-02 | File debug/nhật ký không truy cập được công khai | ❌ F-04 |
| INJ-01 | 8 payload SQLi × 6 endpoint: không lỗi/không rò | ✅ (riêng `export.php` 500/503 là do F-02, không phải injection) |
| INJ-02/03 | JSON hỏng → lỗi có kiểm soát; body > 1 MB → 413 | ✅ |
| UPL-01 | Upload `.php`, `.php.pdf`, PHP giả PNG, `.html`, `.svg` → từ chối | ✅ |
| UPL-02 | Upload PNG hợp lệ → chấp nhận, lưu tên ngẫu nhiên ngoài web | ✅ |
| PUB-01/02 | Đăng ký công khai không tự nâng vai/trạng thái; SĐT trùng bị báo | ✅ |
| PUB-03 | Đăng ký giới hạn ≤ 3 lần/giờ/IP | ❌ F-07 |
| AUTH-P | Đăng nhập Passkey/WebAuthn | ⏭ |

### 5.2 Phân quyền theo vai (7 vai × các module đọc/ghi)
| ID | Kịch bản | Kết quả |
|----|----------|---------|
| PERM-01 | Vai không có quyền module bị 403 ở API đọc (`students, years, gifts, org, thư viện, staff, logs, settings`) — 6 vai không phải admin | ✅ |
| PERM-02 | BĐH/Trưởng khối/GVCN/GLV/Dự bị/Thủ thư không xoá nhật ký, không đổi phân quyền, không reset mật khẩu admin | ✅ (6 vai × 2 hành động) |
| PERM-03 | Bảo trì module → người không phải admin nhận 503; admin vẫn dùng | ✅ |
| SCOPE-01 | `data.php` chỉ trả điểm số của em trong phạm vi | ❌ F-01 |
| SCOPE-02 | `data.php` chỉ trả danh bạ nhân sự khi có quyền `staff` | ❌ F-01 (thủ thư) |
| SCOPE-03 | `data.php` chỉ trả nhật ký khi có quyền `settings` | ❌ F-01 |
| SCOPE-04 | `data.php` chỉ trả `students`/`attendances`/`stampSummaries` theo phạm vi | ✅ |

### 5.3 Chống truy cập chéo lớp/khối (IDOR) — GLV, GVCN, Dự bị lớp A thao tác trên em lớp B (khối khác)
| ID | Kịch bản | Kết quả |
|----|----------|---------|
| IDOR-01 | `students.save` (mã em B → lớp A; và tại lớp B) | ✅ 403 (6/6) |
| IDOR-02 | `bulk_delete`, `bulk_move` em lớp B | ✅ 403 (6/6) |
| IDOR-03 | `scores.set`, `attendance.toggle` (3 vai) và `attendance.scan` (GLV) với em ngoài phạm vi | ✅ 403 / bỏ qua (7/7) |
| IDOR-04 | `export.report` / `export.scores` / `print.php` em-lớp B | ✅ 403 |
| Hồ sơ em B sau các thử nghiệm | Không bị đổi | ✅ |

### 5.4 Từng module nghiệp vụ
| Module | Kịch bản đã chạy | Kết quả |
|--------|------------------|---------|
| **Danh sách thiếu nhi** (`students`) | Thêm mới (mã `GDGLPT26xxxx` do máy chủ cấp + ghi danh) · sửa giữ mã · chuyển lớp hàng loạt · bỏ trống họ tên → 400 · favorites | ✅ |
| | Ngày sinh vô lý / sai định dạng · tên 300 ký tự | ❌ F-09 |
| | Nhập Excel · xoá hàng loạt trên dữ liệu thật | ⏭ |
| **Hồ sơ em** (`student_profile`) | Mở màn hình | ✅ UI · logic ⏭ |
| **Điểm danh** (`attendance`) | Chạm bật/tắt · gỡ · trạng thái `đi trễ` sau giờ chốt · ngày không có chương trình → 400 · ngày sai định dạng · chương trình/em không tồn tại → 404 | ✅ |
| | Ngày tương lai | ❌ F-08 |
| | Xuất Excel | ❌ F-02 |
| **Quét QR** (`qrscan`) | Quét mã → ghi · quét lại không trùng · mã lạ/mã độc không lỗi · GLV quét em khối khác bị bỏ qua | ✅ · camera thật ⏭ |
| **Thẻ QR** (`qrcard`) | Mở màn hình, in dùng escape | ✅ UI |
| **Thẻ QR tuỳ biến** (`custom-qrcard`) | Chưa nối UI; cài mới thiếu bảng | ❌ F-10 |
| **Xin phép** (`leave`) | Buổi đã qua bị từ chối · tạo đơn tương lai · đơn trùng bị từ chối · GLV không duyệt · admin duyệt · duyệt/từ chối lại bị từ chối | ✅ (6/6) |
| **Điểm số** (`scores`) | 0, 8, 10, 7,55 (làm tròn 7,6) chấp nhận · 11, -1, "abc" bị từ chối · đầu điểm/học kỳ không tồn tại | ✅ (9/9) |
| **Sổ liên lạc/Phiếu** (`reports`, `print.php`) | Lưu & gửi · xoá · escape HTML khi in | ✅ |
| **Thông báo** (`announcements`) | Tạo · GLV không đăng/không xoá · đánh dấu đã đọc · admin xoá | ✅ |
| **Niên khoá** (`years`) | Tạo · trùng tên → từ chối · ngày kết thúc < bắt đầu → từ chối · GLV không kích hoạt · khoá/mở khoá | ✅ |
| **Khối & Lớp** (`org`) | Tạo khối/lớp · không xoá khối còn lớp · cấp lại mật khẩu (buộc đổi) | ✅ |
| | Xoá lớp (rỗng hoặc còn em) | ❌ F-03 |
| **Nhân sự** (`staff`) | Mở màn hình · duyệt/từ chối tài khoản đăng ký, đổi vai | UI ✅ · logic ⏭ |
| **Chương trình** (`programs`) | Mở màn hình; logic ngày/giờ được kiểm gián tiếp qua điểm danh | UI ✅ · tạo/xoá ⏭ |
| **Lên lớp** (`promotion`) | Mở màn hình | UI ✅ · `run` ⏭ (phá huỷ) |
| **Ghi chú/Lịch** (`notes`, `calendar`) | Người khác không xoá được ghi chú riêng tư | ✅ |
| **Thư viện** (`thu_vien`) | Đăng bài · GLV không duyệt · xoá · upload (mục 5.1) | ✅ |
| **Quà & Đổi quà** (`gifts`, `rewards`) | Tạo quà · từ chối Mộc ≤ 0, tồn kho âm, tên rỗng | ✅ API · đổi quà: UT ✅ 26 test |
| **Sổ Mộc / Thi đua / Tra cứu công khai** (`somoc.php`, `bxh.php`, `tracuu.php`) | Ba trang mở ở 3 kích thước không lỗi; logic Mộc/chuỗi/chống dò mã: UT | ✅ · 4 test lỗi thời (F-05) |
| **Báo cáo/Thống kê/Phân tích/Sinh nhật/Hướng dẫn/Cài đặt** | Mở màn hình ở 3 kích thước | ✅ UI · logic ⏭ |
| **Thông báo đẩy** (`push`) | — | ⏭ |

### 5.5 Giao diện & bố cục (ma trận đã chạy)
| Vai | Số module | Mobile 390 | Tablet 768 | Desktop 1366 |
|-----|-----------|------------|------------|--------------|
| admin | 22 | ✅ | ✅ | ✅ |
| glv | 18 | ✅ | ✅ | ✅ |
| thu_thu | 6 | ✅ | ✅ | ✅ |
| Công khai (landing, đăng nhập, tra cứu, sổ mộc, bảng thi đua) | 5 trang | ✅ | ✅ | ✅ |

Tiêu chí mỗi ô: không tràn ngang · không lỗi JS/console/HTTP · module hiển thị đúng thứ được yêu cầu · không kẹt loading. Lưu ý a11y ở F-12.

---

## 6. Kết quả bộ test tự động hiện có (PHPUnit, chạy từng file trên DB demo)

| Kết quả | File |
|---------|------|
| ✅ Đạt toàn bộ (17 file) | AssetManifest 2 · Assignment 10 · CSRF 4 · Export 43 (kiểu script) · GiftsApi 9 · Password 9 · PermissionHardening 5 · QrScanApi 14 · QrScan 13 · RewardsOrderApi 9 · RewardsOrder 17 · RewardsRedeem 9 · RewardsScope 1 · Scope 6 · StampEngine 11 · StampHook 6 · StampProfile 6 · Tracuu 6 |
| ❌ Lỗi | `PermissionTest` 1/2 · `RewardsSchemaTest` 2/5 · `SomocTest` 3/12 (còn 1 lỗi sau khi bổ sung mã `HS001`) · `ThiDuaTest` 1/8 |
| Nguyên nhân | Test lỗi thời hoặc thiếu dữ liệu mẫu (xem F-05), **không phải lỗi ứng dụng** |

Tổng: 164 test PHPUnit (157 đạt, 7 lỗi) + 43 test kiểu script (đạt). Cảnh báo: chạy `phpunit --testsuite` gộp thì **không chạy test nào** (F-05).

---

## 7. Lộ trình khắc phục đề xuất

**Ngay (P0 — trong tuần):**
1. F-04: xoá file debug + `error_log`, chặn `error_log` trong `.htaccess`. (15 phút)
2. F-01: lọc `scores`/`leaveRequests`/`reports`/`logs`/`members` theo phạm vi & quyền trong `data.php`. (nửa ngày + test)
3. F-02: sửa 3 truy vấn `export.php`; F-03: sửa đếm ở `OrgService.php:237`. (1–2 giờ + test hồi quy)

**Sớm (P1 — 2 tuần):**
4. F-05: sửa CI (chuyển `ExportTest` sang PHPUnit, thêm MySQL service + fixture, bỏ `|| true`), cập nhật 4 file test lỗi thời.
5. F-06, F-07, F-08, F-09: chặn API theo `must_change_pw`; sửa bộ đếm đăng ký; từ chối điểm danh tương lai; kiểm đầu vào hồ sơ.

**Sau (P2):** F-10 (cài đặt/migration/`custom-qrcard`), F-11…F-14 (logout POST, a11y, phân trang `data.php`, dọn lặt vặt).

---

## 8. Phụ lục — kịch bản tự động và cách chạy lại

Toàn bộ script nằm trong `tests/e2e/` (thư mục `tests/` đang bị `.gitignore`, **chưa commit**):

| File | Nội dung |
|------|----------|
| `e2e.py` | Quét bảo mật API: chưa đăng nhập, file debug, throttle, CSRF, ma trận quyền, tiêm nhiễm, upload (*một số ô "PERM" cho thủ thư ở `notes`/`announcements`/`leave` là báo lỗi giả do tôi gọi sai action — bỏ qua*) |
| `e2e2.py` | IDOR, phạm vi `data.php`, XSS/CSV, đăng ký công khai |
| `flows.py` | Luồng nghiệp vụ từng module (bảng 5.4) |
| `orgflow.py` | Xoá khối/lớp (F-03) |
| `ui.js`, `ui2.js` | Duyệt giao diện Playwright (3 vai × 3 kích thước) và tái hiện lỗi xuất Excel |
| `out/` | Ảnh chụp minh hoạ |

Cách dựng môi trường:
```bash
mysql -uroot -e "CREATE DATABASE tntt_e2e CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
export TNTT_DB_NAME=tntt_e2e TNTT_DB_USER=<user> TNTT_DB_PASS=<pass> TNTT_DB_HOST=127.0.0.1
php config/install.php && for m in config/migrate_*.php; do php $m; done   # (migrate_thu_vien.php sẽ báo lỗi do F-14d — bỏ qua)
TNTT_ALLOW_SEED=1 php config/seed_demo.php
php -S 127.0.0.1:8088 -t public &
python3 tests/e2e/e2e2.py && python3 tests/e2e/flows.py && python3 tests/e2e/orgflow.py
NODE_PATH=<đường dẫn global node_modules> node tests/e2e/ui.js
```
Ghi chú: các script giả định DB tên `tntt_e2e`, `mysql -uroot` không mật khẩu, và tạo 6 tài khoản thử có SĐT `09110000xx`; **chỉ chạy trên DB thử nghiệm**, không chạy trên dữ liệu thật.

**Thay đổi trong repo do đợt kiểm thử này:** chỉ thêm `BAO_CAO_KIEM_THU.md` và thư mục `tests/e2e/`. Không sửa mã nguồn ứng dụng, không commit, không push.
