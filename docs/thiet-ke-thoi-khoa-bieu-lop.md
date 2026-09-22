# Thiết kế: Thời khóa biểu riêng cho từng lớp giáo lý (Hướng B)

> Tài liệu thiết kế — chưa triển khai code. Mục đích: bàn phương án với ban điều hành
> trước khi thực hiện.

## 1. Bối cảnh & vấn đề

Các lớp giáo lý trong đoàn không sinh hoạt theo một lịch chung. Thực tế phát sinh ba
tình huống mà hệ thống hiện tại **chưa biểu diễn được**:

1. **Lớp sáng / lớp chiều** — cùng một ngày (thường là Chúa Nhật) nhưng có lớp học buổi
   sáng, có lớp học buổi chiều, giờ bắt đầu khác nhau.
2. **Đổi lịch cho phù hợp** — một số lớp có thể phải dời giờ hoặc dời buổi giữa năm học để
   thích ứng hoàn cảnh (phòng ốc, giáo lý viên, mùa phụng vụ…).
3. **Đi lễ thứ Năm hay không** — một số lớp bắt buộc tham dự Thánh lễ thứ Năm và phải được
   điểm danh buổi đó; các lớp khác thì không, và **không được tính là vắng** khi không dự.

Hệ quả nếu không giải quyết: điểm danh sai đối tượng, tỷ lệ chuyên cần bị lệch (đếm cả
những buổi mà lớp không hề có lịch), và ban điều hành không thể cấu hình lịch linh hoạt
theo từng lớp.

Tài liệu này trình bày **Hướng B — Thời khóa biểu riêng cho từng lớp**: đưa lịch sinh hoạt
xuống cấp lớp để mỗi lớp tự sở hữu khung giờ của mình.

## 2. Hiện trạng kiến trúc

Hệ thống hiện **lấy `programs` (buổi sinh hoạt) làm trung tâm**, và lịch giờ giấc nằm ở đây
chứ không ở lớp:

- Bảng `programs` có `day_of_week`, `start_time`, `cutoff_time`, `type` (bắt buộc / chiến
  dịch) nhưng **chỉ gắn với `year_id`** → một buổi áp dụng cho **toàn bộ thiếu nhi trong
  năm**, không phân biệt lớp/khối.
- Bảng `attendances` khóa theo `program_id + session_date + student_id`. Việc giới hạn chỉ
  xảy ra lúc **quét** (theo khối, qua `scan_class_ids`), chứ không có khái niệm "buổi này
  *dành cho* những lớp nào".

Toàn bộ chuỗi xử lý đều phụ thuộc vào `dayOfWeek`/`eventDate` của program toàn-đoàn:

| Tầng | File | Vai trò |
|---|---|---|
| CSDL | `programs`, `attendances` | Lịch đặt ở cấp năm, điểm danh khóa theo program |
| API trả dữ liệu | `public/api/data.php` | Gửi danh sách program (sắp theo `start_time`) về client |
| Lọc buổi (client) | `assets/js/modules/attendance.js` → `programsOnDate` | Lọc program theo `dayOfWeek`/`eventDate` |
| Lịch tháng | `assets/js/modules/calendar.js` | Dựng sự kiện từ program theo `dayOfWeek` |
| Ghi điểm danh | `public/api/attendance.php` | Kiểm tra ngày hợp lệ & giờ chốt theo `program.start_time` |

Điểm mấu chốt: **không có tầng nào biết "lớp X học lúc nào."** Đó chính là khoảng trống
Hướng B lấp.

## 3. Mục tiêu & phạm vi

**Mục tiêu**

- Mỗi lớp tự sở hữu **thời khóa biểu tuần** riêng (thứ, giờ bắt đầu, giờ chốt, nhãn
  sáng/chiều).
