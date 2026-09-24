# KẾ HOẠCH REVIEW TOÀN DIỆN — Web TNTT (glyphutrung)

> Tài liệu này lập kế hoạch rà soát lại **toàn bộ** website: bảo mật, backend, frontend,
> cơ sở dữ liệu, kiểm thử/CI, hiệu năng, UX và vệ sinh mã nguồn. Mỗi đợt (Đợt A→I)
> có checklist cụ thể, file trọng điểm và cách chạy subagent theo `CLAUDE.md`.
>
> Ngày lập: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

---

## 1. Mục tiêu & phạm vi

- **Mục tiêu**: xác định rủi ro bảo mật, lỗi logic, nợ kỹ thuật và điểm cần tối ưu trên toàn bộ web, rồi xếp thứ tự ưu tiên để xử lý.
- **Phạm vi**: `public/` (API + assets), `views/`, `config/`, `src/`, `tests/`, `.github/`, và vệ sinh repo (file thừa/nhạy cảm).
- **Kết quả bàn giao**: mỗi đợt cho ra một báo cáo ngắn (`docs/review/<đợt>.md`) gồm: phát hiện, mức độ (Cao/Trung/Thấp), file:dòng, đề xuất sửa. Các lỗi Cao gom vào một danh sách "phải sửa trước khi deploy".

## 2. Tổng quan hệ thống (đã khảo sát)

| Lớp | Công nghệ | Ghi chú |
|-----|-----------|---------|
| Backend | PHP 8.2, PDO/MySQL, kiểu thủ tục + vài service | 29 endpoint trong `public/api/` (~5.800 dòng) |
| Xác thực | Session + mật khẩu, **WebAuthn/passkey** | `auth.php`, `passkey.php`, `public/api/webauthn/` |
| Frontend | Alpine.js + module ESM tự viết | 26 module JS (~7.200 dòng), `core.js` lớn nhất (937 dòng) |
| Build | esbuild minify, tối ưu ảnh (sharp) | `build/minify.cjs`, `npm run build` |
| PWA | Web Push (VAPID), Service Worker | `config/push.php`, `modules/push.js` |
| CSDL | MySQL, `schema.sql` + ~15 script migrate | `config/migrations/`, nhiều `config/migrate_*.php` |
| CI | PHPUnit 10 + `php -l` + ESLint | `.github/workflows/ci.yml` |
| Nghiệp vụ | Điểm danh, thiếu nhi, nhân sự/tổ chức, điểm & thi đua, báo cáo, thư viện, nghỉ phép, lên lớp, thông báo, lịch | 30+ `views/module_*.php` |

## 3. Các đợt review

> Ưu tiên đọc trước: `public/api/_common.php`, `_bootstrap.php`, `_bootstrap_page.php`, `config/db.php` — vì mọi endpoint đều đi qua đây.

### Đợt A — Bảo mật ứng dụng (ƯU TIÊN CAO NHẤT) 🔴
Subagent gợi ý: `@bao-mat` / `chuyen-gia-toi-uu` (opus)

- [ ] **Phân quyền (RBAC) & phạm vi (scope)**: kiểm mọi endpoint có kiểm quyền backend, không chỉ ẩn UI. Đối chiếu `_common.php` (`can_see_admin` ghi rõ chỉ là "che giấu", không thay kiểm soát), `PermissionHardeningTest.php`, `ScopeTest.php`. Tìm endpoint thiếu kiểm quyền (IDOR: sửa/xem dữ liệu ngoài phạm vi lớp/ngành).
- [ ] **SQL Injection**: xác nhận toàn bộ truy vấn dùng prepared statement; soi chỗ nối chuỗi vào SQL (nhất là `data.php`, `export.php`, `reports.php`, `stats`).
- [ ] **XSS**: soi output ra HTML trong `views/module_*.php` và render phía JS (`innerHTML`) — có escape đầy đủ chưa. CSP hiện phải mở `unsafe-inline`/`unsafe-eval` (xem chú thích trong `_common.php`).
- [ ] **CSRF**: xác nhận `csrf.php` phủ mọi endpoint ghi (POST/PUT/DELETE), token gắn đúng.
- [ ] **Xác thực/phiên**: chính sách mật khẩu (`config/password.php`, `default_password`), buộc đổi mật khẩu lần đầu (`must_change_pw`), chống dò mật khẩu (rate limit/lockout ở `auth.php`), cấu hình cookie phiên.
- [ ] **WebAuthn/passkey**: rà `public/api/webauthn/*` — xác minh challenge, origin, chống replay; kiểm việc lưu credential.
- [ ] **Upload & file**: `library.php`, `library_file.php`, `_library.php` — kiểm loại file, đường dẫn (path traversal), giới hạn dung lượng, phục vụ file có kiểm quyền.
- [ ] **Header bảo mật**: đánh giá CSP/HSTS trong `_common.php` — có thể siết `unsafe-inline` bằng nonce không.

