## 📝 Mô Tả
[Mô tả ngắn gọn những thay đổi trong PR này]

## 🔗 Link Issue
<!-- Fixes #issue-number -->

## 📋 Loại Thay Đổi
- [ ] ✨ Feature mới
- [ ] 🐛 Bug fix
- [ ] ♻️ Refactor
- [ ] 📚 Documentation
- [ ] 🎨 UI/UX
- [ ] ⚡ Performance
- [ ] 🔒 Security

## 🎯 Mục đích (một PR = một mục đích)
<!-- PR này chỉ làm MỘT việc. Nếu đang làm nhiều việc, hãy tách. -->

## 🗑️ Code bị xóa (nếu có)
<!-- Liệt kê hàm/biến đã xóa + đã `grep` toàn repo, không còn nơi gọi.
     Bỏ trống nếu không xóa gì. Xem bẫy #194 trong docs/process/FEATURE_WORKFLOW.md -->

## 🧪 Cách Test
[Các bước để người duyệt tự kiểm chứng]

1. ...

## 📸 Screenshots (nếu đụng UI — trước / sau)

## ✅ Checklist (xem docs/process/TESTING.md)
- [ ] `php -l` + `phpunit` (DB thật) + `eslint@9.39.5`: xanh tại máy
- [ ] `check_module_merge`: không trùng tên mảnh JS ngoài danh sách cho phép
- [ ] Smoke test trình duyệt: xanh, **0** `pageerror`/`console.error`
- [ ] Đụng quyền/dữ liệu lớp → có test "lớp khác → 403" và đã chạy
- [ ] Endpoint ghi có `require_write` + `require_permission` + kiểm phạm vi
- [ ] Không có `INSERT IGNORE`/`catch` nào nuốt lỗi thật
- [ ] Tài liệu/CHANGELOG cập nhật nếu đổi hành vi người dùng
- [ ] CI xanh; người **khác** đã duyệt

## 🔒 Security (bắt buộc nếu nhãn `security` hoặc đụng auth/quyền/dữ liệu nhạy cảm)
- [ ] Đã rà theo `docs/security/SECURITY_AUDIT.md` (phần "điểm đã kiểm ổn" là baseline)
- [ ] Không thêm endpoint/tham số lộ dữ liệu ngoài phạm vi người gọi

## 🤖 Nếu dùng subagent (ghi rõ bước nào do agent nào làm)
<!-- vd: analyzer=SPEC, coder=impl, tester=tests, reviewer=review, security=audit -->

## 🎯 Notes
[Ghi chú thêm nếu cần]

---
<!-- Reviewer: @username -->
<!-- Assignee: @username -->
