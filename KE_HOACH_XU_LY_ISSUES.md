# Kế hoạch xử lý các issue sau đợt kiểm thử (29–30/09/2026)

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
