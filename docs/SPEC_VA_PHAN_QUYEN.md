# Spec thực thi — Vá lỗ hổng phân quyền thành viên

- **Nguồn:** `docs/BAO_CAO_PHAN_QUYEN_THANH_VIEN.md`
- **Nhánh triển khai:** `claude/member-permissions-review-a3h7bi`
- **Nguyên tắc chung:**
  - Không thay đổi API contract (giữ nguyên `?action=`, dạng JSON trả về) trừ khi ghi rõ.
  - Fail-closed: khi thiếu dữ liệu/không chắc phạm vi → từ chối (403), không mặc định cho phép.
  - Mọi thay đổi ghi phải giữ trong `require_write()` (POST + CSRF) sẵn có.
  - Không ghi lộ chi tiết lỗi CSDL ra client (`safe_error`).

Mỗi hạng mục dưới đây là một đơn vị triển khai độc lập, có thể làm/PR riêng.

---

## F1 — Chặn IDOR sửa & chuyển hồ sơ thiếu nhi ngoài phạm vi

### Bối cảnh
`public/api/students.php` action `save`/`import` chỉ kiểm tra lớp **đích** (`allowed_class_ids`), không kiểm tra lớp **hiện tại** của em khi cập nhật một mã đã tồn tại → người có `students=edit` phạm vi hẹp (`glv_chu_nhiem`, `truong_khoi`) có thể ghi đè PII và kéo em bất kỳ vào lớp mình.

### Thay đổi

**File:** `public/api/students.php`

1. Thêm helper kiểm tra phạm vi trên lớp **hiện tại** của một em (theo enrollment của niên khoá đang mở):

```php
/**
 * Lớp hiện tại của em trong niên khoá đang mở (null nếu chưa ghi danh).
 */
function current_enrollment_class(int $studentId, int $yid): ?int
{
    $r = db_one(
        'SELECT class_id FROM enrollments WHERE year_id = ? AND student_id = ?',
        [$yid, $studentId]
    );
    return $r ? (int) $r['class_id'] : null;
}
```

2. Trong **action `save`** (khối `case 'save':`), sau khi tra `$existing` (mã đã tồn tại) và TRƯỚC khi `upsert_student`, chặn nếu người dùng không phủ được lớp hiện tại của em:

```php
$existing = db_one('SELECT id FROM students WHERE code = ?', [$s['code']]);
if ($existing) {
    $curClass = current_enrollment_class((int) $existing['id'], $yid);
    // Chỉ chặn khi em ĐÃ có lớp; em chưa ghi danh (curClass = null) thì
    // chỉ cần quyền trên lớp đích (đã kiểm ở trên).
    if ($curClass !== null
        && !can_access_class($me, 'students', $curClass, 'edit')) {
        json_fail('Em này thuộc lớp bạn không phụ trách. '
                . 'Bạn không thể sửa hồ sơ hoặc chuyển em sang lớp khác.', 403);
    }
}
```

   > Lưu ý: giữ nguyên kiểm tra lớp **đích** hiện có (`students.php:126-130`). Kết quả: để sửa/chuyển một em, người dùng phải phủ **cả** lớp nguồn **và** lớp đích.

3. Trong **action `import`**, áp cùng luật cho các dòng khớp mã đã tồn tại. Trong vòng lặp `foreach ($rows ...)`, sau khi xác định `$sid = $existingCodes[$s['code']] ?? null` và trước khi đưa vào `$upsert_students`:

```php
if ($sid) {
    $curClass = current_enrollment_class((int) $sid, $yid);
    if ($curClass !== null
        && !can_access_class($me, 'students', $curClass, 'edit')) {
        $skipped++;
        $errors[] = 'Dòng ' . ($i + 2) . ': em "' . $s['code']
                  . '" thuộc lớp bạn không phụ trách, đã bỏ qua';
        continue;
    }
}
```

   > `import` đã nạp sẵn `$existingCodes` (mã→id) nên có thể tra `curClass` không tốn thêm truy vấn đáng kể; nếu muốn tối ưu, nạp kèm `class_id` vào map `$existingCodes` ngay ở truy vấn `SELECT s.code, s.id ... JOIN enrollments`.

