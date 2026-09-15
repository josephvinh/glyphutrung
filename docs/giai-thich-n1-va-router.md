# Giải thích: N+1 Queries và Router/Controller

> Viết cho dự án **TNTT Super App** (glyphutrung.top).
> Mục đích: giải thích rõ hai khái niệm mà bản audit nêu, đối chiếu với **code thật** của mình, và khuyến nghị nên/không nên làm gì ở quy mô một giáo xứ.

---

## Phần 1 — N+1 Queries

### 1.1. N+1 là gì? (nói bằng đời thường)

Tưởng tượng bạn có danh sách **5 niên khoá** và muốn biết mỗi năm có bao nhiêu lượt điểm danh, bao nhiêu chương trình…

- **Cách N+1 (chậm):** Lấy danh sách 5 năm (1 câu hỏi), rồi **với từng năm** lại chạy sang phòng hồ sơ hỏi riêng "năm này bao nhiêu điểm danh?", "bao nhiêu chương trình?"… Mỗi năm hỏi 4 lần → 5 năm = **20 lần chạy đi chạy lại**.
- Con số **"1"** = câu lấy danh sách. **"N"** = mỗi phần tử lại đẻ thêm câu hỏi con. Tổng = 1 + N×(số câu con).

Vấn đề không phải một câu SQL nặng, mà là **quá nhiều câu nhẹ** — mỗi câu tốn một vòng "đi và về" tới CSDL. Giống việc đi chợ mua 20 món nhưng **ra vào cửa hàng 20 lần** thay vì mua một lượt.

### 1.2. Chỗ N+1 THẬT trong code của mình

**File `public/api/years.php`, hàm `year_usage()`:**

```php
function year_usage(int $yearId): array {
    return [
        'enrollments' => (int) db_one('SELECT COUNT(*) n FROM enrollments   WHERE year_id=?', [$yearId])['n'],
        'programs'    => (int) db_one('SELECT COUNT(*) n FROM programs       WHERE year_id=?', [$yearId])['n'],
        'attendances' => (int) db_one('SELECT COUNT(*) n FROM attendances    WHERE year_id=?', [$yearId])['n'],
        'leaves'      => (int) db_one('SELECT COUNT(*) n FROM leave_requests WHERE year_id=?', [$yearId])['n'],
    ];
}
```

Và nó được gọi **trong vòng lặp** khi liệt kê niên khoá:

```php
foreach ($rows as $y) {
    // ...
    'usage' => year_usage((int) $y['id']),   // <-- mỗi năm +4 câu COUNT
}
```

→ 5 năm = 1 (lấy danh sách) + 5×4 = **21 câu query**. Đây **đúng là** N+1.

**Cách sửa (gộp 4 COUNT của mọi năm thành vài câu):**

```php
// Lấy đếm cho TẤT CẢ năm một lần, nhóm theo year_id
$dsEnroll = db_all('SELECT year_id, COUNT(*) n FROM enrollments   GROUP BY year_id');
$dsProg   = db_all('SELECT year_id, COUNT(*) n FROM programs       GROUP BY year_id');
$dsAtt    = db_all('SELECT year_id, COUNT(*) n FROM attendances    GROUP BY year_id');
$dsLeave  = db_all('SELECT year_id, COUNT(*) n FROM leave_requests GROUP BY year_id');
// đổ vào mảng tra cứu [year_id => n], rồi gán cho từng năm — 0 câu query trong vòng lặp
```

→ Từ 21 câu xuống còn **4 câu**, không phụ thuộc số năm.

### 1.3. Chỗ N+1 nhỏ khác

**`public/api/attendance.php` (quét QR):** có vòng lặp `foreach ($codes as $ma)` chạy `INSERT IGNORE` từng em. Về lý thuyết là insert-trong-lặp, nhưng:
- Mỗi lần quét thường chỉ vài em, **không phải hàng trăm**.
- Đã nằm trong **transaction** (`beginTransaction`), nên nhanh hơn nhiều so với insert rời.

→ Ảnh hưởng nhỏ. Có thể gộp thành bulk insert như phần import nếu muốn, nhưng **không cấp thiết**.

