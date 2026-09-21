# CLAUDE.md - Hướng dẫn sử dụng Subagent cho dự án TNTT

## 🚀 Cách sử dụng Subagent nhanh

Khi bạn cần subagent, chỉ cần nhắc một trong các keyword sau:

---

### 📋 SHORTCUTS SUBAGENT

| Keyword | Subagent | Mục đích |
|---------|----------|-----------|
| `@phan-tich` | Phân tích codebase | Phân tích cấu trúc project |
| `@lap-trinh` | Lập trình viên | Viết code tính năng mới |
| `@kiem-thu` | QA tự động | Viết test cases |
| `@bao-mat` | Chuyên gia bảo mật | Rà soát bảo mật |
| `@toi-uu` | Chuyên gia tối ưu | Tối ưu hiệu năng |
| `@de-xuat` | Tư vấn tính năng | Đề xuất cải thiện |

---

### 📝 CÚ PHÁP GỌI SUBAGENT

```
@subagent
description: "Tên ngắn"
prompt: "Nội dung chi tiết"
run_in_background: false
```

---

### 🎯 VÍ DỤ SỬ DỤNG

**1. Phân tích một file:**
```
@subagent
description: "Phân tích auth.php"
prompt: "Phân tích file public/api/auth.php, liệt kê các function chính, luồng xử lý login, các điểm cần lưu ý về bảo mật"
```

**2. Viết code mới:**
```
@subagent
description: "Viết API điểm danh QR"
prompt: "Viết API endpoint mới cho phép quét QR code hàng loạt để điểm danh nhiều học sinh cùng lúc"
```

**3. Rà soát bảo mật:**
```
@subagent
description: "Kiểm tra XSS"
prompt: "Kiểm tra toàn bộ project TNTT có lỗ hổng XSS không, liệt kê các file và dòng code cần sửa"
```

---

### 🛠️ WORKFLOW MẪU

**Chạy 4 subagent song song:**
```javascript
const [analysis, coding, testing, security] = await Promise.all([
  agent("Phân tích codebase", { label: "Analysis" }),
  agent("Viết tính năng mới", { label: "Coding" }),
  agent("Viết test cases", { label: "Testing" }),
  agent("Rà soát bảo mật", { label: "Security" })
]);
```

---

### 📌 SUBAGENT HIỆN CÓ

| ID | Mô tả | Model khuyến nghị |
|-----|-------|-------------------|
| `chuyen-gia-phan-tich` | Phân tích source code | claude-opus-4-8 |
| `lap-trinh-vien` | Lập trình full-stack | claude-sonnet-5 |
| `kiem-thu-tu-dong` | QA tự động | claude-sonnet-5 |
| `kiem-thu-tuong-tac` | QA tương tác | claude-haiku-4-5 |
| `chuyen-gia-toi-uu` | Bảo mật & tối ưu | claude-opus-4-8 |

---

### ⚡ QUICK COMMANDS

- **Phân tích file:** `@phan-tich public/api/attendance.php`
- **Viết test:** `@kiem-thu public/api/attendance.php`
- **Kiểm tra bảo mật:** `@bao-mat`
- **Tối ưu:** `@toi-uu`
- **Đề xuất:** `@de-xuat`

---

*Lưu ý: Đây là hướng dẫn sử dụng. Các shortcut sẽ được xử lý khi bạn gọi subagent với prompt tương ứng.*
