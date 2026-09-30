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
| F-11…F-16 | Thấp/TB | logout bằng GET, a11y/tap-target, payload 5,7 MB, các lỗi nhỏ, BĐH sửa được tài khoản Quản trị (F-15), SĐT không hợp lệ (F-16) | Xem mục 4 và 8; toàn bộ đã có issue #78–#98 |

**Số liệu kiểm thử**

| Tầng | Kết quả |
|------|---------|
| Cú pháp (PHP lint 170 file / `node --check` 33 file JS) | ✅ 0 lỗi |
| PHPUnit hiện có (164 test PHPUnit + 43 test kiểu script) | 157/164 đạt; **7 lỗi đều do test lỗi thời/thiếu dữ liệu mẫu, không phải lỗi ứng dụng** |
| Kịch bản API/bảo mật/nghiệp vụ mới viết (mục 5) | khoảng 150 kiểm tra (đã loại các ô tự kiểm sai), phát hiện F-01…F-04, F-06…F-09 |
| Đợt bổ sung (mục 8): chương trình, nhân sự, phân công, nhập Excel, thư viện, họp, đổi quà, lên lớp | 79 kiểm tra: 76 đạt; thêm lỗi F-15 (BĐH sửa được tài khoản Quản trị), F-16 (SĐT không hợp lệ) |
| Giao diện tự động (Chromium, 3 vai × 3 kích thước, 138 lượt mở module + 15 trang) | 0 lỗi JS/console/HTTP, 0 tràn ngang; có lỗi a11y nhỏ |

---

## 2. Phạm vi, môi trường và giới hạn

**Môi trường dựng để kiểm thử** (không đụng dữ liệu thật): MariaDB 10.11 cục bộ, PHP 8.4.19, `php -S` làm web server, Chromium + Playwright. Nạp `config/install.php` + toàn bộ `config/migrate_*.php` + `seed_demo.php` (600 thiếu nhi, 20 lớp, ~35k dòng điểm danh, 2.400 dòng điểm). Chọn DB qua biến `TNTT_DB_*` nên **không sửa file cấu hình nào**. Tạo thêm 6 tài khoản thử, mỗi vai một người (`bdh`, `truong_khoi`, `glv_chu_nhiem`, `glv`, `du_bi`, `thu_thu`).

**Những gì CHƯA/KHÔNG kiểm thử được (cần làm thủ công hoặc môi trường thật):**

| Hạng mục | Lý do |
|----------|-------|
| Web Push tới thiết bị thật (FCM/APNs) | Phía máy chủ và service worker đã kiểm ở mục 10; chưa gửi tới push service thật của Google/Apple |
| Passkey với vân tay/Face ID thật | Đã kiểm bằng authenticator ảo (mục 10); chưa thử trên điện thoại thật |
| Quét QR bằng camera vật lý (ánh sáng, độ nét thẻ in) | Đã kiểm với camera giả cung cấp video mã QR (mục 10); chưa thử thẻ in thật |
| Safari iOS / Chrome Android trên thiết bị thật | Đã kiểm engine WebKit thật (WebKitGTK) và PWA trong Chromium (mục 10); Firefox/WebKit của Playwright không tải được vì chính sách mạng chặn `cdn.playwright.dev` |
| Xoá thiếu nhi hàng loạt trên dữ liệu thật | Thao tác phá huỷ (đã kiểm phân quyền `bulk_delete`, không chạy trên dữ liệu thật) |
| Lên lớp cuối năm, nhập Excel, chương trình, nhân sự, phân công, thư viện, đổi quà | **Đã kiểm ở đợt bổ sung, xem mục 8** |
| Tải lớn (hàng nghìn người dùng đồng thời) | Đã đo tải cơ bản (mục 10); chưa thử tải thật trên hosting |
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
| | Nhập Excel (mục 8) | ⚠️ ngày sinh tương lai vẫn được nhập |
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
| **Nhân sự** (`staff`) | Xem mục 8 (STAFF-00…16, ASG-01…11) | ⚠️ 2 lỗi (mục 8) |
| **Chương trình** (`programs`) | Xem mục 8 (PRG-01…06) | ✅ |
| **Lên lớp** (`promotion`) | Xem mục 8 (PROMO-01…07) | ✅ |
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

## 8. Đợt kiểm thử bổ sung (29–30/09/2026)

