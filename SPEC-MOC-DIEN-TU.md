# ĐẶC TẢ HỆ THỐNG: SỔ MỘC ĐIỆN TỬ & ĐỔI QUÀ
*(Digital Stamp & Reward System)*

> **Trạng thái:** Đã chốt thiết kế (v3) sau khi phân tích codebase và thống nhất
> luồng đặt quà online. Bản này thay cho các bản nháp trước — mọi quy tắc đã được
> đối chiếu với `attendances`, `programs`, `attendance.php`, `programs.php` thực tế.

## 1. MỤC TIÊU
Số hóa hoàn toàn quá trình tích lũy **Mộc** (điểm thưởng) khi Thiếu nhi đi lễ
hằng ngày. Tự động cộng Mộc, tính **chuỗi ngày đi lễ liên tiếp (Streak)** để kích
thích phong trào thi đua. Xây dựng **Trạm Đổi Quà** (tại quầy + đặt online) để quản
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
* Loại: **bắt buộc** · Chọn **cả 7 thứ** (`days_of_week`)
* Đặt **Ngày bắt đầu / Ngày kết thúc** (`effective_from` / `effective_to`)
* Bật **"Tính vào thi đua đi lễ"** (`count_for_emulation`) và cho phép quét QR

`attendance.php` đã hỗ trợ sẵn điểm danh nhiều thứ trong tuần + khoảng ngày →
**không cần sửa module Chương trình để tạo được buổi này.**

---

## 3. QUY TẮC NGHIỆP VỤ (GAMIFICATION)

Mộc và chuỗi tính **theo NGÀY** và **theo NĂM HỌC** (`year_id`).

### 3.1. Tích lũy Mộc "đi lễ" (Earn) — xét theo NGÀY
* Ngày có **≥ 1 lần điểm danh** ở buổi `count_for_emulation`:
  * Ngày thường: **+1 Mộc** · Ngày **Chúa Nhật** (`session_date` là CN): **+2 Mộc**
* Áp dụng **cả khi đi trễ** (`status = 'đi trễ'`) — đi trễ vẫn được Mộc "đi lễ".

### 3.2. Chuỗi liên tiếp (Streak)
* **Ngày phải đi lễ** = ngày nằm trong lịch của (các) buổi `count_for_emulation`
  (theo `days_of_week` + khoảng `effective_from`/`effective_to`).
* Đi lễ (đúng giờ **hoặc** đi trễ) → chuỗi nối tiếp.
* **Vắng một ngày có lịch → chuỗi reset về `0`.** Nghỉ **có phép KHÔNG** được miễn
  trừ (đi lễ là hy sinh). Engine không cần đọc `leave_requests`.
* Hôm nay chưa qua buổi thì chưa tính là vắng.

### 3.3. Thưởng chuỗi (Streak Bonus) — trao một lần khi chạm mốc
* 3 ngày: **+1** · 7 ngày: **+3** · 30 ngày: **+15**
* **Nếu ngày chạm mốc là hôm đi trễ → mất mốc thưởng đó** (không trả bù về sau);
  chuỗi vẫn chạy tiếp. Mốc chỉ trao khi ngày chạm mốc là **"có mặt" đúng giờ**.

### 3.4. Tiêu dùng (Spend)
* Trừ Mộc khi đổi quà. **Số dư khả dụng ≥ 0** (kiểm ở cả logic lẫn giao dịch CSDL).
* **Số dư khả dụng = `current_balance − held_balance`** (xem §5, §6).

### 3.5. Phạm vi tính
Chỉ tính từ **Ngày bắt đầu (`effective_from`)** của chương trình trở đi. Đặt ngày
này = ngày ra mắt thì mọi em khởi đầu từ 0, **không cần backfill riêng**.

---

## 4. NGUYÊN TẮC KỸ THUẬT: `attendances` LÀ NGUỒN CHÂN LÝ

Điểm danh có **3 đường ghi** (`attendance.php`): quét QR hàng loạt (chỉ thêm),
chạm tay ghi (INSERT), chạm tay lần nữa gỡ (DELETE). Vì vậy **không** rải logic
cộng/trừ Mộc ở từng đường.

* Sau mỗi thay đổi điểm danh của một em, gọi **`recalc_stamps(student_id, year_id)`**:
  tính lại toàn bộ Mộc kiếm được + chuỗi từ đầu, dựa trên `attendances` của các
  buổi `count_for_emulation`.
* Nhờ đó **gỡ điểm danh → tự hoàn Mộc**, dữ liệu không bao giờ lệch.
* `recalc` **chỉ đụng phần Earn/Streak** (`total_earned`, `current_streak`,
  `longest_streak`, và phần earn của `current_balance`). Phần **Spend/Held** do luồng
  đổi quà quản lý riêng bằng transaction có khóa dòng, độc lập với recalc.
