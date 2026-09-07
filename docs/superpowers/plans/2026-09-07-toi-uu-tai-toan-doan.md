# Tối ưu tải cho Admin/BĐH (toàn đoàn) — Kế hoạch

> **Trạng thái:** PLAN — chưa code. Viết sau khi "thử tải" 600 em cho thấy app treo.

**Vấn đề (đo thực tế trên DB demo 600 em):** `api/data.php` trả **~4,9 MB JSON**
mỗi lần mở, trong đó **~34.792 dòng điểm danh** chiếm ~90%. Trình duyệt tải + parse
+ biến toàn bộ thành reactive (Alpine) → **treo**, nặng nhất với Admin/BĐH (phạm vi
toàn đoàn). Máy chủ sinh nhanh (0,1s) → nghẽn là ở **client**, do payload.

**Mục tiêu:** payload boot < ~0,5 MB cho mọi vai; các màn báo cáo tính **tổng hợp
server-side**; các màn chi tiết **tải lười**. GLV (phạm vi nhỏ) vẫn mượt như cũ.

**Nguyên tắc:** làm **từng Task, app luôn chạy được** sau mỗi Task. Bỏ `attendances`
khỏi payload gộp là **Task cuối** (chỉ khi không còn màn nào phụ thuộc mảng đó).

## Bản đồ tiêu thụ `this.attendances` (đã khảo sát)
| Module | Dùng để | Cần gì |
|---|---|---|
| `stats.js` (Thống kê) | tổng hợp toàn đoàn: theo lớp, tỷ lệ, "em cần quan tâm" | **tổng hợp server** |
| `analytics.js` (Phân tích) | tỷ lệ, theo tuần, theo lớp, em <70% | **tổng hợp server** |
| `attendance.js` (Điểm danh) | tìm/ghi bản ghi của **1 buổi** | **tải theo buổi** |
| `qrscan.js` (Quét QR) | kiểm đã điểm danh chưa của **1 buổi** | **tải theo buổi** |
| `student_profile.js` (Hồ sơ) | thống kê điểm danh **1 em** | **tải theo em** |

`scores` (~2.4k dòng) hiện chưa gây treo → giữ nguyên bản này; nếu sau này nặng
thì áp cùng cách. Trọng tâm plan: **điểm danh**.

## Ràng buộc chung
- CSRF + require_permission + **kiểm phạm vi** (`accessible_class_ids`/`can_access_class`)
  ở mọi endpoint mới. Prepared statements. Tránh JOIN đụng collation (ghép ở PHP như
  bài học `bxh.php`).
- Mẫu tham chiếu: `public/bxh.php` (đã tính tổng hợp server-side, chạy nhẹ với 600 em).
- Kiểm thử mỗi Task: `php -l`, `node --check`, PHPUnit xanh, và **đo lại payload**
  `data.php` trên DB demo (mục tiêu giảm dần về <0,5 MB).

---

## Task 1 — Tổng hợp Thống kê ở server
**Files:** tạo/ sửa `public/api/stats.php` (action `summary`); sửa `stats.js`.

- [ ] `api/stats.php?action=summary&month=YYYY-MM` (hoặc kỳ) → trả JSON đúng dạng
  `stats.js` đang tự tính client: `statSummary` (mỗi lớp {present,late,excused,
  unexcused,total,rate} + tổng), `studentsOfConcern`, `statLeaveCounts`, `statRoster`,
  cờ `showBlockComparison`. Tính bằng SQL GROUP BY trong phạm vi người dùng.
- [ ] `stats.js`: `openStats()` gọi endpoint, lưu vào state (vd `statData`); các
  getter đọc `statData` thay vì lặp `this.attendances`. View `module_stats.php` đọc
  state (đổi tối thiểu).
- [ ] Kiểm: mở Thống kê (admin, demo 600) ra số đúng, không lặp mảng lớn. Commit.

## Task 2 — Tổng hợp Phân tích ở server
**Files:** tạo/ sửa `public/api/analytics.php` (action `summary`); sửa `analytics.js`.

