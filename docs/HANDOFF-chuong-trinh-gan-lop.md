# HANDOFF — Chương trình gắn lớp (thay Thời khóa biểu lớp)

_Cập nhật: 2026-09-23 · Nhánh: `program-classes`_

Tài liệu bàn giao cho session sau. Ghi lại toàn bộ quyết định, việc đã làm,
việc còn treo, và cách kiểm chứng.

## 1. Bối cảnh & quyết định (đã chốt với người dùng)

Thay mô hình "Thời khóa biểu lớp per-lớp" (Hướng B — class_schedules) bằng
**"Chương trình gắn lớp"**: chương trình mang giờ + gắn danh sách lớp tham gia.

Chốt:
- **Giờ theo chương trình** (mọi lớp gắn dùng chung giờ của chương trình).
- **Thay thế** hẳn TKB lớp (gỡ module + bảng class_schedules/schedule_exceptions).
- **Bỏ** "báo nghỉ", "dời giờ", "học bù".
- Tùy chọn chương trình được chọn: **1** cho phép QR, **2** ngưỡng "vắng"
  (mốc chặn cứng — sau giờ này không ghi được, tính vắng), **3** màu + icon,
  **4** thứ tự hiển thị, **5** khoảng ngày áp dụng, **7** tự đóng chiến dịch
  quá ngày. (Bỏ 6 ghi chú, 8 khóa sửa.)
- **Lặp nhiều thứ** (days_of_week) + cờ **"Tính vào thi đua đi lễ"**
  (`count_for_emulation`, độc lập với `count_for_attendance`) — để làm
  "thi đua đi lễ trong tuần" mà không đụng chuyên cần, không quét trùng.
- **Bảng xếp hạng thi đua**: CHƯA làm (để sau).
- **Không đụng module Lên lớp** (`promotion.js`).

Kịch bản gốc người dùng: thi đua đi lễ T2→CN, mỗi ngày tính khác nhau; T5 một
số lớp bắt buộc; sáng CN đã có chương trình chuyên cần toàn đoàn. Giải pháp:
tách theo chương trình (mỗi ngày một chương trình / hoặc chương trình đa-thứ),
dùng `count_for_attendance` cho chuyên cần và `count_for_emulation` cho thi đua;
T5-bắt-buộc-một-số-lớp = một chương trình gắn đúng các lớp đó.

## 2. Đã làm trong đợt này (nhánh `program-classes`)

### CSDL
- `docs/migrate_program_classes.sql` (MỚI): bảng `program_classes(program_id,
  class_id)` + thêm cột vào `programs`: `days_of_week, allow_qr, absent_time,
  color, icon, sort_order, effective_from, effective_to, auto_close_after_event,
  count_for_emulation`. Kèm khối lệnh (đã comment) để **dọn** class_schedules/
  schedule_exceptions nếu môi trường đã chạy thử Hướng B.
- `ylcqukhi_glyphutrung_updated.sql`: cập nhật schema chuẩn (cột mới +
  bảng program_classes) cho cài mới.
- ĐÃ XÓA: `docs/migrate_class_schedules.sql`, `docs/migrate_schedule_exceptions.sql`.

### Backend
- `public/api/programs.php` — `save` nhận & lưu tất cả tùy chọn mới + đa-thứ +
  đồng bộ `program_classes` (rỗng = toàn đoàn).
- `public/api/data.php` — bỏ payload classSchedules/scheduleExceptions; gửi
  `programClasses` (map programId→[classId]) + các trường tùy chọn của program;
  tự đóng chiến dịch quá ngày (lười, có guard cột).
- `public/api/attendance.php` — về program-centric; thêm: lặp nhiều thứ +
  khoảng ngày hiệu lực, lọc theo lớp gắn (toggle/scan/lookup), `allow_qr`
  (chặn scan), `absent_time` (chặn ghi sau ngưỡng vắng).

### Frontend
- `core.js` — nạp `programClasses` từ data (khôi phục bản pre-B, không còn nav TKB).
- `attendance.js` — `programsOn` mới: `programOccursOn` (đa-thứ + khoảng ngày) +
  `programAppliesToClass` (lọc theo lớp gắn).
- `calendar.js` — dùng chung `programOccursOn` (đa-thứ).
- `qrscan.js` — chặn quét nếu buổi tắt QR.
- `stats.js` — khôi phục pre-B (program-centric) + thêm shim `attKey`
  (program-centric) để `reports.js`/`promotion.js` chạy không cần sửa.
- `programs.js` — form đủ trường mới + `programPayload()` dùng chung + đồng bộ
  `programClasses` cục bộ (kể cả khi bật/tắt nhanh).
