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

## Phạm vi theo phân công

Quyền **thao tác dữ liệu** được xét theo TỪNG phân công đang hiệu lực, không
gộp chung:

- **Xem hồ sơ**: thấy mọi lớp/khối mình được phân công (kể cả kiêm nhiệm).
- **Ghi / duyệt / export**: chỉ được khi có **một vai trò** vừa đủ cấp quyền
  trên chức năng đó **vừa** phụ trách đúng lớp/khối liên quan.

Ví dụ chống nhầm quyền: một người là Trưởng Khối (được *xem* điểm danh cả
khối) kiêm GLV một lớp khác (được *sửa* điểm danh lớp mình) — người này
KHÔNG thể sửa điểm danh các lớp trong khối mình chỉ được xem.

Hàm nền: `can_access_class()`, `accessible_class_ids()` trong
`public/api/_common.php`.

### Giao diện

Giao diện đọc phạm vi từ danh sách phân công của bạn:
- Nhãn phạm vi (Trang chủ) liệt kê mọi lớp/khối bạn phụ trách.
- Bộ lọc lớp, danh sách thiếu nhi, sĩ số gồm tất cả lớp kiêm nhiệm.
- Điểm danh mặc định lớp chính, chuyển được sang lớp khác qua bộ chọn.
- Trang cá nhân đánh dấu ★ cho phân công chính.

Cơ sở: getter `myClasses` / `isUnrestrictedScope` trong `access.js`, có fallback về phân công chính khi chưa có dữ liệu phân công.