### 1.4. Đính chính bản audit: chỗ "nghiêm trọng nhất" là **SAI**

Bản audit nói:
> *"Trầm trọng nhất ở `students.php` (Import Excel): mỗi học sinh gọi `next_student_code()` chứa SELECT MAX + 2 INSERT/UPDATE. 500 học sinh = hơn 1500 query!"*

**Không đúng.** Đọc code thật (`students.php`, hành động import) thì nó đã được tối ưu **rất tốt**:

1. Nạp trước danh sách lớp **một lần**, mã đã tồn tại **một lần**.
2. Tính `SELECT MAX(code)` **đúng MỘT lần** (dòng ~175), rồi tăng số thứ tự **trong bộ nhớ** — **không** gọi `next_student_code()` trong vòng lặp.
3. Gom tất cả vào **một câu INSERT nhiều dòng**, chia lô 500:

```php
$sql = 'INSERT INTO students (...) VALUES ' . implode(',', $chunk_upsert)
     . ' ON DUPLICATE KEY UPDATE ...';
db_run($sql, $chunk_params);   // 500 em = 1 câu, không phải 500
```

→ Import 500 em tốn khoảng **4–6 câu query**, không phải 1500. Đây thực ra là **ví dụ mẫu mực** về cách chống N+1. Bản audit rõ ràng đã **không đọc kỹ** đoạn này (chỉ thấy hàm `next_student_code()` tồn tại rồi suy đoán).

### 1.5. Khi nào N+1 đáng lo — và ở quy mô của mình thì sao?

N+1 chỉ thành **điểm nghẽn thật** khi: **N lớn** (hàng nghìn) **và** trang bị **truy cập liên tục**.

| Chỗ | N thực tế | Tần suất | Đáng sửa? |
|---|---|---|---|
| `year_usage` (years.php) | vài niên khoá | hiếm (màn quản trị) | Nên sửa **khi rảnh** — sửa nhanh, gọn |
| QR điểm danh | vài em/lượt | thường xuyên | Nhỏ, để sau cũng được |
| Import Excel | — | 1 lần/năm | **Đã tối ưu rồi** |

**Kết luận:** Ở quy mô một giáo xứ (vài trăm em, vài chục GLV), N+1 hiện tại **không phải khẩn cấp**. Đáng dọn `year_usage` vì nó rẻ và rõ ràng; còn lại để dành. Bản audit **thổi phồng** mức độ ("nghiêm trọng") so với thực tế.

---

## Phần 2 — Router / Controller

### 2.1. Cấu trúc hiện tại của mình: "flat file + switch"

Mỗi file trong `public/api/` tự lo một nhóm việc, theo khuôn:

```php
require __DIR__ . '/_bootstrap.php';   // nạp nền chung
$in = json_input();
switch ($action) {
    case 'create':  require_post(); require_csrf(); /* ... */ break;
    case 'update':  require_post(); require_csrf(); /* ... */ break;
    case 'list':    /* ... */ break;
}
```

Muốn gọi "tạo niên khoá" thì frontend gọi `api/years.php?action=create`.

### 2.2. Router/Controller là gì?

Là một kiểu tổ chức **tập trung**: mọi request đi vào **một cửa duy nhất** (ví dụ `index.php`), rồi một **bộ định tuyến (Router)** nhìn đường dẫn và giao cho đúng **Controller** (lớp xử lý):

```
POST /api/years/create   ->  Router  ->  YearController->create()
POST /api/students/import ->  Router  ->  StudentController->import()
```

Kèm theo thường có "middleware" (lớp trung gian) lo sẵn `require_post`, `require_csrf`, đăng nhập… để controller khỏi lặp lại.

### 2.3. Vì sao audit đề xuất? (nguyên tắc DRY)

DRY = *Don't Repeat Yourself* — đừng lặp code. Audit thấy mỗi file đều lặp `$in = json_input()`, `switch`, khối `try/commit/catch/rollBack`… nên gợi ý gom về Router + Controller cho gọn.

