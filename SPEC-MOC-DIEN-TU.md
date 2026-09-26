# ĐẶC TẢ HỆ THỐNG: SỔ MỘC ĐIỆN TỬ & ĐỔI QUÀ
*(Digital Stamp & Reward System)*

> **Trạng thái:** Đã chốt thiết kế (v2) sau khi phân tích codebase hiện tại.
> Bản này thay cho bản nháp đầu tiên — mọi quy tắc dưới đây đã được đối chiếu
> với `attendances`, `programs`, `attendance.php`, `programs.php` thực tế.

## 1. MỤC TIÊU
Số hóa hoàn toàn quá trình tích lũy **Mộc** (điểm thưởng) khi Thiếu nhi đi lễ
hằng ngày. Tự động cộng Mộc, tính **chuỗi ngày đi lễ liên tiếp (Streak)** để kích
thích phong trào thi đua. Xây dựng **Trạm Đổi Quà điện tử** cho Thư viện để quản
lý minh bạch kho quà và số dư Mộc của các em.

Sổ Mộc là **tầng phần thưởng (gamification) của phong trào "thi đua đi lễ" đã có
sẵn** — không phải một cơ chế tách rời.

---

## 2. CÁCH LIÊN KẾT VỚI MODULE CHƯƠNG TRÌNH (điểm mấu chốt)