- Buổi điểm danh sinh ra từ lịch của lớp, thay vì từ một program toàn-đoàn.
- Cho phép **đổi lịch giữa năm** mà vẫn giữ nguyên lịch sử điểm danh cũ.
- Gắn một lịch với **loại buổi** (Giáo lý thường, Lễ thứ Năm…) để quyết định lớp nào bắt
  buộc dự buổi nào.
- Tính **tỷ lệ chuyên cần** đúng theo số buổi mà lớp thực sự có lịch.

**Trong phạm vi**

- Thiết kế CSDL, API, UI cho thời khóa biểu cấp lớp và luồng điểm danh dựa trên nó.
- Tương thích ngược với dữ liệu điểm danh hiện có.

**Ngoài phạm vi (giai đoạn sau)**

- Xếp thời khóa biểu theo phòng học / tải giáo lý viên.
- Đồng bộ lịch với lịch phụng vụ giáo xứ tự động.
- Ứng dụng thông báo nhắc buổi học theo lịch riêng từng lớp.

## 4. Thiết kế cơ sở dữ liệu

### 4.1. Bảng mới `class_schedules` — thời khóa biểu tuần của từng lớp

```sql
CREATE TABLE class_schedules (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  year_id      INT NOT NULL,
  class_id     INT NOT NULL,
  program_id   INT DEFAULT NULL,          -- "loại buổi": Giáo lý / Lễ thứ Năm... (NULL = giáo lý mặc định)
  day_of_week  TINYINT NOT NULL,          -- 0 CN .. 6 T7
  start_time   TIME NOT NULL,
  cutoff_time  TIME DEFAULT NULL,         -- ghi đè giờ chốt riêng cho lớp
  slot         ENUM('sáng','chiều','tối') DEFAULT NULL,  -- nhãn hiển thị
  active_from  DATE DEFAULT NULL,         -- đổi lịch giữa năm mà giữ lịch sử
  active_to    DATE DEFAULT NULL,
  status       ENUM('kích hoạt','tạm ngưng') NOT NULL DEFAULT 'kích hoạt',
  UNIQUE KEY uq (year_id, class_id, day_of_week, start_time, program_id),
  KEY idx_lookup (year_id, day_of_week, status),
  CONSTRAINT fk_cs_class FOREIGN KEY (class_id)   REFERENCES classes(id)      ON DELETE CASCADE,
  CONSTRAINT fk_cs_prog  FOREIGN KEY (program_id) REFERENCES programs(id)     ON DELETE CASCADE,
  CONSTRAINT fk_cs_year  FOREIGN KEY (year_id)    REFERENCES school_years(id) ON DELETE CASCADE
);
```

### 4.2. Sửa `attendances` — thêm truy vết slot

```sql
ALTER TABLE attendances
  ADD COLUMN schedule_id INT NULL AFTER program_id,
  ADD KEY idx_att_sched (schedule_id, session_date);
```

Giữ nguyên `program_id` và khóa duy nhất `(program_id, session_date, student_id)`. Khóa này
**vẫn đúng** vì mỗi em chỉ thuộc **một lớp → một slot** cho mỗi loại buổi trong ngày → không
va chạm, tương thích ngược với bản ghi điểm danh hiện có.

### 4.3. (Tùy chọn, GĐ 2) Bảng `schedule_exceptions` — nghỉ lễ / dời buổi / học bù

```sql
CREATE TABLE schedule_exceptions (
  schedule_id INT NOT NULL,
  on_date     DATE NOT NULL,
  kind        ENUM('nghỉ','dời giờ','học bù') NOT NULL,
  new_start   TIME NULL,
  note        VARCHAR(255) NULL,
  PRIMARY KEY (schedule_id, on_date)
);
```

Bảng này cần cho việc tính **mẫu số chuyên cần** chính xác: nếu không loại trừ tuần nghỉ,
mẫu số sẽ đếm dư.

## 5. Ba nhu cầu ↔ cách biểu diễn

