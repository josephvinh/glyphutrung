# Báo cáo Sprint 3 — Đợt F (Kiểm thử/CI) & Đợt G (Hiệu năng)

Ngày: 2026-09-24 · Nhánh: `claude/web-review-plan-we2ika`

## Đợt F — Kiểm thử & CI/CD

### 🔴 F1 — Bug thật lọt lưới vì CI không chặn lỗi — ĐÃ SỬA
`config/migrations/index.php:115` gán biến bên trong nội suy chuỗi:
```php
echo "📋 {$count = count($pending)} migration(s) chờ:\n";   // ❌ parse error
```
Đây là **lỗi cú pháp PHP** ở mọi phiên bản → trình chạy migration **crash khi parse**
mỗi khi có migration chờ. CI **không bắt được** vì bước `php -l` kết thúc bằng `|| true`.
→ **Đã sửa** (tách `$count = count($pending);` ra trước) và **đã xác minh cả 132 file
PHP đều lint sạch**.

### 🟠 F2 — CI không chặn lỗi (`|| true`) — ĐÃ SỬA phần PHP
`.github/workflows/ci.yml`:
- Bước **PHP Syntax**: trước đây `... | grep ... || true` → luôn xanh. **Đã sửa** thành
  vòng lặp `php -l` từng file và `exit 1` nếu có lỗi (an toàn vì 132 file đã sạch).
- Bước **ESLint**: vẫn `npx eslint ... || true` (non-blocking) và **chưa có file cấu
  hình** (`eslint.config.js`), nên ESLint 9 thực chất không kiểm gì. → **Đề xuất**: thêm
  `eslint.config.mjs` (globals trình duyệt + Alpine), sửa dần cảnh báo rồi bỏ `|| true`.

### 🟠 F3 — PHPUnit cần MySQL nhưng CI không có service DB (Trung bình)
`tests/bootstrap.php` nạp `public/api/_bootstrap.php` → `config/db.php` **kết nối DB ngay
khi include**. Vì vậy mọi test cần MySQL; trong môi trường không DB test báo *"Không kết
nối được cơ sở dữ liệu"*. Job `php-unit` trên CI (không khai báo service `mysql`) nhiều
khả năng **không thực sự chạy được** các test chạm DB.

**Đề xuất cấu hình CI** (thêm vào job `php-unit`):
```yaml
    services:
      mysql:
        image: mysql:8
        env: { MYSQL_ROOT_PASSWORD: root, MYSQL_DATABASE: ylcqukhi_glyphutrung }
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping" --health-interval=10s
          --health-timeout=5s --health-retries=5
    steps:
      # … sau checkout, nạp schema:
      - run: mysql -h127.0.0.1 -uroot -proot ylcqukhi_glyphutrung < config/schema.sql
      # cấu hình config.local.php trỏ về DB test rồi mới chạy phpunit
```
Hoặc tách tầng dữ liệu để test thuần logic (Password/CSRF/ThiDua) **không** phải nạp DB
(cho `db.php` kết nối *lazy* thay vì khi include).

### 🟢 F4 — Độ phủ test còn mỏng
Hiện có 6 file unit (`ThiDua/Scope/PermissionHardening/CSRF/Assignment/Permission/Password`).
Nên bổ sung cho các luồng rủi ro cao: `attendance` (giờ chốt), `export`, `promotion`,
và các nhánh `scope`/IDOR chưa phủ.

## Đợt G — Hiệu năng

### Đánh giá: **Khá tốt**
- **Cache**: endpoint boot nặng `data.php` dùng `Cache::get/set` (file-based, TTL 60s) cho
  toàn bộ payload → giảm tải DB đáng kể cho lần tải lại.
- **Không thấy N+1 rõ ràng**: các vòng lặp báo cáo/thống kê dùng **truy vấn tổng hợp**
  (`JOIN … GROUP BY`, `IN (?,?,…)`) thay vì truy vấn trong vòng lặp.
- **Index hot-path đầy đủ** (xem Đợt E) hỗ trợ các truy vấn lọc theo năm/buổi/lớp.
- **Build**: có minify JS/CSS (esbuild) và tối ưu ảnh (sharp); `bundle.php` nối source khi thiếu bản min.

### 🟢 G1 — Chiến lược vô hiệu hoá cache (Thấp)
Cache TTL cố định 60s: sau khi ghi (điểm danh/điểm) người dùng có thể thấy dữ liệu cũ tối
đa 60s. Cân nhắc **chủ động xoá khoá cache liên quan** sau thao tác ghi (đã có `sync.txt`
cho polling — có thể tận dụng), thay vì chỉ chờ hết hạn.

### 🟢 G2 — Tải asset (Thấp)
- Cân nhắc lazy-load module JS theo route (26 module) để giảm JS tải ban đầu.
- Kiểm `Cache-Control`/ETag cho asset tĩnh (`assets/`), preconnect/preload font.

## Kết luận Sprint 3
Phát hiện & sửa **1 bug thật** (F1) mà CI đáng lẽ phải bắt; đã **siết CI PHP** để lần sau
không lọt. Việc cần làm tiếp quan trọng nhất: **cho PHPUnit chạy được trên CI** (F3) để
test thực sự có giá trị. Hiệu năng nền tảng tốt, chỉ cần tinh chỉnh cache-invalidation và
tải asset.
