# 🤝 Contributing to TNTT Super App

Cảm ơn bạn quan tâm đến dự án TNTT Super App!

---

## 📋 Quy Tắc Chung

### Branch Naming Convention

```
<type>/<issue-number>-<description>

Ví dụ:
- feature/45-add-qr-scanner
- bug/46-fix-login-error
- design/47-redesign-dashboard
- refactor/48-optimize-queries
- docs/49-update-readme
```

**Types:**
| Type | Dùng cho |
|------|----------|
| `feature/` | Tính năng mới |
| `bug/` | Sữa lỗi |
| `design/` | UI/UX Design |
| `refactor/` | Cải thiện code |
| `docs/` | Documentation |

### Commit Message Convention

```
<type>: <description>

Ví dụ:
- feat: add QR code scanner
- fix: resolve login bug
- refactor: optimize database queries
- docs: update README
- test: add attendance export tests
- perf: improve query performance
- ci: update deployment pipeline
```

**Types:**
| Type | Dùng cho |
|------|----------|
| `feat:` | Feature mới |
| `fix:` | Bug fix |
| `refactor:` | Code improvement |
| `style:` | Formatting |
| `docs:` | Documentation |
| `test:` | Tests |
| `chore:` | Build/dependencies |
| `perf:` | Performance |
| `ci:` | CI/CD |

---

## 🚀 Development Workflow

### Sử dụng Agent Team

Dự án sử dụng AI Agent Team để phát triển. Xem chi tiết tại [.claude/WORKFLOW.md](.claude/WORKFLOW.md).

### Quick Commands

| Command | Agent | Mô tả |
|---------|-------|--------|
| `@phan-tich` | Analyzer | Phân tích requirements |
| `@lap-trinh` | Coder | Implement code |
| `@kiem-thu` | Tester | Viết tests |
| `@reviewer` | Reviewer | Code review |
| `@bao-mat` | Security | Security audit |
| `@toi-uu` | DevOps | Performance optimization |
| `@de-xuat` | Analyzer | Đề xuất cải thiện |

### Full Workflow

```
1️⃣ Tạo Issue → Labels (feature/bug) → Project (TNTT Development)
2️⃣ Tạo Branch → feature/#-description
3️⃣ Phân tích → @phan-tich
4️⃣ Implement → @lap-trinh
5️⃣ Test → @kiem-thu
6️⃣ Review → @reviewer
7️⃣ Tạo PR → Link issue (Fixes #)
8️⃣ Merge → Auto-close issue
```

---

## 📌 Labels

| Label | Dùng cho | Agent |
|-------|----------|-------|
| `feature` | Tính năng mới | Coder |
| `bug` | Sữa lỗi | Coder → Tester |
| `design` | UI/UX Design | Coder |
| `refactor` | Cải thiện code | Coder |
| `docs` | Documentation | Documenter |
| `priority-high` | Ưu tiên cao | DevOps |
| `priority-low` | Ưu tiên thấp | - |
| `agent-review` | Cần Agent review | Reviewer |
| `security` | Security-sensitive | Security |
| `performance` | Performance-sensitive | DevOps |

---

## 🎯 Project Columns

| Column | Ý Nghĩa |
|--------|----------|
| 📋 Backlog | Công việc chưa bắt đầu |
| 🔄 In Progress | Đang làm |
| 👀 In Review | Chờ review |
| ✅ Done | Hoàn thành |

---

## 💡 Tips

✅ **Commit thường xuyên** - Mỗi commit là 1 công việc logic nhỏ
✅ **PR nhỏ gọn** - Dễ review hơn (300-500 lines tối ưu)
✅ **Chi tiết trong issue** - Giúp team hiểu rõ hơn
✅ **Link issue trong PR** - Dùng `Fixes #123` để auto-close
✅ **Review nhanh** - Feedback trong 24h
✅ **Dùng Agent Team** - Tận dụng AI agents để tăng hiệu suất

---

## 🔗 Useful Commands

```bash
# Tạo branch
git checkout -b feature/45-description

# Push code
git push origin feature/45-description

# Xem project
gh project view 1

# Tạo PR
gh pr create --title "feat: description" --body "Fixes #45"
```

---

## ❓ FAQ

**Q: Tôi mới clone repo, làm gì tiếp?**
A: `git pull origin master` để cập nhật

**Q: Branch bị outdated?**
A: `git fetch origin && git rebase origin/master`

**Q: Làm sao đổi commit message?**
A: `git commit --amend` rồi `git push -f origin branch-name`

**Q: PR sai?**
A: Đóng PR, fix code, push lên, tạo PR mới

**Q: Làm sao dùng Agent Team?**
A: Xem [.claude/WORKFLOW.md](.claude/WORKFLOW.md) và [.claude/AGENTS.md](.claude/AGENTS.md)

---

## 📞 Cần Giúp?

- Xem [README.md](README.md)
- Tạo [Discussion](../../discussions)
- Contact: Vinh

---

**Happy coding! 🎉**
