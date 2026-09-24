# Báo cáo Sprint 2 — Đợt C (Backend PHP) & Đợt E (CSDL)

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

## Đợt C — Backend PHP

### Đánh giá: **Chất lượng tốt, kiến trúc nhất quán**

Xác nhận qua rà soát toàn bộ 29 endpoint:

- **Phân quyền chuẩn**: `require_permission()` tự gọi `require_login()`, chặn theo
  `view/edit` và trạng thái bảo trì module. Mẫu **guard-trong-từng-case** áp dụng
  nhất quán: mỗi action (`save/toggle/delete/read/rsvp…`) đều gọi `require_write()`
  (POST+CSRF) + `require_permission()`/`require_login()` ngay đầu case. **Không có
  action nào bị hở.** Endpoint công khai duy nhất là `push.php?key` (trả VAPID
  *public* key — đúng thiết kế).
- **Chống SQLi**: kiểm cả 2 truy vấn UPDATE động (`programs.php $cols`,
  `library.php $set`) — tên cột **hardcoded**, chỉ giá trị mới tham số hóa (`?`).
  Mệnh đề `IN(...)` dựng placeholder an toàn. Không phát hiện SQLi.
- **Validation đầu vào tốt**: ép kiểu `(int)`/`trim`, whitelist enum
  (`type`, `status`), chặn biên (`dayOfWeek` 0–6), lọc mảng `array_filter(intval)`.
- **Toàn vẹn giao dịch**: helper `trong_giao_dich()` (beginTransaction/commit/rollBack + ném lại lỗi).
- **Đa niên khoá**: hầu hết truy vấn gắn `year_id=?` → cô lập dữ liệu theo năm học.
- **Mã HTTP phong phú**: 400/401/403/404/405/409/500/503 dùng đúng ngữ cảnh.
- **Nhật ký thao tác**: `log_action()` ghi `activity_logs`.
- **Đăng nhập an toàn**: `login_throttle` (theo SĐT + IP), `session_regenerate_id(true)`.

### Ghi chú/đề xuất nhỏ (không phải lỗi)
- 🟢 **C1**: `leave.php case 'create'` yêu cầu quyền `leave:view` để **tạo** đơn nghỉ.
  Có thể cố ý (thành viên tự nộp đơn), nhưng nên xác nhận đúng ý đồ: hành động ghi
  thường gắn `edit`. Rà lại ma trận quyền cho khớp mong đợi.
- 🟢 **C2**: `data.php` (424 dòng) và `export.php` (399 dòng) khá lớn — cân nhắc tách
  hàm/nhóm theo chức năng để dễ bảo trì (không gấp).
- 🟢 **C3**: logic giờ chốt điểm danh (`attendance.php`) dựa `strtotime($date.' '.start_time)+cutoff*60`
  và `absent_time` — cần đảm bảo `date_default_timezone_set('Asia/Ho_Chi_Minh')` được
  áp dụng nhất quán ở mọi entrypoint (đã thấy đặt rải rác). Gom về `_common.php`/bootstrap
  để tránh lệch giờ.

## Đợt E — Cơ sở dữ liệu

### Đánh giá: **Thiết kế schema trưởng thành**

- **Khóa ngoại đầy đủ** với `ON DELETE CASCADE`/`SET NULL` hợp lý trên attendances,
  enrollments, scores, leave_requests, announcement_reads, activity_logs…
- **Ràng buộc UNIQUE ở tầng DB** chống trùng: `uq_att(program_id, session_date,
  student_id)`, `uq_enr(year_id, student_id)`, `uq_score(term_id, student_id,
  type_code)`, `uq_lv(program_id, session_date, student_id)` — rất tốt, chặn lỗi
  ngay cả khi tầng ứng dụng sót.
- **Index hot-path hợp lý**: `idx_att_lookup(year_id, session_date)`,
  `idx_att_student(student_id, year_id)`, `idx_enr_class(year_id, class_id)`,
  `idx_lv_status(year_id, status)`, `login_attempts(phone,tried_at)`+`(ip,tried_at)`.

### 🟠 E1 — Hai hệ quản lý schema song song, KHÔNG đồng bộ (Trung bình)
- `config/schema.sql`: schema đầy đủ **30 bảng** (dùng khi cài mới).
- `config/migrations/`: có migration runner đàng hoàng (`index.php` + bảng
  `schema_migrations` tracking), **nhưng** chỉ có `001_initial_schema.sql` chứa
  vỏn vẹn **1 CREATE TABLE** → hệ migration gần như bỏ không.
- `config/migrate_*.php`: **12 script migrate rời rạc** (passkey, roles_du_bi,
  permissions, program_cutoff…) chạy tay, **không** được `schema_migrations` theo dõi.

**Rủi ro**: DB cài mới (từ schema.sql) và DB nâng cấp dần (từ các migrate_*.php) dễ
**lệch nhau**; khó biết môi trường nào đã chạy migrate nào.

**Đề xuất**: chọn MỘT nguồn sự thật. Hoặc (a) đưa toàn bộ thay đổi vào numbered
migrations `002_*.sql, 003_*.sql…` do `migrations/index.php` chạy & tracking, rồi
sinh `schema.sql` từ đó; hoặc (b) giữ schema.sql là chuẩn và mỗi migrate_*.php ghi
một dòng vào `schema_migrations`. Không nên duy trì cả 3 cách như hiện tại.

### 🟢 E2 — Index bổ sung (Thấp)
- `scores`: UNIQUE `(term_id, student_id, type_code)` — truy vấn lọc theo
  `student_id` mà không có `term_id` sẽ không dùng được prefix trái. Nếu có màn hình
  "điểm theo em xuyên kỳ", cân nhắc thêm index `(student_id)`.

## Kết luận Sprint 2
Backend & CSDL là **phần mạnh nhất** của dự án: an toàn, nhất quán, ràng buộc chặt.
Không có lỗi bảo mật/logic nghiêm trọng phát hiện thêm. Việc đáng làm nhất là **hợp
nhất hệ migration (E1)** để tránh lệch schema về sau. Không thực hiện thay đổi schema
trong đợt này vì container không có MySQL để kiểm chứng an toàn (xem Đợt F).