Bổ sung các phần ở mục 2 chưa làm. Chạy `tests/e2e/extra.py` trên DB thử (sao lưu trước, khôi phục sau). **79 kiểm tra: 76 đạt, 3 lỗi** (sau khi sửa các ô tự kiểm sai: giả định tạo thành viên thủ công, giá trị RSVP, dữ liệu sơ đồ lên lớp thiếu).

| Nhóm | Kịch bản | Kết quả |
|------|----------|---------|
| **Chương trình** PRG-01…06 | Tạo/sửa/xoá; từ chối giờ sai, chốt ≤ bắt đầu, tính vắng ≤ bắt đầu, thứ = 9, tên rỗng, ngày áp dụng ngược, chiến dịch thiếu ngày; GLV bị chặn; chương trình đã có điểm danh không xoá được (gợi ý đóng thay vì xoá) | ✅ 12/12 |
| **Nhân sự** STAFF-00…16 | Tạo tay bị từ chối theo thiết kế; sửa hồ sơ; trùng SĐT; thiếu tên; chống BĐH nâng lên admin; TK không sửa được; đăng ký → chờ duyệt không đăng nhập được → duyệt → đăng nhập được; duyệt lần 2 / vai admin bị từ chối; từ chối tài khoản; GLV không duyệt; không xoá được admin | ✅ 15 · ❌ 2 |
| **Phân công** ASG-01…11 | Phân công/kết thúc/xoá; thiếu lớp, vai lạ; TK không phân công ngoài khối, không gán admin; BĐH không gán admin/BĐH; GLV không tự phân công | ✅ 11/11 |
| **Nhập Excel** IMP-01…06 | Dòng hợp lệ thêm, lớp lạ/tên rỗng bỏ qua; GVCN không ghi đè được em lớp khác bằng mã trùng; import rỗng bị từ chối; **3.000 dòng trong 1 request chạy được (200)**; thủ thư bị chặn | ✅ 5 · ❌ 1 |
| **Thư viện** LIB-04…12 | GLV tải lên → chờ duyệt; không tự duyệt; admin duyệt; tệp chưa duyệt của người khác bị 403; chưa đăng nhập 401; xoá | ✅ 9/9 (tệp đã duyệt xem được bởi mọi thành viên đăng nhập, theo thiết kế) |
| **Thông báo họp** ANN-06…11 | Tạo, RSVP, lựa chọn lạ bị từ chối, người xem kết quả bị giới hạn, ngày họp sai bị từ chối | ✅ 6/6 |
| **Đổi quà** RWD-01…08 | Tra cứu; trừ đúng Mộc và tồn kho; thiếu Mộc, vượt tồn kho, qty âm/0/rất lớn, quà lạ đều bị từ chối và số dư không đổi; GLV bị chặn; **4 yêu cầu đồng thời chỉ 1 thành công, số dư không âm** | ✅ 12/12 |
| **Lên lớp** PROMO-01…07 | Đích trùng/không tồn tại/đã khoá bị từ chối; GLV bị chặn; chạy cả khối đúng số em; chạy lại không tạo trùng; em được đánh "lên" chuyển đúng lớp kế tiếp | ✅ 7/7 (cần khai sơ đồ lên lớp trước, đúng thiết kế) |