### Ràng buộc / edge case
- `admin`/`bdh` (`allowed_class_ids` = `null`) → `can_access_class` trả `true` cho mọi lớp: không đổi hành vi.
- Em chưa ghi danh niên khoá hiện tại → chỉ kiểm lớp đích (tạo mới ghi danh hợp lệ).
- Không đổi luồng tạo em mới (`isNew`) — mã do máy chủ cấp, không có em cũ.

### Tiêu chí nghiệm thu
- GLV Chủ nhiệm lớp A gửi `save` với mã em thuộc lớp B → **403**, hồ sơ + enrollment của em không đổi.
- GLV Chủ nhiệm lớp A sửa em thuộc chính lớp A (đổi SĐT) → **thành công**.
- `import` file chứa em lớp B → dòng đó bị `skipped` kèm thông báo, các dòng hợp lệ vẫn nhập.
- admin sửa/chuyển em bất kỳ → thành công (không hồi quy).

---

## F2 + F4 — Hoàn thiện/khoá quản lý nhân sự (saveMember)

Hai lựa chọn; **chọn một** và ghi rõ trong PR. Khuyến nghị **Phương án A** (khôi phục tính năng có kiểm soát) vì UI đang cần `saveMember`.

### Phương án A — Khôi phục `saveMember` an toàn (khuyến nghị)

**File:** `public/api/StaffService.php`

1. **Whitelist vai trò (chống leo thang — F2):** ngay sau khi resolve `$role` và xử lý khoá vai của member được bảo vệ:

```php
$ASSIGNABLE = ['truong_khoi', 'glv_chu_nhiem', 'glv', 'du_bi'];

// Chỉ Quản trị mới được tạo/gán vai admin hoặc BĐH.
if (in_array($role, ['admin', 'bdh'], true)
    && ($this->me['role_code'] ?? '') !== 'admin') {
    return ['ok' => false,
            'error' => 'Chỉ Quản Trị Hệ Thống mới được gán vai Quản trị hoặc Ban Điều Hành.',
            'code' => 403];
}
// Vai không nằm trong danh sách hợp lệ (và không phải admin đang gán cấp cao) → chặn.
if (!in_array($role, $ASSIGNABLE, true)
    && !(($this->me['role_code'] ?? '') === 'admin'
         && in_array($role, ['admin', 'bdh'], true))) {
    return ['ok' => false, 'error' => 'Vai trò không hợp lệ.', 'code' => 400];
}
```

   > Giữ nguyên khối `if ($old && isProtected($old)) $role = $old['role_code'];` sẵn có (không cho hạ vai admin/bdh hiện hữu).

2. **Sửa tên cột `password` → `password_hash` (F4):**
   - Dòng tạo mới `INSERT INTO members (... password ...)` → `password_hash`.
   - `resetPassword()`: `UPDATE members SET password=?` → `SET password_hash=?`.

3. **Sửa hàm cấu hình không tồn tại (F4):** `config('default_password')` → `app_config('default_password')` (cả `saveMember` và `resetPassword`). Kiểm tra khoá này có trong `config/config.php`; nếu không, dùng hằng mặc định `'tntt@2026'` như hiện tại.

4. **Sửa giá trị ENUM status (F4):** `approveMember()` set `status='hoạt động'` → `status='đang phục vụ'` (giá trị hợp lệ trong ENUM `('chờ duyệt','đang phục vụ','tạm nghỉ','đã nghỉ')`).

**File:** `public/api/org.php`

5. **Nối `case 'saveMember'` vào switch** (đang thiếu → UI trả 404):

```php
case 'saveMember':
    $result = $staff->saveMember();
    if (!$result['ok']) {
        json_fail($result['error'], $result['code'] ?? 400);
    }
    json_out(['ok' => true]);
```

   > `saveMember` đã nằm trong `$STAFF_ACTIONS` nên đã yêu cầu `staff=edit` (chỉ admin/bdh). Whitelist ở bước 1 chặn tiếp việc bdh nâng người khác lên admin/bdh.

