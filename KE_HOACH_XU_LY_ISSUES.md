# Kế hoạch xử lý các issue sau đợt kiểm thử (29–30/09/2026)

**Agent mới: đọc `BAN_GIAO.md` trước** (quy ước làm việc, dựng môi trường, bẫy kỹ thuật, việc tiếp theo).

Nguồn: `BAO_CAO_KIEM_THU.md` và 31 issue #78–#109 (không có #89) trên `josephvinh/glyphutrung`.
Nguyên tắc: chia thành 8 gói nhỏ, mỗi gói một PR, không giao một model ôm cả danh sách. Gói nào chạm cùng file thì làm tuần tự; gói độc lập thì chạy song song.

Việc gán model dưới đây là đề xuất theo vai trò (thiết kế/soát an ninh, viết code, duyệt độc lập), dựa trên bảng phân vai trong `.claude/AGENTS.md`. Model viết code không được là model duyệt PR của chính nó.

## 1. Câu hỏi cần chủ dự án quyết định trước

| # | Câu hỏi | Ảnh hưởng |
|---|---------|-----------|
| 1 | GLV có được xem danh bạ SĐT toàn bộ nhân sự không? (hiện GLV có quyền `staff=view`) | #78 |
| 2 | Xoá lớp: chỉ xét niên khoá hiện tại hay mọi niên khoá? | #80 |
| 3 | Giữ hay xoá `custom-qrcard` (~1.800 dòng chưa nối UI, module `qrcard` chưa đăng ký)? | #87, #104, #106 |
| 4 | Host có APCu không? Nếu không, giới hạn tần suất lưu vào file hay DB? | #102 |
| 5 | Mật mã tra cứu: nhận cả ngày/tháng/năm, hay giữ tháng/ngày/năm? | #108 |
| 6 | Bảng thi đua và sổ Mộc công khai có được hiện tên đầy đủ của trẻ không? | ghi chú trong #108 |

## 1b. Quyết định của chủ dự án (30/09/2026)

| # | Quyết định | Tác động lên kế hoạch |
|---|------------|-----------------------|
| 1 | GLV **được** xem danh bạ SĐT toàn bộ nhân sự | #78: giữ `members` cho vai có quyền `staff` ≥ view (GLV có); vai không có quyền `staff` (ví dụ Thủ thư) không nhận danh bạ. `scores`, `leaveRequests`, `reports` lọc theo phạm vi; `logs` chỉ khi có quyền `settings` |
| 2 | Xoá lớp: chỉ xét **niên khoá hiện tại** | #80: đổi từ "mọi niên khoá" sang chỉ đếm ghi danh của niên khoá hiện tại. Khoá ngoại `fk_enr_class` là RESTRICT nên ghi danh niên khoá cũ vẫn chặn ở tầng CSDL: cần bắt lỗi và trả thông báo rõ thay vì 500 |
| 3 | **Xoá** `custom-qrcard` | Xoá `public/api/custom-qrcard.php`, `custom-qrcard.js` và mọi tham chiếu; #104 đóng do xoá (P2 rút phần sửa `custom-qrcard.php`); #87 chỉ còn phần trang "Chưa cài đặt" gây hiểu nhầm; #106 gộp vào P6 |
| 4 | Host **có** APCu | #102: dùng APCu; vẫn cần fallback an toàn (không im lặng vô hiệu) khi thiếu APCu (dev/CI) và gắn các hàm `enforce_*_limit` vào đúng chỗ |
| 5 | Tra cứu nhận **dd/mm/yyyy**; **chuẩn hoá toàn web** theo định dạng này | #108 + gói mới **P7b** (kiểm kê mọi chỗ hiển thị/nhập ngày rồi chuẩn hoá `dd/mm/yyyy`); mật mã tra cứu: dd/mm/yyyy là chuẩn, tạm chấp nhận mm/dd/yyyy cũ khi không mơ hồ để phụ huynh đã được hướng dẫn trước đây không bị khoá |
| 6 | Công khai tên trẻ ở `somoc.php`/`bxh.php` là **được** | Không đổi; ghi chú trong #108 xem như đã quyết |

Kịch bản e2e nay đọc `E2E_BASE` (URL), `E2E_DB` (tên DB) và `E2E_ORIGIN` (PWA) từ biến môi trường để nhiều gói chạy song song trên DB và cổng riêng.

## 2. Các gói việc