* Chống cộng trùng: `stamp_transactions` có `ref_attendance_id` làm khóa idempotent
  cho giao dịch loại `attendance`/`streak_bonus`.

---

## 5. THIẾT KẾ CƠ SỞ DỮ LIỆU (DATABASE SCHEMA)

Bổ sung/điều chỉnh các bảng sau. Tất cả gắn `year_id` (ví theo năm học).

**`student_stamps` — Ví Mộc (mỗi em một dòng / năm)**
* `id`, `year_id` (FK `school_years`), `student_id` (FK `students`)
* `current_balance` — tổng Mộc đang có
* `held_balance` — Mộc đang bị **giữ** bởi các đơn `chờ lấy`
* `total_earned` — tổng Mộc đã kiếm trong năm (dùng cho Bảng xếp hạng)
* `current_streak`, `longest_streak`, `last_attendance_date`
* `UNIQUE (year_id, student_id)`
* *Khả dụng để tiêu = `current_balance − held_balance`.*

**`stamp_transactions` — Lịch sử giao dịch (Audit log)**
* `id`, `year_id`, `student_id`
* `amount` (`+1`, `+3`, `-10`…)
* `type` (`attendance`, `streak_bonus`, `spend`, `manual_adjust`)
* `ref_attendance_id` (NULL nếu không từ điểm danh — chống cộng trùng)
* `ref_order_id` (NULL nếu không từ đổi quà)
* `description`, `actor_id` (bắt buộc với `spend`, `manual_adjust`), `created_at`

**`gifts` — Danh mục quà (module danh mục quản lý bảng này)**
* `id`, `name`, `stamp_cost`, `stock`, `image_url` (tùy chọn), `status` (còn bán / ẩn)

**`gift_orders` — Đầu đơn đặt quà (một đơn = nhiều quà)**
* `id`, `year_id`, `student_id`
* `total_cost` — tổng Mộc của cả đơn (tổng các dòng)
* `redeem_code_hash` — **hash** của "mật mã đổi quà" do em tự đặt (không lưu thô)
* `status` (`chờ lấy`, `đã giao`, `đã hủy`, `quá hạn`)
* `created_at`, `expires_at` (mặc định +7 ngày)
* `delivered_by` (FK `members`), `delivered_at`
* *Ràng buộc: mỗi em chỉ **1 đơn `chờ lấy`** một lúc (unique có điều kiện / kiểm ở logic).*

**`gift_order_items` — Các dòng quà trong đơn**
* `id`, `order_id` (FK `gift_orders`)
* `gift_id` (FK `gifts`), `qty` (số lượng), `unit_cost`, `line_cost` (= qty × unit_cost)

---

## 6. CÁC MODULE & LUỒNG

### 6.1. Module danh mục quà (Quản trị / Thủ thư)
CRUD bảng `gifts`: **thêm/sửa tên quà · số Mộc (`stamp_cost`) · số lượng tồn
(`stock`)**, ảnh (tùy chọn), ẩn/hiện. Đây là nơi anh chị điều khiển kho quà.

### 6.2. Hồ sơ Thiếu nhi (`module_student_profile.php`) — GLV xem
Card hiển thị: **Ví** (số Mộc hiện có / tổng Mộc năm), **Lửa 🔥** (chuỗi hiện tại +
dài nhất), và **Lịch sử giao dịch** rút gọn.

### 6.3. Trang "Cuốn sổ" public (`tracuu.php`) — em & phụ huynh tự dùng
Em nhập **mã thiếu nhi** → mở sổ, hai tab:

* **Tab Sổ Mộc:** số Mộc hiện có, chuỗi 🔥, lịch sử. Khi có đơn đang chờ, hiện
  **khu vực thông báo mã đổi quà** (mã đơn + danh sách quà đã đặt + hạn lấy).
* **Tab Đổi quà:** xem danh sách quà + số Mộc yêu cầu + tồn còn lại.
  * **Chọn nhiều quà vào một đơn** (giỏ hàng), mỗi quà có thể chọn số lượng.
  * Đặt đơn: nhập **mã thiếu nhi** + **tự đặt mật mã đổi quà** cho đơn này.
  * Điều kiện: tổng Mộc của đơn **≤ số Mộc khả dụng**, và mỗi quà còn tồn.
  * Đặt thành công → hệ thống **giữ (held) tổng Mộc** + **giữ tồn kho** từng quà,
    và mã đơn hiện sang tab Sổ Mộc.

### 6.4. Module điều khiển đổi quà (Thủ thư trên nhà thờ) — trong `module_library.php`
Làm **cả hai việc**:

