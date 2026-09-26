# TÀI LIỆU Ý TƯỞNG & YÊU CẦU NGHIỆP VỤ (BRD)
**Tính năng:** Hệ thống Sổ Mộc Điện Tử & Trạm Đổi Quà
**Dự án:** Web Quản lý Đoàn Thiếu Nhi Thánh Thể (TNTT)

---

## 1. BỐI CẢNH & MỤC TIÊU
* **Thực trạng:** Giáo xứ đang sử dụng "Sổ Mộc" giấy để khuyến khích các em đi lễ hằng ngày. Tuy nhiên, sổ giấy dễ rách, mất, dễ bị đóng mộc khống (gian lận), và gây mất nhiều thời gian cho Thủ thư khi đếm mộc để đổi quà.
* **Mục tiêu:** Tận dụng hệ thống điểm danh QR hiện có để "số hóa" hoàn toàn Sổ Mộc. Biến nó thành một hệ thống "Ví điểm thưởng" (Gamification) tự động, minh bạch và tạo động lực mạnh mẽ cho Thiếu nhi.

## 2. QUY TẮC THI ĐUA & TÍCH LŨY (GAMIFICATION RULES)
Hệ thống xoay quanh 2 khái niệm cốt lõi: **Ví Mộc** (Điểm để mua sắm) và **Chuỗi - Streak** (Động lực giữ thói quen).

**2.1. Quy tắc cộng Mộc cơ bản (Kiếm tiền):**
Hệ thống tự động lắng nghe hành động "Điểm danh thành công" (qua quét QR hoặc GLV điểm danh tay) để cộng mộc.
* Đi lễ ngày thường (Thứ 2 - Thứ 7): **+1 Mộc / lần**.
* Đi lễ Chúa Nhật (Ngày trọng tâm): **+2 Mộc / lần**.

**2.2. Cơ chế Thưởng Chuỗi (Streak Bonus):**
Giống như các ứng dụng học tập (Duolingo), việc đi lễ liên tục không ngắt quãng sẽ được thưởng lớn để tránh tình trạng các em lười biếng.
* Đạt chuỗi **3 ngày liên tiếp**: Thưởng thêm **+1 Mộc**.
* Đạt chuỗi **7 ngày liên tiếp**: Thưởng thêm **+3 Mộc**.
* Đạt chuỗi **30 ngày liên tiếp**: Thưởng thêm **+15 Mộc**.
* *Hình phạt:* Bỏ lỡ đi lễ 1 ngày -> Chuỗi (Streak) lập tức bị reset về `0`. (Tuy nhiên số Mộc trong ví không bị mất).

## 3. CÁC LUỒNG TRẢI NGHIỆM CHÍNH (USER FLOWS)

**Luồng 1: Trạm Đổi Quà (Dành cho Thủ thư Thư viện)**
* **Giao diện:** Tích hợp vào module Thư viện hiện tại, tạo ra một màn hình "Máy tính tiền - POS" rất nhanh gọn.
* **Thao tác:** 
  1. Thiếu nhi đưa thẻ QR. Thủ thư quét thẻ bằng camera của web.
  2. Màn hình tự động nảy lên: Tên + Phân đoàn + **Số dư Mộc hiện tại**.
  3. Bên dưới hiển thị danh sách Quà tặng (Truyện tranh: 15 mộc, Tràng hạt: 10 mộc...).
  4. Nếu số dư đủ, món quà sáng lên. Thủ thư bấm chọn quà -> Hệ thống tự động trừ Mộc, phát âm thanh "Ting" thành công và lưu lịch sử.

**Luồng 2: Hồ sơ Thiếu nhi (Dành cho Huynh trưởng)**
* Trên ứng dụng của GLV, khi bấm vào Hồ sơ của một em, GLV sẽ thấy ngay một thanh trạng thái nổi bật:
  * 👛 **Ví Mộc:** Số mộc đang có (để xài) / Tổng mộc đã kiếm từ đầu năm (để xét thi đua cuối năm).
  * 🔥 **Ngọn lửa Chuỗi:** Số ngày đi lễ liên tiếp (Vd: 🔥 5 ngày).

**Luồng 3: Cổng Tra cứu (Dành cho Phụ huynh / Thiếu nhi)** *(Phát triển sau)*
* Một trang web public đơn giản. Các em nhập Mã thẻ QR của mình vào để tự xem "Sổ mộc" ở nhà. Nhìn thấy ngọn lửa chuỗi đang cháy sẽ thôi thúc các em sáng mai đi lễ tiếp để không bị đứt chuỗi.

## 4. YÊU CẦU DÀNH CHO LẬP TRÌNH VIÊN THỰC THI (DEV NOTES)
Để bạn lập trình viên nắm bắt nhanh hướng thiết kế kỹ thuật (Technical Design):

1. **Database Schema:** Cần thiết kế tách bạch:
   * Bảng `student_stamps` (Ví tổng): Lưu `current_balance`, `total_earned`, `current_streak`, `last_attendance_date`.
   * Bảng `stamp_transactions` (Lịch sử/Sao kê): Cực kỳ quan trọng để chống gian lận (Audit trail). Phải ghi rõ: Ngày X, cộng/trừ bao nhiêu mộc, lý do (Đi lễ / Thưởng chuỗi / Đổi quà).
   * Bảng `gifts` (Danh mục quà): Tên quà, Giá mộc, Số lượng tồn kho.
2. **Backend Logic:** 
   * Thuật toán tính Chuỗi (Streak) và Cộng mộc cần được gắn (hook) vào ngay sau lệnh `INSERT` điểm danh thành công của API hiện tại.
   * Cần chú ý xử lý bất đồng bộ hoặc chạy ngầm để luồng quét QR hàng loạt (batch scan 200 em) ở sân nhà thờ không bị chậm đi do phải tính toán chuỗi quá lâu.
3. **Frontend:** 
   * Giao diện Trạm Đổi Quà ưu tiên sử dụng `Alpine.js` để làm thao tác quét -> chọn quà -> trừ điểm mượt mà, không cần tải lại trang (SPA feeling). Đảm bảo giao diện tối giản để Thủ thư thao tác bằng 1 tay trên điện thoại/tablet.
