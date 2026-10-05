# Quy Trình Phát Triển Một Tính Năng Mới

> Mục tiêu: mỗi tính năng đi từ ý tưởng tới `master` theo một đường **có lan
> can** — lỗi lộ ra sớm (CI, smoke test trình duyệt), không ai tự ý nới phạm vi,
> và mỗi thay đổi có thể lần ngược được. Quy trình này áp dụng cho cả người lẫn
> agent (Claude). Phần dành riêng cho agent: xem `docs/process/AGENT_RULES.md`.

Tài liệu này nói **làm thế nào**. Phần **kiểm thử** chi tiết ở
`docs/process/TESTING.md`; phần **GitHub** (nhánh, bảo vệ, PR) ở
`docs/process/GITHUB_SETUP.md`.

---

## 0. Nguyên tắc nền (đọc một lần, nhớ mãi)

1. **Một thay đổi = một mục đích = một PR.** Không gộp "sửa điều hướng + đổi UI +
   dọn code" vào một PR. (Chính kiểu gộp đó đã làm PR #194 xóa nhầm hàm
   `normalizeText` mà không ai thấy.)
2. **Xóa code phải cố ý và truy được.** Trước khi xóa một hàm/biến, `grep` toàn
   repo mọi nơi gọi tới. Mô tả PR nói rõ "đã xóa X vì Y, đã kiểm N nơi dùng".
3. **Không có lỗi âm thầm.** Không `INSERT IGNORE`/`try{}catch(bỏ qua)` cho dữ
   liệu quan trọng (seed quyền, migration). Lỗi phải nổi lên, không bị nuốt.
4. **Nguồn sự thật là một.** Thêm module mới chỉ khai ở `asset_manifest.php`;
   trạng thái màn hình chỉ do một hàm đổi; phạm vi dữ liệu chỉ tính một chỗ.
5. **Chứng cứ trước khi nói xong.** "Chạy được" nghĩa là đã chạy test + mở app
   thật và dán kết quả. Xem `superpowers: verification-before-completion`.

---

## 1. Bức tranh tổng thể

```
Ý tưởng / Issue
      │
      ▼
[1] Làm rõ & thiết kế  ──► SPEC ngắn trong issue   (agent: tntt-analyzer)
      │
      ▼
[2] Tách nhánh từ origin/master
      │
      ▼
[3] TDD: viết test đỏ trước ──► implement cho xanh  (agent: tntt-tester → tntt-coder)
      │
      ▼
[4] Tự kiểm: lint + phpunit + smoke test trình duyệt (bắt buộc)
      │
      ▼
[5] Review (người/agent tntt-reviewer) + Security nếu đụng quyền/dữ liệu nhạy cảm
      │
      ▼
[6] Mở PR ──► CI xanh ──► người duyệt ──► merge (squash)
      │
      ▼
[7] Triển khai lên host + theo dõi
```

Các bước **không** bao giờ bỏ: [4] (tự kiểm) và CI xanh ở [6]. Mọi bước khác co
giãn theo kích cỡ thay đổi.

---

## 2. Chi tiết từng bước

### [1] Làm rõ & thiết kế

- Tạo issue theo mẫu (`.github/ISSUE_TEMPLATE/feature.md`). Trả lời được:
  - **Ai dùng, giải quyết việc gì?** (vai nào: GLV, Trưởng khối, BĐH, phụ huynh…)
  - **Đụng tới dữ liệu cá nhân / phân quyền không?** Nếu có → bắt buộc có bước
    Security ở [5] và test phân quyền ở [4].
  - **Màn hình nào, API nào, bảng nào?**
  - **Tiêu chí hoàn thành (acceptance)** + **ca test cần phủ**.
- Với việc lớn/mơ hồ: viết SPEC ngắn ngay trong issue (hoặc `docs/` nếu dài).
  Dùng skill `brainstorming` trước khi code.
- **Agent:** giao bước này cho `tntt-analyzer` (Opus). Output là SPEC + danh sách
  file sẽ đụng + ca test — **không** code.

### [2] Tách nhánh

```bash
git fetch origin master
git checkout -b feat/<slug-ngan> origin/master     # LUÔN từ origin/master, không từ nhánh cũ
```

Quy ước tên nhánh: `feat/…`, `fix/…`, `refactor/…`, `docs/…`, `perf/…`, `security/…`.
Nếu PR cũ của nhánh này đã merge, **đừng** chồng commit mới lên — tạo lại nhánh
từ `origin/master` (xem `docs/process/GITHUB_SETUP.md`).