| Gói | Issue | Nội dung | Thiết kế / soát an ninh | Viết code | Duyệt độc lập |
|-----|-------|----------|-------------------------|-----------|---------------|
| **W0 · CI** | #82 | CI xanh giả: `ExportTest` gọi `exit`, CI không có MySQL, lint `\|\| true`, 4/22 file test lỗi thời | không cần | Sonnet 5.5 | Opus 5.5 |
| **P1 · Rò rỉ dữ liệu, phân quyền** | #78, #97, #83 | `data.php` lộ điểm/nhật ký/danh bạ; BĐH sửa được tài khoản admin; `must_change_pw` không chặn API | Fable 5.1 | Opus 5.5 | Fable 5.1 |
| **P2 · Tính năng hỏng do đổi schema** | #79, #80, #104 (phần xoá preset) | Xuất Excel lỗi 500/503; xoá lớp lỗi 500; xoá preset lỗi 500 | không cần | Sonnet 5.5 | Opus 5.5 |
| **P3 · Kiểm tra đầu vào** | #86, #98, #85 | Ngày sinh vô lý, SĐT sai, điểm danh buổi tương lai | Haiku 4.5 (liệt kê quy tắc) | Sonnet 5.5 | Sonnet 5.5 (phiên khác) |
| **P4 · Chống lạm dụng, đăng nhập** | #84, #102, #88, #103, #96, #95 | Đăng ký spam, RateLimiter vô hiệu khi thiếu APCu, logout GET, Passkey (bộ đếm chữ ký, xác minh người dùng), độ trễ đăng nhập sai, CSP | Opus 5.5 | Sonnet 5.5 | Opus 5.5 |
| **P5 · Web Push** | #99, #100, #107 | SSRF mù, gửi push đồng bộ giữ request, chiếm subscription trùng endpoint | Opus 5.5 | Sonnet 5.5 | Opus 5.5 |
| **P6 · Triển khai, cài đặt** | #81, #101, #87, #93, #94, #106 | File debug và `error_log` công khai, thiếu `login.min.js`, cài mới thiếu bảng, script bỏ qua `TNTT_DB_NAME`, admin thiếu phân công, mã chết | Sonnet 5.5 | Sonnet 5.5 (`tntt-devops`) | Opus 5.5 |
| **P7 · Giao diện, khả năng truy cập** | #109, #92, #105, #91, #108 | 1.098 vi phạm tương phản, thiếu nhãn, icon đè placeholder, iframe ẩn, khoá trùng, UX mật mã tra cứu | Sonnet 5.5 | Sonnet 5.5; Haiku 4.5 cho sửa hàng loạt `aria-label` | Sonnet 5.5 kèm chạy axe-core |
| **P7b · Định dạng ngày dd/mm/yyyy toàn web** | #108 (phần tra cứu) + kiểm kê toàn web | Nhập/hiển thị ngày không thống nhất (ô `<input type=date>` theo ngôn ngữ trình duyệt, `toLocaleDateString`, `date('Y-m-d')`), mật mã tra cứu | Sonnet 5.5 (kiểm kê chỉ đọc) | Sonnet 5.5 | Opus 5.5 |
| **P8 · Hiệu năng** | #90 | `data.php` 5,7 MB (chưa nén) với 600 em | Opus 5.5 | Sonnet 5.5 | Opus 5.5 |

Lý do giao model:
- P1, P4, P5 là bảo mật và phân quyền: dùng model mạnh nhất để thiết kế và soát, viết code theo đặc tả rõ nên giao Sonnet.
- P2, P3, P6, P7 là việc cơ học hoặc có đặc tả sẵn: Sonnet là đủ. Haiku chỉ dùng cho việc nhỏ và lặp lại.
- P8 cần thiết kế lại cách tải dữ liệu: dùng Opus.

## 3. Thứ tự và phụ thuộc

