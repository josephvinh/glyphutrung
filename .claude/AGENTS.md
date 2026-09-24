# TNTT Agent Team - Hướng Dẫn Sử Dụng

> ⚠️ **QUY TRÌNH BẮT BUỘC**: Mọi feature phải được phát triển trên **branch riêng** và tạo **Pull Request** để merge vào `master`. Không commit trực tiếp vào `master`!

## 🚨 Quy Trình Workflow (BẮT BUỘC)

```
┌─────────────────────────────────────────────────────────────────────┐
│                     QUY TRÌNH DEVELOPMENT                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1️⃣ TẠO BRANCH                                                   │
│     git checkout -b feat/[feature-name]                            │
│     │                                                               │
│     ▼                                                               │
│  2️⃣ CHẠY AGENTS                                                   │
│     ANALYZER → CODER → TESTER → REVIEWER → DOCUMENTER            │
│     │                                                               │
│     ▼                                                               │
│  3️⃣ COMMIT TRÊN BRANCH                                            │
│     git add . && git commit -m "feat: ..."                         │
│     │                                                               │
│     ▼                                                               │
│  4️⃣ PUSH BRANCH & TẠO PR                                          │
│     git push -u origin feat/[feature-name]                         │
│     gh pr create --title "feat: ..." --body "..."                  │
│     │                                                               │
│     ▼                                                               │
│  5️⃣ REVIEW PR                                                     │
│     │ ─── Cần fix? ───▶ Quay lại bước 2-3                         │
│     │                                                               │
│     ▼                                                               │
│  6️⃣ MERGE PR (sau khi approved)                                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### ❌ SAI - Không làm như thế này:
```bash
# ❌ SAI: Commit trực tiếp vào master
git checkout master
git commit -m "feat: new feature"  # <-- KHÔNG LÀM THẾ NÀY!
git push origin master
```

### ✅ ĐÚNG - Phải làm như thế này:
```bash
# ✅ ĐÚNG: Làm việc trên branch riêng
git checkout -b feat/my-new-feature    # Tạo branch mới
# ... làm việc với agents ...
git add . && git commit -m "feat: ..."
git push -u origin feat/my-new-feature # Push branch
gh pr create --title "feat: ..."      # Tạo PR
```

---

## 👥 Agent Team

| Agent | Model | Role | Khi nào dùng |
|-------|-------|------|--------------|
| **analyzer** | opus | Phân tích requirements, thiết kế | Đầu project |
| **coder** | sonnet | Implement code | Implement features |
| **tester** | sonnet | Viết và chạy tests | Sau khi code xong |
| **reviewer** | opus | Code review, quality | Trước merge |
| **security** | opus | Security audit | Security-sensitive changes |
| **devops** | sonnet | CI/CD, deployment | Deployment |
| **documenter** | haiku | Documentation | Sau khi done |

---

## 📋 Cách Gọi Agents

### 1. Chuẩn bị TRƯỚC KHI gọi agent

```bash
# LUÔN LUÔN tạo branch mới trước khi làm việc
git checkout -b feat/[feature-name]
```

### 2. Gọi Agent đơn lẻ

```
@agent
description: "Thiết kế feature X"
prompt: "Phân tích và thiết kế tính năng X..."
subagent_type: "general-purpose"
model: "opus"
```

### 3. Gọi nhiều agents song song (tasks độc lập)

```
# Agent 1
@agent
description: "Implement API"
prompt: "Viết API endpoint..."
subagent_type: "general-purpose"
model: "sonnet"

# Agent 2 (cùng lúc)
@agent
description: "Write tests"
prompt: "Viết unit tests..."
subagent_type: "general-purpose"
model: "sonnet"
```

### 4. Workflow có thứ tự

```
Task → ANALYZER → CODER → TESTER → REVIEWER → COMMIT & PUSH → PR
```

---

## 🔄 Workflow Chi Tiết

### Bước 1: Tạo Branch (BẮT BUỘC)

```bash
git checkout -b feat/[feature-name]
# Ví dụ: feat/export-attendance-csv
```

### Bước 2: Gọi ANALYZER (nếu cần SPEC mới)

```
@agent
description: "Thiết kế feature X"
prompt: |
  Phân tích và tạo SPEC.md cho tính năng [mô tả].
  
  Output:
  1. docs/specs/[feature-name].md - Technical specification
  2. .claude/reports/analyzer-report.md - Báo cáo phân tích
  
  Đọc: .claude/agents/analyzer.md
model: "opus"
```

### Bước 3: Gọi CODER

```
@agent
description: "Implement feature X"
prompt: |
  Implement tính năng theo SPEC.md đã tạo.
  
  Files cần tạo/sửa:
  - public/api/[module].php
  - views/module_[module].php
  
  Sau khi xong, viết báo cáo vào:
  .claude/reports/coder-report.md
  
  Đọc: .claude/agents/coder.md