### Đợt B — Vệ sinh repo & bí mật (ƯU TIÊN CAO) 🔴
> Đã phát hiện ngay trong khảo sát, cần xử lý sớm:

- [ ] **Secret bị commit** trong `config/config.php`: khoá **VAPID private** (`push.private`), `setup_key`, `default_password`. → Cần **thu hồi/đổi khoá VAPID**, chuyển toàn bộ secret sang `config/config.local.php` (đã có cơ chế, đã ignore), để `config.php` chỉ còn giá trị rỗng/placeholder.
- [ ] **~6.400 file rác** trong `scratch/review/profile-admin/` (nguyên một profile Chromium: `.db`, `.log`, `.old`, cả `Cookies`) bị track. → Xoá khỏi git, thêm `scratch/` vào `.gitignore`.
- [ ] File thừa/nhạy cảm khác đang track: `cookies.txt`, `error_log`, `public/error_log`, `public/api/error_log`, `phpunit10.phar` (~5MB), thư mục lạ `NGOC VINH/`, `[working-dir] NGOC VINH/`, file rỗng `Enter`. → Đánh giá xoá/ignore từng cái.
- [ ] Rà soát toàn bộ 177 file `*.old` và 70 file `*.db` đang track — phần lớn nên loại khỏi repo.
- [ ] Cân nhắc chạy lịch sử git để đảm bảo secret đã lộ được coi là "đã lộ" (xoay khoá), không chỉ xoá ở HEAD.

### Đợt C — Backend PHP (API, nghiệp vụ, CSDL truy cập)
Subagent gợi ý: `@phan-tich` + `@lap-trinh`

- [ ] Nhất quán xử lý lỗi & mã HTTP giữa các endpoint; định dạng JSON trả về thống nhất.
- [ ] Kiểm chứng đầu vào (validation) cho mọi tham số; ép kiểu số/ngày.
- [ ] Rà logic nghiệp vụ trọng yếu: điểm danh & giờ chốt (`attendance.php`, `check_times.php`, `config['cutoff_minutes']`), tính điểm/thi đua (`scores.php`, `ThiDuaTest`, `config/thi_dua.php`), lên lớp (`promotion.php`, `pass_score`/`pass_attendance`), gán nhân sự & kiêm nhiệm (`assignments.php`, `AssignmentTest`).
- [ ] `data.php` (424 dòng) và `export.php` (399 dòng): tách nhỏ, kiểm quyền theo scope, hiệu năng truy vấn.
- [ ] Múi giờ: `test_timezone.php`, `test_timezone` — xác nhận nhất quán Asia/Ho_Chi_Minh toàn hệ thống.
- [ ] Trùng lặp mã: các service (`OrgService`, `StaffService`) vs endpoint — gom logic dùng chung.

### Đợt D — Frontend JS & giao diện
Subagent gợi ý: `@phan-tich` + `@toi-uu`

