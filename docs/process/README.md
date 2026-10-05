# Quy Trình & Quy Ước — Mục Lục

Bản đồ toàn bộ tài liệu quy trình của dự án. Điểm vào ngắn gọn cho người lẫn
agent là `CLAUDE.md` (gốc repo).

## Quy trình làm việc

| Tài liệu | Nói về |
|----------|--------|
| [FEATURE_WORKFLOW.md](FEATURE_WORKFLOW.md) | Phát triển một tính năng: ý tưởng → ship, dùng subagent, Definition of Done |
| [TESTING.md](TESTING.md) | Các tầng test, **cách chạy**: DB, phpunit, eslint, smoke trình duyệt, kiểm trùng module |
| [GITHUB_SETUP.md](GITHUB_SETUP.md) | Branch protection, CI bắt buộc, secret scan, Dependabot, merge, deploy |
| [AGENT_RULES.md](AGENT_RULES.md) | Quy định cho agent (đặc biệt Claude): phạm vi, an toàn, kiểm chứng, cấm đoán |

## Bảy loại audit (rà **cái gì** để khẳng định chất lượng)

| Audit | Câu hỏi | Tài liệu |
|-------|---------|----------|
| 🔒 Bảo mật | Ai lấy/làm được gì **không** được phép? | [../security/SECURITY_AUDIT.md](../security/SECURITY_AUDIT.md) |
| ⚙️ Chức năng | Mỗi tính năng làm **đúng việc**, mọi vai, mọi ca? | [FUNCTIONAL_AUDIT.md](FUNCTIONAL_AUDIT.md) |
| 🎨 Design / UX | Trông/đụng vào có nhất quán, dễ dùng? | [DESIGN_AUDIT.md](DESIGN_AUDIT.md) |
| ♿ Khả năng tiếp cận | Người trợ năng / lớn tuổi dùng được? (WCAG AA) | [ACCESSIBILITY_AUDIT.md](ACCESSIBILITY_AUDIT.md) |
| ⚡ Hiệu năng / tải | Mở nhanh, mượt, ít băng thông (mạng yếu)? | [PERFORMANCE_AUDIT.md](PERFORMANCE_AUDIT.md) |
| 🕵️ Quyền riêng tư | Thu thập/giữ/lộ dữ liệu cá nhân trẻ em đúng chừng mực? | [PRIVACY_AUDIT.md](PRIVACY_AUDIT.md) |
| 🗄️ Toàn vẹn dữ liệu | Lược đồ không lệch, không mồ côi, số liệu đối soát? | [DATA_INTEGRITY_AUDIT.md](DATA_INTEGRITY_AUDIT.md) |
| 🏗️ Hạ tầng / triển khai | Server hardening, HTTPS, **backup + khôi phục**, deploy? | [INFRA_AUDIT.md](INFRA_AUDIT.md) |
| 📦 Phụ thuộc | Thư viện có CVE, cập nhật kỷ luật? | [DEPENDENCY_AUDIT.md](DEPENDENCY_AUDIT.md) |
| 🧹 Chất lượng code | Dễ đọc/sửa, không bẫy ngầm, không nợ? | [CODE_QUALITY_AUDIT.md](CODE_QUALITY_AUDIT.md) |

> Mỗi audit: **convention** (how-to) ở đây; **báo cáo toàn-app** từng đợt lưu
> `docs/audit/<TÊN>_<YYYY-MM>.md`; finding trong một PR để thẳng ở PR.

## Khi nào dùng cái nào (nhanh)

- **Làm tính năng mới** → `FEATURE_WORKFLOW` + `TESTING` + `FUNCTIONAL_AUDIT`
  (+ `DESIGN`/`ACCESSIBILITY` nếu có UI, + `SECURITY`/`PRIVACY` nếu đụng dữ liệu).
- **Đụng dữ liệu trẻ em / phân quyền / endpoint công khai** → `SECURITY` +
  `PRIVACY` trước tiên.
- **Thấy chậm / tốn mạng** → `PERFORMANCE`.
- **Đổi schema** → `DATA_INTEGRITY` + migration.
- **Đưa lên host / lo mất dữ liệu** → `INFRA` (backup!).
- **Nâng thư viện** → `DEPENDENCY`.
- **Dọn nợ** → `CODE_QUALITY` + skill `/simplify`.

Thứ tự ưu tiên khi mâu thuẫn: chỉ dẫn người dùng → `CLAUDE.md` + các doc này →
mặc định của agent.
