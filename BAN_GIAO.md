# Bàn giao cho agent tiếp theo

Cập nhật: 30/09/2026. Đọc file này trước, rồi đọc `KE_HOACH_XU_LY_ISSUES.md` (mục 6 là tiến độ hiện tại). Repo: `josephvinh/glyphutrung`, nhánh làm việc của bộ tài liệu này: `audit`.

## 1. Bối cảnh trong 5 dòng

- Ứng dụng: quản lý đoàn thiếu nhi TNTT. PHP thuần (`public/api/*.php`, `config/*`), giao diện Alpine.js (`public/assets/js/modules/`, `views/*.php`), MariaDB 10.11.
- Một đợt kiểm thử toàn diện tìm ra lỗi; mỗi lỗi là một issue (#78–#111, sau đó #115, #121–#124). Báo cáo đầy đủ: `BAO_CAO_KIEM_THU.md`.
- Các issue được gom thành gói W0, P1–P8, P7b (xem kế hoạch). Mỗi gói: một nhánh, một PR, một phiên **viết** và một phiên **duyệt độc lập** (phiên khác).
- Đã gộp vào `master`: W0 (CI thật), P2, P3, P6 và 4 issue nhẹ. Đang chờ gộp: PR #125. Dở dang: P1 và P5. Chưa bắt đầu: P4, P7, P7b, P8.
- Quyết định của chủ dự án nằm ở mục 1b của `KE_HOACH_XU_LY_ISSUES.md`. Đừng hỏi lại những điều đã chốt.

## 2. Quy ước làm việc (chủ dự án đã quen, đừng phá)

1. **Không mở PR và không gộp PR nếu chưa được hỏi.** Sửa xong thì đẩy nhánh, báo cáo, hỏi "mở PR không?". Khi chủ dự án đồng ý thì mới mở; chỉ gộp khi họ bảo gộp.
2. **Viết và duyệt phải là hai phiên khác nhau.** Duyệt độc lập phải tự chạy lại phép đo (tái hiện lỗi trên `master`, chứng minh hết lỗi trên nhánh), thử phá code cố ý để xem test có bắt không, rồi mới kết luận ACCEPT / ACCEPT WITH CONDITIONS / REJECT. Điều kiện của người duyệt phải xử lý trước khi mở PR.
3. **Báo cáo trung thực:** test đỏ thì nói đỏ; phần chưa kiểm thì ghi là chưa kiểm; không khẳng định "xong" khi chưa chạy lệnh kiểm chứng.
4. **Chỉ đóng issue bằng `Closes #N` trong PR khi có bằng chứng** (số liệu trước/sau) trong mô tả PR. Mô tả PR theo mẫu `.github/pull_request_template.md`, cuối bài có dòng ghi công của Claude Code theo hướng dẫn phiên.
5. **Sửa tối thiểu đúng phạm vi issue.** Phát hiện lỗi mới ngoài phạm vi thì mở issue riêng, đừng sửa lẫn.
6. **Mỗi agent dùng DB riêng và cổng riêng.** Không dùng `pkill` (từng giết nhầm shell). Dừng server bằng `kill <pid>` lấy từ `ss -ltnp`.
7. **Chỉ chạy kịch bản e2e trên DB thử nghiệm**, không bao giờ trên dữ liệu thật.
8. **Ngôn ngữ:** tiếng Việt, câu ngắn, nói rõ cái gì đã làm và cái gì chưa.

## 3. Việc cần làm tiếp (thứ tự đề nghị)

Chi tiết từng việc ở `KE_HOACH_XU_LY_ISSUES.md` mục 6.5. Tóm tắt:

| # | Việc | Ghi chú |
|---|------|---------|
| 1 | Chờ chủ dự án gộp **PR #125** | đã duyệt độc lập, CI thật xanh |
| 2 | **#115** viết lại `ExportTest` gọi `export.php` qua HTTP; test `accessible_class_ids` cho người bị giới hạn lớp; test 403 của export | cùng hoặc ngay sau P1 |
| 3 | **#123** `failOnRisky`/`failOnWarning`; nâng `min_tests` (hiện 200, thực tế 208); sàn assertion | cần nhánh thử phá cố ý để chứng minh CI đỏ |
| 4 | **P1** (#78, #97, #83): hoàn tất nhánh `fix/p1-scope-authz` theo `docs/audit/P1_design.md` | xem mục 5 |
| 5 | **P5** (#99, #100, #107): hoàn tất nhánh `fix/p5-web-push` theo `docs/audit/P5_design.md` | xem mục 5 |
| 6 | **#98** (sau P1), **P4** (sau P1), **P8** (sau P1) | P4 và P8 cùng sửa `_bootstrap.php`, `data.php` với P1 |
| 7 | **P7b**, **P7** (làm cuối) | P7 chạm nhiều `views/*.php` nên dễ xung đột; gồm #109, #121, #122 |
| 8 | **#124**, **#110** | #110 cần chủ dự án quyết định giữ hay bỏ action `attendance` của `export.php` |

## 4. Dựng môi trường (khoảng 3 lệnh)

Yêu cầu: PHP 8.2+, MariaDB 10.11, Node với Playwright (global ở `/opt/node22/lib/node_modules`), Python 3 (có `openpyxl` nếu chạy kịch bản Excel).

```bash
# 1) MariaDB hay bị dừng giữa các phiên:
service mariadb start

# 2) DB riêng cho agent của bạn (đặt tên DB khác nhau cho mỗi agent, ví dụ tntt_p1).
#    User 'tntt'/'tntt' phải có quyền trên DB đó (cả @localhost và @127.0.0.1):
mysql -uroot -e "CREATE DATABASE tntt_x CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  GRANT ALL ON tntt_x.* TO 'tntt'@'localhost'; GRANT ALL ON tntt_x.* TO 'tntt'@'127.0.0.1'; FLUSH PRIVILEGES;"
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_USER=tntt TNTT_DB_PASS=tntt TNTT_DB_NAME=tntt_x
php config/install.php            # admin: 0901000001 / tntt@2026
php tests/fixtures/ci_seed.php    # (chỉ khi chạy PHPUnit; có trên master sau #116)
TNTT_ALLOW_SEED=1 php config/seed_demo.php   # (chỉ khi cần dữ liệu 600 em cho e2e)

# 3) Server thử, dùng router để tệp không tồn tại trả 404 thật (php -S mặc định trả 200):
php -S 127.0.0.1:8090 -t public docs/audit/router.php &
```

Kịch bản e2e đọc biến môi trường `E2E_BASE` (mặc định `http://127.0.0.1:8088`), `E2E_DB` (mặc định `tntt_e2e`), `E2E_ORIGIN` (cho PWA). Ví dụ:

```bash
E2E_BASE=http://127.0.0.1:8090 E2E_DB=tntt_x python3 tests/e2e/e2e2.py
```

Các kịch bản tự tạo 6 tài khoản thử (SĐT `09110000xx`, mật khẩu `Test@1234`) trong DB. Danh sách kịch bản và gói nghiệm thu tương ứng: `KE_HOACH_XU_LY_ISSUES.md` mục 4; cách chạy lại: `BAO_CAO_KIEM_THU.md` mục 9.

**Dữ liệu demo 600 em:** file dump cũ nằm ở thư mục tạm của phiên trước, **không có trong repo**; hãy dựng lại bằng `seed_demo.php` như trên (cần `TNTT_ALLOW_SEED=1` hoặc DB có chữ `demo` trong tên).

### PHPUnit

- CI dùng PHPUnit 10.5 (PHP 8.2). Trên máy thử, `phar.phpunit.de` có thể bị proxy chặn: cài qua composer (`composer require --dev phpunit/phpunit:^10.5` vào thư mục tạm ngoài repo) rồi chạy `php <đường dẫn>/vendor/bin/phpunit --testsuite "TNTT Unit Tests"`.
- Kỳ vọng hiện tại trên `master` + #125: **208 test, 741 assertion, 0 skip**. CI có sàn ≥ 200 test và chặn test bị skip.
- Test cần DB đã `install.php` + `ci_seed.php`.

### Kiểm CI trên GitHub

Bộ công cụ GitHub MCP chạy được `workflow_dispatch` (`actions_run_trigger`, workflow `ci.yml`, `ref` = tên nhánh), liệt kê job (`actions_list` / `list_workflow_jobs`) và đọc log (`get_job_logs` với `return_content`). Để chứng minh CI biết đỏ: đẩy nhánh tạm có lỗi cố ý, chạy `workflow_dispatch`, đọc kết quả. Đã làm ở run #323 và #324.

## 5. Hai nhánh dở dang: cần biết trước khi làm

Cả hai nhánh tách từ `master` **cũ**, nay lệch nhiều commit so với `master` (đã có P2, P3, P6, W0 và các PR nhẹ). **Việc đầu tiên: merge `origin/master` vào nhánh** (merge, không rebase; nhánh đã đẩy lên remote), giải quyết xung đột rồi chạy lại PHPUnit.

### P1 — `fix/p1-scope-authz` (issue #78, #97, #83)

- Trạng thái: 10 commit, gồm cả commit WIP `9d55496` (đẩy lúc tạm dừng). Chưa chạy đủ kịch bản, chưa duyệt.
- Thiết kế: `docs/audit/P1_design.md` (lọc `data.php` theo phạm vi qua JOIN `enrollments`, `members` rỗng nếu không có quyền `staff`, `logs` chỉ cho quyền `settings`, khoá cache `data_v2_`, `StaffService::guardTarget()` trả 404 khi người không phải admin đụng tới admin, `require_login_pending_pw()` cho `must_change_pw`).
- Mặc định đã chốt: GLV **được** xem danh bạ SĐT toàn bộ nhân sự; không ai ngoài admin xem `logs`; BĐH không sửa BĐH khác nhưng sửa được định danh của chính mình; GLV không thấy tài khoản chờ duyệt.
- Kiểm: `e2e2.py` (SCOPE-01…03), `extra.py` (STAFF-06), `e2e.py` (AUTH-05), `p1_regress.py` (có trên nhánh).
- Nhớ kiểm trước: AUTH-08 (CSRF ở màn đổi mật khẩu bắt buộc).
- **Duyệt bằng phiên khác hẳn phiên viết**: thiết kế, viết và duyệt lần trước đều dùng Opus (Fable hết hạn mức), nên độc lập thấp hơn kế hoạch.

### P5 — `fix/p5-web-push` (issue #99, #100, #107)

- Trạng thái: 5 commit: lược đồ token + hàng đợi (`push_outbox`), allowlist endpoint chống SSRF, gửi sau phản hồi (async) với `curl_multi`, token riêng cho mỗi subscription (băm SHA-256), chuông chỉ gửi sau khi giao dịch commit. Còn thiếu: unit test, nghiệm thu `push.py` (PUSH-20, 21, 22), duyệt.
- Thiết kế: `docs/audit/P5_design.md`.
- Mặc định đã chốt: gửi bất đồng bộ; endpoint lạ trả lỗi chung; chỉ 4 dịch vụ push (FCM, Mozilla, `*.push.apple.com`, `*.notify.windows.com`, cổng 443); tự xoá subscription lỗi; quá độ 30 ngày; giới hạn tần suất "Send-test" để P4.
- Đã biết: không kiểm được gửi tới máy chủ push thật của Google/Apple; cần kiểm thủ công trên thiết bị thật sau P4/P5.

## 6. Bẫy kỹ thuật đã gặp

- `tests/` nằm trong `.gitignore`: khi commit file test phải `git add -f`.
- `php -S` không có router thì trả 200 cho mọi đường dẫn thiếu (kể cả `/error_log`); luôn dùng `docs/audit/router.php`.
- Dùng `git merge`, không `rebase` hay `--force` trên nhánh đã đẩy.
- Test điểm danh QR dùng buổi **hôm nay** (chương trình thử bắt đầu 23:59) và buổi cùng thứ tuần trước, vì server từ chối buổi tương lai (#85). Chạy sát nửa đêm có thể sai.
- Lớp Tailwind dùng trong `views/*.php` phải có trong `public/assets/css/tailwind.css` (biên dịch sẵn, không có quy trình build, xem #122): `pl-10`, `left-3.5`, `h-64`… đang thiếu. Kiểm bằng `grep -c '\.tênlớp{' public/assets/css/tailwind.css`.
- Khoá trùng giữa các module JS (module nạp sau đè module trước) không bị ESLint bắt (xem #121).
- Hook "stop" của môi trường có thể nhắc "untracked files": thư mục `.claude/worktrees/` đã đưa vào `.git/info/exclude`, đừng commit nó.
- Bản chụp DB demo và các tệp tạm của phiên trước nằm ở thư mục tạm (scratchpad), **sẽ mất** khi container bị thu hồi; không phụ thuộc vào chúng.

## 7. Giới hạn của phiên trước (có thể khác ở phiên bạn)

- Không xoá được nhánh trên GitHub (`git push --delete` bị HTTP 403); không đọc hay đổi được cài đặt repo (branch protection, secrets). Đừng vòng qua bằng token lấy từ biến môi trường; hỏi chủ dự án.
- **Branch protection chưa bật** (đã thử bằng PR thử #126: CI đỏ nhưng trạng thái gộp `unstable`). Chủ dự án hoãn việc này. Hệ quả: PR có CI đỏ vẫn gộp được, nên luôn nhắc chủ dự án xem CI trước khi gộp.
- Nhánh tạm `ci-protect-test` còn trên GitHub, chờ chủ dự án xoá.
- Chủ dự án thường ít hạn mức theo phiên 5 giờ: làm việc nhỏ, đẩy nhánh/commit thường xuyên, ghi tiến độ vào `KE_HOACH_XU_LY_ISSUES.md` mục 6 sau mỗi bước để phiên sau đọc được.

## 8. Cách kết thúc một phiên

1. Commit và đẩy mọi thay đổi (nhánh gói + nhánh `audit`).
2. Cập nhật `KE_HOACH_XU_LY_ISSUES.md` mục 6 (đã làm / đang chờ / dở dang / chưa làm) và mục 6.7 nếu có bẫy mới.
3. Báo chủ dự án: cái gì đã xong và có bằng chứng, cái gì chưa kiểm, cần họ quyết định gì.
