# Báo Cáo Audit Toàn Vẹn Dữ Liệu — GĐGL Phú Trung
**Ngày:** 2026-05-10
**Phạm vi:** Đọc tĩnh code + schema (không có DB để chạy truy vấn)
**Người thực hiện:** Claude Code (agent)

---

## Tổng quan

| Mức | Số | Mã |
|-----|----|----|
| 🟠 Cao | 1 | DATA-1 |
| 🟡 Trung bình | 2 | DATA-2, DATA-3 |
| 🔵 Thấp / Thông tin | 1 | DATA-4 |

> ⚠️ **Giới hạn:** Không có MariaDB để chạy truy vấn orphan check.

---

## Baseline đã có

| Điểm | Trạng thái |
|-------|------------|
| Schema SQL | ✅ Có |
| Migrations | ✅ Có (6 files) |
| Install.php $migrations | ✅ Có |
| Foreign Keys | ✅ ~91 FK constraints |
| UNIQUE constraints | ✅ students.code, members.phone |
| Indexes | ✅ |

---

## Vấn đề

### 🟠 DATA-1 — Ba nguồn lược đồ có thể lệch

- **Vị trí:**
  - `config/schema.sql`
  - `config/migrations/*.sql`
  - `config/install.php` ($migrations array)
- **Mô tả:** Ba nơi định nghĩa schema → dễ trôi lệch. Đã gây lỗi quyền `thu_vien`.
- **Cách sửa:**
  1. Schema mới chỉ thêm ở migrations
  2. Schema.sql phải phản ánh migrations
  3. Install.php $migrations phải đồng bộ

---

### 🟡 DATA-2 — INSERT IGNORE có thể nuốt lỗi thật

- **Vị trí:**
  - `attendance.php:32,183,193`
  - `announcements.php:205,212`
  - `programs.php:120`
- **Mô tả:** INSERT IGNORE có thể ẩn lỗi constraint/trigger thật
- **Cách sửa:** Dùng ON DUPLICATE KEY UPDATE thay vì INSERT IGNORE

---

### 🟡 DATA-3 — Thiếu test đối soát ví Mộc

- **Mô tả:** Cần chạy truy vấn đối soát trên DB:
```sql
SELECT ss.student_id, ss.current_balance, ss.held_balance,
       (SELECT COALESCE(SUM(amount),0) FROM stamp_transactions st
         WHERE st.student_id=ss.student_id AND st.year_id=ss.year_id) AS tong_gd
  FROM student_stamps ss
 HAVING ss.current_balance < 0 OR ss.held_balance < 0 OR ss.held_balance > ss.current_balance
     OR ss.current_balance <> tong_gd;
```
- **Cách sửa:** Chạy truy vấn trên DB test, thêm test vào CI

---

### 🔵 DATA-4 — Cần test orphan check

- **Mô tả:** Cần chạy các truy vấn kiểm tra dòng mồ côi:
```sql
-- Ghi danh trỏ tới em/lớp không tồn tại
SELECT COUNT(*) FROM enrollments e LEFT JOIN students s ON s.id=e.student_id WHERE s.id IS NULL;
SELECT COUNT(*) FROM enrollments e LEFT JOIN classes  c ON c.id=e.class_id   WHERE c.id IS NULL;

-- Thành viên có role_code không có trong roles
SELECT COUNT(*) FROM members m LEFT JOIN roles r ON r.code=m.role_code WHERE r.code IS NULL;
```
- **Cách sửa:** Chạy truy vấn trên DB test

---

## Truy vấn orphan check (cần chạy trên DB)

```sql
-- Ghi danh trỏ tới em/lớp không tồn tại
SELECT COUNT(*) FROM enrollments e LEFT JOIN students s ON s.id=e.student_id WHERE s.id IS NULL;
SELECT COUNT(*) FROM enrollments e LEFT JOIN classes  c ON c.id=e.class_id   WHERE c.id IS NULL;

-- Thành viên có role_code không có trong roles
SELECT COUNT(*) FROM members m LEFT JOIN roles r ON r.code=m.role_code WHERE r.code IS NULL;

-- Phân công trỏ lớp đã xóa
SELECT COUNT(*) FROM member_assignments a LEFT JOIN classes c ON c.id=a.class_id
  WHERE a.class_id IS NOT NULL AND c.id IS NULL AND a.to_date IS NULL;

-- Điểm số của học kỳ không thuộc niên khoá nào
SELECT COUNT(*) FROM scores sc LEFT JOIN terms t ON t.id=sc.term_id WHERE t.id IS NULL;
```

---

## Checklist cần chạy trên DB

- [ ] So sánh schema giữa fresh install và upgraded DB
- [ ] Chạy orphan check queries
- [ ] Chạy đối soát ví Mộc
- [ ] Kiểm FK ON DELETE behavior

---

## Khuyến nghị

1. **DATA-1:** Đồng bộ 3 nguồn lược đồ
2. **DATA-2:** Thay INSERT IGNORE bằng ON DUPLICATE KEY UPDATE
3. Thêm test đối soát vào CI
