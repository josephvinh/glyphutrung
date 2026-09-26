# ĐẶC TẢ HỆ THỐNG: SỔ MỘC ĐIỆN TỬ & ĐỔI QUÀ
*(Digital Stamp & Reward System)*

## 1. MỤC TIÊU
Số hóa hoàn toàn quá trình tích lũy Mộc (điểm thưởng) khi Thiếu nhi đi lễ hằng ngày. Tự động hóa việc cộng điểm, tính chuỗi liên tiếp (Streak) để kích thích phong trào thi đua. Xây dựng Trạm đổi quà điện tử cho Thư viện để quản lý minh bạch kho quà và số dư Mộc của các em.

## 2. QUY TẮC NGHIỆP VỤ (GAMIFICATION)
* **Earn (Tích lũy):** Tự động kích hoạt khi có dữ liệu điểm danh / quét mã đi lễ.
  * Lễ thường: `+1 Mộc`
  * Lễ Chúa Nhật: `+2 Mộc`
* **Streak Bonus (Thưởng chuỗi):** Tính số ngày đi lễ liên tiếp (không ngắt quãng).
  * Đạt 3 ngày liên tiếp: `+1 Mộc`
  * Đạt 7 ngày liên tiếp: `+3 Mộc`
  * Đạt 30 ngày liên tiếp: `+15 Mộc`
  * *Lưu ý:* Bỏ lỡ 1 ngày đi lễ thì Chuỗi sẽ bị reset về `0`.
* **Spend (Tiêu dùng):** Trừ Mộc khi đổi quà tại Thư viện. Số dư không được nhỏ hơn 0.

## 3. THIẾT KẾ CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)
Đề xuất bổ sung/cập nhật 3 bảng trong CSDL:

**Bảng `student_stamps` (Ví Mộc của Thiếu nhi)**
Lưu trữ tổng quan trạng thái tài khoản mộc của mỗi học sinh.
* `student_id` (Khóa ngoại)
* `current_balance` (Số mộc đang có thể dùng)
* `total_earned` (Tổng số mộc đã kiếm được từ trước đến nay - dùng cho Bảng xếp hạng)
* `current_streak` (Số ngày đi lễ liên tục hiện tại)
* `longest_streak` (Chuỗi dài nhất từng đạt được)
* `last_attendance_date` (Ngày đi lễ gần nhất để check đứt chuỗi)

**Bảng `stamp_transactions` (Lịch sử giao dịch - Audit log)**
Sao kê chi tiết mọi biến động để đảm bảo minh bạch.
* `id`
* `student_id`
* `amount` (Ví dụ: `+1`, `+3`, `-10`)
* `type` (`attendance`, `streak_bonus`, `spend`, `manual_adjust`)
* `description` (Vd: "Đi lễ chiều 15/10", "Đổi truyện tranh", "Thưởng chuỗi 7 ngày")
* `created_at`

**Bảng `gifts` (Danh mục Quà tặng Thư viện)**
* `id`
* `name` (Tên quà: Vd: Bút bi, Cuốn truyện, Tràng hạt...)
* `stamp_cost` (Giá trị quy đổi bằng Mộc)
* `stock` (Số lượng tồn kho)
* `image_url` (Hình ảnh - Tùy chọn)

## 4. LUỒNG GIAO DIỆN VÀ TÍNH NĂNG (UI/UX)

### 4.1. Cập nhật `module_student_profile.php` (Hồ sơ Thiếu nhi)
* Thêm một thẻ (Tab) hoặc Card hiển thị:
  * **Biểu tượng Ví:** `Số mộc hiện có` / `Tổng mộc`.
  * **Biểu tượng Lửa (Fire):** `Chuỗi hiện tại (🔥 5 ngày)`.
  * Dưới cùng là danh sách `Lịch sử giao dịch` ngắn gọn.

### 4.2. Xây dựng "Trạm Đổi Quà" trong `module_library.php`
* **Màn hình 1: Quét thẻ**
  * Tích hợp lại module Camera (`qrscan.js`) hoặc ô nhập mã thẻ. Thủ thư quét thẻ của em thiếu nhi đang đứng trước mặt.
* **Màn hình 2: Cửa hàng (POS - Point of Sale)**
  * Sau khi quét, hiển thị to tên em đó và **Số mộc đang có**.
  * Bên dưới hiển thị Grid (dạng lưới) các món quà.
  * Nếu món quà có giá `> số mộc đang có`: Nút mờ đi (Disabled).
  * Nếu món quà có giá `<= số mộc đang có`: Nút màu nổi bật. Thủ thư bấm vào -> Hỏi xác nhận "Chắc chắn đổi [Tên quà] trừ [X] Mộc?" -> Bấm OK.
  * Phát âm thanh báo thành công, trừ mộc, ghi log, màn hình quay lại trạng thái quét thẻ tiếp theo.

### 4.3. Cổng tra cứu Public (Tính năng mở rộng - Phase 2)
* Xây dựng trang `tracuu.php` độc lập.
* Học sinh/Phụ huynh nhập số điện thoại hoặc mã thẻ để tự xem sổ Mộc ở nhà, xem mình đang có bao nhiêu Mộc, chuỗi bao nhiêu ngày để có động lực ngày mai tiếp tục đi lễ.

## 5. KẾ HOẠCH TRIỂN KHAI PHẦN MỀM (IMPLEMENTATION PLAN)
* **Bước 1:** Cấu trúc Database (Tạo 3 bảng mới trên CSDL).
* **Bước 2:** Viết API Backend logic (PHP) xử lý tự động cộng Mộc và tính Chuỗi khi lưu điểm danh hằng ngày.
* **Bước 3:** Cập nhật UI Hồ sơ thiếu nhi (`module_student_profile.php`) để GLV có thể xem nhanh số dư Mộc và Lửa Chuỗi.
* **Bước 4:** Xây dựng màn hình UI "Trạm Đổi Quà" bằng Alpine.js cho Thủ thư thư viện.
* **Bước 5:** Test và tinh chỉnh thông báo.
