# TNTT Agent Team - Hướng Dẫn Sử Dụng

## Tổng Quan

Hệ thống Agent Team cho phép bạn điều phối nhiều AI agents để làm việc trên các tasks khác nhau một cách hiệu quả.

## Cấu Trúc Agent Team

| Agent | Model | Role | Khi nào dùng |
|-------|-------|------|--------------|
| **analyzer** | opus | Phân tích requirements, thiết kế architecture | Đầu project, requirements mới |
| **coder** | sonnet | Implement code PHP/JS | Implement features |
| **tester** | sonnet | Viết và chạy tests | Sau khi code xong |
| **reviewer** | opus | Code review, quality check | Trước merge |
| **security** | opus | Security audit | Security-sensitive changes |
| **devops** | sonnet | CI/CD, deployment | Deployment, builds |
| **documenter** | haiku | Documentation | Sau khi feature done |

## Cách Gọi Agents

### 1. Gọi đơn lẻ (Single Agent)

```bash
# Trong Claude Code, dùng:
@agent
description: "Analyze attendance export feature"
prompt: "Phân tích và thiết kế tính năng xuất báo cáo điểm danh CSV"
subagent_type: "general-purpose"
model: "opus"
```

### 2. Gọi nhiều agents song song (Parallel)

```bash
# Chạy 2 agents cùng lúc
@agent
description: "Implement API"
prompt: "Viết API endpoint mới..."
subagent_type: "general-purpose"
model: "sonnet"

# (Spawn another agent simultaneously)
@agent
description: "Write tests"
prompt: "Viết unit tests cho API..."
subagent_type: "general-purpose"
model: "sonnet"
```

### 3. Workflow có thứ tự (Sequential)

```
User Task
    |
    v
ANALYZER -> Tạo SPEC
    |
    v
CODER -> Implement theo SPEC
    |
    v
TESTER -> Viết tests
    |
    v
REVIEWER -> Code review
    |
    v
SECURITY -> Security audit (nếu cần)
    |
    v
DEPLOY
```

## Ví Dụ Thực Tế

### Task: Thêm tính năng xuất CSV

**Step 1: Gọi Analyzer**
```
@agent
description: "Thiết kế feature export CSV"
prompt: "Phân tích yêu cầu: Thêm tính năng xuất báo cáo điểm danh ra file CSV cho module attendance. Tạo SPEC.md với:
1. API endpoints cần tạo
2. Data flow từ frontend -> backend
3. Database queries cần thiết
4. Acceptance criteria
5. Test cases cần cover"
subagent_type: "general-purpose"
model: "opus"
```

**Step 2: Gọi Coder**
```
@agent
description: "Implement export CSV"
prompt: "Đọc SPEC.md trong docs/specs/attendance-export.md. Implement tính năng theo spec:
- Files: public/api/attendance.php (thêm endpoint mới), views/module_attendance.php (thêm UI)
- Tạo file migration nếu cần
- Xuất report khi hoàn thành"
subagent_type: "general-purpose"
model: "sonnet"
```

**Step 3: Gọi Tester**
```
@agent
description: "Test export CSV feature"
prompt: "Viết unit tests cho tính năng xuất CSV trong tests/UnitTest.php:
- Test happy path: export thành công
- Test edge cases: empty data, large dataset
- Test error handling: permission denied
- Chạy tests và báo cáo kết quả"
subagent_type: "general-purpose"
model: "sonnet"
```

**Step 4: Gọi Reviewer**
```
@agent
description: "Review export CSV code"
prompt: "Review code trong:
- public/api/attendance.php (endpoint mới)
- tests/UnitTest.php (test cases)
Kiểm tra:
1. Security: SQL injection, XSS, permissions
2. Performance: N+1 queries, indexing
3. Code quality: patterns, naming, comments
4. Test coverage: edge cases, error handling"
subagent_type: "general-purpose"
model: "opus"
```

## Quick Commands

### Theo CLAUDE.md, bạn có thể dùng shortcuts:

| Shortcut | Agent | Use case |
|----------|-------|----------|
| `@phan-tich` | Analyzer | Phân tích codebase |
| `@lap-trinh` | Coder | Viết code mới |
| `@kiem-thu` | Tester | Viết tests |
| `@bao-mat` | Security | Security audit |
| `@toi-uu` | DevOps | Performance optimization |
| `@de-xuat` | Analyzer | Đề xuất cải thiện |

### Ví dụ:
```
@phan-tich public/api/attendance.php
```
→ Gọi Analyzer để phân tích file attendance

## Agent Files

```
.claude/
├── agents/
│   ├── analyzer.md      # Agent definition
│   ├── coder.md          # Agent definition
│   ├── tester.md         # Agent definition
│   ├── reviewer.md       # Agent definition
│   ├── security.md       # Agent definition
│   ├── devops.md         # Agent definition
│   └── documenter.md     # Agent definition
├── prompts/
│   ├── task-template.md  # Task brief template
│   └── report-template.md # Report template
└── workflows/
    └── agent-team.js     # Workflow script
```

## Tips & Best Practices

### 1. Chọn Agent đúng
- **Opus** (đắt hơn): Architecture, Security, Final Review
- **Sonnet** (trung bình): Implementation, Testing, DevOps
- **Haiku** (rẻ nhất): Documentation, simple tasks

### 2. Giao task rõ ràng
- Mỗi agent nên có 1 task cụ thể
- Cung cấp đủ context nhưng không quá nhiều
- Xác định rõ input và expected output

### 3. Review sau mỗi agent
- Không bỏ qua review step
- Fix issues trước khi move sang task tiếp theo
- Document các findings

### 4. Parallel khi có thể
- Các tasks độc lập có thể chạy song song
- VD: Implement feature A và feature B cùng lúc

## Troubleshooting

### Agent không hoạt động?
1. Kiểm tra agent file tồn tại: `.claude/agents/*.md`
2. Kiểm tra syntax trong agent file
3. Thử gọi với model cụ thể

### Context bị quá dài?
- Chia task thành nhiều phần nhỏ
- Dùng task brief files thay vì paste trực tiếp

### Kết quả không như mong đợi?
- Cung cấp more specific instructions
- Thêm examples trong prompt
- Xem lại acceptance criteria

## Cost Optimization

| Model | Cost/1K tokens | Best for |
|-------|----------------|----------|
| Opus | ~$15 | Critical tasks |
| Sonnet | ~$3 | Regular work |
| Haiku | ~$0.25 | Simple tasks |

**Recommendation**: 
- 80% Sonnet (implementation)
- 15% Opus (architecture, review)
- 5% Haiku (documentation)

---

## Integration với External AI

### Sẽ hỗ trợ:
- **Gemini API**: Creative tasks, code generation
- **GPT-4/Codex**: Specialized coding

### Setup khi có API keys:
```bash
# Thêm vào config
.env:
  GEMINI_API_KEY=your_key
  OPENAI_API_KEY=your_key
```

## Liên hệ & Support

- Documentation: `.claude/AGENTS.md`
- Agent definitions: `.claude/agents/*.md`
- Workflow scripts: `.claude/workflows/*.js`
