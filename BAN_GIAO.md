# BÀN GIAO CÔNG VIỆC
**Ngày:** 02/10/2026
**Người bàn giao:** Claude Code (Agent)
**Repo:** josephvinh/glyphutrung

---

## 1. TỔNG QUAN

Đợt kiểm thử (29-30/09/2026) đã xử lý 31 issues (#78-#109). Đến 02/10/2026, đã hoàn thành phần lớn công việc.

### Số liệu
- **PR đã merge:** 15+ PRs vào master
- **Issues đã đóng:** ~27 issues
- **PR đang mở:** #155 (P4 Security)
- **Branch protection:** Đã bật

---

## 2. ĐÃ HOÀN THÀNH

### CI Infrastructure
| Issue | PR | Nội dung |
|-------|-----|----------|
| #82 | #116, #125 | CI thật - PHPUnit có DB, ESLint đỏ khi có lỗi |
| #123 | #151, #139 | failOnRisky, failOnWarning, min_tests 205, per-file check |
| #124 | #150, #140 | eslint@9.39.5 ghim, npm install --ignore-scripts |

### Security & Authorization
| Issue | PR | Nội dung |
|-------|-----|----------|
| #141 | #149 | Chặn BĐH tác động lên admin/bdh |
| #97 | #142 | Phân quyền data.php scope |
| #83 | #142 | must_change_pw chặn API |
| #78 | #142 | Rò rỉ dữ liệu - lọc theo phạm vi |
| #88 | #120 | Logout chỉ nhận POST, GET trả 405 |
| P5 | #128 | Web Push - SSRF, async, token |
| #84, #95, #96, #102, #103 | #155 | P4 Security: spam register, rate limiter, login delay, CSP, passkey |

### Input Validation
| Issue | PR | Nội dung |
|-------|-----|----------|
| #98 | #148 | Phone validation 10 số, bắt đầu bằng 0 |
| #111 | #147 | parseDate giữ chuỗi ngày sai thay vì xoá |
| #85 | #113 | Điểm danh từ chối buổi tương lai |
| #86 | #113 | Ngày sinh vô lý |

### UI/UX
| Issue | PR | Nội dung |
|-------|-----|----------|
| #121 | #135 | 5 khoá trùng JS (init→initCore, etc.) |
| #109 | #136 | 1000+ vi phạm tương phản text-slate-400→text-slate-500 |
| #122 | #136 | Tailwind rebuild |
| #105 | #119 | iframe/ảnh ẩn không tải undefined |
| #92 | #118 | Kính lúp không đè placeholder |
| #91 | #117 | Xoá khoá trùng libItemIcon |
| #110 | #154 | export attendance: bỏ cột Tỷ lệ, thêm tên chương trình |

### Schema & Deploy
| Issue | PR | Nội dung |
|-------|-----|----------|
| #79, #80 | #112 | Xuất Excel lỗi 500, xoá lớp lỗi 500 |
| #81, #101, #87, #93, #94, #106 | #114 | File debug, thiếu login.min.js, dọn mã chết |

### Tests
| Issue | PR | Nội dung |
|-------|-----|----------|
| #115 | #138 | ExportApiTest 11 tests |

---

## 3. CHƯA HOÀN THÀNH - CẦN LÀM

### 3.1 P8 - Performance data.php (#90)
**Lý do đóng:** #132 conflict

**Yêu cầu:**
- Giảm kích thước data.php (hiện 5.7MB với 600 em)
- Thêm gzip compression
- Lọc attDays, scores theo phạm vi user

**Files liên quan:**
- `public/api/data.php`
- `public/api/_bootstrap.php`

**Cách làm:**
1. Tách nhánh từ origin/master
2. Implement theo `docs/audit/P8_design.md` (nếu có)
3. Test với dataset lớn
4. Mở PR, chờ review

### 3.2 P7b - Date Format dd/mm/yyyy (#108)
**Lý do đóng:** #133 conflict

**Yêu cầu:**
- Chuẩn hoá ngày nhập/hiển thị dd/mm/yyyy
- Tra cứu nhận dd/mm/yyyy
- Tạm chấp nhận mm/dd/yyyy khi không mơ hồ

**Files liên quan:**
- `views/*.php` (nhiều file)
- `public/assets/js/modules/shell.js` (parseDate)
- `public/api/tracuu.php`

**Cách làm:**
1. Tách nhánh từ origin/master
2. Kiểm kê các chỗ hiển thị/nhập ngày
3. Chuẩn hoá theo quyết định: dd/mm/yyyy
4. Test với các format khác nhau
5. Mở PR, chờ review

### 3.3 P4 - Security (#84, #102, #103, #96, #95)
**Lý do đóng:** #134 conflict

**Yêu cầu:**
- #84: Đăng ký spam prevention
- #102: RateLimiter với APCu, fallback không im lặng
- #103: Passkey - bộ đếm chữ ký, xác minh
- #96: Độ trễ đăng nhập sai
- #95: CSP headers

**Files liên quan:**
- `public/api/auth.php`
- `public/api/_bootstrap.php`
- `public/api/StaffService.php`
- `public/index.php`

**Cách làm:**
1. Tách nhánh từ origin/master
2. Implement theo thiết kế bảo mật
3. Test đăng ký, login, rate limiting
4. Mở PR, chờ review (cần Opus review)

---

## 4. ISSUES CÒN LẠI

### Cần chủ dự án quyết định

| # | Issue | Câu hỏi |
|---|-------|---------|
| #110 | export.php "Tỷ lệ" | Giữ hay bỏ cột này? |

### Đề nghị chưa quyết

- Phân công đã kết thúc mất class_id khi xoá lớp

---

## 5. HƯỚNG DẪN TIẾP TỤC

### Quy ước làm việc

1. **Tách nhánh từ origin/master** - không từ nhánh cũ
2. **Một issue = một PR** - không gộp nhiều issue
3. **Hỏi trước khi mở PR** - theo quy ước BAN_GIAO.md
4. **Branch protection đã bật** - cần CI xanh mới merge được

### Commands thường dùng

```bash
# Lấy code mới nhất
git fetch origin master
git checkout -b fix/<issue-name> origin/master

# Sau khi làm xong
git push -u origin fix/<issue-name>

# Tạo PR (hỏi người dùng trước)
gh pr create --repo josephvinh/glyphutrung --title "fix(#N): mô tả"
```

### Kiểm tra CI

```bash
gh pr checks <PR-number> --repo josephvinh/glyphutrung
```

---

## 6. FILES QUAN TRỌNG

| File | Mục đích |
|------|----------|
| `KE_HOACH_XU_LY_ISSUES.md` | Kế hoạch chi tiết tất cả issues |
| `.github/workflows/ci.yml` | CI pipeline |
| `phpunit.xml` | PHPUnit config |
| `eslint.config.js` | ESLint config |

---

## 7. GHI CHÚ

- **Auto mode:** Claude Code auto mode chặn `merge --admin`. Cần merge bằng GitHub UI hoặc chờ review.
- **Branch protection:** Đã bật, cần 3 status checks xanh (PHPUnit Tests, PHP Syntax Check, JavaScript Lint).
- **PRs đã đóng:** P8 (#132), P7b date (#133), P4 security (#134) - cần làm lại với master mới.

---

**Ngày cập nhật:** 01/10/2026  
**Phiên bản:** 1.0
