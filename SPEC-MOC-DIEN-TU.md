# ĐẶC TẢ HỆ THỐNG: SỔ MỘC ĐIỆN TỬ & ĐỔI QUÀ
*(Digital Stamp & Reward System)*

## 1. MỤC TIÊU
Số hóa hoàn toàn quá trình tích lũy Mộc (điểm thưởng) khi Thiếu nhi đi lễ hằng ngày. Tự động hóa việc cộng điểm, tính chuỗi liên tiếp (Streak). 
Đặc biệt, cung cấp một **Cổng tra cứu công khai (Public Portal)** để các em tự theo dõi số mộc của mình và **"Đặt đổi quà trước"**, giúp quá trình phát quà tại Thư viện diễn ra nhanh chóng, minh bạch và thú vị.

## 2. QUY TẮC NGHIỆP VỤ (GAMIFICATION)
* **Earn (Tích lũy):** Tự động kích hoạt khi có dữ liệu điểm danh / quét mã đi lễ.
  * Lễ thường: `+1 Mộc`
  * Lễ Chúa Nhật: `+2 Mộc`
* **Streak Bonus (Thưởng chuỗi):** Tính số ngày đi lễ liên tiếp.
  * Đạt 3 ngày liên tiếp: `+1 Mộc`
  * Đạt 7 ngày liên tiếp: `+3 Mộc`
  * Đạt 30 ngày liên tiếp: `+15 Mộc`
  * *Lưu ý:* Bỏ lỡ 1 ngày đi lễ thì Chuỗi sẽ bị reset về `0`.

## 3. THIẾT KẾ CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)
Bổ sung 4 bảng mới vào CSDL:

**3.1. Bảng `student_stamps` (Ví Mộc)**
* `student_id` (Khóa ngoại)
* `current_balance` (Số mộc khả dụng)
* `total_earned` (Tổng số mộc đã kiếm)
* `current_streak` (Chuỗi ngày đi lễ liên tục)
* `longest_streak` (Chuỗi dài nhất)
* `last_attendance_date` (Ngày đi lễ gần nhất)

**3.2. Bảng `stamp_transactions` (Lịch sử giao dịch)**
* `id`, `student_id`, `amount`, `type` (`attendance`, `streak_bonus`, `spend`, `refund`), `description`, `created_at`.

**3.3. Bảng `gifts` (Danh mục Quà tặng)**
* `id`, `name`, `stamp_cost`, `stock`, `image_url`, `is_active`.

**3.4. Bảng `gift_orders` (Đơn đặt quà trước)**
* `id`
* `student_id`
* `gift_id`
* `exchange_code` (Mật mã đổi quà - do Thiếu nhi tự đặt khi order)
* `status` (`pending` - đang chờ đổi, `fulfilled` - đã nhận quà, `cancelled` - đã hủy)
* `created_at`
* `fulfilled_at`

## 4. LUỒNG GIAO DIỆN VÀ TÍNH NĂNG (UI/UX)

### 4.1. Module Quản Lý Quà Tặng (Dành cho Admin/Thủ thư)
* Thêm một tab "Quản lý Quà" trong `module_library.php` (hoặc module riêng).
* **Tính năng:**
  * Thêm, sửa, xóa danh mục quà (Tên quà, số Mộc cần đổi, số lượng tồn kho).
  * Ẩn/hiện món quà (khi hết hàng).

### 4.2. Cổng Tra Cứu Công Khai (Sổ Mộc Của Em)
* **Truy cập:** Mở qua một đường link riêng (vd: `somoc.php`).
* **Đăng nhập:** Các em chỉ cần nhập **Mã Thiếu Nhi**.
* **Màn hình chính (Sổ Mộc):**
  * Hiển thị to, rõ: Số dư Mộc hiện tại và 🔥 Lửa Chuỗi.
  * Hiển thị khu vực **"Mã Đổi Quà Của Bạn"** (những món quà đã đặt trước nhưng chưa nhận).
* **Màn hình Đổi Quà (Cửa hàng):**
  * Hiển thị danh sách quà tặng (có hình ảnh, số Mộc yêu cầu).
  * Nút "Đổi món này": Trình duyệt sẽ kiểm tra xem số dư mộc có đủ không.
  * **Luồng "Đặt đổi trước":**
    1. Em bấm chọn món quà vừa ý.
    2. Nhập lại Mã Thiếu Nhi (để xác nhận) và tự tạo một **"Mật mã đổi quà"** (vd: PIN 4 số hoặc 1 chữ ngắn).
    3. Hệ thống báo thành công, tạo một đơn hàng trạng thái `pending` (Lưu ý: Mộc sẽ tạm thời bị "giam" lại để tránh em đó đặt lố số mộc đang có).
    4. Trở về màn hình chính, mã đổi quà sẽ hiện lên.

### 4.3. Trạm Phát Quà Tại Thư Viện (Dành cho GLV/Thủ thư)
* Tích hợp vào `module_library.php`.
* **Luồng làm việc:**
  1. Thiếu nhi lên thư viện, đưa thẻ QR hoặc đọc Mã Thiếu Nhi.
  2. Thủ thư quét thẻ/nhập mã trên hệ thống.
  3. Hệ thống hiện ra **Danh sách món quà em đó đã "Đặt đổi trước"**.
  4. Thủ thư hỏi: "Đọc mật mã đổi quà của em!".
  5. Thủ thư nhập Mật mã vào hệ thống để xác thực.
  6. Nếu đúng: Hệ thống đánh dấu đơn hàng là `fulfilled`, **chính thức trừ mộc**, vô hiệu hóa mật mã, và giảm số lượng tồn kho (stock).
  7. Thủ thư trao quà cho em.

## 5. KẾ HOẠCH TRIỂN KHAI PHẦN MỀM
* **Giai đoạn 1:** Cấu trúc Database (4 bảng) và API Backend tính Mộc (như đã làm).
* **Giai đoạn 2:** Viết Module Quản lý Quà Tặng (CRUD cơ bản cho Admin).
* **Giai đoạn 3:** Xây dựng Cổng Public `somoc.php` cho Thiếu nhi tra cứu và Đặt quà.
* **Giai đoạn 4:** Cập nhật UI Trạm quét mã Đổi Quà trong Thư viện dành cho Thủ thư.
* **Giai đoạn 5:** Test toàn diện luồng (Tích mộc -> Đặt quà -> Xác thực mật mã -> Trừ mộc).
