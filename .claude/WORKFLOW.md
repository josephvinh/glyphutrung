# 🚀 TNTT Super App - Development Workflow

**Repo:** https://github.com/josephvinh/glyphutrung

Hướng dẫn này kết hợp **GitHub Workflow** và **Agent Team Workflow** để tạo quy trình phát triển hiệu quả cho team.

---

## 📋 Mục Lục

1. [Tổng Quan Hệ Thống](#tổng-quan-hệ-thống)
2. [GitHub Setup](#phần-1-github-setup)
3. [Agent Team](#phần-2-agent-team)
4. [Development Workflow](#phần-3-development-workflow)
5. [Quick Reference](#phần-4-quick-reference)

---

## Tổng Quan Hệ Thống

```
┌─────────────────────────────────────────────────────────────────┐
│                      TNTT Development Flow                       │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  GitHub Issues ──► Branch ──► Agent Team ──► PR ──► Merge    │
│       │              │            │              │              │
│       ▼              ▼            ▼              ▼              │
│   Labels         <type>/      Analyze          Review           │
│   Project        #issue      → Code           → Deploy         │
│   Board                        → Test                           │
│                                  → Review                       │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

### Luồng Chính

1. **Tạo Issue** → GitHub Issue với labels và project
2. **Tạo Branch** → `feature/#issue-description`
3. **Agent Implementation** → Analyzer → Coder → Tester → Reviewer
4. **Tạo PR** → Link issue → Code review
5. **Merge & Deploy** → Auto-close issue

---

## PHẦN 1: GitHub Setup

### 1.1 Tạo GitHub Project

**Người thực hiện:** Project Owner (Vinh)

1. Vào https://github.com/josephvinh/glyphutrung
2. Click tab **Projects** → **New project**
3. Chọn **Board** template
4. **Project name:** `TNTT Development`
5. Rename columns:
   - `📋 Backlog` → Công việc chưa làm
   - `🔄 In Progress` → Đang làm
   - `👀 In Review` → Chờ review
   - `✅ Done` → Hoàn thành

### 1.2 Setup Labels

Tạo 8 labels sau trong **Settings → Labels**:

| Label Name | Color | Description | Agent Mapping | Workflow |
|------------|-------|-------------|---------------|----------|
| `feature` | #28a745 | Tính năng mới | Coder | Phân tích → Code → Test → Review |
| `bug` | #d73a49 | Sữa lỗi | Coder → Tester | Phân tích → Code → Test → Review |
| `design` | #6f42c1 | UI/UX Design | Coder | Phân tích → Code UI → Review |
| `refactor` | #fd7e14 | Code improvement | Coder | Phân tích → Code → Review |
| `docs` | #0366d6 | Documentation | Documenter | Viết docs |
| `priority-high` | #ff6b6b | Ưu tiên cao | DevOps | Ưu tiên deploy sau merge |
| `priority-low` | #6a737d | Ưu tiên thấp | - | Không ảnh hưởng workflow |
| `agent-review` | #ffd33d | Cần Agent review | Reviewer | Gọi @reviewer trước merge |
| `security` | #dc3545 | Security-sensitive | Security | @bao-mat bắt buộc trước merge |
| `performance` | #17a2b8 | Performance-sensitive | DevOps | @toi-uu trước deploy |

### 1.3 GitHub Templates

Tạo folder structure:

```bash
mkdir -p .github/ISSUE_TEMPLATE
```

#### Feature Template (`.github/ISSUE_TEMPLATE/feature.md`)

```yaml
---
name: Feature Request
about: Đề xuất tính năng mới cho TNTT Super App
title: "[FEATURE] "
labels: feature
---

## 📝 Mô Tả Tính Năng
[Mô tả rõ ràng tính năng muốn thêm vào]

## 🎯 Nguyên Nhân
[Tại sao cần tính năng này?]

## ✅ Yêu Cầu
- [ ] Requirement 1
- [ ] Requirement 2

## 🔧 Technical Notes
[Notes về technical approach nếu có]

## 📸 Mockups
[Nếu có]
```

#### Bug Template (`.github/ISSUE_TEMPLATE/bug.md`)

```yaml
---
name: Bug Report
about: Báo cáo lỗi trong ứng dụng
title: "[BUG] "
labels: bug
---

## 🐛 Mô Tả Lỗi
[Mô tả chi tiết lỗi]

## 🔄 Cách Tái Hiện
1. Bước 1
2. Bước 2

## ✅ Kết Quả Mong Muốn
[Cái gì nên xảy ra?]

## ❌ Kết Quả Thực Tế
[Cái gì thực sự xảy ra?]

## 💻 Môi Trường
- Browser: [e.g. Chrome]
- OS: [e.g. Windows 11]
- Version: [e.g. 1.0.0]
```

#### PR Template (`.github/pull_request_template.md`)

```markdown
## 📝 Mô Tả
[Mô tả ngắn gọn những thay đổi]

## 🔗 Link Issue
Fixes #[issue-number]

## 📋 Loại Thay Đổi
- [ ] ✨ Feature mới
- [ ] 🐛 Bug fix
- [ ] ♻️ Refactor
- [ ] 📚 Documentation
- [ ] 🎨 UI/UX

## 🤖 Agent Tasks Completed
- [ ] Analyzer: SPEC.md created
- [ ] Coder: Code implemented
- [ ] Tester: Tests written
- [ ] Reviewer: Code reviewed

## 🧪 Cách Test
1. Step 1
2. Step 2

## 📸 Screenshots (nếu có)

## ✅ Checklist
- [ ] Code đã được review
- [ ] Tests pass
- [ ] Không breaking changes
- [ ] Console không warning/error
```

---

## PHẦN 2: Agent Team

### 2.1 Cấu Trúc Agent Team

| Agent | Model | Role | Khi nào dùng |
|-------|-------|------|--------------|
| **analyzer** | opus | Phân tích requirements, thiết kế architecture | Đầu project, requirements mới |
| **coder** | sonnet | Implement code PHP/JS | Implement features |
| **tester** | sonnet | Viết và chạy tests | Sau khi code xong |
| **reviewer** | opus | Code review, quality check | Trước merge |
| **security** | opus | Security audit | Security-sensitive changes |
| **devops** | sonnet | CI/CD, deployment | Deployment, builds |
| **documenter** | haiku | Documentation | Sau khi feature done |

### 2.2 Quick Commands

| Shortcut | Agent | Use case |
|----------|-------|----------|
| `@phan-tich` | Analyzer | Phân tích codebase |
| `@lap-trinh` | Coder | Viết code mới |
| `@kiem-thu` | Tester | Viết tests |
| `@bao-mat` | Security | Security audit |
| `@toi-uu` | DevOps | Performance optimization |
| `@de-xuat` | Analyzer | Đề xuất cải thiện |

### 2.3 Gọi Agent

```bash
@phan-tich public/api/attendance.php
```

### 2.4 Agent Files

```
.claude/
├── agents/
│   ├── analyzer.md      # Agent definition
│   ├── coder.md
│   ├── tester.md
│   ├── reviewer.md
│   ├── security.md
│   ├── devops.md
│   └── documenter.md
├── prompts/
│   ├── task-template.md
│   └── report-template.md
└── workflows/
    └── agent-team.js
```

---

## PHẦN 3: Development Workflow

### 3.1 Full Development Cycle

```
┌──────────────────────────────────────────────────────────────────┐
│                     Development Cycle                             │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1️⃣ Issue         Tạo GitHub Issue                              │
│      │            (labels: feature/bug, priority)                │
│      ▼                                                             │
│  2️⃣ Branch        git checkout -b feature/#-description          │
│      │                                                             │
│      ▼                                                             │
│  3️⃣ Analyze       @phan-tich - Tạo SPEC.md                      │
│      │                                                             │
│      ▼                                                             │
│  4️⃣ Implement     @lap-trinh - Code theo SPEC                   │
│      │                                                             │
│      ▼                                                             │
│  5️⃣ Test          @kiem-thu - Viết tests                        │
│      │                                                             │
│      ▼                                                             │
│  6️⃣ Review        @reviewer - Code review                       │
│      │                                                             │
│      ▼                                                             │
│  7️⃣ Security      @bao-mat (nếu cần)                            │
│      │                                                             │
│      ▼                                                             │
│  8️⃣ PR            Tạo Pull Request                             │
│      │            (link issue: Fixes #)                          │
│      ▼                                                             │
│  9️⃣ Merge         Auto-close issue → Done                        │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

### 3.2 Chi Tiết Từng Bước

#### Step 1: Tạo Issue

1. Vào repo → **Issues** → **New issue**
2. Chọn template (`Feature` hoặc `Bug`)
3. Điền thông tin:
   - Title: `[FEATURE] Mô tả` hoặc `[BUG] Mô tả`
   - Labels: `feature` hoặc `bug`, `priority-high/low`
   - Project: `TNTT Development` → `📋 Backlog`

#### Step 2: Tạo Branch

```bash
# Tạo branch từ issue
git checkout -b feature/45-add-qr-scanner
# Hoặc: git checkout -b bug/46-fix-login-error
```

**Branch naming:** `<type>/<issue-number>-<description>`

| Type | Dùng cho |
|------|----------|
| `feature/` | Tính năng mới |
| `bug/` | Sữa lỗi |
| `design/` | UI/UX |
| `refactor/` | Cải thiện code |
| `docs/` | Documentation |

#### Step 3: Analyze (@phan-tich)

```bash
# Phân tích requirements
@phan-tich public/api/attendance.php

# Hoặc tạo SPEC.md thủ công
```

**Output:** `SPEC.md` trong `docs/specs/`

#### Step 4: Implement (@lap-trinh)

```bash
# Implement theo SPEC.md
@lap-trinh docs/specs/feature-xyz.md
```

**Output:** Code trong các files tương ứng

#### Step 5: Test (@kiem-thu)

```bash
# Viết và chạy tests
@kiem-thu public/api/attendance.php
```

**Output:** Tests trong `tests/`

#### Step 6: Review (@reviewer)

```bash
# Code review
@reviewer

# Hoặc review specific files
@reviewer public/api/attendance.php
```

**Output:** Review report trong `.claude/reports/`

#### Step 7: Security (@bao-mat) - Optional

```bash
# Chỉ cho security-sensitive changes
@bao-mat
```

#### Step 8: Tạo Pull Request

1. Push code: `git push origin feature/45-add-qr-scanner`
2. GitHub → **Compare & pull request**
3. Điền theo PR template:
   - Title: `feat: add QR scanner (#45)`
   - Description: Theo template
   - Link issue: `Fixes #45`
4. Assign reviewers
5. Click **Create pull request**

#### Step 9: Merge

1. Reviewers approve
2. **Merge pull request**
3. GitHub auto-close issue → `✅ Done`

### 3.3 Parallel Agent Tasks

Cho các tasks độc lập:

```javascript
// Chạy song song
@lap-trinh feature A
@lap-trinh feature B

// Hoặc implement và test song song
@lap-trinh docs/specs/feature-a.md
@kiem-thu tests/test-feature-a.php
```

---

## PHẦN 4: Quick Reference

### Branch Types

```
feature/    → Tính năng mới
bug/        → Sữa lỗi
design/     → UI/UX
refactor/   → Code improvement
docs/       → Documentation
```

### Commit Types

```
feat:       → Tính năng mới
fix:        → Bug fix
refactor:   → Code improvement
docs:       → Documentation
style:      → Formatting
test:       → Tests
chore:      → Build/deps
perf:       → Performance
ci:         → CI/CD
```

### Labels

```
feature         → Tính năng mới
bug             → Sữa lỗi
design          → UI/UX Design
refactor        → Cải thiện code
docs            → Documentation
priority-high   → Ưu tiên cao
priority-low    → Ưu tiên thấp
agent-review    → Cần Agent review
```

### Project Columns

```
📋 Backlog      → Chưa làm
🔄 In Progress  → Đang làm
👀 In Review    → Chờ review
✅ Done         → Hoàn thành
```

### Agent Commands

```
@phan-tich      → Phân tích requirements
@lap-trinh      → Implement code
@kiem-thu       → Write tests
@reviewer       → Code review
@bao-mat        → Security audit
@toi-uu         → Performance optimization
@de-xuat        → Đề xuất cải thiện
```

### Useful Commands

```bash
# Tạo branch từ issue
git checkout -b feature/45-description

# Push code
git push origin feature/45-description

# Xem project board
gh project view 1

# Tạo PR
gh pr create --title "feat: description" --body "Fixes #45"
```

---

## ✅ Checklist Khi Setup Xong

### GitHub Setup
- [ ] Project "TNTT Development" tạo xong
- [ ] 8 labels tạo xong
- [ ] Issue templates push xong
- [ ] PR template push xong
- [ ] CONTRIBUTING.md push xong

### Agent Setup
- [ ] `.claude/agents/*.md` tồn tại
- [ ] Test: Chạy `@phan-tich` thử

---

## 🎉 Ready!

Bây giờ bạn có thể:

1. **Tạo Issue đầu tiên** → Thêm vào Project
2. **Tạo Branch** → `feature/#-description`
3. **Chạy Agent Team** → `@phan-tich` → `@lap-trinh` → `@kiem-thu`
4. **Tạo PR** → Link issue → Merge
5. **Xem Issue auto-close** → Done!

**Enjoy! 🚀**