model: "sonnet"
```

### Bước 4: Gọi TESTER

```
@agent
description: "Test feature X"
prompt: |
  Viết unit tests cho tính năng đã implement.
  
  Files cần test:
  - tests/unit/[Module]Test.php
  
  Chạy tests và báo cáo kết quả vào:
  .claude/reports/tester-report.md
  
  Đọc: .claude/agents/tester.md
model: "sonnet"
```

### Bước 5: Gọi REVIEWER

```
@agent
description: "Review feature X"
prompt: |
  Review code đã implement.
  
  Files cần review:
  - public/api/[module].php
  - tests/unit/[Module]Test.php
  
  Báo cáo vào:
  .claude/reports/reviewer-report.md
  
  Đọc: .claude/agents/reviewer.md
model: "opus"
```

### Bước 6: Fix Issues (nếu có)

Nếu reviewer phát hiện issues:
1. Gọi CODER để fix
2. Tester verify lại
3. Reviewer review lại

### Bước 7: Commit và Push (TRÊN BRANCH)

```bash
git add .
git commit -m "feat([module]): mô tả ngắn gọn

- Thay đổi 1
- Thay đổi 2

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"

git push -u origin feat/[feature-name]
```

### Bước 8: Tạo Pull Request

```bash
# Cách 1: Dùng gh CLI
gh pr create \
  --title "feat([module]): mô tả feature" \
  --body "## Mô tả
...
🤖 Generated with [Claude Code](https://claude.com/claude-code)" \
  --base master

# Cách 2: Mở browser
# https://github.com/josephvinh/glyphutrung/pull/new/feat/[feature-name]
```

### Bước 9: Merge (sau khi approved)

```bash
# Merge qua GitHub UI hoặc:
gh pr merge PR_NUMBER
```

---

## 📁 Agent Files

```
.claude/
├── AGENTS.md              ← File này - Hướng dẫn sử dụng
├── agents/
│   ├── analyzer.md        # Agent definition
│   ├── coder.md          # Agent definition
│   ├── tester.md         # Agent definition
│   ├── reviewer.md       # Agent definition
│   ├── security.md       # Agent definition
│   ├── devops.md         # Agent definition
│   └── documenter.md     # Agent definition
├── prompts/
│   ├── task-template.md  # Template cho task brief
│   └── report-template.md # Template cho report
├── reports/              # Reports từ các agents
│   ├── analyzer-report.md
│   ├── coder-report.md
│   ├── tester-report.md
│   ├── reviewer-report.md
│   └── documenter-report.md
└── briefs/               # Task briefs
    └── task-[id].md
```

---

## 🎯 Quick Commands (Shortcuts)

| Shortcut | Agent | Use case |
|----------|-------|----------|
| `@phan-tich [file]` | Analyzer | Phân tích file/module |
| `@lap-trinh [task]` | Coder | Viết code mới |
| `@kiem-thu [file]` | Tester | Viết tests |
| `@bao-mat` | Security | Security audit |
| `@toi-uu` | DevOps | Performance optimization |
| `@de-xuat` | Analyzer | Đề xuất cải thiện |

---

## ⚠️ Lưu Ý Quan Trọng

### 1. LUÔN làm việc trên Branch riêng
- Không commit trực tiếp vào `master`
- Mỗi feature = 1 branch = 1 PR
- Branch naming: `feat/`, `fix/`, `docs/`, `refactor/`

### 2. Commit Message Format
```
<type>(<scope>): <subject>

<body>

Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

### 3. PR Description Template
```markdown
## Mô tả
[Mô tả feature]

## Changes
- [File 1]: [Mô tả]
- [File 2]: [Mô tả]

## Testing
- [ ] Unit tests passed
- [ ] Manual test verified

🤖 Generated with [Claude Code](https://claude.com/claude-code)
```

### 4. Review Checklist
- [ ] Code đúng spec?
- [ ] Tests đầy đủ?
- [ ] Security OK?
- [ ] Performance OK?
- [ ] Documentation updated?

---

## 💡 Tips

### Chạy agents song song khi có thể
Tasks độc lập có thể chạy song song:
- Coder + Tester (implement + test cùng lúc)
- Reviewer có thể review trong khi Coder vẫn fix

### Sử dụng Worktree cho features lớn
```bash
# Tạo worktree cho feature riêng
git worktree add ../feat-export-csv feat/export-attendance-csv
```

### Nhớ save progress
```bash
# Tạo checkpoint trước khi làm big change
git add . && git commit -m "WIP: đang implement feature X"
```

---

## 📚 Tham Khảo

- Git Branching: https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/about-branches
- Conventional Commits: https://www.conventionalcommits.org/
- GitHub PR: https://docs.github.com/en/pull-requests
