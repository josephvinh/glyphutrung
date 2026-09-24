# Hợp nhất hệ thống Migration — Thiết kế & Quy trình

Ngày: 2026-09-24 · Liên quan: Đợt E (E1) trong `docs/review/`

## Vấn đề hiện tại (3 nơi quản lý schema, không đồng bộ)
1. `config/schema.sql` — schema đầy đủ 30 bảng, dùng khi **cài mới** (qua `install.php`).
2. `config/install.php` — ngoài schema.sql còn có **mảng `$migrations` nội tuyến** (vài
   `ALTER/CREATE`) chạy idempotent, rồi seed vai/module/quyền.
3. `config/migrate_*.php` — **12 script rời** chạy tay trên máy chủ, **không được
   `schema_migrations` theo dõi**.
4. `config/migrations/index.php` — runner đúng chuẩn (tracking bằng bảng
   `schema_migrations`) nhưng gần như bỏ trống (chỉ `001_initial_schema.sql`).
   *(Runner này vừa được sửa lỗi parse ở Đợt F — nay chạy được.)*

**Rủi ro**: DB cài mới (từ schema.sql) và DB nâng cấp dần (chạy tay migrate_*.php) dễ lệch
nhau; không có bản ghi script nào đã chạy.

## Phân loại 12 script `migrate_*.php`

| Script | Loại thao tác | Chuyển sang SQL numbered? |
|--------|---------------|---------------------------|
| `migrate_passkey.php` | CREATE TABLE | ✅ DDL thuần |
| `migrate_program_cutoff.php` | ALTER ADD COLUMN | ✅ DDL thuần |
| `migrate_thu_vien.php` | CREATE TABLE + ALTER + seed modules/perms/categories | ✅ DDL + INSERT IGNORE |
| `migrate_lich_hop.php` | CREATE TABLE + seed + **có hàm PHP** | ⚠️ Tách phần DDL sang SQL; xem lại phần PHP |
| `migrate_guide.php` | INSERT IGNORE modules/permissions | ✅ Data seed idempotent |
| `migrate_bdh_view.php` | UPDATE permissions (có điều kiện) | ✅ SQL có điều kiện |
| `migrate_perm_hardening.php` | UPDATE roles + INSERT IGNORE perms | ✅ SQL có điều kiện |
| `migrate_permissions.php` | INSERT IGNORE + UPDATE permissions | ✅ SQL có điều kiện |
| `migrate_modules_dongbo.php` | UPDATE modules | ✅ SQL có điều kiện |
| `migrate_modules_sync.php` | INSERT + UPDATE modules/permissions | ✅ SQL có điều kiện |
| `migrate_roles_du_bi.php` | INSERT/UPDATE roles/titles/permissions | ✅ SQL có điều kiện |
| `migrate_student_codes.php` | UPDATE students (**sinh mã bằng PHP**) | ⚠️ Giữ dạng PHP migration (logic sinh mã) |

→ 10/12 chuyển được sang SQL numbered; 2 script có **logic PHP** (`lich_hop` định nghĩa
hàm, `student_codes` sinh mã) nên giữ dạng migration PHP có tracking.

## Thiết kế mục tiêu (MỘT nguồn sự thật)

```
config/
  schema.sql                     # ĐẦY ĐỦ, hiện hành — chỉ dùng cài mới
  migrations/
    index.php                    # runner DUY NHẤT (đã hỗ trợ tracking)
    001_initial_schema.sql        # = toàn bộ schema.sql (đồng bộ)
    002_passkey.sql               # từ migrate_passkey
    003_program_cutoff.sql        # từ migrate_program_cutoff
    004_thu_vien.sql              # từ migrate_thu_vien (DDL + seed)
    005_lich_hop.sql              # phần DDL của migrate_lich_hop
    006_perm_hardening.sql        # từ migrate_perm_hardening
    007_modules_sync.sql          # gộp modules_dongbo + modules_sync
    008_roles_du_bi.sql           # từ migrate_roles_du_bi
    009_guide.sql                 # từ migrate_guide
    010_permissions_fix.sql       # gộp permissions + bdh_view
    011_student_codes.php         # GIỮ PHP (sinh mã) — runner hỗ trợ .php
```

Nguyên tắc:
- **Cài mới**: `install.php` chạy `schema.sql` + seed (bỏ mảng `$migrations` nội tuyến —
  dời các ALTER đó vào `schema.sql` cho schema luôn hiện hành).
- **Nâng cấp DB cũ**: chỉ một lệnh `php config/migrations/index.php` — runner áp dụng các
  `002+` chưa chạy và ghi vào `schema_migrations`.
- Không dùng `shell_exec` (host chia sẻ hay chặn) — runner đọc `.sql` và `exec` trực tiếp,
  còn migration `.php` thì `include` trong hàm bọc để tránh trùng khai báo.

## Quy trình thực hiện AN TOÀN (bắt buộc có DB để kiểm chứng)

> Không nên đổi trên máy không có MySQL. Dùng chính service MySQL của CI (đã dựng ở Đợt F)
> hoặc một DB staging.

1. **Backup** DB production trước mọi thao tác.
2. Tạo DB staging từ bản sao production.
3. Chuyển từng `migrate_*.php` thành file `.sql`/`.php` numbered theo bảng trên; giữ tính
   **idempotent** (IF NOT EXISTS / INSERT IGNORE / UPDATE có điều kiện — như bản gốc).
4. Trên staging (đã chạy tay các migrate cũ): chạy `php config/migrations/index.php` —
   phải báo "không có migration chờ" hoặc áp dụng sạch, không đổi dữ liệu ngoài dự kiến.
5. Trên một DB **cài mới** từ `schema.sql`: chạy runner — mọi `002+` phải no-op (vì
   schema.sql đã hiện hành) và được đánh dấu đã chạy.
6. Chạy `phpunit` (job CI có DB) để đảm bảo không hồi quy.
7. Sau khi xác nhận: xoá dần các `migrate_*.php` cũ khỏi repo (đã thay bằng numbered),
   cập nhật `docs/HANDOFF.md` + hướng dẫn deploy.

## Vì sao chưa thực hiện tự động trong đợt này
Chuyển đổi migration đụng trực tiếp cấu trúc & dữ liệu CSDL production. Môi trường review
**không có MySQL** để kiểm chứng từng bước, và host chia sẻ có thể chặn `shell_exec`. Ship
công cụ migration chưa test sẽ rủi ro cho DB thật. Do đó tài liệu này là **spec để thực
hiện có kiểm chứng** trên staging (dùng được ngay service MySQL của CI vừa thêm).
