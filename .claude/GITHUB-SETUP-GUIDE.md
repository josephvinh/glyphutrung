# 🚀 TNTT Super App - GitHub Setup Guide

**Repo:** https://github.com/josephvinh/glyphutrung

Hướng dẫn này để setup GitHub workflow cho team. Làm theo từng bước.

---

## 📋 Mục Lục

1. [Setup trên GitHub UI](#phần-1-setup-trên-github-ui)
2. [Setup Files trên Local](#phần-2-setup-files-trên-local)
3. [Workflow Làm Việc](#phần-3-workflow-làm-việc)
4. [Quick Reference](#phần-4-quick-reference)

---

## PHẦN 1: Setup trên GitHub UI

### Bước 1.1: Tạo GitHub Project

**Người thực hiện:** Project Owner (Vinh)

1. Vào https://github.com/josephvinh/glyphutrung
2. Click tab **Projects** (nếu không thấy, vào Settings → Features → enable Projects)
3. Click **New project**
4. Chọn **Board** template
5. **Project name:** `TNTT Development`
6. Click **Create project**

**Sau khi tạo:**
- Board sẽ có columns mặc định
- Rename/sắp xếp thành:
  - `📋 Backlog` (công việc chưa làm)
  - `🔄 In Progress` (đang làm)
  - `👀 In Review` (chờ review)
  - `✅ Done` (hoàn thành)

---

### Bước 1.2: Setup Labels

**Người thực hiện:** Project Owner (Vinh)

1. Vào repo → **Settings** → **Labels** (bên trái)
2. **Xóa** các label cũ (nếu có)
3. **Click "New label"** và tạo từng cái sau:

```
Label Name      | Color    | Description
----------------|----------|------------------
feature         | #28a745  | Tính năng mới
bug             | #d73a49  | Sữa lỗi
design          | #6f42c1  | UI/UX Design
refactor        | #fd7e14  | Code improvement
docs            | #0366d6  | Documentation
priority-high   | #ff6b6b  | Ưu tiên cao
priority-low    | #6a737d  | Ưu tiên thấp
review-needed   | #ffd33d  | Chờ review
```

✅ **Bước 1 & 2 xong!**

---

## PHẦN 2: Setup Files trên Local

**Người thực hiện:** Bất kỳ developer nào (chạy 1 lần trên local)

### Bước 2.1: Clone repo (nếu chưa có)

```bash
git clone https://github.com/josephvinh/glyphutrung.git
cd glyphutrung
```

### Bước 2.2: Tạo folder structure

```bash
# Tạo thư mục
mkdir -p .github/ISSUE_TEMPLATE
mkdir -p docs
```

### Bước 2.3: Tạo file Feature Template

Tạo file `.github/ISSUE_TEMPLATE/feature.md`:

```bash
cat > .github/ISSUE_TEMPLATE/feature.md << 'EOF'
---
name: Feature Request
about: Đề xuất tính năng mới cho TNTT Super App
title: "[FEATURE] "
labels: feature
---

## 📝 Mô Tả Tính Năng
[Mô tả rõ ràng tính năng muốn thêm vào]

## 🎯 Nguyên Nhân
[Tại sao cần tính năng này? Giải quyết vấn đề gì?]

## ✅ Yêu Cầu
- [ ] Requirement 1
- [ ] Requirement 2
- [ ] Requirement 3

## 📸 Screenshots/Mockups
[Nếu có, thêm hình ảnh hoặc liên kết mockup]

## 📌 Ghi Chú Thêm
[Thông tin bổ sung nếu có]
EOF
```

### Bước 2.4: Tạo file Bug Template

Tạo file `.github/ISSUE_TEMPLATE/bug.md`:

```bash
cat > .github/ISSUE_TEMPLATE/bug.md << 'EOF'
---
name: Bug Report
about: Báo cáo lỗi trong ứng dụng
title: "[BUG] "
labels: bug
---

## 🐛 Mô Tả Lỗi
[Mô tả chi tiết lỗi bạn gặp phải]

## 🔄 Cách Tái Hiện
**Các bước để tái hiện lỗi:**
1. Bước 1
2. Bước 2
3. Bước 3

## ✅ Kết Quả Mong Muốn
[Cái gì nên xảy ra?]

## ❌ Kết Quả Thực Tế
[Cái gì thực sự xảy ra?]

## 📸 Screenshots
[Thêm hình ảnh hoặc video nếu có]

## 💻 Môi Trường
- Browser: [e.g. Chrome, Safari]
- OS: [e.g. Windows 11, macOS]
- Version: [e.g. 1.0.0]

## 📝 Logs/Error Messages
[Thêm console log hoặc error message nếu có]

## ➕ Thông Tin Bổ Sung
[Ghi chú thêm nếu cần]
EOF
```

### Bước 2.5: Tạo file PR Template

Tạo file `.github/pull_request_template.md`:

```bash
cat > .github/pull_request_template.md << 'EOF'
## 📝 Mô Tả
[Mô tả ngắn gọn những thay đổi trong PR này]

## 🔗 Link Issue
Fixes #[issue-number]

## 📋 Loại Thay Đổi
- [ ] ✨ Feature mới
- [ ] 🐛 Bug fix
- [ ] ♻️ Refactor
- [ ] 📚 Documentation
- [ ] 🎨 UI/UX

## 🧪 Cách Test
[Hướng dẫn cách test những thay đổi này]

1. Step 1
2. Step 2
3. ...

## 📸 Screenshots (nếu có)
[Thêm screenshot của feature mới hoặc fix]

## ✅ Checklist
- [ ] Code đã được review
- [ ] Test đã pass
- [ ] Documentation cập nhật
- [ ] Không có breaking changes
- [ ] Console không có warning/error

## 🎯 Notes
[Ghi chú thêm nếu cần]
EOF
```

### Bước 2.6: Tạo file CONTRIBUTING.md

Tạo file `CONTRIBUTING.md` (ở root):

```bash
cat > CONTRIBUTING.md << 'EOF'
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
```

**Types:**
- `feature/` - Tính năng mới
- `bug/` - Sữa lỗi
- `design/` - UI/UX Design
- `refactor/` - Cải thiện code
- `docs/` - Documentation

### Commit Message Convention

```
<type>: <description>

Ví dụ:
- feat: add QR code scanner
- fix: resolve login bug
- refactor: optimize database queries
- docs: update README
```

**Types:**
- `feat:` - Feature mới
- `fix:` - Bug fix
- `refactor:` - Code improvement
- `style:` - Formatting
- `docs:` - Documentation
- `test:` - Tests
- `chore:` - Build/dependencies

---

## 🚀 Workflow

### 1️⃣ Tạo Issue

1. Vào repo → **Issues** → **New issue**
2. Chọn template (`Feature` hoặc `Bug`)
3. Điền thông tin theo template
4. Thêm **labels** (feature, bug, design, etc.)
5. Thêm vào **Project**: TNTT Development
6. Thêm **priority** nếu cần (priority-high, priority-low)

### 2️⃣ Tạo Branch

```bash
# Cách 1: GitHub sẽ suggest "Create a branch for this issue"
# Click nút đó trên issue page

# Cách 2: Tạo thủ công (nếu GitHub không suggest)
git fetch origin
git checkout -b feature/45-add-qr-scanner
```

### 3️⃣ Làm Việc & Commit

```bash
# Làm việc trên branch
# Commit theo convention
git commit -m "feat: add QR code scanner component"
git commit -m "feat: implement QR scanning logic"

# Push lên origin
git push origin feature/45-add-qr-scanner
```

### 4️⃣ Tạo Pull Request

1. Sau khi push, vào repo trên GitHub
2. GitHub sẽ suggest: "Compare & pull request"
3. **Title:** `feat: add QR code scanner (#45)`
4. **Description:** Điền theo PR template
5. **Link issue:** `Fixes #45` (tự động close issue khi merge)
6. **Reviewers:** Assign người review (nếu cần)
7. Click **Create pull request**

### 5️⃣ Review & Merge

1. PR status → `👀 In Review` trong Project
2. Chờ reviewer feedback
3. Update code nếu cần
4. Khi approved → **Merge pull request**
5. GitHub auto-delete branch (optional)
6. Issue auto-close → `✅ Done` trong Project

---

## 📌 Labels

| Label | Dùng cho | Khi nào dùng |
|-------|----------|-------------|
| `feature` | Tính năng mới | Khi tạo feature request |
| `bug` | Sữa lỗi | Khi báo lỗi |
| `design` | UI/UX Design | Cho công việc design |
| `refactor` | Cải thiện code | Code cleanup, optimization |
| `docs` | Documentation | Cập nhật docs, README |
| `priority-high` | Ưu tiên cao | Công việc gấp |
| `priority-low` | Ưu tiên thấp | Công việc không gấp |
| `review-needed` | Chờ review | Công việc chờ ai đó review |

---

## 🎯 Project Columns

| Column | Ý Nghĩa |
|--------|---------|
| 📋 Backlog | Công việc chưa bắt đầu |
| 🔄 In Progress | Đang làm |
| 👀 In Review | Chờ review |
| ✅ Done | Hoàn thành |

**Cách move:** Drag-drop card trong board, hoặc tự động khi PR được merge.

---

## 💡 Tips

✅ **Commit thường xuyên** - Mỗi commit là 1 công việc logic nhỏ
✅ **PR nhỏ gọn** - Dễ review hơn (300-500 lines tối ưu)
✅ **Chi tiết trong issue** - Giúp team hiểu rõ hơn
✅ **Link issue trong PR** - Dùng `Fixes #123` để auto-close
✅ **Review nhanh** - Feedback trong 24h

---

## 🔗 Useful Commands

```bash
# Tạo branch từ issue URL
gh issue develop 45

# View project
gh project view 1

# Create PR
gh pr create --title "feat: add QR scanner" --body "Fixes #45"

# List issues
gh issue list --label feature
```

---

## ❓ FAQ

**Q: Tôi mới clone repo, làm gì tiếp?**
A: Cập nhật từ main branch: `git pull origin main`

**Q: Branch tôi bị outdated?**
A: Update: `git fetch origin && git rebase origin/main`

**Q: Làm sao để đổi commit message?**
A: `git commit --amend` rồi `git push -f origin branch-name`

**Q: PR sai, làm sao?**
A: Đóng PR, fix code, push lên, tạo PR mới.

---

## 📞 Cần Giúp?

- Xem [README.md](README.md)
- Tạo [Discussion](../../discussions)
- Contact: Vinh

---

**Happy coding! 🎉**
EOF
```

### Bước 2.7: Commit và Push

```bash
# Thêm tất cả files vào git
git add .github/ CONTRIBUTING.md

# Commit
git commit -m "docs: add GitHub templates and contributing guide"

# Push lên main
git push origin main
```

✅ **Phần 2 xong!**

---

## PHẦN 3: Workflow Làm Việc

### Quy Trình Hàng Ngày

#### Khi bắt đầu công việc mới:

```bash
# 1. Tạo issue trên GitHub UI
#    - Chọn template (Feature/Bug)
#    - Add labels
#    - Add project: TNTT Development

# 2. GitHub suggest: "Create a branch for this issue"
#    Hoặc tạo thủ công:
git fetch origin
git checkout -b feature/45-add-qr-scanner

# 3. Làm việc & commit theo convention
git add .
git commit -m "feat: add QR scanner component"

# 4. Push lên
git push origin feature/45-add-qr-scanner

# 5. Tạo PR trên GitHub
#    - Fill description từ template
#    - Link issue: Fixes #45
#    - Assign reviewers

# 6. Sau khi merge
#    - Issue auto-close
#    - Card auto-move to Done
```

---

### Ví Dụ Thực Tế

**Issue:** Add QR Code Attendance Scanner

```
Title: [FEATURE] Add QR Code Attendance Scanner
Description: 
  - Implement QR code scanner component
  - Store attendance records in database
  - Generate attendance reports

Labels: feature, priority-high
Project: TNTT Development
```

**Branch & Commits:**

```bash
git checkout -b feature/50-add-qr-scanner

git commit -m "feat: add QR scanner component"
git commit -m "feat: implement QR scanning logic"
git commit -m "feat: store attendance records"
git commit -m "feat: add attendance report export"

git push origin feature/50-add-qr-scanner
```

**PR:**

```
Title: Add QR Code Attendance Scanner (#50)

Description:
- Implement QR code scanner
- Store attendance in database
- Export attendance reports

Fixes #50
```

**After Merge:** Issue auto-close ✅

---

## PHẦN 4: Quick Reference

### Branch Types

```
feature/  → Tính năng mới
bug/      → Sữa lỗi
design/   → UI/UX
refactor/ → Code improvement
docs/     → Documentation
```

### Commit Types

```
feat:     → Tính năng mới
fix:      → Bug fix
refactor: → Code improvement
docs:     → Documentation
style:    → Formatting
test:     → Tests
chore:    → Build/deps
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
review-needed   → Chờ review
```

### Project Columns

```
📋 Backlog → Chưa làm
🔄 In Progress → Đang làm
👀 In Review → Chờ review
✅ Done → Hoàn thành
```

---

## ✅ Checklist Khi Setup Xong

- [ ] Project "TNTT Development" tạo xong
- [ ] 7 labels tạo xong
- [ ] `.github/ISSUE_TEMPLATE/feature.md` push xong
- [ ] `.github/ISSUE_TEMPLATE/bug.md` push xong
- [ ] `.github/pull_request_template.md` push xong
- [ ] `CONTRIBUTING.md` push xong
- [ ] Test: Tạo 1 test issue để verify templates

---

## 🎉 Done!

Repo đã sẵn sàng! Bây giờ:

1. Tạo issue đầu tiên
2. Tạo branch từ issue
3. Làm việc & commit
4. Tạo PR & merge
5. Xem issue auto-close & card auto-move to Done

**Enjoy! 🚀**
