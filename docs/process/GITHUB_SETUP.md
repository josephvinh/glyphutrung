# Thiết Lập GitHub Chuẩn Chỉ

> Repo: `josephvinh/glyphutrung` · nhánh mặc định: `master`.
> Tài liệu này là **danh sách việc cần làm một lần** để GitHub trở thành lan can
> thật sự: không ai (người hay agent) push thẳng lên `master`, mọi thay đổi qua
> PR + CI xanh + có người duyệt, và secret không lọt vào repo.
>
> Phần lớn thao tác cần quyền **admin** của repo. Mỗi mục ghi **đường trong UI**.

---

## ⚠️ 0. Việc quan trọng nhất: cân nhắc chuyển repo sang PRIVATE

Hiện repo **công khai** (`private: false`). Đây là ứng dụng quản lý **hồ sơ trẻ
em** (tên, ngày sinh, địa chỉ, SĐT phụ huynh). Mã nguồn công khai còn làm lộ:
cấu trúc khóa cache (xem S1), tên DB, và từng có khóa VAPID + `error_log` trong
lịch sử (S2).

- **Khuyến nghị:** Settings → chung → "Danger Zone" → **Change visibility →
  Private**, trừ khi có lý do rõ ràng phải mở mã.
- Nếu **giữ công khai**: bắt buộc xử lý xong S1 + S2 trong SECURITY_AUDIT (xoay
  secret, đưa cache ra ngoài web root) trước, và coi mọi thứ từng commit là đã lộ.

> Secret scanning + push protection **miễn phí cho repo công khai**; với repo
> private cần bật riêng (mục 6).

---

## 1. Bảo vệ nhánh `master` (Branch protection / Ruleset)

Settings → **Rules → Rulesets → New branch ruleset** (hoặc Branches → Add rule).
Target: `master`. Bật:

- ☑ **Require a pull request before merging**
  - Required approvals: **1** (tối thiểu; người duyệt ≠ người mở PR).
  - ☑ Dismiss stale approvals khi có push mới.
  - ☑ Require review từ **Code Owners** (xem mục 3).
  - ☑ Require conversation resolution (mọi comment phải được giải quyết).
- ☑ **Require status checks to pass** — chọn các check bắt buộc (mục 4):
  - `PHPUnit Tests`, `PHP Syntax Check`, `JavaScript Lint`
  - (sau khi thêm) `Module Merge Check`, `Browser Smoke`, `Secret Scan`
  - ☑ Require branches **up to date** trước khi merge.
- ☑ **Block force pushes**.
- ☑ **Restrict deletions**.
- ☑ **Require linear history** (đi cùng merge squash — mục 5).
- (Khuyến nghị) ☑ Require signed commits.
- **Không** cho bỏ qua (bypass) với bất kỳ ai, kể cả admin — hoặc chỉ cho một
  danh sách bypass rất hẹp. (Lưu ý: "auto mode" của Claude Code **chặn**
  `merge --admin`; merge qua UI sau khi CI xanh.)

> BAN_GIAO.md nói branch protection "đã bật" với 3 check — rà lại cho khớp danh
> sách trên và bổ sung các check mới khi đã thêm vào CI.

---

## 2. Quyền cộng tác viên

Settings → **Collaborators and teams**:

- Cấp quyền tối thiểu cần thiết (Write cho người code; Admin chỉ cho người quản
  trị repo).
- Không ai cần "push thẳng master" vì mọi thay đổi qua PR.

---

## 3. CODEOWNERS

Tạo `.github/CODEOWNERS` để tự động gắn người duyệt cho vùng nhạy cảm:

```
# Mặc định
*                       @josephvinh

# Vùng bảo mật / phân quyền — cần người am hiểu duyệt
/public/api/_bootstrap.php     @josephvinh
/public/api/_common.php        @josephvinh
/public/api/auth.php           @josephvinh
/public/api/passkey.php        @josephvinh
/config/                       @josephvinh
/.github/                      @josephvinh
/docs/security/                @josephvinh
```

Thay/đổi thêm owner khi có thêm người. Có CODEOWNERS + "Require review from Code
Owners" nghĩa là đụng các file trên **bắt buộc** đúng người duyệt.

