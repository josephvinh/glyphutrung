# BÀN GIAO CÔNG VIỆC
**Ngày:** 02/10/2026
**Người bàn giao:** Claude Code (Agent)
**Repo:** josephvinh/glyphutrung
**Cập nhật:** 02/10/2026 - Hoàn thành P4, P5, P7b, P8, Responsive

---

## 1. TỔNG QUAN

Đợt kiểm thử (29-30/09/2026) đã xử lý 31 issues (#78-#109). Đến 02/10/2026, đã hoàn thành phần lớn công việc.

### Số liệu
- **PR đã merge:** 21+ PRs vào master
- **Issues đã đóng:** ~40 issues
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
| P5 | #128, #159 | Web Push - SSRF (#99), async (#100), token (#107) |
| #84 | #155 | Chặn spam đăng ký (bỏ register_ok) |
| #95 | #155 | CSP: thêm object-src 'none' |
| #96 | #155 | Login delay: chỉ làm chậm khi có lần sai |
| #102 | #155 | RateLimiter: fallback session, enforce_api_write_limit |
| #103 | #155 | Passkey: sign_count, requireUserVerification |

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
| Responsive | #162 | Landing page, bottom nav, BXH breakpoints, iOS zoom |

### Schema & Deploy
| Issue | PR | Nội dung |
|-------|-----|----------|
| #79, #80 | #112 | Xuất Excel lỗi 500, xoá lớp lỗi 500 |
| #81, #101, #87, #93, #94, #106 | #114 | File debug, thiếu login.min.js, dọn mã chết |

### Tests
| Issue | PR | Nội dung |
|-------|-----|----------|
| #115 | #138 | ExportApiTest 11 tests |

### P7b - Date Format
| Issue | PR | Nội dung |
|-------|-----|----------|
| #108 | #160 | Chuẩn hóa ngày sinh dd/mm/yyyy |

### P8 - Performance
| Issue | PR | Nội dung |
|-------|-----|----------|
| #90 | #161 | Filter classId/programId giảm payload data.php |

---

## 3. HƯỚNG DẪN TIẾP TỤC

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

## 4. FILES QUAN TRỌNG

| File | Mục đích |
|------|----------|
| `KE_HOACH_XU_LY_ISSUES.md` | Kế hoạch chi tiết tất cả issues |
| `.github/workflows/ci.yml` | CI pipeline |
| `phpunit.xml` | PHPUnit config |
| `eslint.config.js` | ESLint config |

---

## 5. GHI CHÚ

- **Auto mode:** Claude Code auto mode chặn `merge --admin`. Cần merge bằng GitHub UI hoặc chờ review.
- **Branch protection:** Đã bật, cần 3 status checks xanh (PHPUnit Tests, PHP Syntax Check, JavaScript Lint).

---

**Ngày cập nhật:** 02/10/2026
**Phiên bản:** 2.0