- `views/module_programs.php` — form: chọn nhiều thứ, giờ "tính vắng", khoảng
  ngày, lớp áp dụng (checkbox), công tắc chuyên cần/thi đua/QR/tự-đóng, màu +
  thứ tự.
- `asset_manifest.php` — bỏ module `schedules`.
- ĐÃ XÓA: `public/api/schedules.php`, `public/assets/js/modules/schedules.js`,
  `views/module_schedules.php`.

### Đã đóng
- **PR #12** (QR + gỡ học bù/dời giờ trên nền TKB) — người dùng đã đóng vì bị
  thay thế. KHÔNG mở lại.

## 3. CÒN TREO — cho session sau

### A. Báo cáo / Sổ liên lạc (người dùng yêu cầu bàn ở session riêng)
Hiện `stats.js`/`reports.js`/`promotion.js` là **program-centric toàn đoàn** —
**CHƯA** tôn trọng `program_classes`. Hệ quả cần xử lý:
- **Mẫu số chuyên cần chưa lọc theo lớp gắn.** VD chương trình "Lễ Thứ Năm"
  gắn 2 lớp sẽ vẫn bị `stats` tính cho MỌI lớp → lớp không gắn bị tính vắng oan.
  → Cần cho `sessionsBetween`/`statSummary` lọc buổi theo lớp của em
  (dựa `programClasses`). Đây là phần "thiết kế lại báo cáo" đã bàn nhưng
  **hoãn theo yêu cầu**.
- 2 câu hỏi còn treo với người dùng:
  - **(a)** Lên lớp có tính chuyên cần **theo lớp gắn** không (nhất quán Sổ
    liên lạc) hay giữ nguyên toàn đoàn?
  - **(b)** Thống kê tổng đoàn hiển thị thế nào khi mỗi lớp khác số buổi
    (khuyến nghị: tỷ lệ trung bình theo lớp).
- `count_for_emulation` hiện chỉ lưu + gửi xuống client; **chưa** có bảng xếp
  hạng thi đua và chưa tách khỏi báo cáo (vì báo cáo chưa đụng). Khi làm báo
  cáo: loại `count_for_emulation && !count_for_attendance` khỏi chuyên cần.

### B. `absent_time` (mốc "vắng")
Đang hiểu là **chặn cứng** (sau giờ đó không ghi được, tính vắng). Nếu người
dùng muốn "ghi nhận nhưng gắn nhãn vắng" (thêm trạng thái) thì cần đổi enum
`attendances.status` + logic — CHƯA làm.

### C. Việc dọn dẹp có thể cần
- Nếu có môi trường đã chạy migrate Hướng B: chạy khối "dọn" trong
  `migrate_program_classes.sql`.
- Cân nhắc bổ sung PHPUnit cho program_classes/attendance scope (CI hiện
  không có DB service nên test DB bị bỏ qua).

## 4. Deploy / Migration
1. Chạy `docs/migrate_program_classes.sql` trên CSDL production.
2. (Nếu đã lỡ chạy Hướng B) chạy khối lệnh "dọn" cuối file đó.
3. Deploy code nhánh `program-classes`.

## 5. Kiểm chứng (chưa chạy được ở môi trường này — cần app + DB)
- [ ] Tạo chương trình đa-thứ (T2–T7), `count_for_attendance=0`,
      `count_for_emulation=1` → điểm danh mỗi ngày, KHÔNG vào chuyên cần.
- [ ] Chương trình gắn 2 lớp → chỉ 2 lớp đó thấy buổi & điểm danh được;
      lớp khác bị chặn (toggle/scan báo lỗi đúng).
- [ ] `allow_qr=0` → nút quét QR chặn.
- [ ] `absent_time` → sau giờ đó không ghi được (tay + QR).
- [ ] `effective_from/to` → ngoài khoảng không hiện buổi.
- [ ] Chiến dịch `auto_close_after_event=1` quá ngày → tự chuyển "đã đóng".
- [ ] Lịch tháng hiện đúng buổi đa-thứ.
- [ ] Sổ liên lạc / Thống kê vẫn chạy (program-centric) — LƯU Ý mẫu số chưa
      lọc theo lớp gắn (mục 3.A).

## 6. Đã validate ở môi trường này
- `php -l`: attendance.php, data.php, programs.php — sạch.
- `node --check`: core/programs/attendance/calendar/qrscan/stats/reports/
  promotion — sạch.
- Không còn tham chiếu tới class_schedules/schedule_exceptions/module TKB.
