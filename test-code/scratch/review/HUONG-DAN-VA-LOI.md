# HƯỚNG DẪN VÁ LỖI — DUYỆT THÀNH VIÊN / NHÂN SỰ

Áp dụng cho web đang deploy. Có **2 cách**: tải nguyên tệp, hoặc tìm–thay tại chỗ.

---

## TÓM TẮT: 3 lỗi, 2 tệp

Tất cả nằm trong module **Nhân sự**. Bản deploy hiện tại khiến chức năng
"duyệt thành viên mới đăng ký" **hỏng hoàn toàn** — không ai duyệt được.

| # | Tệp | Lỗi | Hậu quả |
|---|---|---|---|
| 1 | `public/api/org.php` | Cổng phân quyền đặt cứng `org` cho mọi action | BĐH bị chặn 403; Trưởng khối vượt quyền |
| 2 | `public/api/org.php` | `SELECT` thiếu cột `status` | **Không ai duyệt được**, kể cả admin |
| 3 | `views/module_staff.php` | Khối hàng chờ thiếu điều kiện quyền | Trưởng khối thấy nút Duyệt/Từ chối |

---

## LỖI 2 — nghiêm trọng nhất, dễ bỏ sót

Trong `approveMember`, `rejectMember`, `resetPassword`:

```php
$m = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
...
if ($m['status'] !== 'chờ duyệt') json_fail('Tài khoản này đã được duyệt rồi.');
```

`SELECT` **không lấy cột `status`** → `$m['status']` là `null`
→ `'chờ duyệt' !== null` luôn đúng → **luôn báo "đã được duyệt rồi"**.

Đã kiểm chứng thật trên bản mô phỏng deploy: admin gọi duyệt hồ sơ
**đang** chờ duyệt → `HTTP 400 {"ok":false,"error":"Tài khoản này đã được duyệt rồi."}`

---

# CÁCH 1 — Tải nguyên tệp (khuyến nghị)

Ghi đè 2 tệp này lên máy chủ:

```
public/api/org.php
views/module_staff.php
```

Bản đầy đủ nằm trong `scratch/review/deploy-patch/`.

> ⚠️ **Lưu ý:** nếu máy chủ đang có chỉnh sửa riêng chưa có ở đây,
> hãy dùng CÁCH 2 để không mất phần đó.

---

# CÁCH 2 — Tìm–thay tại chỗ (chính xác, an toàn)

## 2.1. `public/api/org.php` — sửa cổng phân quyền

**TÌM** (khoảng dòng 19–27):

```php
require __DIR__ . '/_bootstrap.php';

$me   = require_permission('org', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();
```

**THAY BẰNG:**

```php
require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';

// Duyệt tài khoản + quản lý nhân sự thuộc module Nhân sự (staff).
// Còn lại (khối, lớp, chủ nhiệm/trưởng khối) thuộc module Tổ chức (org).
$STAFF_ACTIONS = ['saveMember', 'deleteMember', 'approveMember', 'rejectMember', 'resetPassword'];
$me   = in_array($action, $STAFF_ACTIONS, true)
    ? require_permission('staff', 'edit')
    : require_permission('org', 'edit');

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$yid = (int) $year['id'];
$in     = json_input();
```

## 2.2. `public/api/org.php` — bổ sung cột `status`

Có **3 chỗ**, mỗi chỗ là 1 dòng. Tìm chính xác chuỗi
`SELECT id, role_code, full_name FROM members WHERE id=?` và thay theo
**action** tương ứng (xem dòng ngay trên mỗi chỗ):

**a) Trong `case 'approveMember':`**
```php
// TÌM:
$m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
// THAY:
$m  = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [$id]);
```

**b) Trong `case 'rejectMember':`**
```php
// TÌM:
$m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
// THAY:
$m  = db_one('SELECT id, role_code, full_name, phone, status FROM members WHERE id=?', [$id]);
```

**c) Trong `case 'resetPassword':`**
```php
// TÌM:
$m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
// THAY:
$m  = db_one('SELECT id, role_code, full_name, phone FROM members WHERE id=?', [$id]);
```

> Chuỗi TÌM giống hệt nhau ở cả 3 chỗ, nên **đừng dùng Replace All** —
> phải sửa từng chỗ theo đúng `case` chứa nó.

## 2.3. `views/module_staff.php` — ẩn hàng chờ với người chỉ-xem

**TÌM** (khoảng dòng 36):

```html
    <div x-show="pendingMembers.length > 0" style="display: none;"
         class="bg-amber-50 border border-amber-200 rounded-card p-5 mb-4">
```

**THAY BẰNG:**

```html
    <div x-show="canManageOrg && pendingMembers.length > 0" style="display: none;"
         class="bg-amber-50 border border-amber-200 rounded-card p-5 mb-4">
```

---

# SAU KHI VÁ

1. **Không cần build lại JS/CSS** — cả 2 tệp đều là PHP render phía máy chủ.
2. Xoá cache máy chủ nếu có: thư mục `public/cache/*.json`.
3. Kiểm tra nhanh:
   - Đăng nhập **BĐH** → Nhân sự → thấy hàng chờ duyệt, bấm **Duyệt** → phải thành công.
   - Đăng nhập **Trưởng khối** → Nhân sự → **không** thấy nút Duyệt/Từ chối.

---

# BẢNG PHÂN QUYỀN SAU KHI VÁ

| Vai trò | `org` | `staff` | Thêm/sửa nhân sự | Duyệt tài khoản |
|---|---|---|---|---|
| admin | edit | edit | ✅ | ✅ |
| bdh | view | edit | ✅ | ✅ |
| truong_khoi | edit | view | ❌ | ❌ |
| glv_chu_nhiem | view | view | ❌ | ❌ |
| glv | view | view | ❌ | ❌ |
| du_bi | view | view | ❌ | ❌ |

> ⚠️ **Thay đổi hành vi cần biết:** trước đây Trưởng khối **thêm/sửa được nhân sự**
> (nhờ `org=edit`). Sau khi vá, họ **mất quyền đó** — đúng theo bảng phân quyền
> nhưng khác thực tế đang chạy. Nếu muốn giữ, hãy cấp `staff=edit` cho
> `truong_khoi` trong bảng `permissions`.

---

# TỆP KÈM THEO

| Tệp | Nội dung |
|---|---|
| `va-phan-quyen-duyet-thanh-vien.patch` | Patch chuẩn `git diff`, áp bằng `git apply` |
| `deploy-patch/org.php` | Bản đầy đủ đã sửa |
| `deploy-patch/module_staff.php` | Bản đầy đủ đã sửa |

Áp patch bằng dòng lệnh (nếu máy chủ có git):

```bash
git apply va-phan-quyen-duyet-thanh-vien.patch
```