* **(a) Xác nhận đơn đặt trước:** quét thẻ / nhập **mã thiếu nhi** + **mật mã đổi
  quà** → hệ thống kiểm mã đúng và đơn còn `chờ lấy` → hiện danh sách quà → Thủ thư
  đưa quà → bấm xác nhận:
  * **Trừ Mộc thật** (chuyển từ `held` sang đã tiêu, ghi `stamp_transactions` type
    `spend` gắn `ref_order_id`), giảm tồn kho thật, đơn chuyển `đã giao`,
    **vô hiệu hóa mã** (không dùng lại được).
  * Giao **trọn đơn** (all-or-nothing) để đơn giản; tồn kho đã được giữ từ lúc đặt
    nên bảo đảm đủ hàng.
* **(b) Đổi trực tiếp tại quầy** (em không đặt trước): quét thẻ → chọn quà (nhiều
  món) → xác nhận → trừ Mộc + tồn kho ngay trong **một transaction có khóa dòng**.

### 6.5. Vòng đời đơn & chống lạm dụng
* **Hết hạn tự hủy** sau **7 ngày** (job/kiểm khi truy cập): nhả `held` Mộc + hoàn
  tồn kho, đơn chuyển `quá hạn`.
* Em **tự hủy** đơn: nhập lại **mã thiếu nhi + mật mã đổi quà** → nhả Mộc + tồn kho,
  đơn `đã hủy`.
* Mỗi em **1 đơn `chờ lấy`** một lúc.
* Bảo mật: đặt đơn chỉ cần mã thiếu nhi (dễ), nhưng **lấy quà cần 3 lớp**: thẻ vật
  lý + Thủ thư có mặt + mật mã đúng. Kẻ chỉ biết mã thiếu nhi cùng lắm **chiếm 1
  suất đặt**, không lấy được Mộc/quà. `tracuu.php` có **rate-limit** chống dò mã.
* Mọi thao tác trừ/nhả Mộc và tồn kho chạy trong **transaction có khóa dòng**
  (`SELECT ... FOR UPDATE`) để chống đua (race) khi nhiều người thao tác cùng lúc.

---

## 6bis. THIẾT LẬP VAI TRÒ & CÔ LẬP QUYỀN (chống leo thang)

### Vai trò `thu_thu` (Thủ Thư)
* Thêm role: `code='thu_thu'`, `label='Thủ Thư'`, `level=1`, `scope='toàn đoàn'`.
* Gán người qua **kiêm nhiệm** (`member_assignments`, `block_id/class_id = NULL`) —
  **không** đổi `role_code` gốc. Người đang là GLV vẫn giữ vai GLV, chỉ **thêm** vai
  Thủ thư khi đứng quầy.

### Hai module mới (tách riêng)
| module_key | Chức năng | Ai được `edit` |
|---|---|---|
| `gifts` | Danh mục quà (tên, số Mộc, tồn kho) | `admin`, `bdh`, `thu_thu` |
| `rewards` | Trạm Đổi Quà (POS + xác nhận đơn) | `admin`, `thu_thu` **(BĐH không đứng quầy)** |

* **Đổi quà tại quầy & override "quên mật mã": chỉ `thu_thu`** (và `admin`). Override
  = ai có `edit` trên `rewards` → được giao không cần mật mã, **có ghi log**.
* Mọi role khác (`glv`, `glv_chu_nhiem`, `truong_khoi`, `du_bi`): `none` trên cả hai.

### Cô lập chống leo thang (BẮT BUỘC)
`thu_thu` có scope `toàn đoàn` nhưng **chỉ** trên `rewards`/`gifts`. Nguy cơ: 2 hàm
gộp phạm vi trong core **bỏ qua module**, nên vai `toàn đoàn` sẽ nới rộng cả những
miền khác. Ba lớp cô lập:

1. **Module `rewards`/`gifts` không chia theo lớp.** API chỉ gác
   `require_permission('rewards'|'gifts','edit')`; **không** gọi `can_access_class`,
   `allowed_class_ids`, hay `scan_class_ids`. Định danh em qua **mã thẻ**, không qua lớp.
2. **Vá 2 hàm gộp phạm vi cho "module-aware":**
   * `scan_class_ids()` (`_bootstrap.php`): chỉ tính assignment có ≥`view` trên
     `attendance`. → `thu_thu` (none) không nới phạm vi quét điểm danh.
   * `responsible_class_ids()` (`_common.php`): chỉ tính assignment của vai có quyền
     trên **miền thiếu nhi** (students/attendance). → `thu_thu` không nới ranh giới
     xem/sửa/xóa hồ sơ, in thẻ QR. Giữ nguyên hành vi cho mọi vai hiện có.