---

## 4. CI — các job bắt buộc

File: `.github/workflows/ci.yml`. Hiện có 3 job (đều đã "thật", đỏ khi có lỗi):

| Job | Kiểm |
|-----|------|
| **PHPUnit Tests** | MariaDB 10.11 + `install.php` + `ci_seed.php` + PHPUnit; có sàn số test, chặn test skip, kiểm mỗi file có testcase |
| **PHP Syntax Check** | `php -l` mọi file |
| **JavaScript Lint** | `eslint@9.39.5` (ghim phiên bản) |

**Cần thêm** (biến khoảng trống #194 thành không thể lọt — xem TESTING.md):

- **Module Merge Check** — `node build/check_module_merge.cjs` (bắt trùng tên mảnh JS).
- **Browser Smoke** — dựng MariaDB + `php -S` + `node tests/e2e/smoke.js`; đỏ nếu
  có `pageerror`/`console.error` hoặc điều hướng sai. Chromium: dùng
  `browser-actions/setup-chrome` hoặc `npx playwright install --with-deps chromium`.
- **Secret Scan** — gitleaks (mục 6).

Sau khi thêm, đưa tên job vào danh sách "Require status checks" ở mục 1.

Gợi ý chất lượng CI: bật cache npm (`actions/setup-node` với `cache: npm`), chạy
các job song song (đã vậy), đặt `timeout-minutes` cho mỗi job.

---

## 5. Chiến lược merge

Settings → General → **Pull Requests**:

- ☑ Allow **squash merging** → đặt làm mặc định. Mỗi PR = 1 commit trên `master`,
  lịch sử sạch, dễ revert.
- ☐ Tắt "Allow merge commits" và "Allow rebase merging" (giữ một lối duy nhất).
- ☑ "Automatically delete head branches" sau khi merge.
- ☑ "Always suggest updating pull request branches".

---

## 6. Quét secret & phụ thuộc

### Secret scanning (GitHub native)
Settings → **Code security**:
- ☑ Secret scanning + ☑ **Push protection** (chặn commit chứa secret ngay khi push).
- Miễn phí cho repo công khai; repo private cần GitHub Advanced Security.

### gitleaks trong CI (chạy ở mọi repo, kể cả private)
Thêm job quét cả diff lẫn lịch sử:

```yaml
  secret-scan:
    name: Secret Scan
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: gitleaks/gitleaks-action@v2
        env: { GITLEAKS_VERSION: latest }
```

### Dependabot
Tạo `.github/dependabot.yml` (npm devDeps: esbuild, playwright, eslint… cần cập
nhật vá lỗi):

```yaml
version: 2
updates:
  - package-ecosystem: npm
    directory: "/"
    schedule: { interval: weekly }
    open-pull-requests-limit: 5
  - package-ecosystem: github-actions
    directory: "/"
    schedule: { interval: weekly }
```

Settings → Code security → bật **Dependabot alerts** + **security updates**.

> Lưu ý: vendor JS trong `public/assets/js/vendor/` là bản **tự nhúng** (không qua
> npm) — Dependabot không theo dõi được. Khi nâng, cập nhật thủ công và ghi phiên
> bản (xem TESTING.md mục vendor). Hiện các bản đều đã vá.

---

## 7. Templates (đã có trong repo — giữ khớp)

- Issue: `.github/ISSUE_TEMPLATE/feature.md`, `bug.md`.
- PR: `.github/pull_request_template.md` — có checklist (tests pass, console sạch,
  không breaking, security nếu có nhãn). Khi quy trình đổi, cập nhật template cho
  khớp `FEATURE_WORKFLOW.md`/`TESTING.md`.

---

## 8. Nhãn (Labels)

Settings → Labels. Bộ tối thiểu:

- Loại: `feature`, `bug`, `refactor`, `docs`, `ui/ux`, `performance`, `security`.
- Ưu tiên: `priority-high`, `priority-low`.
- Trạng thái: `blocked`, `needs-review`, `wontfix`.

Nhãn `security` nên kích hoạt bước Security bắt buộc (quy ước trong
`FEATURE_WORKFLOW.md`).

---

## 9. Quy ước nhánh & vòng đời PR (tóm tắt)

```bash
git fetch origin master
git checkout -b feat/<slug> origin/master     # LUÔN từ origin/master
# ... code + tự kiểm (TESTING.md) ...
git push -u origin feat/<slug>
# Mở PR (chỉ khi được yêu cầu) → CI xanh → người khác duyệt → Squash & merge
```

- **PR đã merge không tái sử dụng.** Việc tiếp theo = nhánh mới từ `origin/master`.
- Nếu nhánh còn commit chưa merge mà `master` đã tiến: **rebase** lên base mới
  (đừng xóa commit chưa merge).
- Không force-push lên nhánh người khác.

---

## 10. Triển khai lên host (và sửa bẫy `.gitignore`)

Host production lấy code bằng `git pull`. Các file "chỉ dành cho phát triển"
(build/, tests/, docs/, phpunit.xml, package*.json…) **không cần** trên host,
nhưng cách loại chúng **không phải** bằng `.gitignore`.

### Bẫy hiện tại
`.gitignore` đang liệt kê `tests/`, `docs/`, `build/`, `package.json`,
`tsconfig.json`, `CLAUDE.md`… với ý "không lên host". Nhưng các file này **đang
được track** → hậu quả thật là: **file MỚI** trong các thư mục đó (test mới, tài
liệu mới như chính các file này, CLAUDE.md) bị git **bỏ qua âm thầm**, không bao
giờ được `git add`. Đó là lỗi, không phải tính năng.

### Cách đúng
1. **Bỏ** các mục dev-only khỏi `.gitignore` để chúng được track bình thường.
   `.gitignore` chỉ nên chứa: `node_modules/`, bundle minified, `config/config.local.php`,
   `public/cache/*`, logs/`error_log`, `.phpunit.result.cache`, `scratch/`,
   `test-results/`, `playwright-report/`, `phpunit10.phar`, `.claude/settings.local.json`,
   và rác thư mục làm việc.
2. **Loại dev-only ở bước DEPLOY**, không phải ở git. Ví dụ dùng `rsync` với
   `--exclude` khi đẩy lên host:
   ```bash
   rsync -az --delete \
     --exclude='.git/' --exclude='tests/' --exclude='docs/' --exclude='build/' \
     --exclude='node_modules/' --exclude='.github/' --exclude='*.md' \
     --exclude='package*.json' --exclude='tsconfig.json' --exclude='phpunit.xml' \
     ./ user@host:/path/to/app/
   ```
   Hoặc nếu host `git pull` trực tiếp: để nguyên (các file dev vô hại trên host
   nếu web root là `public/` và `.htaccess` chặn `.md`).
3. Đưa `config/cache` (sau khi dời khỏi `public/`, xem S1) và `storage/` vào vùng
   ghi được, ngoài web root.

> Thao tác sửa `.gitignore` là **thay đổi code** → làm trong một PR `chore/`
> riêng kèm `git rm --cached` cho các file đang bị ignore-nhưng-track, không trộn
> vào PR tính năng.

---

## 11. Checklist thiết lập (tick một lần)

- [ ] Quyết định visibility (khuyến nghị Private) — hoặc đã xử lý S1+S2 nếu giữ public.
- [ ] Ruleset bảo vệ `master`: PR bắt buộc, 1 duyệt, CODEOWNERS, CI bắt buộc,
      chặn force-push, linear history.
- [ ] `.github/CODEOWNERS` đã tạo.
- [ ] CI thêm job Module Merge + Browser Smoke + Secret Scan; đưa vào required checks.
- [ ] Merge: chỉ Squash, tự xóa nhánh sau merge.
- [ ] Secret scanning + push protection bật; gitleaks trong CI.
- [ ] `.github/dependabot.yml` + Dependabot alerts/security updates.
- [ ] Nhãn chuẩn; `security` kích hoạt bước Security.
- [ ] `.gitignore` sửa đúng (PR riêng) + quy trình deploy loại dev-only.

---
_Cập nhật khi đổi cấu hình repo hoặc thêm job CI._
