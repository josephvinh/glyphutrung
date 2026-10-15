# Triển khai (deploy) lên host — thứ tự và cách kiểm

> Mã mới lên host mà **chưa chạy migration** thì lỗi theo kiểu khó đoán. Hai sự cố
> đã gặp thật: thiếu bảng `push_notifications` (migration 030) làm **mọi trang sau
> đăng nhập trắng** (trang khách vẫn lên); thiếu cột `scanned_at`/`offline_marked`
> (009) làm điểm danh lỗi khi ghi. Luôn theo thứ tự dưới đây.

## Thứ tự

1. **Sao lưu DB** (phpMyAdmin → Export → Quick, hoặc `mysqldump`). Sao lưu cả
   `storage/` và `config/config.local.php`.
2. **Kiểm DB còn thiếu gì** (chỉ đọc, an toàn trên DB thật) — xem mục dưới.
3. **Chạy các migration còn thiếu** trong `config/migrations/`, theo số thứ tự.
   File migration idempotent (có `IF EXISTS`/kiểm cột trước khi thêm): chạy lại
   không hỏng. Chạy bằng `mysql < file.sql` hoặc dán nội dung vào tab SQL của
   phpMyAdmin (chọn đúng database).
4. **Kiểm lại** — mọi dòng phải ✅.
5. **Rồi mới đưa mã lên** (`git pull` / rsync).
6. **Kiểm sau deploy:** đăng nhập, mở dashboard, điểm danh thử một em; xem log lỗi
   PHP của host không có dòng mới.

Không chạy `config/install.php` / `seed_demo.php` trên DB thật (CLAUDE.md luật 8).

## Kiểm DB đã đủ migration chưa

**Có terminal / SSH:**

```bash
php scripts/kiem_schema.php
```

In ✅/❌ cho từng migration và liệt kê file cần chạy; mã thoát 1 nếu còn thiếu.
`php scripts/kiem_schema.php --sql` in lại câu SQL ở dưới.

**Chỉ có phpMyAdmin:** mở tab **SQL của database** (không phải của một bảng, và
không phải database `information_schema`), đổi `TÊN_DATABASE` rồi chạy. Cột `co` =
`0` là **thiếu**; kết quả được xếp để các dòng thiếu lên đầu.

```sql
SET @db = 'TÊN_DATABASE';   -- ĐỔI thành tên database thật
SELECT x.migration, x.bang, x.cot,
  CASE WHEN x.cot IS NULL
    THEN (SELECT COUNT(*) FROM information_schema.TABLES  t WHERE t.TABLE_SCHEMA = @db AND t.TABLE_NAME = x.bang)
    ELSE (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = @db AND c.TABLE_NAME = x.bang AND c.COLUMN_NAME = x.cot)
  END AS co
FROM (
  SELECT '003_stamps_rewards.sql' AS migration, 'student_stamps' AS bang, NULL AS cot
  UNION ALL SELECT '003_stamps_rewards.sql', 'stamp_transactions', NULL
  UNION ALL SELECT '003_stamps_rewards.sql', 'gifts', NULL
  UNION ALL SELECT '003_stamps_rewards.sql', 'gift_orders', NULL
  UNION ALL SELECT '003_stamps_rewards.sql', 'gift_order_items', NULL
  UNION ALL SELECT '004_tracuu_throttle.sql', 'tracuu_attempts', NULL
  UNION ALL SELECT '005_tracuu_code_fails.sql', 'tracuu_code_fails', NULL
  UNION ALL SELECT '006_bible_tracking.sql', 'bible_daily', NULL
  UNION ALL SELECT '007_student_retention.sql', 'students', 'hidden_at'
  UNION ALL SELECT '007_student_retention.sql', 'students', 'deleted_at'
  UNION ALL SELECT '009_offline_attendance_flag.sql', 'attendances', 'scanned_at'
  UNION ALL SELECT '009_offline_attendance_flag.sql', 'attendances', 'offline_marked'
  UNION ALL SELECT '030_push_notifications.sql', 'push_notifications', NULL
  UNION ALL SELECT '030_push_notifications.sql', 'push_outbox', 'notification_id'
  UNION ALL SELECT '031_add_organization_tables.sql', 'classes', 'shift'
  UNION ALL SELECT '031_add_organization_tables.sql', 'members', 'glv_year'
  UNION ALL SELECT '031_add_organization_tables.sql', 'member_service_history', NULL
) x
ORDER BY co, x.migration;
```

Migration 008 chỉ gieo quyền (không có bảng/cột): kiểm bằng
`SELECT COUNT(*) FROM permissions WHERE module_key = 'thu_vien';` — phải > 0 (script
CLI đã kiểm sẵn).

## Khi thêm migration mới

- Thêm file `config/migrations/NNN_*.sql` (idempotent) + cập nhật `install.php`.
- Nếu có bảng/cột mới: thêm một dòng vào `kiem_schema_danh_sach()` trong
  `scripts/kiem_schema.php` **và** vào khối SQL trên (test `SchemaCheckTest` bắt
  nếu hai nơi lệch nhau).
- Ghi vào mô tả PR: "cần chạy migration NNN trước khi triển khai".

## Sau deploy: bộ nhớ đệm

- `storage/cache/` (ngoài web root) phải tồn tại và ghi được; `public/cache/` cũ
  phải xóa. `https://<miền>/cache/sync.txt` và `https://<miền>/storage/cache/.gitignore`
  phải trả 403/404 — nếu hiện nội dung thì `storage/` đang lộ ra web: tên miền phải
  trỏ vào `public/`.