3. **Test hồi quy bắt buộc:** member = GLV(lớp A) **+** `thu_thu`(toàn đoàn):
   `students`/`data` chỉ thấy lớp A · `scan_class_ids` chỉ khối của A ·
   `custom-qrcard` chỉ lớp A · sửa điểm danh lớp ngoài A **bị chặn** ·
   nhưng `rewards` phục vụ **được mọi em**.

---

## 7. KẾ HOẠCH TRIỂN KHAI (IMPLEMENTATION PLAN)

**Phase 1 — Lõi Mộc + đổi quà tại quầy + cô lập quyền**
* **Bước 0:** Test `recalc_stamps` (Mộc + chuỗi + mốc thưởng + đi trễ + CN + reset).
* **Bước 1:** Migration: `student_stamps`, `stamp_transactions`, `gifts`,
  `gift_orders`, `gift_order_items`; seed role `thu_thu`, 2 module `gifts`/`rewards`
  + các dòng `permissions` (dựng schema đầy đủ ngay để không đập lại sau).
* **Bước 2:** **Cô lập quyền** — vá `scan_class_ids` + `responsible_class_ids` cho
  module-aware, kèm **test hồi quy** GLV+thu_thu (§6bis).
* **Bước 3:** Backend `recalc_stamps`, móc vào 3 đường ghi của `attendance.php`
  (chỉ chạy cho buổi `count_for_emulation`).
* **Bước 4:** Module danh mục quà (CRUD `gifts`).
* **Bước 5:** Hồ sơ thiếu nhi hiển thị Ví Mộc & Lửa Chuỗi.
* **Bước 6:** Module điều khiển đổi quà — **đổi trực tiếp tại quầy** (6.4b), giỏ
  nhiều quà, transaction khóa dòng.
* **Bước 7:** Test tổng thể, tinh chỉnh thông báo & âm thanh.

**Phase 2 — Cổng public**
* **Bước 7:** `tracuu.php` — tab Sổ Mộc (chỉ đọc, xác thực nhẹ bằng mã thiếu nhi,
  rate-limit).

**Phase 3 — Đặt quà online**
* **Bước 8:** Tab Đổi quà + đặt đơn (giỏ nhiều quà, mật mã đổi quà, giữ Mộc + tồn).
* **Bước 9:** Xác nhận đơn đặt trước ở module điều khiển (6.4a) + vòng đời đơn
  (hết hạn/hủy, 6.5).

---

## 8. QUYẾT ĐỊNH ĐÃ CHỐT (tóm tắt)

| # | Vấn đề | Quyết định |
|---|--------|-----------|
| 1 | Cờ liên kết | Dùng lại `count_for_emulation`, không thêm cờ mới |
| 2 | Phạm vi ví | Theo năm học (`year_id`) |
| 3 | Nghỉ có phép | Vẫn đứt chuỗi (đi lễ là hy sinh, không miễn trừ) |
| 4 | Đi trễ | Vẫn +1 Mộc; mất mốc thưởng nếu chạm mốc vào hôm trễ |
| 5 | Cộng theo | Theo ngày (chống cộng đôi khi nhiều buổi/ngày); CN +2 |
| 6 | Nguồn chân lý | `attendances` + `recalc_stamps` (gỡ điểm danh tự hoàn Mộc) |
| 7 | Mốc bắt đầu | Từ `effective_from` của chương trình (không backfill riêng) |
| 8 | Đổi quà online | Đặt trước + Thủ thư xác nhận; trừ Mộc lúc giao |
| 9 | Giữ Mộc/tồn | Giữ (held) Mộc + tồn kho lúc đặt; nhả khi hủy/hết hạn |
| 10 | Vòng đời đơn | Hết hạn 7 ngày · 1 đơn chờ/em · tự hủy bằng mật mã |
| 11 | Số quà mỗi đơn | **Nhiều quà/đơn** (giỏ hàng, có số lượng); giao trọn đơn |
| 12 | Đổi tại quầy | Vẫn giữ, cho em không đặt trước |
| 13 | Xác thực lấy quà | 3 lớp: thẻ vật lý + Thủ thư + mật mã đơn |
| 14 | Tra cứu public | Mã thiếu nhi + rate-limit (không dùng SĐT) |
| 15 | Vai trò Thủ thư | Role `thu_thu` (toàn đoàn), gán bằng kiêm nhiệm |
| 16 | Module quyền | Tách `gifts` + `rewards`; đổi quà & override **chỉ `thu_thu`+admin** |
| 17 | Quên mật mã | Thủ thư override (có log); tại nhà chờ hết hạn/ra quầy |
| 18 | Chống leo thang | rewards không chia lớp + vá 2 hàm scope + test hồi quy |