| Nhu cầu | Cách biểu diễn trong `class_schedules` |
|---|---|
| **Lớp sáng / lớp chiều** | Lớp A: 1 dòng `day=0, start=07:30, slot='sáng'`. Lớp B: `day=0, start=14:00, slot='chiều'`. Cùng một lớp học 2 ca thì 2 dòng. |
| **Đi lễ thứ Năm hay không** | Chỉ lớp phải đi mới có dòng `day=4, program_id=<Lễ thứ Năm>`. Lớp không có dòng đó → không kỳ vọng, không tính vắng. |
| **Đổi lịch cho phù hợp** | Đặt `active_to` cho dòng cũ, thêm dòng mới `active_from`. Lịch sử điểm danh cũ nguyên vẹn. |

### Ví dụ dữ liệu

Giả sử năm 2026–2027 (`year_id=1`), có lớp Chiên 1 (`class_id=10`) học sáng CN, lớp Nghĩa 2
(`class_id=22`) học chiều CN và bắt buộc đi lễ thứ Năm (`program_id=5`):

```text
id  year  class  program  dow  start   cutoff  slot    active_from  active_to
1   1     10     NULL     0    07:30   08:00   sáng    NULL         NULL
2   1     22     NULL     0    14:00   14:30   chiều   NULL         NULL
3   1     22     5        4    18:00   18:15   tối     NULL         NULL
```

→ CN, lớp Chiên 1 điểm danh ca sáng; lớp Nghĩa 2 ca chiều. Thứ Năm chỉ Nghĩa 2 có buổi lễ;
Chiên 1 không xuất hiện → không ai bị tính vắng oan.

## 6. Chiến lược tích hợp

Kiến trúc hiện lấy program làm trung tâm; Hướng B đảo lại thành **lớp làm trung tâm**. Có
hai chiến lược chuyển đổi:

**B‑nhẹ (khuyến nghị làm trước)**

- Giữ `program_id`; `class_schedules.program_id` trỏ về "loại buổi".
- Marker vẫn chọn "buổi", nhưng danh sách buổi được **sinh từ lịch lớp** thay vì lọc program
  toàn đoàn.
- Khóa duy nhất không đổi, giữ tương thích ngược → **ít phá vỡ nhất**.

**B‑thuần**

- Bỏ hẳn program khỏi luồng thường; `attendances` trỏ trực tiếp `schedule_id`, program chỉ
  còn cho chiến dịch.
- Sạch hơn về mô hình nhưng phải đổi khóa duy nhất, migrate bản ghi cũ, viết lại nhiều truy
  vấn báo cáo → **rủi ro cao hơn**.

> **Khuyến nghị:** làm **B‑nhẹ** trước, chạy ổn rồi mới cân nhắc nâng lên B‑thuần. Hai hướng
> không mâu thuẫn: `class_schedules.program_id` chính là cầu nối.

## 7. Các thành phần phải sửa

| # | File / vị trí | Thay đổi |
|---|---|---|
| 1 | Migration SQL | 2 bảng mới + cột `attendances.schedule_id` |
| 2 | `public/api/data.php` (≈ dòng 106–116) | Gửi thêm mảng `classSchedules` xuống client, kèm lọc theo `allowed_class_ids` để mỗi người chỉ thấy lịch lớp mình |
| 3 | `assets/js/modules/attendance.js` (`programsOnDate`, dòng 39–47) | **Thay đổi lớn nhất ở client**: sinh danh sách buổi = (lớp × slot) khớp ngày, gộp giờ từ `class_schedules` |
| 4 | `assets/js/modules/calendar.js` (dòng 63–100) | Dựng sự kiện lịch tháng từ `class_schedules` thay vì program |
| 5 | `public/api/attendance.php` (dòng 33–44) | Kiểm tra ngày hợp lệ **theo lịch của lớp em đó**; giờ chốt lấy `schedule.cutoff_time`/`start_time` (ghi đè) trước; ghi kèm `schedule_id` |
| 6 | `views/module_programs.php` + màn mới | UI quản lý thời khóa biểu: gán slot cho từng lớp |
| 7 | `views/module_attendance.php` | Bộ chọn buổi theo lớp/ca thay vì theo program |
| 8 | `public/api/reports.php` + thống kê | **Mẫu số chuyên cần** đổi từ "số buổi toàn đoàn" thành "số buổi theo lịch của lớp em" (trừ exceptions) |

