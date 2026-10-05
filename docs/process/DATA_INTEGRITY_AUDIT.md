# Quy Ước Audit Toàn Vẹn Dữ Liệu / Migration

> Kiểm **cấu trúc + dữ liệu DB** nhất quán: lược đồ không lệch giữa các nguồn,
> khóa ngoại phủ đủ, không có dòng mồ côi, và các con số đối soát được (vd ví Mộc
> = tổng giao dịch). Bổ sung cho `FUNCTIONAL_AUDIT.md` (bất biến lúc chạy) ở góc
> **trạng thái tĩnh của DB**.

---

## 1. Rủi ro lớn nhất: BA nguồn lược đồ dễ lệch

Hiện có **ba** nơi định nghĩa/nâng cấp schema — dễ trôi lệch âm thầm (đã gây lỗi
quyền `thu_vien` khi cài mới):

1. `config/schema.sql` — `install.php` chạy để dựng DB mới.
2. `config/migrations/001…006_*.sql` — các bước nâng cấp đánh số.
3. **Mảng `$migrations` trong `config/install.php`** (~25 lệnh `CREATE/ALTER`).

**Quy ước cần theo:**
- [ ] DB **cài mới** (chạy `schema.sql` + `install.php`) và DB **nâng cấp dần**
      (chạy hết `migrations/`) phải cho **cùng một lược đồ**. Kiểm bằng cách so
      `SHOW CREATE TABLE` giữa hai DB.
- [ ] Mỗi thay đổi cấu trúc mới chỉ thêm ở **một đường chuẩn** (migration đánh
      số), và được phản chiếu vào `schema.sql` cho bản cài mới.
- [ ] Seed dữ liệu khởi tạo (quyền, module) **không `INSERT IGNORE`** cho ràng
      buộc có thể thất bại thật (khóa ngoại) — lỗi phải nổi lên.

## 2. Khóa ngoại & ràng buộc (đã có nhiều — kiểm phủ đủ)

`schema.sql` có ~91 chỗ FK/UNIQUE/INDEX. Kiểm:
- [ ] Mọi cột `*_id` tham chiếu bảng khác có **FOREIGN KEY** (members.role_code→
      roles, enrollments→students/classes, scores→terms…).
- [ ] Hành vi `ON DELETE` đúng ý: `CASCADE` cho dữ liệu con của em (điểm danh/
      điểm xóa theo khi xóa em), `RESTRICT` cho lịch sử cần giữ (`fk_enr_class`).
- [ ] UNIQUE đúng: `students.code`, `members.phone`, ghi danh `uq_enr(year_id,
      student_id)`, điểm danh `(program_id, session_date, student_id)`.
- [ ] Charset/collation **đồng nhất** `utf8mb4_unicode_ci` mọi bảng/cột (tránh
      "Illegal mix of collations" — đã gặp ở bxh/thi_dua nên phải ghép ở PHP).

## 3. Dòng mồ côi & số liệu đối soát (truy vấn định kỳ)

Chạy trên bản sao DB (không phải DB thật khi sửa). Kỳ vọng **0 dòng**:

```sql
-- Ghi danh trỏ tới em/lớp không tồn tại
SELECT COUNT(*) FROM enrollments e LEFT JOIN students s ON s.id=e.student_id WHERE s.id IS NULL;
SELECT COUNT(*) FROM enrollments e LEFT JOIN classes  c ON c.id=e.class_id   WHERE c.id IS NULL;
-- Thành viên có role_code không có trong roles
SELECT COUNT(*) FROM members m LEFT JOIN roles r ON r.code=m.role_code WHERE r.code IS NULL;
-- Phân công đang hiệu lực trỏ lớp đã xóa
SELECT COUNT(*) FROM member_assignments a LEFT JOIN classes c ON c.id=a.class_id
  WHERE a.class_id IS NOT NULL AND c.id IS NULL AND a.to_date IS NULL;
-- Điểm số của học kỳ không thuộc niên khoá nào
SELECT COUNT(*) FROM scores sc LEFT JOIN terms t ON t.id=sc.term_id WHERE t.id IS NULL;
```

**Đối soát Sổ Mộc** (bất biến ví = giao dịch):
```sql
-- Ví phải khớp tổng giao dịch; held không vượt current; không âm
SELECT ss.student_id, ss.current_balance, ss.held_balance,
       (SELECT COALESCE(SUM(amount),0) FROM stamp_transactions st
         WHERE st.student_id=ss.student_id AND st.year_id=ss.year_id) AS tong_gd
  FROM student_stamps ss
 HAVING ss.current_balance < 0 OR ss.held_balance < 0 OR ss.held_balance > ss.current_balance
     OR ss.current_balance <> tong_gd;    -- ra dòng nào = lệch, phải điều tra
```

## 4. Checklist

- [ ] Cài-mới vs nâng-cấp cho cùng lược đồ (so `SHOW CREATE TABLE`).
- [ ] FK phủ đủ; `ON DELETE` đúng ý; không nuốt lỗi seed.
- [ ] UNIQUE/collation nhất quán.
- [ ] Truy vấn mồ côi (mục 3) trả 0 dòng.
- [ ] Đối soát ví Mộc không lệch; không `current/held` âm.
- [ ] Migration mới: đánh số tăng, idempotent, chạy lại an toàn, có trong cả
      `schema.sql` lẫn `migrations/`.

## 5. Công cụ

```bash
# So lược đồ giữa DB cài-mới và DB nâng-cấp-dần
for t in $(mysql -N tntt_fresh -e "SHOW TABLES"); do
  diff <(mysql -N tntt_fresh -e "SHOW CREATE TABLE $t") \
       <(mysql -N tntt_upgraded -e "SHOW CREATE TABLE $t") && echo "$t OK" || echo "$t LỆCH"
done
```
Các test liên quan đã có: `RewardsSchemaTest`, `StampEngineTest`,
`PermissionHardeningTest`. Thêm test đối soát khi đổi quy tắc ví/điểm.

## 6. Báo cáo & quy trình

Finding: **mức** (🔴 mất/hỏng dữ liệu, lược đồ lệch / 🟠 thiếu FK, mồ côi / 🟡 lệch
nhỏ / 🔵 ghi chú), **bảng/truy vấn**, **cách sửa + migration**. Toàn-app:
`docs/audit/DATA_INTEGRITY_AUDIT_<YYYY-MM>.md`. Mọi vá cấu trúc đi qua migration
đánh số (xem `FEATURE_WORKFLOW.md`).

---
_Cập nhật truy vấn đối soát khi thêm bảng/bất biến mới._