Không tạo cờ mới. **Tái sử dụng cờ `count_for_emulation`** ("Tính vào thi đua đi
lễ") đã có trên bảng `programs`.

* Buổi nào **bật cờ "Tính vào thi đua đi lễ"** → mọi lần điểm danh của buổi đó
  vừa vào bảng báo cáo thi đua (như cũ), **vừa tự sinh Mộc + tính chuỗi**.
* Buổi tắt cờ → không ảnh hưởng gì tới Mộc.

**Chương trình "Đi lễ hằng ngày"** được tạo bằng module Chương trình sẵn có:
* Loại: **bắt buộc**
* Chọn **cả 7 thứ** trong tuần (`days_of_week`)
* Đặt **Ngày bắt đầu / Ngày kết thúc** (`effective_from` / `effective_to`)
* Bật **"Tính vào thi đua đi lễ"** (`count_for_emulation`)
* Bật cho phép quét QR (`allow_qr`) để dùng ở Trạm Đổi Quà và điểm danh

`attendance.php` đã hỗ trợ sẵn điểm danh nhiều thứ trong tuần + khoảng ngày →
**không cần sửa module Chương trình để tạo được buổi này.**

---

## 3. QUY TẮC NGHIỆP VỤ (GAMIFICATION)

Mộc và chuỗi tính **theo NGÀY** và **theo NĂM HỌC** (`year_id`).

### 3.1. Tích lũy Mộc "đi lễ" (Earn)
Xét theo **ngày**, không theo từng buổi (chống cộng đôi nếu một ngày có nhiều
buổi thi đua):

* Ngày có **≥ 1 lần điểm danh** ở buổi `count_for_emulation`:
  * Ngày thường: **+1 Mộc**
  * Ngày **Chúa Nhật** (`session_date` rơi vào Chủ Nhật): **+2 Mộc**
* Áp dụng **cả khi đi trễ** (`status = 'đi trễ'`) — đi trễ vẫn được Mộc "đi lễ".

### 3.2. Chuỗi liên tiếp (Streak)
* **Ngày phải đi lễ** = ngày nằm trong lịch của (các) buổi `count_for_emulation`
  (theo `days_of_week` + khoảng `effective_from`/`effective_to`).
* Đi lễ (đúng giờ **hoặc** đi trễ) → **chuỗi nối tiếp**.
* **Vắng một ngày có lịch → chuỗi reset về `0`.**
  * **Nghỉ có phép KHÔNG được miễn trừ.** Đi lễ là hy sinh; đơn `leave_requests`
    đã duyệt vẫn làm đứt chuỗi. (Engine không cần đọc bảng `leave_requests`.)
* Ngày hôm nay chưa qua buổi thì **chưa tính là vắng**.

### 3.3. Thưởng chuỗi (Streak Bonus)
Trao **một lần** khi chuỗi lần đầu chạm mốc:
* Đạt 3 ngày liên tiếp: **+1 Mộc**
* Đạt 7 ngày liên tiếp: **+3 Mộc**
* Đạt 30 ngày liên tiếp: **+15 Mộc**

**Điều kiện đi trễ:** nếu ngày **chạm mốc** đúng vào hôm **đi trễ** → **mất luôn**
mốc thưởng đó (không trả bù về sau); chuỗi vẫn chạy tiếp. Mốc thưởng chỉ trao khi
ngày chạm mốc là **"có mặt" đúng giờ**.

### 3.4. Tiêu dùng (Spend)
* Trừ Mộc khi đổi quà tại Thư viện.
* Số dư **không được nhỏ hơn 0** (kiểm ở cả tầng logic lẫn giao dịch CSDL).

### 3.5. Phạm vi tính (mốc thời gian bắt đầu)
Chỉ tính từ **Ngày bắt đầu (`effective_from`)** của chương trình trở đi. Đặt ngày
này = ngày ra mắt tính năng thì mọi em khởi đầu từ 0, **không cần backfill riêng**.

---

## 4. NGUYÊN TẮC KỸ THUẬT: `attendances` LÀ NGUỒN CHÂN LÝ

Điểm danh có **3 đường ghi** (`attendance.php`): quét QR hàng loạt (chỉ thêm),
chạm tay để ghi (INSERT), chạm tay lần nữa để gỡ (DELETE). Vì vậy **không** rải
logic cộng/trừ Mộc ở từng đường.

* Sau mỗi thay đổi điểm danh của một em, gọi **`recalc_stamps(student_id, year_id)`**:
  tính lại toàn bộ số Mộc kiếm được + chuỗi từ đầu, dựa trên `attendances` của các
  buổi `count_for_emulation`.
* Nhờ đó **gỡ điểm danh → tự hoàn Mộc**, dữ liệu không bao giờ lệch dù được sửa
  bằng đường nào (kể cả sửa tay trong DB rồi chạy lại recalc).
* `recalc` **chỉ đụng phần Earn/Streak**; phần **Spend (đổi quà)** dùng transaction
  trừ trực tiếp có khóa dòng (`SELECT ... FOR UPDATE`), độc lập với recalc.
* Chống cộng trùng: `stamp_transactions` có `ref_attendance_id` (hoặc `ref_type` +
  `ref_id`) làm khóa idempotent cho giao dịch loại `attendance`.

---

## 5. THIẾT KẾ CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)

Bổ sung 3 bảng. Tất cả gắn `year_id` để ví tính **theo năm học**.

**Bảng `student_stamps` (Ví Mộc — mỗi em một dòng / năm)**
* `id`
* `year_id` (Khóa ngoại → `school_years`)
* `student_id` (Khóa ngoại → `students`)
* `current_balance` (Số Mộc đang có thể dùng)
* `total_earned` (Tổng Mộc đã kiếm trong năm — dùng cho Bảng xếp hạng)
* `current_streak` (Số ngày đi lễ liên tiếp hiện tại)
* `longest_streak` (Chuỗi dài nhất trong năm)
* `last_attendance_date` (Ngày đi lễ gần nhất — để kiểm đứt chuỗi)
* `UNIQUE (year_id, student_id)`

**Bảng `stamp_transactions` (Lịch sử giao dịch — Audit log)**
* `id`
* `year_id`
* `student_id`
* `amount` (Ví dụ: `+1`, `+3`, `-10`)
* `type` (`attendance`, `streak_bonus`, `spend`, `manual_adjust`)
* `ref_attendance_id` (NULL nếu không phải giao dịch từ điểm danh — chống cộng trùng)
* `description` (Vd: "Đi lễ CN 15/10", "Đổi truyện tranh", "Thưởng chuỗi 7 ngày")
* `actor_id` (Người thực hiện — bắt buộc với `spend` và `manual_adjust`)
* `created_at`

**Bảng `gifts` (Danh mục Quà tặng Thư viện)**
* `id`
* `name` (Bút bi, Cuốn truyện, Tràng hạt...)
* `stamp_cost` (Giá trị quy đổi bằng Mộc)
* `stock` (Số lượng tồn kho)
* `image_url` (Tùy chọn)
* `status` (còn bán / ẩn)

---

## 6. LUỒNG GIAO DIỆN VÀ TÍNH NĂNG (UI/UX)

### 6.1. Cập nhật `module_student_profile.php` (Hồ sơ Thiếu nhi)
Thêm một thẻ (Tab) hoặc Card hiển thị:
* **Biểu tượng Ví:** `Số Mộc hiện có` / `Tổng Mộc trong năm`.
* **Biểu tượng Lửa (🔥):** `Chuỗi hiện tại (🔥 5 ngày)` + chuỗi dài nhất.
* Dưới cùng: danh sách `Lịch sử giao dịch` gần đây (rút gọn).

### 6.2. "Trạm Đổi Quà" trong `module_library.php`
* **Màn hình 1 — Quét thẻ:** dùng lại `qrscan.js` hoặc ô nhập mã thẻ. Thủ thư quét
  thẻ em đang đứng trước mặt.
* **Màn hình 2 — Cửa hàng (POS):**
  * Hiện to **tên em** và **Số Mộc đang có**.
  * Grid các món quà.
  * Quà giá `>` số Mộc → nút mờ (disabled). Quà giá `<=` số Mộc → nút nổi bật.
  * Bấm đổi → hỏi xác nhận *"Chắc chắn đổi [Tên quà] trừ [X] Mộc?"* → OK.
  * Giao dịch nguyên tử: trừ Mộc + giảm `stock` + ghi `stamp_transactions` trong
    **một transaction có khóa dòng**.
  * Phát âm thanh thành công → quay lại màn quét thẻ tiếp theo.

### 6.3. Cổng tra cứu Public — `tracuu.php` (Phase 2)
* Trang độc lập cho Học sinh/Phụ huynh tự xem sổ Mộc ở nhà.
* **Bảo mật:** không dùng số điện thoại làm khóa tra cứu (dễ dò); ưu tiên **mã thẻ
  QR bí mật**, có **rate-limit** chống dò hàng loạt.

---

## 7. KẾ HOẠCH TRIỂN KHAI (IMPLEMENTATION PLAN)

* **Bước 0:** Viết test cho `recalc_stamps` (Mộc + chuỗi + mốc thưởng + đi trễ +
  Chúa Nhật + reset khi vắng) — phần dễ sai nhất, repo đã có PHPUnit sẵn.
* **Bước 1:** Migration CSDL — tạo 3 bảng (`student_stamps`, `stamp_transactions`,
  `gifts`).
* **Bước 2:** Backend logic (PHP) — hàm `recalc_stamps`, móc vào cả 3 đường ghi
  của `attendance.php`; chỉ chạy cho buổi `count_for_emulation`.
* **Bước 3:** API + UI Hồ sơ thiếu nhi — hiển thị Ví Mộc & Lửa Chuỗi.
* **Bước 4:** API đổi quà (transaction có khóa dòng) + UI "Trạm Đổi Quà"
  (Alpine.js) cho Thủ thư.
* **Bước 5:** Quản lý kho quà (thêm/sửa/xóa `gifts`, nhập tồn kho).
* **Bước 6:** Test tổng thể, tinh chỉnh thông báo & âm thanh.
* **Phase 2:** Cổng tra cứu public `tracuu.php`.

---

## 8. QUYẾT ĐỊNH ĐÃ CHỐT (tóm tắt)

| # | Vấn đề | Quyết định |
|---|--------|-----------|
| 1 | Cờ liên kết | Dùng lại `count_for_emulation`, không thêm cờ mới |
| 2 | Phạm vi ví | **Theo năm học** (`year_id`) |
| 3 | Nghỉ có phép | **Vẫn đứt chuỗi** (đi lễ là hy sinh, không miễn trừ) |
| 4 | Đi trễ | Vẫn **+1 Mộc**; **mất mốc thưởng** nếu chạm mốc vào hôm trễ |
| 5 | Cộng theo | **Theo ngày** (chống cộng đôi khi nhiều buổi/ngày) |
| 6 | Chúa Nhật | Nhận theo thứ của `session_date` → **+2 Mộc** |
| 7 | Nguồn chân lý | Bảng `attendances` + hàm `recalc_stamps` (gỡ điểm danh tự hoàn Mộc) |
| 8 | Mốc bắt đầu | Từ `effective_from` của chương trình (không backfill riêng) |
| 9 | Tra cứu public | Phase 2, dùng mã thẻ + rate-limit (không dùng SĐT) |