**Lỗi mới (đã tạo issue):**
- **F-15 · Trung bình** — BĐH sửa được hồ sơ tài khoản Quản trị (đổi SĐT đăng nhập, đổi tên) qua `org.php?action=saveMember`; đăng nhập admin bằng SĐT cũ trả 401. [#97](https://github.com/josephvinh/glyphutrung/issues/97)
- **F-16 · Thấp** — `saveMember` nhận SĐT không hợp lệ (`"12"` lưu thành `012`). [#98](https://github.com/josephvinh/glyphutrung/issues/98)
- Mở rộng F-09: import Excel cũng nhận ngày sinh tương lai (bình luận tại [#86](https://github.com/josephvinh/glyphutrung/issues/86)).

Chưa kiểm ở đợt này: Web Push, Passkey, camera QR thật, trình duyệt iOS/Android thật, tải/đồng thời ngoài đổi quà.

### Tương ứng issue trên GitHub
F-01 #78 · F-02 #79 · F-03 #80 · F-04 #81 · F-05 #82 · F-06 #83 · F-07 #84 · F-08 #85 · F-09 #86 · F-10 #87 · F-11 #88 · F-12 #109 (thay cho #89, số này không tồn tại trên GitHub) · F-13 #90 · F-14: #91 (khoá trùng), #92 (icon tìm kiếm), #93 (env `TNTT_DB_NAME`), #94 (admin thiếu phân công), #95 (CSP), #96 (độ trễ đăng nhập sai) · F-15 #97 · F-16 #98

---

## 9. Phụ lục — kịch bản tự động và cách chạy lại

Toàn bộ script nằm trong `tests/e2e/` (thư mục `tests/` đang bị `.gitignore`, **chưa commit**):

| File | Nội dung |
|------|----------|
| `e2e.py` | Quét bảo mật API: chưa đăng nhập, file debug, throttle, CSRF, ma trận quyền, tiêm nhiễm, upload (*một số ô "PERM" cho thủ thư ở `notes`/`announcements`/`leave` là báo lỗi giả do tôi gọi sai action — bỏ qua*) |
| `e2e2.py` | IDOR, phạm vi `data.php`, XSS/CSV, đăng ký công khai |
| `flows.py` | Luồng nghiệp vụ từng module (bảng 5.4) |
| `orgflow.py` | Xoá khối/lớp (F-03) |
| `extra.py` | Đợt bổ sung (mục 8): chương trình, nhân sự, phân công, nhập Excel, thư viện, họp/RSVP, đổi quà, lên lớp |
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

---

## 10. Đợt kiểm thử mở rộng (30/09/2026): phần cứng giả lập, PWA, Excel, trang công khai, tải, WebKit

Đã cài thêm công cụ từ các nguồn được phép: PHPStan (phar), axe-core (`@axe-core/playwright`), ApacheBench, `ffmpeg`, `xvfb`, `webkit2gtk-driver` + Selenium, `qrcode`, `openpyxl`, `cryptography`. **Không tải được** Firefox/WebKit của Playwright vì proxy chặn `cdn.playwright.dev` (chính sách tổ chức, không lách qua).

| Hạng mục | Cách kiểm | Kết quả |
|----------|-----------|---------|
| **Quét QR bằng camera** | Chromium với camera giả phát video 3 mã: em A, mã lạ `ZZZ999`, em B; đường giải mã jsQR (đường dự phòng dùng cho Safari iOS) | ✅ Ghi đúng 2 em (`đi trễ`, phương thức `qr`), mã lạ bị từ chối, cộng Mộc đúng. ❌ lỗi console do iframe ẩn: [#105](https://github.com/josephvinh/glyphutrung/issues/105) |
| **Passkey (WebAuthn)** | Authenticator ảo qua CDP | ✅ Đăng ký, lưu khoá, đăng nhập không mật khẩu, gỡ. ❌ không kiểm bộ đếm chữ ký, không bắt xác minh người dùng, lộ thông điệp exception: [#103](https://github.com/josephvinh/glyphutrung/issues/103) |
| **Web Push (máy chủ)** | Push service giả bằng TLS cục bộ; xác minh JWT ES256 | ✅ Khoá công khai, đăng ký/huỷ, header VAPID đúng chuẩn và chữ ký hợp lệ, TTL, không gửi nội dung, xoá subscription chết khi 404/410, giữ khi 500, hàng đợi `pending` dùng một lần. ❌ SSRF mù [#99](https://github.com/josephvinh/glyphutrung/issues/99); gửi đồng bộ giữ request 6–10 s [#100](https://github.com/josephvinh/glyphutrung/issues/100); chiếm subscription trùng endpoint [#107](https://github.com/josephvinh/glyphutrung/issues/107) |
| **PWA / Service worker** | Tên miền `tntt.localhost` (sw.js bỏ qua hẳn `localhost`), CDP `deliverPushMessage`, chế độ offline | ✅ Đăng ký SW, kho chỉ chứa tệp tĩnh (không chứa API), sự kiện push làm SW gọi `pending`, manifest không có lỗi cài đặt (Chromium). ⚠️ Mất mạng: trình duyệt hiện màn hình lỗi, chưa có trang offline (đúng thiết kế "dữ liệu luôn lấy từ mạng", chỉ ghi nhận) |
| **Excel qua giao diện** | Tải mẫu → tạo file bằng `openpyxl` → nhập → xuất → đọc lại | ✅ 11/11: mẫu có sheet "Danh sách" + "Hướng dẫn", phông Times New Roman 13, nhập thêm đúng dòng hợp lệ, bỏ dòng lớp lạ, SĐT giữ số 0 và đổi +84 → 0, ngày sinh `dd/mm/yyyy` và `yyyy-mm-dd`, ngày sai không làm hỏng lô, xuất đủ số em, SĐT ở dạng văn bản |
| **Trang công khai** (`tracuu`, `somoc`, `bxh`) | Gửi mã + mật mã, dò mã, chống XSS | ✅ 16/17: lỗi gộp không lộ mã có thật, khoá 5 lần sai theo mã, chặn theo IP, chỉ nhận POST, escape đúng. ⚠️ mật mã bắt buộc tháng-ngày-năm gây khó cho phụ huynh: [#108](https://github.com/josephvinh/glyphutrung/issues/108) |
| **Thẻ QR tuỳ biến** | API sau khi chạy migration | ❌ Mọi action xem trước/xuất trả 403 kể cả admin (module `qrcard` chưa đăng ký); `delete_preset` lỗi 500: [#104](https://github.com/josephvinh/glyphutrung/issues/104) |
| **Khả năng truy cập (axe-core, WCAG 2.1 AA)** | 22 màn × mobile/desktop | ❌ 1.098 vi phạm tương phản (chủ yếu `slate-400` 2,5:1), 14 `select` + 5 `input` + 4 nút thiếu nhãn: [#109](https://github.com/josephvinh/glyphutrung/issues/109) |
| **Phân tích tĩnh PHP (PHPStan 2.2.16, level 1 và 4)** | Toàn bộ `public/`, `config/`, `src/`, `views/` | 28 lỗi level 1 phần lớn là dương tính giả (biến do `include`, thư viện WebAuthn nạp động). Phát hiện thật: `db_run()->rowCount()` ([#104](https://github.com/josephvinh/glyphutrung/issues/104)) và mã chết ([#106](https://github.com/josephvinh/glyphutrung/issues/106)) |
| **Thư viện JS bên thứ ba** | Đối chiếu phiên bản | ✅ SheetJS 0.20.3 (bản đã vá), Alpine 3.15.0, jsQR 1.4.0, qrcode-generator 1.4.4: không thấy bản đã biết lỗ hổng |
| **Giới hạn tần suất** | 75 request `data.php` liên tiếp | ❌ Không có tác dụng khi thiếu APCu; các hàm `enforce_*_limit` còn lại không được gọi: [#102](https://github.com/josephvinh/glyphutrung/issues/102) |
| **Tải cơ bản (ApacheBench, PHP 8 worker, dữ liệu 600 em)** | c=20 | ✅ 0 request lỗi: trang khách ~11.000 req/s, `auth me` ~10.500 req/s, `tracuu` ~3.300 req/s (p99 18 ms), `data.php?part=core` ~490 req/s (p99 20 ms). Đây là máy thử, không thay cho đo trên hosting |
| **Engine WebKit thật** | WebKitGTK 2.52 qua WebKitWebDriver + Xvfb (`tests/e2e/webkit.py`) | ✅ Đăng nhập và mở 6 module, không tràn ngang, hiển thị đúng (ảnh `tests/e2e/out/webkit-app.png`) |
| **Triển khai** | Chạy ở chế độ production (host khác `localhost`) | ❌ Thiếu `login.min.js` (file build bị `.gitignore`) thì trang đăng nhập hỏng: [#101](https://github.com/josephvinh/glyphutrung/issues/101) |

**Lưu ý phương pháp:** một số ô kiểm đầu tiên của đợt này dùng sai điều kiện (mật mã tra cứu tưởng là `dd/mm/yyyy`; ô công thức Excel bị `openpyxl` ghi thành công thức thật; kỳ vọng `aud` của JWT kèm cổng). Đã sửa hoặc loại bỏ trước khi ghi kết quả. Các mục hiển thị ✅ ở trên là kết quả sau khi sửa. Kịch bản PUSH-11 (`aud`) đã chỉnh kỳ vọng nhưng chưa chạy lại sau chỉnh.

Kịch bản mới: `hw.js` (camera + Passkey), `push.py`, `pwa.js`, `excel.js`, `public.py`, `customqr.py`, `axe.js`, `webkit.py` trong `tests/e2e/`.

**Số issue trên GitHub:** #78–#88, #90–#109 (không có #89). Riêng mục đăng ký lại issue a11y là #109.