6. **Dọn code chết:** `StaffService::approveMember/rejectMember` trùng với bản inline trong `org.php` (cases 96-142). Giữ **một** đường. Khuyến nghị giữ bản inline của `org.php` (đang dùng, đúng cột), xoá 2 method trùng khỏi `StaffService` để tránh nhầm; nếu giữ, phải sửa cột/ENUM như trên.

### Phương án B — Khoá cứng (nếu chưa muốn mở lại tính năng)
- Giữ `org.php` không có `case 'saveMember'` (tiếp tục trả 404).
- **Xoá** `StaffService::saveMember` và `resetPassword` (code chết, sai cột/hàm) để loại bỏ lỗ hổng ngủ F2.
- Ghi chú TODO rõ ràng nếu định làm lại sau.

### Tiêu chí nghiệm thu (Phương án A)
- bdh gọi `saveMember` với `role='admin'` hoặc `role='bdh'` → **403**.
- admin gọi `saveMember` với `role='bdh'` → thành công.
- bdh tạo/sửa nhân sự vai `glv`/`glv_chu_nhiem`/`truong_khoi`/`du_bi` → thành công, ghi đúng `password_hash`.
- `resetPassword` đặt lại được mật khẩu (cột `password_hash`), buộc đổi lần sau (`must_change_pw=1`).
- Sửa nhân sự từ UI (`views/module_staff.php` → `org.js:saveMember`) không còn 404.

---

## F3 — Rào & tài liệu hoá phạm vi cho `permission_of()`

### Thay đổi

**File:** `public/api/_bootstrap.php`
- Bổ sung docblock cho `permission_of()` nêu rõ: *"Hàm này chỉ trả mức-quyền theo module, KHÔNG xét phạm vi lớp/khối. Mọi thao tác theo-đối-tượng (một em/một lớp cụ thể) BẮT BUỘC gọi thêm `can_access_class()` / `can_manage_class()` / `can_manage_block()`."*

**File:** `docs/` (tuỳ chọn) — thêm `docs/PHAN_QUYEN.md` mô tả 2 tầng kiểm tra (module-gate vs scope-check) để người mới không lặp lỗi F1.

**Kiểm tra rà (grep audit):** liệt kê mọi endpoint gọi `require_permission(..., 'edit')` rồi ghi theo `id`/`code` từ input mà **không** có `can_access_class`/`can_manage_*` kèm theo. Danh sách endpoint ghi theo-đối-tượng cần soát: `students.php`, `attendance.php`, `scores.php`, `leave.php`, `reports.php`, `org.php`, `promotion.php`, `notes.php`, `announcements.php`. (F1 là ca đã biết; xác nhận các file còn lại đều có kiểm scope — báo cáo cho thấy attendance/scores/leave/reports/export đã có.)

### Tiêu chí nghiệm thu
- Docblock có mặt; không thay đổi hành vi runtime.
- Kết quả audit ghi lại trong PR: mỗi endpoint ghi theo-đối-tượng đều có ít nhất một lần kiểm scope, hoặc được ghi nhận là an toàn (chỉ admin/bdh).

---

## F5 — Chốt & đồng bộ quyền `org` của Trưởng Khối

### Quyết định cần chốt (chủ dự án xác nhận)
Trưởng Khối trên module `org` nên là **`edit`** (được quản lý khối-lớp trong khối mình — khớp DB hiện tại) hay **`view`** (khớp `migrate_permissions.php`)?

> Khuyến nghị: **`edit`** — vì các thao tác org của Trưởng Khối đã bị `can_manage_block/class` giới hạn trong khối mình; đây cũng đúng mô tả vai "Quản lý các lớp trong khối mình".

### Thay đổi (theo phương án `edit`)
- **File:** `config/migrate_permissions.php` — bỏ/điều chỉnh Bước 1 (đang set `org.truong_khoi='view'`) để không đảo ngược trạng thái production; hoặc đổi thành `edit`.
- Đồng bộ seed (`config/schema.sql` nếu có dòng permissions seed) cho khớp.
- Ghi rõ trong PR rằng `fix_permission.php` (đặt `org.bdh='edit'`) đã được thay bằng migration chuẩn (xem F8).

### Tiêu chí nghiệm thu
- Chạy lại migration không làm đổi quyền `org.truong_khoi` so với chủ đích đã chốt.
- `migrate_permissions.php` idempotent, không mâu thuẫn với DB thật.