### [3] TDD — test đỏ trước, code cho xanh

Thứ tự (skill `test-driven-development`):

1. **Viết test thất bại** mô tả hành vi mong muốn. Chỗ đặt:
   - Logic backend thuần (quyền, tính toán, validate) → `tests/unit/XxxTest.php`
     (PHPUnit; CI chỉ chạy `tests/unit/`, **không** chạy test ngoài thư mục này).
   - Luồng trình duyệt → một ca trong smoke test Playwright (xem TESTING.md).
2. **Chạy, thấy đỏ** (đỏ vì lý do đúng, không phải lỗi cú pháp).
3. **Implement tối thiểu** cho xanh. Theo lối viết của file xung quanh (comment,
   đặt tên, idiom). Dữ liệu vào từ client luôn coi là **không tin cậy**.
4. **Refactor** khi đã xanh.

Quy tắc đặc thù repo khi code:

- **Backend (PHP):** mọi endpoint ghi bắt đầu bằng `require_write()` (POST+CSRF+
  rate-limit) rồi `require_permission(module, 'edit')`. Thao tác theo-đối-tượng
  (một em/lớp/khối) **phải** gọi thêm `can_access_class()`/`can_manage_*()` —
  `require_permission` chỉ là cổng theo module, không xét phạm vi.
