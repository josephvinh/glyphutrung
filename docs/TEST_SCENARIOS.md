# Kịch Bản Kiểm Thử Toàn Diện — TNTT Super App

> **Phạm vi:** Tất cả chức năng của ứng dụng web TNTT (Gia Đình Giáo Lý Phú Trung)
> **Phiên bản:** Theo codebase hiện tại
> **Ngày cập nhật:** 2024

---

## Mục Lục

1. [Xác Thực & Phân Quyền](#1-xác-thực--phân-quyền)
2. [Quản Lý Thiếu Nhi](#2-quản-lý-thiếu-nhi)
3. [Điểm Danh](#3-điểm-danh)
4. [Điểm Số](#4-điểm-số)
5. [Chương Trình Sinh Hoạt](#5-chương-trình-sinh-hoạt)
6. [Khối & Lớp](#6-khối--lớp)
7. [Nhân Sự](#7-nhân-sự)
8. [Thông Báo](#8-thông-báo)
9. [Sổ Mộc & Đổi Quà](#9-sổ-mộc--đổi-quà)
10. [Báo Cáo](#10-báo-cáo)
11. [Trang Công Khai](#11-trang-công-khai)
12. [Cài Đặt](#12-cài-đặt)

---

## 1. Xác Thực & Phân Quyền

### 1.1 Đăng Nhập

| Mã | TC-01 |
|-----|-------|
| **Mô tả** | Đăng nhập thành công bằng số điện thoại và mật khẩu |
| **Người thực hiện** | Mọi vai trò |
| **Điều kiện tiên quyết** | Tài khoản đã được tạo và kích hoạt |
| **Input** | SĐT: `0901234567`, Mật khẩu: `MatKhau123` |
| **Bước thực hiện** | 1. Truy cập `/login`<br>2. Nhập số điện thoại<br>3. Nhập mật khẩu<br>4. Bấm "Đăng nhập" |
| **Kết quả mong đợi** | - Đăng nhập thành công<br>- Chuyển hướng về Dashboard<br>- Hiển thị thông tin người dùng ở sidebar |
| **Backend logic** | - Kiểm tra `members.phone`<br>- So sánh bcrypt hash<br>- Tạo PHP session<br>- Ghi `login_attempts` xóa lịch sử sai |
| **Database** | `SELECT * FROM members WHERE phone = ? AND password = ?` |
| **Phân quyền UI** | Sidebar hiện module theo vai: `admin/bdh` thấy "Ban Điều Hành", `glv` chỉ thấy chức năng cơ bản |

| Mã | TC-02 |
|-----|-------|
| **Mô tả** | Đăng nhập thất bại - sai mật khẩu |
| **Input** | SĐT: `0901234567`, Mật khẩu: `SaiMatKhau` |
| **Bước thực hiện** | 1-4. Như TC-01 với mật khẩu sai |
| **Kết quả mong đợi** | - Thông báo "Sai số điện thoại hoặc mật khẩu"<br>- Ghi `login_attempts` với IP + số điện thoại<br>- Sau 5 lần sai: khóa 15 phút |
| **Backend logic** | - Gọi `login_failed()` → ghi + delay tăng dần<br>- Kiểm tra `login_throttle()` |
| **Rate limit** | Tối đa 5 lần/15 phút/số điện thoại, 20 lần/15 phút/IP |

| Mã | TC-03 |
|-----|-------|
| **Mô tả** | Tài khoản đang bị khóa do đăng nhập sai nhiều lần |
| **Input** | SĐT: `0901234567`, Mật khẩu: bất kỳ |
| **Bước thực hiện** | 1-4. Thử đăng nhập sau khi đã khóa |
| **Kết quả mong đợi** | Thông báo: "Bạn đã nhập sai quá nhiều lần. Vui lòng đợi 15 phút rồi thử lại" |
| **Backend logic** | `login_throttle()` trả về HTTP 429 |
| **Response** | `{ok: false, error: "Bạn đã nhập sai..."}` |

| Mã | TC-04 |
|-----|-------|
| **Mô tả** | Tài khoản bị vô hiệu hóa (status = 'đã nghỉ') |
| **Input** | SĐT tài khoản đã nghỉ, đúng mật khẩu |
| **Kết quả mong đợi** | HTTP 403: "Tài khoản đã ngưng hoạt động." |
| **Backend logic** | `require_login_pending_pw()` kiểm tra `$me['status'] === 'đã nghỉ'` |

| Mã | TC-05 |
|-----|-------|
| **Mô tả** | Bắt buộc đổi mật khẩu khi đăng nhập lần đầu |
| **Input** | Tài khoản mới được cấp (must_change_pw = true) |
| **Kết quả mong đợi** | - Chuyển hướng đến trang đổi mật khẩu<br>- Không cho truy cập các chức năng khác |
| **Backend logic** | `require_login()` kiểm tra `must_change_pw` → HTTP 403 code 'must_change_pw' |

### 1.2 Phân Quyền Theo Vai

| Mã | TC-06 |
|-----|-------|
| **Mô tả** | GLV chỉ thấy thiếu nhi trong lớp được phân công |
| **Người thực hiện** | Tài khoản vai `glv` được phân công lớp "Khai Tâm 1A" |
| **Bước thực hiện** | 1. Đăng nhập với vai GLV<br>2. Vào "Danh sách thiếu nhi"<br>3. Kiểm tra bộ lọc lớp |
| **Kết quả mong đợi** | - Chỉ hiển thị học sinh lớp "Khai Tâm 1A"<br>- Không thấy học sinh lớp khác<br>- Sidebar không hiển thị "Khối & Lớp" |
| **Backend logic** | `allowed_class_ids()` trả về `['Khai Tâm 1A']`<br>SQL: `WHERE class_name IN (?)` |
| **Phân quyền UI** | `visibleModules('bdh')` trả về mảng rỗng cho vai glv |

| Mã | TC-07 |
|-----|-------|
| **Mô tả** | Trưởng Khối thấy toàn bộ lớp trong khối nhưng không thấy khối khác |
| **Người thực hiện** | Tài khoản vai `truong_khoi` phụ trách khối "Khai Tâm" |
| **Kết quả mong đợi** | - Hiển thị mọi lớp thuộc khối Khai Tâm<br>- Không thấy lớp thuộc khối "Huynh Trưởng" |
| **Backend logic** | `responsible_blocks()` trả về `block_id` của khối được phân công |

| Mã | TC-08 |
|-----|-------|
| **Mô tả** | BĐH thấy toàn bộ dữ liệu toàn đoàn |
| **Người thực hiện** | Tài khoản vai `bdh` |
| **Kết quả mong đợi** | - Mọi khối, lớp, thiếu nhi đều hiển thị<br>- Có quyền quản lý nhân sự<br>- Có quyền phát thông báo toàn đoàn |

| Mã | TC-09 |
|-----|-------|
| **Mô tả** | Cố truy cập API không có quyền |
| **Người thực hiện** | Vai `glv` cố gắng gọi API `org/saveBlock` |
| **Kết quả mong đợi** | - Backend trả HTTP 403<br>- Frontend hiển thị thông báo lỗi |
| **Backend logic** | `require_permission('org', 'edit')` kiểm tra `permission_of('org')` |

### 1.3 CSRF Protection

| Mã | TC-10 |
|-----|-------|
| **Mô tả** | POST request không có CSRF token bị từ chối |
| **Bước thực hiện** | Gửi POST request đến `/api/students.php?action=save` với curl, không có `_csrf` |
| **Kết quả mong đợi** | HTTP 403: "Invalid CSRF token." |
| **Backend logic** | `require_write()` → `require_csrf()` → `verify_csrf(token)` |

---

## 2. Quản Lý Thiếu Nhi

### 2.1 Xem Danh Sách

| Mã | TC-11 |
|-----|-------|
| **Mô tả** | Hiển thị danh sách thiếu nhi với phân trang |
| **URL** | `/#/students` |
| **Bước thực hiện** | 1. Vào "Danh sách thiếu nhi"<br>2. Quan sát số lượng hiển thị ban đầu<br>3. Cuộn xuống hoặc bấm "Xem thêm" |
| **Kết quả mong đợi** | - Ban đầu hiển thị 20 em<br>- Bấm "Xem thêm" hiển thị thêm 20 em<br>- `displayLimit` tăng 20 mỗi lần |
| **Backend logic** | `SELECT * FROM students WHERE class_id IN (?) LIMIT 20` |

| Mã | TC-12 |
|-----|-------|
| **Mô tả** | Tìm kiếm thiếu nhi theo tên |
| **Input** | Từ khóa: "An" |
| **Bước thực hiện** | 1. Gõ "An" vào ô tìm kiếm<br>2. Quan sát kết quả lọc real-time |
| **Kết quả mong đợi** | - Chỉ hiển thị em có "An" trong họ tên<br>- Tìm kiếm không phân biệt dấu |
| **Backend logic** | `WHERE (name LIKE '%An%' OR holyName LIKE '%An%')` |

| Mã | TC-13 |
|-----|-------|
| **Mô tả** | Lọc theo lớp |
| **Input** | Bộ lọc lớp: "Khai Tâm 1A" |
| **Bước thực hiện** | 1. Chọn khối "Khai Tâm"<br>2. Chọn lớp "Khai Tâm 1A" |
| **Kết quả mong đợi** | Chỉ hiển thị thiếu nhi thuộc lớp được chọn |
| **Backend logic** | `WHERE class_id = ?` |

| Mã | TC-14 |
|-----|-------|
| **Mô tả** | Lọc theo giới tính |
| **Input** | Bộ lọc giới tính: "Nam" |
| **Kết quả mong đợi** | Chỉ hiển thị thiếu nhi nam |
| **Backend logic** | `WHERE gender = 1` |

| Mã | TC-15 |
|-----|-------|
| **Mô tả** | Lọc theo độ tuổi |
| **Input** | Từ: 10, Đến: 12 |
| **Kết quả mong đợi** | Hiển thị thiếu nhi từ 10-12 tuổi |
| **Tính toán tuổi** | `YEAR(CURDATE()) - YEAR(birthDate)` |

| Mã | TC-16 |
|-----|-------|
| **Mô tả** | Lọc theo tình trạng |
| **Input** | Tình trạng: "đang sinh hoạt" / "dừng sinh hoạt" / "chuyển đi" |
| **Kết quả mong đợi** | Hiển thị đúng thiếu nhi theo tình trạng |

| Mã | TC-17 |
|-----|-------|
| **Mô tả** | Lọc theo địa chỉ |
| **Input** | Từ khóa: "Nguyễn Trãi" |
| **Kết quả mong đợi** | Hiển thị thiếu nhi có địa chỉ chứa từ khóa |

### 2.2 Thêm Thiếu Nhi

| Mã | TC-18 |
|-----|-------|
| **Mô tả** | Thêm thiếu nhi mới thành công |
| **Bước thực hiện** | 1. Bấm "+ Thêm thiếu nhi"<br>2. Điền đầy đủ thông tin<br>3. Bấm "Lưu" |
| **Input** | - Tên Thánh: "Phêrô"<br>- Họ và Tên: "Nguyễn Văn An"<br>- Giới tính: Nam<br>- Ngày sinh: 05/09/2017<br>- Lớp: Khai Tâm 1A<br>- Tên Cha: Nguyễn Văn Bình<br>- SĐT Cha: 0901234567<br>- Tên Mẹ: Trần Thị Cúc<br>- SĐT Mẹ: 0907654321<br>- Địa chỉ: 12 Nguyễn Trãi |
| **Kết quả mong đợi** | - Toast "Lưu thành công"<br>- Danh sách cập nhật với em mới<br>- Mã số được cấp tự động |
| **Backend logic** | `INSERT INTO students (code, name, holyName, ...)` |
| **CSRF** | Bắt buộc gửi `_csrf` token |
| **Validation** | - Tên không được trống<br>- Lớp phải được chọn |

| Mã | TC-19 |
|-----|-------|
| **Mô tả** | Thêm thiếu nhi thất bại - thiếu thông tin bắt buộc |
| **Bước thực hiện** | 1. Bấm "+ Thêm thiếu nhi"<br>2. Để trống Họ và Tên<br>3. Bấm "Lưu" |
| **Kết quả mong đợi** | Toast warning: "Vui lòng nhập họ và tên." |
| **Frontend validation** | `if (!String(e.name || '').trim()) return warning()` |

| Mã | TC-20 |
|-----|-------|
| **Mô tả** | Mã số thiếu nhi được tự động cấp |
| **Kết quả mong đợi** | Mã số hiển thị dạng "TN26001" (format TN + số) |
| **Backend logic** | Action `next_code` trả về mã kế tiếp |

### 2.3 Sửa Thiếu Nhi

| Mã | TC-21 |
|-----|-------|
| **Mô tả** | Sửa thông tin thiếu nhi |
| **Bước thực hiện** | 1. Bấm vào tên thiếu nhi<br>2. Chỉnh sửa thông tin<br>3. Bấm "Lưu" |
| **Kết quả mong đợi** | - Toast "Lưu thành công"<br>- Dữ liệu cập nhật<br>- Nạp lại từ server để đảm bảo đồng bộ |

| Mã | TC-22 |
|-----|-------|
| **Mô tả** | Chỉnh sửa lớp của thiếu nhi |
| **Bước thực hiện** | 1. Sửa thiếu nhi<br>2. Đổi từ "Khai Tâm 1A" sang "Khai Tâm 2A" |
| **Kết quả mong đợi** | - Khối tự động cập nhật theo lớp<br>- `class.block` được đồng bộ |

| Mã | TC-23 |
|-----|-------|
| **Mô tả** | Cảnh báo khi đóng form có thay đổi chưa lưu |
| **Bước thực hiện** | 1. Mở form sửa<br>2. Thay đổi thông tin (không lưu)<br>3. Bấm nút đóng modal |
| **Kết quả mong đợi** | Modal xác nhận: "Bỏ các thay đổi chưa lưu?" |
| **Logic** | `_editDirty()` so sánh snapshot trước/sau |

| Mã | TC-24 |
|-----|-------|
| **Mô tả** | Auto-save draft khi nhập form |
| **Bước thực hiện** | 1. Mở form thêm thiếu nhi<br>2. Nhập thông tin<br>3. Đợi 1.5 giây |
| **Kết quả mong đợi** | Dữ liệu được lưu tạm vào localStorage |
| **Logic** | `scheduleDraftSave()` debounce 1500ms |

### 2.4 Xóa Thiếu Nhi

| Mã | TC-25 |
|-----|-------|
| **Mô tả** | Xóa một thiếu nhi |
| **Bước thực hiện** | 1. Chọn thiếu nhi cần xóa<br>2. Bấm "Xóa"<br>3. Xác nhận trong modal |
| **Kết quả mong đợi** | - Toast: "Đã xóa 1 em"<br>- Thiếu nhi biến mất khỏi danh sách |
| **Backend logic** | `DELETE FROM students WHERE id = ?` |
| **Cảnh báo** | Hành động không thể hoàn tác |

| Mã | TC-26 |
|-----|-------|
| **Mô tả** | Xóa nhiều thiếu nhi (bulk delete) |
| **Bước thực hiện** | 1. Checkbox chọn nhiều em<br>2. Bấm "Xóa đã chọn" |
| **Kết quả mong đợi** | Modal xác nhận với số lượng: "Xóa vĩnh viễn 5 em?" |

### 2.5 Chuyển Lớp (Bulk Move)

| Mã | TC-27 |
|-----|-------|
| **Mô tả** | Chuyển nhiều thiếu nhi sang lớp khác |
| **Bước thực hiện** | 1. Checkbox chọn các em<br>2. Bấm "Chuyển lớp"<br>3. Chọn lớp đích<br>4. Xác nhận |
| **Kết quả mong đợi** | - Toast: "Đã chuyển 3 em sang lớp XYZ"<br>- Danh sách cập nhật |
| **Backend logic** | `UPDATE students SET class_id = ?, class_name = ?, block = ? WHERE id IN (?)` |

### 2.6 Xuất/Nhập Excel

| Mã | TC-28 |
|-----|-------|
| **Mô tả** | Xuất danh sách thiếu nhi ra Excel |
| **Bước thực hiện** | 1. Lọc danh sách (nếu cần)<br>2. Bấm "Xuất Excel" |
| **Kết quả mong đợi** | File `.xlsx` được tải về với tên `Danh_Sach_Thieu_Nhi_20240115.xlsx` |
| **Sheet columns** | Mã số, Tên Thánh, Họ và Tên, Giới tính, Ngày sinh, Khối, Lớp, Tình trạng, Tên Cha, SĐT Cha, Tên Mẹ, SĐT Mẹ, Địa chỉ |

| Mã | TC-29 |
|-----|-------|
| **Mô tả** | Tải file mẫu khi chưa có dữ liệu |
| **Điều kiện** | Lớp chưa có thiếu nhi nào |
| **Bước thực hiện** | 1. Chọn lớp trống<br>2. Bấm "Xuất Excel" |
| **Kết quả mong đợi** | File mẫu `Mau_Nhap_Danh_Sach_Khai_Tam_1A_20240115.xlsx` được tải về |

| Mã | TC-30 |
|-----|-------|
| **Mô tả** | Nhập danh sách từ Excel |
| **Bước thực hiện** | 1. Bấm "Nhập Excel"<br>2. Chọn file `.xlsx`<br>3. Xem trước dữ liệu<br>4. Xác nhận |
| **Kết quả mong đợi** | - Danh sách thiếu nhi được cập nhật<br>- Toast thông báo số em đã thêm |
| **Validation** | - File phải có cột "Họ và Tên"<br>- Bỏ qua dòng bắt đầu bằng `#` |

### 2.7 Xuất PDF

| Mã | TC-31 |
|-----|-------|
| **Mô tả** | Xuất danh sách ra PDF (dạng bảng) |
| **Bước thực hiện** | 1. Lọc danh sách<br>2. Bấm "In danh sách"<br>3. Chọn "Danh sách (bảng)" |
| **Kết quả mong đợi** | Cửa sổ print mới mở ra với bảng thiếu nhi |

| Mã | TC-32 |
|-----|-------|
| **Mô tả** | Xuất thẻ thiếu nhi (dạng card) |
| **Kết quả mong đợi** | Mỗi em trên một card với thông tin đầy đủ |

### 2.8 Keyboard Shortcuts

| Mã | TC-33 |
|-----|-------|
| **Mô tả** | Phím tắt điều hướng danh sách |
| **Bước thực hiện** | 1. Focus vào danh sách (không focus input)<br>2. Bấm `j` hoặc `k` |
| **Kết quả mong đợi** | Di chuyển chọn xuống (`j`) hoặc lên (`k`) |

| Mã | TC-34 |
|-----|-------|
| **Mô tả** | Phím tắt mở sửa |
| **Bước thực hiện** | 1. Chọn 1 em bằng click hoặc `j/k`<br>2. Bấm `e` |
| **Kết quả mong đợi** | Mở form sửa thiếu nhi |

| Mã | TC-35 |
|-----|-------|
| **Mô tả** | Phím tắt thêm mới |
| **Bước thực hiện** | 1. Focus vào danh sách<br>2. Bấm `n` |
| **Kết quả mong đợi** | Mở form thêm thiếu nhi (chỉ khi có quyền edit) |

| Mã | TC-36 |
|-----|-------|
| **Mô tả** | Copy số điện thoại |
| **Bước thực hiện** | 1. Hover vào icon copy trên SĐT cha/mẹ<br>2. Bấm icon |
| **Kết quả mong đợi** | Toast: "Đã sao chép số điện thoại!" |

---

## 3. Điểm Danh

### 3.1 Mở Buổi Điểm Danh

| Mã | TC-37 |
|-----|-------|
| **Mô tả** | Mở buổi điểm danh trong ngày |
| **URL** | `/#/attendance` |
| **Bước thực hiện** | 1. Vào "Điểm Danh"<br>2. Chọn ngày hôm nay<br>3. Chọn chương trình<br>4. Bấm "Bắt đầu" |
| **Kết quả mong đợi** | - Danh sách thiếu nhi hiển thị<br>- Có thể bấm chọn từng em |
| **Backend logic** | `SELECT * FROM students WHERE class_id IN (scan_class_ids)` |

| Mã | TC-38 |
|-----|-------|
| **Mô tả** | Chỉ hiện buổi đã tới giờ bắt đầu |
| **Điều kiện** | Hiện tại là 7:00, chương trình bắt đầu 7:30 |
| **Kết quả mong đợi** | Buổi "Chương Trình Ngày" bị mờ, không cho mở |
| **Logic** | `programStarted()` so sánh `nowTs` với `startTime` |

| Mã | TC-39 |
|-----|-------|
| **Mô tả** | Hiện buổi quá khứ để sửa |
| **Điều kiện** | Chọn ngày hôm qua |
| **Kết quả mong đợi** | Mọi buổi trong ngày đều hiển thị bình thường |

### 3.2 Điểm Danh

| Mã | TC-40 |
|-----|-------|
| **Mô tả** | Đánh dấu "Có mặt" cho thiếu nhi |
| **Bước thực hiện** | 1. Mở buổi điểm danh<br>2. Bấm vào tên thiếu nhi |
| **Kết quả mong đợi** | - Chip màu xanh "Có mặt"<br>- Gửi API `attendance/toggle`<br>- Ghi nhận `markedBy`, `markedAt` |
| **Backend logic** | `INSERT INTO attendances (program_id, date, student_id, status, marked_by)` |

| Mã | TC-41 |
|-----|-------|
| **Mô tả** | Gỡ điểm danh (bấm lại lần 2) |
| **Bước thực hiện** | 1. Bấm em đã điểm danh |
| **Kết quả mong đợi** | - Xóa trạng thái<br>- Chip trở về "Chưa điểm danh" |
| **Backend logic** | `DELETE FROM attendances WHERE program_id = ? AND date = ? AND student_id = ?` |

| Mã | TC-42 |
|-----|-------|
| **Mô tả** | Tự động đánh "Đi trễ" sau giờ chốt |
| **Điều kiện** | Bấm sau giờ chốt (giờ bắt đầu + 30 phút) |
| **Kết quả mong đợi** | Chip màu vàng "Đi trễ" |
| **Backend logic** | `status = 'đi trễ'` khi `NOW() > cutoff_time` |

| Mã | TC-43 |
|-----|-------|
| **Mô tả** | Chống bấm đúp (double-tap protection) |
| **Bước thực hiện** | Bấm nhanh 2 lần vào cùng một em trong 450ms |
| **Kết quả mong đợi** | Chỉ xử lý 1 lần bấm đầu tiên |
| **Logic** | `_chamGanNhat` kiểm tra timestamp |

### 3.3 Suy ra Vắng

| Mã | TC-44 |
|-----|-------|
| **Mô tả** | Hiển thị "Vắng có phép" khi có đơn xin phép được duyệt |
| **Điều kiện** | Thiếu nhi có đơn nghỉ được duyệt cho ngày này |
| **Kết quả mong đợi** | Chip màu xanh dương "Có phép" |

| Mã | TC-45 |
|-----|-------|
| **Mô tả** | Hiển thị "Vắng không phép" sau giờ chốt |
| **Điều kiện** | - Không có bản ghi điểm danh<br>- Đã quá giờ chốt |
| **Kết quả mong đợi** | Chip màu đỏ "Vắng" |

| Mã | TC-46 |
|-----|-------|
| **Mô tả** | Hiển thị "Chưa điểm danh" khi chưa quá giờ chốt |
| **Kết quả mong đợi** | Chip màu xám "Chưa" |

### 3.4 Thống Kê Buổi

| Mã | TC-47 |
|-----|-------|
| **Mô tả** | Hiển thị số liệu buổi điểm danh |
| **Kết quả mong đợi** | Badge: "Có mặt: 25 | Trễ: 2 | Vắng: 3 | Tổng: 30" |
| **Logic** | `sessionStats` đếm theo từng trạng thái |

### 3.5 Quét QR

| Mã | TC-48 |
|-----|-------|
| **Mô tả** | Quét mã QR trên thẻ thiếu nhi |
| **Bước thực hiện** | 1. Bấm icon QR<br>2. Đưa thẻ vào camera |
| **Kết quả mong đợi** | - Tự động đánh dấu có mặt<br>- Bíp thành công + rung |
| **Camera** | Sử dụng `BarcodeDetector` (native) hoặc `jsQR` (fallback) |

| Mã | TC-49 |
|-----|-------|
| **Mô tả** | Xử lý khi thiết bị ngoại tuyến |
| **Điều kiện** | Mất kết nối internet trong lúc điểm danh |
| **Kết quả mong đợi** | - Lưu vào queue offline<br>- Đồng bộ khi có mạng<br>- Badge hiển thị số chờ đồng bộ |

### 3.6 Xuất Báo Cáo Điểm Danh

| Mã | TC-50 |
|-----|-------|
| **Mô tả** | Xuất Excel báo cáo điểm danh |
| **Bước thực hiện** | 1. Bấm "Xuất Excel"<br>2. Chọn lớp và khoảng ngày<br>3. Bấm "Tải về" |
| **Kết quả mong đợi** | File Excel với chi tiết từng buổi |

---

## 4. Điểm Số

### 4.1 Nhập Điểm

| Mã | TC-51 |
|-----|-------|
| **Mô tả** | Nhập điểm miệng |
| **URL** | `/#/scores` |
| **Bước thực hiện** | 1. Chọn lớp<br>2. Chọn học kỳ<br>3. Chọn cột "Miệng"<br>4. Nhập điểm từng em |
| **Input** | Điểm: 7, 8, 9 (số từ 0-10) |
| **Kết quả mong đợi** | - Điểm được lưu<br>- ĐTB cập nhật real-time |
| **Backend logic** | `INSERT INTO scores (student_id, term_id, type, value)` hoặc `UPDATE` |

| Mã | TC-52 |
|-----|-------|
| **Mô tả** | Nhập điểm không hợp lệ |
| **Input** | Điểm: 15 |
| **Kết quả mong đợi** | Toast: "Điểm phải là số từ 0 đến 10."<br>Ô input được khôi phục giá trị cũ |

| Mã | TC-53 |
|-----|-------|
| **Mô tả** | Xóa điểm bằng cách để trống |
| **Bước thực hiện** | Xóa số trong ô, bấm Tab |
| **Kết quả mong đợi** | Bản ghi điểm bị xóa, ĐTB cập nhật |

### 4.2 Tính Điểm Trung Bình

| Mã | TC-54 |
|-----|-------|
| **Mô tả** | Tính ĐTB có trọng số |
| **Input** | Miệng: 8, 15 phút: 7, Giữa kỳ: 8, Cuối kỳ: 9 |
| **Tính toán** | (8×1 + 7×1 + 8×2 + 9×3) / (1+1+2+3) = 60/7 = 8.6 |
| **Kết quả mong đợi** | ĐTB hiển thị: 8.6 |

| Mã | TC-55 |
|-----|-------|
| **Mô tả** | ĐTB chỉ tính trên cột đã có điểm |
| **Input** | Chỉ có Miệng: 8 |
| **Kết quả mong đợi** | ĐTB = 8.0 (chưa có 15', GK, CK) |

| Mã | TC-56 |
|-----|-------|
| **Mô tả** | Xếp loại học lực |
| **Điều kiện** | ĐTB ≥ 8 → Giỏi, ≥ 6.5 → Khá, ≥ 5 → Trung bình, < 5 → Yếu |
| **Kết quả mong đợi** | Badge màu theo loại |

### 4.3 Xuất Bảng Điểm

| Mã | TC-57 |
|-----|-------|
| **Mô tả** | Xuất bảng điểm Excel |
| **Kết quả mong đợi** | File `Bang_Diem_Khai_Tam_1A.xlsx` với đầy đủ cột điểm |

---

## 5. Chương Trình Sinh Hoạt

### 5.1 Xem Chương Trình

| Mã | TC-58 |
|-----|-------|
| **Mô tả** | Hiển thị danh sách chương trình |
| **URL** | `/#/programs` |
| **Kết quả mong đợi** | Danh sách với: Tên, Thứ, Giờ bắt đầu, Giờ chốt, Loại (bắt buộc/chiến dịch) |

| Mã | TC-59 |
|-----|-------|
| **Mô tả** | Chương trình lặp nhiều thứ |
| **Điều kiện** | Chương trình có `daysOfWeek: [0, 6]` (CN + T7) |
| **Kết quả mong đợi** | Hiển thị "Chúa Nhật, Thứ Bảy" |

| Mã | TC-60 |
|-----|-------|
| **Mô tả** | Chương trình chiến dịch |
| **Điều kiện** | `type: 'chiến dịch'` |
| **Kết quả mong đợi** | Hiển thị ngày cụ thể thay vì thứ trong tuần |

| Mã | TC-61 |
|-----|-------|
| **Mô tả** | Chương trình có khoảng áp dụng |
| **Điều kiện** | `effectiveFrom: '2024-01-01'`, `effectiveTo: '2024-06-30'` |
| **Kết quả mong đợi** | Chỉ hiện trong khoảng ngày quy định |

### 5.2 Tạo/Sửa Chương Trình

| Mã | TC-62 |
|-----|-------|
| **Mô tả** | Tạo chương trình bắt buộc mới |
| **Input** | - Tên: "Chương Trình Ngày"<br>- Loại: Bắt buộc<br>- Thứ: Chúa Nhật<br>- Giờ bắt đầu: 07:30<br>- Giờ chốt: 08:00 |
| **Kết quả mong đợi** | Toast "Lưu thành công"<br>Chương trình xuất hiện trong danh sách |
| **Validation** | - Tên không trống<br>- Giờ chốt > giờ bắt đầu |

| Mã | TC-63 |
|-----|-------|
| **Mô tả** | Tạo chương trình chiến dịch |
| **Input** | - Tên: "Đại Hội Thiếu Nhi"<br>- Loại: Chiến dịch<br>- Ngày: 15/03/2024 |
| **Validation** | Chiến dịch bắt buộc có ngày cụ thể |

| Mã | TC-64 |
|-----|-------|
| **Mô tả** | Bật/tắt chương trình |
| **Bước thực hiện** | Toggle switch trên card chương trình |
| **Kết quả mong đợi** | Trạng thái: "Kích hoạt" ↔ "Đã đóng" |

| Mã | TC-65 |
|-----|-------|
| **Mô tả** | Gắn lớp cho chương trình |
| **Bước thực hiện** | 1. Sửa chương trình<br>2. Chọn các lớp tham gia |
| **Kết quả mong đợi** | Chỉ thiếu nhi thuộc các lớp được chọn mới thấy trong điểm danh |

---

## 6. Khối & Lớp

### 6.1 Quản Lý Khối

| Mã | TC-66 |
|-----|-------|
| **Mô tả** | Tạo khối mới |
| **URL** | `/#/org` |
| **Bước thực hiện** | 1. Bấm "Thêm Khối"<br>2. Nhập tên: "Khai Tâm"<br>3. Lưu |
| **Kết quả mong đợi** | Khối mới xuất hiện trong danh sách |
| **Backend logic** | `INSERT INTO blocks (name)` |

| Mã | TC-67 |
|-----|-------|
| **Mô tả** | Đổi tên khối |
| **Bước thực hiện** | 1. Sửa khối<br>2. Đổi tên |
| **Kết quả mong đợi** | - Tên khối cập nhật<br>- Lớp trong khối giữ nguyên<br>- Thiếu nhi giữ nguyên khối |
| **Cascade** | `UPDATE classes SET block = ? WHERE block = ?`<br>`UPDATE students SET block = ? WHERE block = ?` |

| Mã | TC-68 |
|-----|-------|
| **Mô tả** | Xóa khối có lớp bên trong |
| **Kết quả mong đợi** | Toast cảnh báo: "Khối còn X lớp. Hãy xóa hết lớp trước." |

| Mã | TC-69 |
|-----|-------|
| **Mô tả** | Phân công Trưởng Khối |
| **Bước thực hiện** | 1. Chọn khối<br>2. Chọn thành viên làm Trưởng Khối |
| **Backend logic** | `INSERT INTO assignments (member_id, role, block_id)` |

### 6.2 Quản Lý Lớp

| Mã | TC-70 |
|-----|-------|
| **Mô tả** | Tạo lớp mới |
| **Bước thực hiện** | 1. Chọn khối<br>2. Bấm "Thêm Lớp"<br>3. Nhập tên: "Khai Tâm 1A"<br>4. Lưu |
| **Kết quả mong đợi** | Lớp mới xuất hiện trong khối |
| **Backend logic** | `INSERT INTO classes (name, block_id)` |

| Mã | TC-71 |
|-----|-------|
| **Mô tả** | Sửa tên lớp |
| **Bước thực hiện** | 1. Sửa lớp<br>2. Đổi tên "1A" → "1B" |
| **Cascade** | - Thiếu nhi trong lớp cập nhật tên lớp<br>- Nhân sự cập nhật tên lớp<br>- Thông báo cập nhật tên lớp |

| Mã | TC-72 |
|-----|-------|
| **Mô tả** | Xóa lớp có thiếu nhi |
| **Kết quả mong đợi** | Toast: "Lớp còn X em. Hãy chuyển các em sang lớp khác trước." |

| Mã | TC-73 |
|-----|-------|
| **Mô tả** | Phân công GLV Chủ Nhiệm |
| **Bước thực hiện** | 1. Chọn lớp<br>2. Chọn thành viên làm Chủ Nhiệm |
| **Backend logic** | `INSERT INTO assignments (member_id, role: 'glv_chu_nhiem', class_id)` |

| Mã | TC-74 |
|-----|-------|
| **Mô tả** | Thêm GLV phụ tá vào lớp |
| **Bước thực hiện** | 1. Chọn lớp<br>2. Bấm "Thêm GLV"<br>3. Chọn thành viên |
| **Backend logic** | `INSERT INTO assignments (member_id, role: 'glv', class_id)` |

| Mã | TC-75 |
|-----|-------|
| **Mô tả** | Gỡ phân công GLV |
| **Bước thực hiện** | 1. Click vào GLV đã phân công<br>2. Bấm "Gỡ" |
| **Kết quả mong đợi** | Phân công được xóa, GLV không còn quyền trên lớp |

| Mã | TC-76 |
|-----|-------|
| **Mô tả** | Hiển thị sĩ số lớp |
| **Kết quả mong đợi** | Badge số em bên cạnh tên lớp |
| **Backend logic** | `SELECT class_id, COUNT(*) FROM students GROUP BY class_id` |

---

## 7. Nhân Sự

### 7.1 Xem Danh Sách Nhân Sự

| Mã | TC-77 |
|-----|-------|
| **Mô tả** | Hiển thị danh sách thành viên |
| **URL** | `/#/staff` |
| **Sắp xếp** | Theo cấp bậc: Admin > BĐH > Trưởng Khối > GLV Chủ Nhiệm > GLV |

| Mã | TC-78 |
|-----|-------|
| **Mô tả** | Lọc theo vai trò |
| **Input** | Chọn: "Giáo Lý Viên" |
| **Kết quả mong đợi** | Chỉ hiển thị thành viên có vai `glv` |

| Mã | TC-79 |
|-----|-------|
| **Mô tả** | Tìm kiếm thành viên |
| **Input** | Từ khóa: "Nguyễn" |
| **Kết quả mong đợi** | Tìm theo họ tên, tên thánh, lớp/phụ trách |

### 7.2 Duyệt Đăng Ký Mới

| Mã | TC-80 |
|-----|-------|
| **Mô tả** | Duyệt tài khoản mới đăng ký |
| **Điều kiện** | Có tài khoản status = 'chờ duyệt' |
| **Bước thực hiện** | 1. Vào mục "Chờ duyệt"<br>2. Bấm "Duyệt"<br>3. Chọn vai trò mặc định: GLV |
| **Kết quả mong đợi** | - Status đổi sang 'đang phục vụ'<br>- Thành viên xuất hiện trong danh sách |
| **Backend logic** | `UPDATE members SET status = 'đang phục vụ', role_code = ? WHERE id = ?` |

| Mã | TC-81 |
|-----|-------|
| **Mô tả** | Từ chối đăng ký |
| **Bước thực hiện** | 1. Bấm "Từ chối"<br>2. Xác nhận |
| **Kết quả mong đợi** | Tài khoản bị xóa hoàn toàn |

### 7.3 Quản Lý Thành Viên

| Mã | TC-82 |
|-----|-------|
| **Mô tả** | Sửa thông tin thành viên |
| **Bước thực hiện** | 1. Bấm vào thành viên<br>2. Sửa thông tin<br>3. Lưu |
| **Validation** | - Họ tên không trống<br>- Vai trò phù hợp với scope |

| Mã | TC-83 |
|-----|-------|
| **Mô tả** | Sửa vai trò thành viên |
| **Bước thực hiện** | 1. Sửa thành viên<br>2. Đổi vai từ GLV → Trưởng Khối<br>3. Chọn khối phụ trách |
| **Backend logic** | `UPDATE members SET role_code = ?, block = ? WHERE id = ?` |

| Mã | TC-84 |
|-----|-------|
| **Mô tả** | Không sửa được vai trò thành viên đã kiêm nhiệm |
| **Điều kiện** | Thành viên có phân công kiêm nhiệm |
| **Kết quả mong đợi** | Ô vai trò bị khóa, chỉ sửa được danh tính |

| Mã | TC-85 |
|-----|-------|
| **Mô tả** | Không sửa được tài khoản admin/BĐH |
| **Điều kiện** | Thành viên có vai `admin` hoặc `bdh` |
| **Kết quả mong đợi** | Không cho phép sửa từ màn Nhân Sự |

### 7.4 Cấp Lại Mật Khẩu

| Mã | TC-86 |
|-----|-------|
| **Mô tả** | Cấp lại mật khẩu cho thành viên |
| **Bước thực hiện** | 1. Bấm "Cấp lại mật khẩu"<br>2. Xác nhận |
| **Kết quả mong đợi** | Toast hiển thị mật khẩu tạm MỘT LẦN DUY NHẤT |
| **Backend logic** | - Sinh mật khẩu ngẫu nhiên<br>- Gửi SMS hoặc hiển thị<br>- Set `must_change_pw = true` |

### 7.5 Gán vai Thủ Thư

| Mã | TC-87 |
|-----|-------|
| **Mô tả** | Thêm vai Thủ Thư cho thành viên |
| **Bước thực hiện** | 1. Mở thành viên<br>2. Toggle "Thủ Thư" |
| **Kết quả mong đợi** | Phân công `role: 'thu_thu'` được tạo |

### 7.6 Xóa Thành Viên

| Mã | TC-88 |
|-----|-------|
| **Mô tả** | Xóa thành viên |
| **Bước thực hiện** | 1. Bấm "Xóa"<br>2. Xác nhận |
| **Kết quả mong đợi** | Thành viên biến mất khỏi danh sách |
| **Validation** | Không cho xóa admin/BĐH |

---

## 8. Thông Báo

### 8.1 Xem Thông Báo

| Mã | TC-89 |
|-----|-------|
| **Mô tả** | Hiển thị thông báo đã phát |
| **URL** | `/#/announcements` |
| **Điều kiện hiển thị** | - status = 'đã phát'<br>- Chưa hết hạn<br>- Đúng đối tượng (toàn đoàn/khối/lớp) |

| Mã | TC-90 |
|-----|-------|
| **Mô tả** | Badge số thông báo chưa đọc |
| **Kết quả mong đợi** | Badge đỏ trên icon thông báo |

| Mã | TC-91 |
|-----|-------|
| **Mô tả** | Đánh dấu đã đọc |
| **Bước thực hiện** | 1. Mở thông báo<br>2. Đọc nội dung |
| **Kết quả mong đợi** | Badge giảm, ID được lưu vào `readAnnouncements` |

| Mã | TC-92 |
|-----|-------|
| **Mô tả** | Đánh dấu tất cả đã đọc |
| **Bước thực hiện** | Bấm "Đánh dấu tất cả đã đọc" |
| **Kết quả mong đợi** | Tất cả badge biến mất |

### 8.2 Tạo Thông Báo

| Mã | TC-93 |
|-----|-------|
| **Mô tả** | Phát thông báo toàn đoàn |
| **Input** | - Tiêu đề: "Thông báo lịch sinh hoạt"<br>- Nội dung: "Ngày mai nghỉ..."<br>- Mức độ: Thường<br>- Đối tượng: Toàn đoàn |
| **Kết quả mong đợi** | - Toast thành công<br>- Mọi thành viên đều thấy |
| **Backend logic** | `INSERT INTO announcements (title, body, audience_type, ...)` |

| Mã | TC-94 |
|-----|-------|
| **Mô tả** | Phát thông báo cho khối |
| **Input** | Đối tượng: Khối "Khai Tâm" |
| **Kết quả mong đợi** | Chỉ thành viên thuộc khối Khai Tâm thấy |

| Mã | TC-95 |
|-----|-------|
| **Mô tả** | Phát thông báo cho lớp |
| **Input** | Đối tượng: Lớp "Khai Tâm 1A" |
| **Kết quả mong đợi** | Chỉ GLV và thiếu nhi lớp 1A thấy |

| Mã | TC-96 |
|-----|-------|
| **Mô tả** | Thông báo có lịch họp |
| **Input** | Bật "Có lịch họp"<br>- Thời gian: 15/03/2024 14:00<br>- Địa điểm: Nhà thờ |
| **Kết quả mong đợi** | Hiển thị thông tin RSVP |

| Mã | TC-97 |
|-----|-------|
| **Mô tả** | Thông báo mức Khẩn |
| **Input** | Mức độ: Khẩn |
| **Kết quả mong đợi** | Nền đỏ nổi bật trong danh sách |

### 8.3 Quản Lý Thông Báo

| Mã | TC-98 |
|-----|-------|
| **Mô tả** | Sửa thông báo đã phát |
| **Bước thực hiện** | 1. Mở thông báo<br>2. Bấm "Sửa" |
| **Kết quả mong đợi** | Có thể chỉnh sửa nội dung |

| Mã | TC-99 |
|-----|-------|
| **Mô tả** | Thu hồi thông báo |
| **Bước thực hiện** | 1. Mở thông báo<br>2. Bấm "Thu hồi" |
| **Kết quả mong đợi** | Status đổi sang 'nháp', người đọc không còn thấy |

| Mã | TC-100 |
|-----|-------|
| **Mô tả** | Xóa thông báo |
| **Bước thực hiện** | Bấm "Xóa" |
| **Kết quả mong đợi** | Thông báo biến mất |

| Mã | TC-101 |
|-----|-------|
| **Mô tả** | Trưởng Khối chỉ sửa thông báo của khối mình |
| **Điều kiện** | Vai `truong_khoi` phụ trách khối "Khai Tâm" |
| **Kết quả mong đợi** | Không sửa được thông báo khối "Huynh Trưởng" |

---

## 9. Sổ Mộc & Đổi Quà

### 9.1 Tra Cứu Sổ Mộc

| Mã | TC-102 |
|-----|-------|
| **Mô tả** | Xem sổ mộc của thiếu nhi |
| **URL** | `/#/gifts` hoặc từ hồ sơ thiếu nhi |
| **Kết quả mong đợi** | - Tổng Mộc hiện có<br>- Lịch sử giao dịch |

### 9.2 Đổi Quà (POS)

| Mã | TC-103 |
|-----|-------|
| **Mô tả** | Quét thẻ và bắt đầu đổi quà |
| **Bước thực hiện** | 1. Nhập mã thẻ hoặc quét QR<br>2. Xem số Mộc khả dụng |
| **Kết quả mong đợi** | Hiển thị thông tin em + số Mộc |

| Mã | TC-104 |
|-----|-------|
| **Mô tả** | Thêm quà vào giỏ |
| **Bước thực hiện** | 1. Chọn quà muốn đổi<br>2. Bấm "+" |
| **Validation** | - Đủ Mộc<br>- Còn hàng trong kho |

| Mã | TC-105 |
|-----|-------|
| **Mô tả** | Xác nhận đổi quà |
| **Bước thực hiện** | 1. Kiểm tra giỏ hàng<br>2. Bấm "Đổi quà"<br>3. Xác nhận |
| **Kết quả mong đợi** | - Mộc được trừ<br>- Tồn quà giảm<br>- Bíp thành công |
| **Backend logic** | `INSERT INTO reward_transactions`<br>`UPDATE gifts SET stock = stock - ?` |

| Mã | TC-106 |
|-----|-------|
| **Mô tả** | Không đủ Mộc để đổi |
| **Input** | Giỏ hàng: 50 Mộc, Số dư: 30 Mộc |
| **Kết quả mong đợi** | Toast cảnh báo, không cho xác nhận |

| Mã | TC-107 |
|-----|-------|
| **Mô tả** | Hết hàng |
| **Kết quả mong đợi** | Thẻ quà bị mờ, hiển thị "Hết hàng" |

### 9.3 Xác Nhận Đơn Đặt Trước

| Mã | TC-108 |
|-----|-------|
| **Mô tả** | Giao đơn đặt trước với mật mã |
| **Bước thực hiện** | 1. Quét thẻ em<br>2. Nhập mật mã đổi quà của em<br>3. Bấm "Xác nhận" |
| **Kết quả mong đợi** | Đơn được giao, Mộc trừ |

| Mã | TC-109 |
|-----|-------|
| **Mô tả** | Giao đơn bằng quyền Thủ Thư (quên mật mã) |
| **Bước thực hiện** | 1. Nhập sai mật mã 3 lần<br>2. Bấm "Giao bằng quyền Thủ Thư" |
| **Kết quả mong đợi** | Ghi log riêng cho việc override |

---

## 10. Báo Cáo

### 10.1 Báo Cáo Điểm Danh

| Mã | TC-110 |
|-----|-------|
| **Mô tả** | Xem báo cáo điểm danh theo tháng |
| **URL** | `/#/reports` |
| **Input** | - Tháng: 01/2024<br>- Lớp: Khai Tâm 1A |
| **Kết quả mong đợi** | Biểu đồ/table điểm danh từng ngày |

### 10.2 Xếp Hạng

| Mã | TC-111 |
|-----|-------|
| **Mô tả** | Xem bảng xếp hạng thiếu nhi |
| **URL** | `bxh.php` (công khai) |
| **Tiêu chí** | - Điểm chuyên cần<br>- Điểm học tập |
| **Kết quả mong đợi** | Top 10 hoặc Top 20 |

### 10.3 Thống Kê

| Mã | TC-112 |
|-----|-------|
| **Mô tả** | Xem dashboard thống kê |
| **URL** | `/#/stats` |
| **Kết quả mong đợi** | - Tổng số thiếu nhi<br>- Tỷ lệ điểm danh<br>- Biểu đồ theo khối |

---

## 11. Trang Công Khai

### 11.1 Tra Cứu Thiếu Nhi

| Mã | TC-113 |
|-----|-------|
| **Mô tả** | Tra cứu thông tin thiếu nhi |
| **URL** | `tracuu.php` |
| **Input** | Mã số hoặc tên |
| **Kết quả mong đợi** | - Họ tên, lớp, tình trạng<br>- Không có thông tin nhạy cảm |
| **Security** | Không hiển thị: địa chỉ, SĐT phụ huynh |

### 11.2 Sổ Mộc Online

| Mã | TC-114 |
|-----|-------|
| **Mô tả** | Xem sổ mộc công khai |
| **URL** | `somoc.php` |
| **Input** | Mã số thiếu nhi |
| **Kết quả mong đợi** | Số Mộc hiện có, lịch sử đổi quà |

### 11.3 Đặt Quà Online

| Mã | TC-115 |
|-----|-------|
| **Mô tả** | Thiếu nhi đặt quà trực tuyến |
| **URL** | `somoc.php?action=order` |
| **Bước thực hiện** | 1. Nhập mã thẻ<br>2. Chọn quà<br>3. Nhập mật mã<br>4. Xác nhận |
| **Kết quả mong đợi** | Đơn được tạo, chờ Thủ Thư xác nhận |

---

## 12. Cài Đặt

### 12.1 Hồ Sơ Cá Nhân

| Mã | TC-116 |
|-----|-------|
| **Mô tả** | Xem và sửa hồ sơ cá nhân |
| **URL** | `/#/settings` |
| **Input** | - Họ và tên<br>- Ngày sinh<br>- SĐT |
| **Kết quả mong đợi** | Thông tin cập nhật |

### 12.2 Đổi Mật Khẩu

| Mã | TC-117 |
|-----|-------|
| **Mô tả** | Đổi mật khẩu cá nhân |
| **Input** | - Mật khẩu cũ<br>- Mật khẩu mới<br>- Xác nhận mật khẩu mới |
| **Validation** | - Mật khẩu mới khác mật khẩu cũ<br>- Độ dài tối thiểu 6 ký tự |

| Mã | TC-118 |
|-----|-------|
| **Mô tả** | Đổi mật khẩu khi bị buộc |
| **Điều kiện** | must_change_pw = true |
| **Kết quả mong đợi** | Không cho phép truy cập app cho đến khi đổi |

### 12.3 Niên Khoá

| Mã | TC-119 |
|-----|-------|
| **Mô tả** | Chuyển niên khoá |
| **URL** | `/#/years` |
| **Bước thực hiện** | 1. Chọn niên khoá mới<br>2. Xác nhận |
| **Kết quả mong đợi** | Dữ liệu lọc theo niên khoá mới |

---

## Phụ Lục

### A. Mã Lỗi HTTP

| Code | Ý nghĩa |
|------|---------|
| 200 | Thành công |
| 401 | Chưa đăng nhập |
| 403 | Không có quyền / Tài khoản bị khóa |
| 404 | Không tìm thấy |
| 413 | Request body quá lớn (>1MB) |
| 429 | Rate limit - thử lại sau |

### B. Response Format

```json
// Thành công
{ "ok": true, "data": {...} }

// Thất bại
{ "ok": false, "error": "Thông báo lỗi" }

// Validation
{ "ok": false, "error": "Tên không được trống", "field": "name" }
```

### C. Các Module Chính

| Module Key | Tên | Vai được phép |
|-----------|-----|---------------|
| students | Thiếu Nhi | GLV+, view |
| attendance | Điểm Danh | GLV+, view |
| scores | Điểm Số | GLV+, edit |
| programs | Chương Trình | BĐH+, edit |
| org | Khối & Lớp | BĐH+, edit |
| staff | Nhân Sự | BĐH+, edit |
| announcements | Thông Báo | GLV+, edit |
| rewards | Sổ Mộc | Thu Thu, Admin |
| reports | Báo Cáo | BĐH+ |
| gifts | Quà | Admin |

### D. Test Data Mẫu

```
Tài khoản test:
- Admin: 0901000001 / admin123
- BĐH: 0901000002 / bdh123
- Trưởng Khối: 0901000003 / tk123
- GLV: 0901000004 / glv123

Khối: Khai Tâm, Hiền, Huynh Trưởng
Lớp: Khai Tâm 1A, 1B, 2A, 2B
```

---

*Kịch bản này được tạo tự động từ phân tích codebase TNTT Super App*