- [ ] `core.js` (937 dòng) & `shell.js`: tách trách nhiệm, kiểm quản lý state Alpine, rò rỉ listener.
- [ ] Nhất quán gọi API & xử lý lỗi (toast) sau khi đã thay `alert()/confirm()` (commit #15) — soát còn sót `alert/confirm` gốc không.
- [ ] `qrscan.js` (439 dòng) & `attendance.js` (429 dòng): xử lý camera/permission, huỷ stream khi rời trang, tốc độ quét hàng loạt.
- [ ] Escape dữ liệu khi render (chống XSS phía client), tránh `innerHTML` với dữ liệu người dùng.
- [ ] Kích thước bundle & lazy-load module theo route; kiểm `bundle.php` nối source khi thiếu file min.

### Đợt E — Cơ sở dữ liệu & migrations
- [ ] Đối chiếu `config/schema.sql` với `ylcqukhi_glyphutrung_updated.sql` và các `migrate_*.php` — có khớp không, thứ tự chạy rõ ràng chưa (`config/migrations/index.php`).
- [ ] Index cho cột hay lọc/join (member_id, class_id, block_id, ngày điểm danh) để tối ưu báo cáo.
- [ ] Ràng buộc khoá ngoại & xoá mềm (`docs/xoa-thieu-nhi-giu-thanh-vien.sql`) hoạt động đúng.
- [ ] Charset/collation utf8mb4 đồng nhất.

### Đợt F — Kiểm thử & CI/CD
Subagent gợi ý: `@kiem-thu`

- [ ] **CI hiện quá lỏng**: bước ESLint và `php -l` đều kết thúc bằng `|| true` → không chặn lỗi. Đề xuất cho fail thật sự; thêm ESLint config.
- [ ] Mở rộng test: hiện chỉ có unit (`tests/unit/`, 7 file). Bổ sung test cho `attendance`, `export`, `promotion`, và các nhánh phân quyền còn thiếu.
- [ ] CI chỉ chạy trên `master/main` — xác nhận nhánh mặc định để PR được kiểm.
- [ ] Cân nhắc bỏ `phpunit10.phar` khỏi repo, để CI tự tải (đã tải trong workflow).
- [ ] `deploy.yml` + `.cpanel.yml`: rà quy trình deploy AZDIGI, không lộ secret trong log.

### Đợt G — Hiệu năng & tối ưu
Subagent gợi ý: `@toi-uu` (opus)

- [ ] Truy vấn N+1 (xem `docs/giai-thich-n1-va-router.md`) trong báo cáo/thống kê.
- [ ] Cache: `cache.php`, `public/cache/*` — chiến lược & vô hiệu hoá cache.
- [ ] Tối ưu ảnh (`build/optimize_images.cjs`) và tải asset (font, icon lucide).
- [ ] Thời gian phản hồi các trang nặng: `stats`, `reports`, `data.php`.

### Đợt H — UX / Trải nghiệm & khả năng tiếp cận
- [ ] Luồng chính trên mobile (bottom nav): điểm danh, tra cứu thiếu nhi, báo cáo.
- [ ] Thông báo lỗi/thành công rõ ràng (toast/modal), trạng thái loading (`partial_heavy_loading.php`).
- [ ] Nhất quán ngôn ngữ/nhãn tiếng Việt, khả năng in ấn (`print.php`, thẻ QR).

### Đợt I — Tài liệu & bảo trì
- [ ] Cập nhật `docs/HANDOFF.md`, `SPEC_VA_PHAN_QUYEN.md` khớp mã hiện tại.
- [ ] `CHANGES_SUMMARY.md` phản ánh trạng thái mới nhất.
- [ ] Chuẩn hoá hướng dẫn deploy (`DEPLOY-AZDIGI.md`, `DEPLOY-THU-CONG.md`).

## 4. Thứ tự ưu tiên & lịch trình đề xuất

| Giai đoạn | Đợt | Lý do |
|-----------|-----|-------|
| **Sprint 1 (gấp)** | B → A | Xử lý secret lộ + file rác trước; rồi rà bảo mật ứng dụng. |
| **Sprint 2** | C → E | Backend & CSDL — nền tảng nghiệp vụ. |
| **Sprint 3** | F → G | Siết CI, thêm test, tối ưu hiệu năng. |
| **Sprint 4** | D → H → I | Frontend, UX, tài liệu. |

Nguyên tắc: mỗi đợt kết thúc bằng báo cáo + danh sách việc sửa được đánh mức độ. Lỗi **Cao** phải xử lý trước khi lên production.

## 5. Cách thực hiện (theo CLAUDE.md)

Chạy song song 4 subagent cho các đợt độc lập, ví dụ:

```
@subagent  description: "Rà bảo mật toàn API"    prompt: "Thực hiện Đợt A trong docs/KE-HOACH-REVIEW-TOAN-DIEN.md, xuất docs/review/A-bao-mat.md"
@subagent  description: "Vệ sinh repo & secret"  prompt: "Thực hiện Đợt B, đề xuất patch .gitignore + danh sách file cần xoá khỏi git"
@subagent  description: "Rà backend PHP"          prompt: "Thực hiện Đợt C"
@subagent  description: "Rà frontend JS"          prompt: "Thực hiện Đợt D"
```

## 6. Tiêu chí hoàn thành

- [ ] Mọi đợt có báo cáo trong `docs/review/`.
- [ ] Không còn secret trong mã nguồn; khoá đã lộ đã được xoay.
- [ ] Repo sạch file rác; `.gitignore` bao phủ.
- [ ] CI chặn được lỗi thật (không còn `|| true`).
- [ ] Danh sách lỗi **Cao** = 0 trước khi deploy.

---
*Kế hoạch — chưa thực hiện thay đổi mã. Bước tiếp theo do bạn quyết định đợt nào chạy trước.*
