# Phân Công Kiêm Nhiệm

## Bối cảnh

Trước đây mỗi thành viên chỉ có **1 vai trò chính** + **1 khối/lớp duy nhất**. Khi một GLV kiêm nhiệm nhiều lớp hoặc một Trưởng Khối cũng là GLV lớp khác, hệ thống không xử lý được.

## Giải pháp

Bảng `member_assignments` cho phép:
- **Nhiều vai trò** đồng thời: VD anh A vừa là Trưởng Khối vừa là GLV
- **Nhiều phạm vi** đồng thời: VD chị B kiêm GLV 2 lớp
- **Lịch sử phân công**: biết ai từng phụ trách lớp nào, khi nào
- **Phân công chính** (`is_primary`): vai trò mặc định khi đăng nhập

## Cách dùng

### Thêm phân công mới

1. Vào **Nhân Sự** (module staff)
2. Chọn thành viên → cuộn xuống **Phân công** → bấm **Thêm**
3. Chọn vai trò, khối/lớp, ghi chú (tùy chọn)
4. Bấm **Lưu**

### Kết thúc phân công

Bấm biểu tượng ✕ cạnh phân công cần kết thúc. Hệ thống ghi lại ngày kết thúc.

### Xem lịch sử

Cả phân công active và đã kết thúc đều hiển thị trong phần Phân công.

## Quy tắc

| Quy tắc | Xử lý |
|---------|--------|
| Mỗi người có tối đa 1 phân công chính | Hệ thống tự gỡ primary cũ |
| Phân công đầu tiên tự động là primary | `is_primary = 1` |
| Vai trò khối cần chọn khối | API validate scope |
| Vai trò lớp cần chọn lớp | API validate scope |
| Chỉ xóa được khi đã kết thúc | Giữ audit trail |