1. **W0 trước tiên**, để mọi PR sau có CI thật.
2. **Chạy song song** (không chạm chung file): P1, P2, P5, P6.
3. **Tuần tự do chung file:**
   - P1 rồi P8 (cùng sửa `public/api/data.php`).
   - P1 rồi P4 (cùng sửa `public/api/_bootstrap.php`: `require_login`, `require_write`).
   - P3 và P4 không chạy cùng lúc với P1 trên `auth.php`, `students.php`.
   - Trong P5, `push.php` và `config/push.php` chỉ một người sửa.
   - `StaffService.php` nằm trong P1 (#97) và P3 (#98): làm #97 trước.
4. **P7 làm cuối**, vì sửa nhiều `views/*.php` và `public/index.php`, dễ xung đột với các gói khác.
5. **P6:** phải có phương án dự phòng cho `login.min.js` (#101) trước khi xoá `public/rebuild_login.php` (#81).

## 4. Quy trình mỗi gói

1. Một nhánh và một PR cho mỗi gói (tách từ nhánh chính, không gộp gói).
2. Trước khi sửa: chạy lại kịch bản trong `tests/e2e/` để tái hiện lỗi, lưu kết quả.
3. Sửa tối thiểu đúng phạm vi issue. Thêm test hồi quy (PHPUnit hoặc kịch bản e2e).
4. Chạy lại kịch bản: phải chuyển từ FAIL sang PASS và các kịch bản khác không hỏng.
5. Model duyệt độc lập đọc diff và chạy lại kiểm thử, rồi mới merge.
6. Chỉ đóng issue khi có bằng chứng (đầu ra kịch bản trước/sau) ghi trong PR.

Kịch bản dùng để nghiệm thu từng gói (trong `tests/e2e/`):

| Gói | Kịch bản |
|-----|----------|
| W0 | chạy lại CI trên PR mẫu; `phpunit` phải chạy được các test thật |
| P1 | `e2e2.py` (SCOPE-01…03), `extra.py` (STAFF-06), `e2e.py` (AUTH-05) |
| P2 | `ui2.js` (xuất Excel), `orgflow.py` (ORG-02, ORG-03), `customqr.py` (CQR-12, CQR-13) |
| P3 | `flows.py` (STU-04, STU-05, ATT-04), `extra.py` (STAFF-04, IMP-03) |
| P4 | `e2e2.py` (PUB-03), `e2e.py` (AUTH-07), `hw.js` (PK-05) |
| P5 | `push.py` (PUSH-20, 21, 22) |
| P6 | `e2e.py` (SEC-02), kiểm thủ công trang đăng nhập ở chế độ production khi thiếu `login.min.js` |
| P7 | `axe.js`, `ui.js`, `hw.js` (CAM-05) |
| P8 | `perf` bằng ApacheBench như trong báo cáo mục 10; kích thước và thời gian `data.php` |

## 5. Ghi chú

- Kịch bản e2e chạy trên DB thử nghiệm, xem `BAO_CAO_KIEM_THU.md` mục 9 để dựng môi trường.
- Chưa kiểm được: gửi push tới máy chủ thật của Google/Apple, Passkey với vân tay/Face ID thật, thẻ QR in thật, tải lớn trên hosting thật. Nên có bước kiểm thủ công trên thiết bị thật sau P4, P5.
- Mọi issue liên quan có liên kết chéo trong nội dung issue (ví dụ #79 ↔ #87, #81 ↔ #101, #87 ↔ #104).

## 6. Tiến độ (cập nhật 30/09/2026)

Tóm tắt: 8 PR đã gộp vào `master` (W0, P2, P3, P6 và 4 issue nhẹ); CI giờ chạy test thật và đã kiểm là biết đỏ. Đang chờ: PR #125 (việc còn lại của W0). Dở dang: P1 và P5. Chưa bắt đầu: P4, P7, P7b, P8.

### 6.1 Đã gộp vào `master` (sau duyệt độc lập)

Thứ tự gộp: #114, #112, #113, #117, #119, #120, #118, rồi #116 (W0, gộp cuối để CI thật chạy trên `master` đã có mọi bản sửa).

| Gói / issue | Nhánh | PR | Issue đóng | Ghi chú |
|-------------|-------|----|-----------|---------|
| P6 triển khai, cài đặt | `fix/p6-deploy-cleanup` | #114 | #81, #101, #87, #93, #94, #104 | Tham chiếu #106 (còn `src/Router.php` vì `tests/UnitTest.php` dùng). Duyệt: Opus, có thử Apache 2.4 |
| P2 schema | `fix/p2-schema-regressions` | #112 | #79, #80 | Test `tests/e2e/p2_regress.py` (27 ca). Lỗi "Tỷ lệ" → #110 |
| P3 kiểm tra đầu vào | `fix/p3-input-validation` | #113 | #85, #86 | Test `p3_regress.py` (101 ca). Duyệt: chấp nhận sau khi vá điều kiện (bỏ qua qua `scan`) |
| W0 CI thật | `fix/w0-ci-that` | #116 | #82 | Gộp thử lên `master` mới phát hiện 7 test `QrScanApiTest` đỏ vì P3 (#85) nay từ chối buổi tương lai; đã sửa test dùng buổi hôm nay/tuần trước và thêm `test_scan_rejects_future_date` (xác nhận đỏ khi tắt kiểm ngày). Test chỉ đúng nếu không chạy sát nửa đêm |
| #91 khoá trùng `libItemIcon` | `fix/91-library-dupe-key` | #117 | #91 | Duyệt: chấp nhận có điều kiện (đưa `no-dupe-keys` vào CI → #125) |
| #92 kính lúp đè placeholder | `fix/92-search-padding` | #118 | #92 | `pl-10` và `left-3.5` không có trong `tailwind.css` → dùng `pl-11`, `left-4`. Chưa kiểm chế độ tối |
| #105 iframe/ảnh ẩn tải `undefined` | `fix/105-hidden-iframe` | #119 | #105 | Đo: 2 request thừa → 0 |
| #88 logout nhận GET | `fix/88-logout-post` | #120 | #88 | GET → 405. Logout chưa đòi CSRF (xét trong P4) |

### 6.2 Kiểm CI thật (đã làm)

- `workflow_dispatch` trên `master` (commit `7c115a6`, run #322): 3 job xanh (PHPUnit Tests, PHP Syntax Check, JavaScript Lint), PHP 8.2.34, PHPUnit 10.5.65.
- Kiểm CI có còn xanh giả không, bằng nhánh tạm phá cố ý (run #323, #324): lỗi cú pháp PHP + `const` khai báo lại + test assert sai → cả 3 job đỏ; file test gọi `exit(0)` lúc nạp (lỗi gốc #82) → PHPUnit đỏ với thông báo "Không có junit.xml — PHPUnit chết trước khi chạy xong?".
- Kiểm branch protection (PR thử #126, đã đóng): CI đỏ nhưng trạng thái gộp là `unstable`, không phải `blocked`; `master` ghi `protected: false`. Kết luận: **chưa có quy tắc bảo vệ nhánh nào bắt buộc CI xanh** (có thể do quy tắc chưa lưu/chưa bật, hoặc gói repo không hỗ trợ). Chủ dự án hoãn việc này; xem #124.

### 6.3 Đang chờ duyệt/gộp

| PR | Nhánh | Nội dung | Trạng thái |
|----|-------|----------|-----------|
| #125 | `fix/w0-followups` | `no-dupe-keys` thành lỗi; `ci_seed.php` chỉ kiểm phân công chính (`is_primary=1`) của admin, không tạo hộ (#94); CI chặn test bị skip (`max_skipped=0`) | Duyệt độc lập (Opus): chấp nhận có điều kiện, điều kiện đã sửa ở `2be60f3`. CI thật trên PR: 3 job xanh (208 test, 741 assertion). Chờ chủ dự án gộp |

### 6.4 Dở dang (đã đẩy lên nhánh, chưa có PR)

| Gói | Nhánh | Tình trạng | Issue |
|-----|-------|-----------|-------|
| P1 rò rỉ dữ liệu, phân quyền | `fix/p1-scope-authz` | Commit WIP `9d55496`; chưa chạy đủ kịch bản, chưa duyệt. Thiết kế: `docs/audit/P1_design.md`. Nhánh cần merge lại `master` mới (có P2/P3/P6/W0) | #78, #97, #83 |
| P5 Web Push | `fix/p5-web-push` | 5 commit (lược đồ token + hàng đợi, allowlist SSRF, gửi sau phản hồi, token subscription, chuông sau commit); còn thiếu unit test, nghiệm thu `push.py`, duyệt. Thiết kế: `docs/audit/P5_design.md`. Cũng cần merge lại `master` | #99, #100, #107 |
| #115 test export | `audit` | Đã viết `tests/unit/ExportApiTest.php` (11 tests). Phát hiện bugs mới: #129 (attendance 503), #130 (attendance-detail 500) | #115, #129, #130 |

### 6.5 Chưa làm / Đang làm (thứ tự đề nghị)

1. ~~**Gộp #125**~~ ✅ ĐÃ GỘP.
2. **#115** (ĐANG LÀM): viết lại `ExportTest` → `tests/unit/ExportApiTest.php` (11 tests). Phát hiện bugs mới: #129 (attendance trả 503), #130 (attendance-detail trả 500 thay vì 403/400).
3. **#123**: bật `failOnRisky`/`failOnWarning` trong `phpunit.xml`; nâng `min_tests` sát 208 và thêm sàn assertion; kiểm từng file có testcase.
4. **P1**: hoàn tất theo `docs/audit/P1_design.md`; chạy `e2e2.py` (SCOPE-01…03), `extra.py` (STAFF-06), `e2e.py` (AUTH-05), `p1_regress.py`; duyệt độc lập bằng phiên khác phiên viết; mở PR. Mặc định đã chốt: chỉ quản trị viên xem `logs`; BĐH không sửa BĐH khác nhưng sửa được định danh của mình; GLV không thấy tài khoản chờ duyệt; danh bạ SĐT giữ cho vai có quyền `staff` (GLV có).
5. **P5**: hoàn tất unit test, nghiệm thu `push.py` (PUSH-20, 21, 22); duyệt độc lập; mở PR. Mặc định đã chốt: gửi bất đồng bộ, chỉ 4 dịch vụ push, tự xoá subscription lỗi, quá độ 30 ngày, giới hạn "Send-test" để P4.
6. **#98** (sau P1): kiểm tra SĐT trong `StaffService`.
7. **P4** (sau P1, cùng sửa `_bootstrap.php`): #84, #102 (APCu, fallback không im lặng), #103, #96, #95, giới hạn tần suất "Send-test", và CSRF cho logout.
8. **P8** (sau P1): #90 kích thước `data.php`.
9. **P7b**: chuẩn hoá `dd/mm/yyyy` toàn web; #108 (tra cứu nhận dd/mm/yyyy, tạm chấp nhận mm/dd/yyyy cũ khi không mơ hồ); #111 (`parseDate` khi nhập Excel). Bước kiểm kê trước đó chưa hoàn tất, cần chạy lại.
10. **P7** (làm cuối, chạm nhiều `views/*.php`): #109 (a11y), #122 (build lại `tailwind.css`, thiếu nhiều lớp đang dùng), #121 (khoá trùng giữa module JS, gồm `init` làm điểm danh ngoại tuyến không tự đồng bộ).
11. **#124**: CI chưa chạy `tests/UnitTest.php` và e2e; 12 khối `<script>` trong 6 file PHP chưa lint; `eslint@9` chưa ghim; **branch protection chưa bật (hoãn theo chủ dự án)**.
12. **#110** (action `attendance` của `export.php`, cột "Tỷ lệ"): cần chủ dự án quyết định giữ hay bỏ action (xem #106).
13. Đề nghị chưa quyết: phân công đã kết thúc mất `class_id` khi xoá lớp (chỉ mở issue nếu chủ dự án muốn).

### 6.6 Việc chủ dự án cần tự làm

- Xoá nhánh tạm `ci-protect-test` trên GitHub (xoá từ máy của tôi bị chặn với HTTP 403).
- Bật branch protection (hoãn): https://github.com/josephvinh/glyphutrung/settings/rules/new?target=branch ; bắt buộc 3 check PHPUnit Tests, PHP Syntax Check, JavaScript Lint. Repo private trên gói miễn phí không hỗ trợ tính năng này.

### 6.7 Ghi chú vận hành khi làm tiếp

- Fable 5.1 không dùng được (hết hạn mức): thiết kế, viết, duyệt P1 đều dùng Opus nên mức độc lập thấp hơn kế hoạch. Nên duyệt P1 bằng phiên khác hẳn phiên viết.
- Mỗi agent dùng DB riêng (`tntt_p1`, `tntt_p5`, ...) và cổng riêng; không dùng `pkill`. MariaDB cục bộ hay bị dừng giữa các phiên: `service mariadb start`.
- Môi trường dựng lại: MariaDB 10.11 cục bộ, `php -S` kèm router; công cụ đã dùng: Playwright, axe-core, PHPStan phar, PHPUnit 11, openpyxl, ffmpeg (camera giả).
- Hai bản thiết kế nằm trong repo: `docs/audit/P1_design.md`, `docs/audit/P5_design.md`.
- Phiên này bị chặn xoá nhánh và không đọc được cài đặt repo; việc cần quyền admin hoặc xoá nhánh phải do chủ dự án làm.