---

## F6 — Sửa `scope` rỗng của vai `du_bi`

### Thay đổi
- **Migration mới** (idempotent), ví dụ `config/migrate_fix_dubi_scope.php`:

```php
<?php
require __DIR__ . '/db.php';
db_run("UPDATE roles SET scope = 'lớp' WHERE code = 'du_bi' AND (scope = '' OR scope IS NULL)");
echo "Đã đặt scope='lớp' cho vai Dự Bị.\n";
```

- Cập nhật seed roles trong `config/schema.sql` để bản cài mới không lặp lỗi.

### Tiêu chí nghiệm thu
- `du_bi` có phân công lớp → `can_access_class($me, 'attendance', <lớp đó>, 'edit')` trả `true`; điểm danh được lớp được phân công.
- `du_bi` không phân công → vẫn không truy cập lớp nào (fail-closed).

---

## F7 — Đồng bộ module `thu_vien` (schema ↔ DB thật)

### Thay đổi
- **Migration** thêm quyền `thu_vien` vào DB thật nếu thiếu (idempotent, dùng `INSERT IGNORE`):

```php
$rows = [
  ['admin','edit'], ['bdh','edit'], ['truong_khoi','view'],
  ['glv_chu_nhiem','view'], ['glv','view'], ['du_bi','view'],
];
// đảm bảo module tồn tại trước (INSERT IGNORE INTO modules ...), rồi:
foreach ($rows as [$role,$lv]) {
    db_run("INSERT IGNORE INTO permissions (module_key, role_code, level)
            VALUES ('thu_vien', ?, ?)", [$role, $lv]);
}
```

- Xác nhận key module dùng trong code là `thu_vien` (khớp `data.php:335` và `schema.sql`).

### Tiêu chí nghiệm thu
- `permission_of('thu_vien')` trả đúng mức theo vai; `libraryPending` (`data.php:335`) hoạt động đúng với admin/bdh.

---

## F8 — Loại bỏ `fix_permission.php` khỏi web-root

### Thay đổi
- **Xoá** `/fix_permission.php` khỏi repo. Nội dung của nó (`org.bdh='edit'`) đã/được đưa vào migration chuẩn (F5).
- Kiểm tra `.cpanel.yml` / quy trình deploy không copy file ad-hoc ở root vào thư mục public.
- (Tuỳ chọn) Rà thêm các script ghi-DB khác ở root; nếu cần giữ, đặt trong `config/` và bọc `guard_setup()` như `install.php`/`seed_demo.php`.

### Tiêu chí nghiệm thu
- Không còn file cập nhật quyền chạy được qua URL mà không có guard.
- Grep repo: không còn script ghi `permissions`/`members` nằm ngoài `config/` mà thiếu `guard_setup()`.

---

## Thứ tự triển khai đề xuất

1. **F1** (rủi ro cao nhất, ảnh hưởng PII trẻ em) — độc lập, vá ngay.
2. **F2 + F4** (làm chung: khôi phục saveMember có whitelist + sửa cột/hàm/ENUM).
3. **F8** (xoá file lộ — nhanh, nên kèm F5).
4. **F5** (chốt chủ đích quyền org Trưởng Khối + đồng bộ migration).
5. **F6, F7** (migration dữ liệu nhỏ, ít rủi ro).
6. **F3** (docblock + audit — chốt sổ, phòng tái diễn).

## Kiểm thử tổng thể
- Bổ sung test PHPUnit (dự án có `phpunit.xml`, `tests/`) cho:
  - `can_access_class` với các tổ hợp scope (toàn đoàn / khối / lớp) và mức quyền.
  - Ca F1: sửa em ngoài phạm vi bị chặn.
  - Ca F2: bdh không gán được vai admin/bdh.
- Chạy toàn bộ test hiện có trước khi push; không được đỏ.
- Với mỗi migration: chạy hai lần liên tiếp để xác nhận idempotent.

---

*Spec này mô tả thay đổi cần làm; chưa sửa mã nghiệp vụ. Xác nhận Phương án cho F2/F4 và chủ đích cho F5 để tôi bắt tay triển khai.*