Hạng mục 3, 5, 8 là những chỗ tốn công và dễ sai nhất; cần test kỹ.

## 8. Ca biên & cách xử lý

- **Em chuyển lớp giữa năm** → lịch kỳ vọng đổi theo. Kết hợp `active_from/to` và lịch sử
  `enrollments`; báo cáo chuyên cần phải tính mẫu số theo lớp **tại từng thời điểm**.
- **Nghỉ lễ / dời buổi / học bù** → cần `schedule_exceptions` (GĐ 2); nếu không, mẫu số đếm
  dư các tuần nghỉ.
- **Học bù ca khác** (lớp sáng đi học ca chiều một hôm) → nhờ `schedule_id` truy vết, cho
  phép điểm danh chéo slot mà vẫn biết gốc.
- **Tương thích ngược** → điểm danh cũ có `schedule_id = NULL` vẫn hiển thị bình thường; có
  thể viết script suy ngược slot cho dữ liệu cũ.
- **Phân quyền** → tái sử dụng `scan_class_ids` / `can_access_class` hiện có: giáo lý viên
  chỉ thấy và điểm danh lịch của lớp/khối mình phụ trách.

## 9. Lộ trình triển khai

Chia nhỏ để giảm rủi ro, mỗi giai đoạn đều chạy được độc lập:

1. **GĐ 1 — Nền dữ liệu:** tạo `class_schedules` + `attendances.schedule_id` + màn CRUD thời
   khóa biểu (chưa đụng luồng điểm danh). Seed lịch = suy từ program hiện tại để không gãy
   gì.
2. **GĐ 2 — Luồng điểm danh:** chuyển `programsOnDate` + `attendance.php` sang sinh buổi theo
   lịch lớp; ghi `schedule_id`.
3. **GĐ 3 — Lịch & báo cáo:** cập nhật `calendar.js` và mẫu số chuyên cần theo lịch lớp.
4. **GĐ 4 — Ngoại lệ:** thêm `schedule_exceptions` cho nghỉ / dời / bù.

Mỗi giai đoạn nên có test (dự án đã có PHPUnit) trước khi sang giai đoạn sau.

## 10. Rủi ro, đánh đổi & đề xuất

**Đánh đổi so với Hướng A (gắn phạm vi cho program)**

- **Mạnh hơn:** mỗi lớp có lịch thật, tự sinh buổi, hỗ trợ sáng/chiều/đổi giữa năm như công
  dân hạng nhất.
- **Nặng hơn:** đụng 6–7 file xuyên suốt stack + đổi cách tính mẫu số chuyên cần (rủi ro sai
  số liệu nếu bỏ sót exceptions).

**Rủi ro chính**

- Sai mẫu số chuyên cần khi chưa có `schedule_exceptions` → giảm thiểu bằng cách làm GĐ 4 sớm
  hoặc khóa mẫu số ở bản thủ công trong lúc chờ.
- Client `attendance.js` logic sinh buổi phức tạp hơn → cần test với lớp có nhiều slot.
- Migrate dữ liệu điểm danh cũ (`schedule_id = NULL`) → chấp nhận để NULL, không bắt buộc suy
  ngược.

**Đề xuất quyết định**

1. Chốt làm **B‑nhẹ** trước (giữ `program_id` làm cầu nối).
2. Bắt đầu từ **GĐ 1** — an toàn vì chưa đụng luồng điểm danh đang chạy.
3. Cần ban điều hành xác nhận: **nhãn slot** (chỉ `sáng`/`chiều`/`tối` hay cần thêm?) và phạm
   vi đổi lịch (theo năm hay theo học kỳ `terms`).