**Điều này đúng về mặt sách vở.** Nhưng lặp một khuôn mẫu ổn định **không phải là bug**, và nó có cái giá riêng khi thay đổi.

### 2.4. Đánh giá thẳng: **CHƯA nên** viết lại Router bây giờ

| Tiêu chí | Flat file (hiện tại) | Router + Controller |
|---|---|---|
| Người dùng thấy khác gì | — | **Không gì cả** |
| Công sức | 0 | **Rất lớn** (viết lại toàn bộ API) |
| Rủi ro gãy chức năng đang chạy | thấp | **Cao** (đụng vào mọi endpoint) |
| Dễ hiểu với 1 người bảo trì | **Rất dễ** (mở đúng file, đọc `switch`) | Phải hiểu cả tầng router/middleware |
| Hợp lý khi | app nhỏ–vừa, 1–2 người code | app lớn, nhiều người, nhiều chục controller |

Với một app **một người bảo trì**, cỡ vài chục endpoint, cấu trúc phẳng hiện tại **là lựa chọn đúng**: mở `years.php` ra là thấy trọn logic niên khoá, không phải nhảy qua 4 lớp file. Viết lại Router chỉ để "cho đúng chuẩn" là **tối ưu hoá non** (over-engineering) — tốn hàng ngày công, rủi ro cao, mà người dùng **không hưởng lợi gì**.

### 2.5. Nếu muốn "bớt lặp" mà KHÔNG đại phẫu

> ✅ **ĐÃ THỰC HIỆN** (commit `bf58797`). Xem tóm tắt cuối phần này.

Có thể tỉa dần, an toàn, không cần Router:

1. **Gói phần đầu lặp lại** thành 1 helper, ví dụ trong `_bootstrap.php`:
   ```php
   function guard_write() { require_post(); require_csrf(); return require_login(); }
   ```
   Rồi mỗi case ghi chỉ cần: `$me = guard_write();`

2. **Gói khối transaction** thành helper nhận một hàm:
   ```php
   function trong_giao_dich(callable $fn) {
       db()->beginTransaction();
       try { $r = $fn(); db()->commit(); return $r; }
       catch (\Throwable $e) { db()->rollBack(); throw $e; }
   }
   ```
   Dùng: `trong_giao_dich(fn() => /* các lệnh ghi */);`

→ Giảm lặp **ngay**, giữ nguyên cấu trúc phẳng dễ hiểu, không rủi ro viết lại toàn hệ thống.

**Đã làm gì (commit `bf58797`):**
- Thêm `require_write()` và `trong_giao_dich()` vào `public/api/_bootstrap.php`.
- Gộp **44 cặp** `require_post()+require_csrf()` → `require_write()` ở 14 file.
- Áp `trong_giao_dich()` cho **4 khối transaction ngắn** (years create/activate, settings resetPerms, org duyệt).
- **Cố ý GIỮ NGUYÊN:** các khối transaction lớn (import ~80 dòng, org 82 dòng, years update có rollBack lồng, promotion) và `assignments.php` (dùng CSRF không kèm POST). Bọc closure những chỗ này chỉ làm **rối hơn** và **thêm rủi ro** — trái mục tiêu.
- Đã test `trong_giao_dich()` trên DB thật: commit lưu, exception rollback + ném lại, transaction đóng đúng.

---

## Tóm tắt khuyến nghị

| Vấn đề | Mức độ thật | Nên làm |
|---|---|---|
| N+1 ở `year_usage` | Nhỏ, rõ ràng | Dọn khi rảnh (GROUP BY) — vài phút |
| N+1 ở QR điểm danh | Rất nhỏ | Để sau |
| N+1 ở import | **Đã tối ưu** | Không cần làm gì |
| Viết lại Router/Controller | Quan điểm, không phải bug | **Không nên** bây giờ; nếu muốn thì tỉa helper (2.5) |

**Thông điệp chính:** Bản audit đúng về khái niệm nhưng **thổi phồng mức độ** và có chỗ **chấm sai** (import). Ở quy mô giáo xứ, ưu tiên nên là **đúng và dễ bảo trì**, không phải chạy theo mọi "chuẩn kiến trúc" của app lớn.