- **Frontend (JS):** mỗi màn là một mảnh trong `public/assets/js/modules/`, gộp
  vào component Alpine `tnttApp`. **Không** đặt trùng tên thuộc tính/hàm giữa các
  mảnh — mảnh sau sẽ đè mảnh trước một cách âm thầm (xem bẫy #194). Thêm module
  mới: khai **một chỗ** ở `public/assets/asset_manifest.php`.
- **Schema:** đổi cấu trúc DB qua `config/migrations/NNN_*.sql` (đánh số tăng).
  Seed dữ liệu khởi tạo (quyền, module) đặt trong `install.php` và **không dùng
  `INSERT IGNORE`** cho ràng buộc có thể thất bại thật (vd khóa ngoại) — xem lỗi
  quyền `thu_vien` bị nuốt trong review giao diện.

### [4] Tự kiểm (BẮT BUỘC, trước khi mở PR)

Chạy đúng những gì CI sẽ chạy, cộng smoke test trình duyệt mà CI (hiện tại) chưa
có. Lệnh cụ thể: xem `docs/process/TESTING.md`. Tối thiểu:

```bash
# 1. Lint JS (y hệt CI)
npx --yes eslint@9.39.5 public/assets/js/ public/sw.js

# 2. Cú pháp PHP toàn repo
find . -name '*.php' -not -path './vendor/*' -not -path './node_modules/*' \
  -not -path './.claude/*' -print0 | xargs -0 -n1 php -l

# 3. PHPUnit trên DB thật (MariaDB) — xem TESTING.md để dựng DB
php phpunit.phar --testsuite "TNTT Unit Tests"

# 4. Smoke test trình duyệt (bắt mọi pageerror/console.error) — xem TESTING.md
```

**Không** mở PR khi bất kỳ bước nào đỏ. Nếu thay đổi có giao diện: kèm ảnh chụp
màn hình (trước/sau).

### [5] Review + Security

- **Review (người hoặc `tntt-reviewer`/Opus):** đọc diff theo
  `docs/process/TESTING.md` → "Checklist review". Đặc biệt đọc kỹ **những dòng bị
  xóa**.
- **Security (`tntt-security`/Opus):** bắt buộc khi thay đổi đụng: đăng nhập/
  phiên, phân quyền, dữ liệu cá nhân thiếu nhi/nhân sự, endpoint công khai
  (`somoc*`, `tracuu`, `bxh`, `push`, `bible`), upload, hoặc cache. Dùng checklist
  trong `docs/security/SECURITY_AUDIT.md` (phần "điểm đã kiểm ổn" là baseline).
- Dùng skill `requesting-code-review` / `receiving-code-review` để trao đổi phản
  hồi một cách có kỹ thuật (không đồng ý mù quáng, không bỏ qua phản hồi đúng).

### [6] Mở PR → CI → merge

- PR theo mẫu `.github/pull_request_template.md`. Mô tả: **cái gì + vì sao**, cách
  test, ảnh chụp nếu có UI, và liên kết issue.
- Chờ **CI xanh** (PHPUnit, PHP Syntax, ESLint — và smoke test Playwright sau khi
  thêm vào CI). Branch protection chặn merge khi đỏ (xem GITHUB_SETUP.md).
- Người **khác** duyệt (không tự duyệt PR của mình). Merge **squash** để lịch sử
  `master` sạch, mỗi PR một commit.
- **Không** tạo PR trừ khi được yêu cầu rõ (quy ước repo + AGENT_RULES.md).

### [7] Triển khai & theo dõi

- Host production nhận code qua `git pull` (các mục dev-only được loại ở bước
  deploy, không phải bằng `.gitignore` — xem GITHUB_SETUP.md → "Triển khai").
- Sau khi cập nhật code có đổi schema: chạy `php config/install.php` (idempotent)
  hoặc migration tương ứng trên host.
- Thêm giám sát lỗi runtime phía client (xem khuyến nghị ở review giao diện:
  `window.onerror` gửi về endpoint log) để lỗi kiểu #194 lộ ra trong vài phút.

---

## 3. Dùng subagent (Claude) cho một tính năng

Repo đã định nghĩa sẵn các subagent trong `.claude/agents/` (gọi qua công cụ
`Agent`, `subagent_type: "tntt-..."`). **Chỉ dùng khi người dùng yêu cầu dùng
subagent** (xem AGENT_RULES.md — không tự ý spawn).

| Bước | Subagent | Model | Việc |
|------|----------|-------|------|
| Thiết kế | `tntt-analyzer` | opus | Phân tích yêu cầu, viết SPEC + ca test. KHÔNG code. |
| Code | `tntt-coder` | sonnet | Implement theo SPEC. |
| Test | `tntt-tester` | sonnet | Viết + chạy test (unit + smoke). Báo cáo kết quả thật. |
| Review | `tntt-reviewer` | opus | Rà chất lượng + bug, đọc kỹ dòng xóa. |
| Security | `tntt-security` | opus | Audit khi đụng quyền/dữ liệu nhạy cảm. |
| DevOps | `tntt-devops` | sonnet | CI/CD, deploy, build. |
| Tài liệu | `tntt-documenter` | haiku | Changelog, comment, cập nhật docs. |

Cách phối hợp:

- **Tuần tự** cho việc có phụ thuộc: analyzer → coder → tester → reviewer →
  (security) → PR. Mỗi bước nhận output bước trước, không bỏ review giữa chừng.
- **Song song** cho việc độc lập (skill `dispatching-parallel-agents`): vd coder
  làm API trong khi tester soạn khung test — chỉ khi không chung file/trạng thái.
- Giao **một việc rõ ràng** cho mỗi subagent: input, output mong đợi, phạm vi
  file được đụng. Agent báo cáo lại **sự thật** (test đỏ thì nói đỏ).
- Mẫu giao việc/báo cáo: `.claude/prompts/task-template.md`,
  `.claude/prompts/report-template.md`.

> Lưu ý về chi phí: mặc định ~80% việc dùng Sonnet (implement/test/devops), ~15%
> Opus (thiết kế/review/security), ~5% Haiku (tài liệu).

---

## 4. Định nghĩa "Xong" (Definition of Done)

Một tính năng chỉ **xong** khi tất cả đúng:

- [ ] Có issue + SPEC/tiêu chí hoàn thành rõ.
- [ ] Code theo đúng chốt quyền (require_write + require_permission + kiểm phạm vi
      nếu theo-đối-tượng).
- [ ] Test unit mới cho logic mới; **test phân quyền** nếu đụng dữ liệu theo lớp.
- [ ] `eslint`, `php -l`, `phpunit` xanh tại máy.
- [ ] Smoke test trình duyệt xanh, **không** `pageerror`/`console.error`.
- [ ] Không đặt trùng tên thuộc tính/hàm giữa các mảnh JS (có kiểm — xem TESTING).
- [ ] Review xong; Security xong (nếu thuộc diện nhạy cảm).
- [ ] CI xanh trên PR; người khác duyệt.
- [ ] Mô tả PR đầy đủ; ảnh chụp nếu có UI.
- [ ] Tài liệu/CHANGELOG cập nhật nếu đổi hành vi người dùng.

---
_Khi quy trình này cần đổi, sửa thẳng tại đây trong một PR `docs/` riêng._