- [ ] `api/analytics.php?action=summary` → `attendanceStats{present,late,excused,
  unexcused,rate,totalPossible}`, `weeklyData[]` (tỷ lệ theo tuần), `classData[]`
  (tỷ lệ theo lớp), `lowAttendance[]` (em <70%). SQL GROUP BY theo tuần/lớp.
- [ ] `analytics.js`: `openAnalytics()` nạp endpoint vào state; getter đọc state.
- [ ] Kiểm mở Phân tích (demo 600) đúng số. Commit.

## Task 3 — Tải điểm danh theo buổi (Điểm danh + QR)
**Files:** `public/api/attendance.php` (action `session`); `attendance.js`, `qrscan.js`.

- [ ] `api/attendance.php?action=session` `{programId, date, classId?}` → danh sách
  bản ghi điểm danh của đúng buổi đó trong phạm vi (thường 1 lớp ~30 em → rất nhẹ).
- [ ] `attendance.js`: khi mở/chọn buổi → nạp records buổi đó vào `this.attendances`
  (chỉ buổi đó) rồi tìm/ghi như cũ. Ghi (`save`) vẫn như hiện tại; cập nhật mảng cục bộ.
- [ ] `qrscan.js`: trước khi quét một buổi → nạp records buổi đó để biết ai đã điểm danh.
- [ ] Kiểm: điểm danh + quét QR 1 lớp chạy đúng; không cần mảng toàn đoàn. Commit.

## Task 4 — Tải điểm danh theo em (Hồ sơ)
**Files:** `public/api/students.php` (hoặc `student_profile`) action `history`; `student_profile.js`.

- [ ] Endpoint `{studentId}` → điểm danh + điểm + phiếu của **1 em** (kiểm phạm vi
  lớp của em). `openStudentProfile()` nạp vào state riêng (`profileData`), các hàm
  `getAttendanceRate`/`getUnexcusedAbsences` đọc state đó thay vì `this.attendances`.
- [ ] Kiểm mở hồ sơ 1 em (demo) ra số đúng. Commit.

## Task 5 — Bỏ `attendances` khỏi payload gộp
**Files:** `public/api/data.php`; `core.js` (`loadData`).

- [ ] `data.php`: **không trả `attendances`** nữa (hoặc chỉ trả rỗng []). Giữ
  students/programs/scores/leave/reports/announcements/members/logs.
- [ ] `core.js loadData()`: `this.attendances = []` (khởi tạo rỗng); các màn tự nạp
  (Task 1–4). Bảo đảm không màn nào còn giả định mảng đầy đủ.
- [ ] **Đo lại** `data.php` trên demo 600 → kỳ vọng **< 0,5 MB**. Commit.

## Task 6 — Kiểm thử tổng
- [ ] Unit (PHPUnit) cho các hàm tổng hợp tách được (tỷ lệ, phân nhóm).
- [ ] E2E demo 600 (admin): Trang chủ, Thống kê, Phân tích, Điểm danh, Hồ sơ, Bảng
  thi đua — **mượt, không treo**. Đo payload boot đạt mục tiêu.
- [ ] Xác nhận GLV (phạm vi 1 lớp) vẫn đúng + nhanh.

## Rủi ro & ghi chú
- Đây là refactor lớn nhất từ trước tới nay: đụng stats/analytics/attendance/qrscan/
  student_profile + data.php. Làm tuần tự, mỗi Task giữ app chạy.
- `stats.js`/`analytics.js` hiện tính client rất chi tiết → phải **tái tạo đúng logic
  bằng SQL/PHP**; đọc kỹ 2 file này khi làm (getter `statSummary`, `attendanceStats`,
  `weeklyData`, `classData`, `lowAttendance`, `studentsOfConcern`).
- Có thể thêm cache ngắn cho endpoint tổng hợp (tuỳ chọn, sau).

## Ngoài phạm vi
- Tối ưu `scores`/`reports` (chưa gây treo). Phân trang danh sách Thiếu Nhi (600 em
  trong 1 danh sách cũng nên phân trang — có thể làm plan riêng nếu cần).
