# Báo cáo QA — Luồng đăng ký thành viên mới

**Ngày:** 2026-09-20 · **App:** http://localhost:8888/tntt/public/ · **DB:** `tntt_demo`

**Người thực hiện:** agent `kiem-thu-tuong-tac` (QA tương tác) + kiểm chứng độc lập ở tầng API/DB.

---

## 1. Kết luận

| Hạng mục | Kết quả |
|---|---|
| Tạo tài khoản mới (form đăng ký) | ✅ **Chạy tốt** — đã kiểm chứng độc lập ở tầng API |
| Kiểm tra dữ liệu đầu vào (SĐT, ngày sinh, mật khẩu, trùng SĐT) | ✅ **Đúng** — đều trả HTTP 400 kèm thông báo tiếng Việt |
| Duyệt / từ chối tài khoản vừa đăng ký | ❌ **HỎNG (đã có bản sửa trong working tree, chưa commit)** |
| Lỗi UX | ⚠️ Form không tự xoá sau khi đăng ký thành công |

**Lỗi bạn nhìn thấy gần như chắc chắn nằm ở bước DUYỆT**, không phải ở bước đăng ký.

---

## 2. Bước đăng ký: đã kiểm chứng độc lập

Gọi thẳng API (không qua trình duyệt):

```
POST api/auth.php?action=register   (danhXung=du_bi)
  -> HTTP 200  {"ok":true,"code":"GLV002"}

POST lần 2 cùng SĐT
  -> HTTP 400  "Số điện thoại này đã được đăng ký. Nếu quên mật khẩu, liên hệ Ban Điều Hành để cấp lại."

GET  /models  (key4u) -> 419 model khả dụng
```

Agent QA tương tác kiểm tra trên trình duyệt thật: happy path PASS; 5/5 ca lỗi PASS
(thiếu ngày sinh, ngày sinh 2020, SĐT "abc", mật khẩu < 6 ký tự, trùng SĐT);
không có lỗi JavaScript trong Console; request trả 200/400 đúng, không có 500.

---

## 3. Lỗi thật: không thể duyệt tài khoản tự đăng ký

### Triệu chứng
Ban Điều Hành mở hàng chờ duyệt và bấm duyệt một tài khoản **đang chờ duyệt**,
hệ thống trả về:

> **"Tài khoản này đã được duyệt rồi."**

Dù tài khoản đó chưa hề được duyệt. Tương tự, nút **Từ chối** báo
"Chỉ từ chối được tài khoản đang chờ duyệt."

### Nguyên nhân

`public/api/org.php` — câu `SELECT` **thiếu cột** mà code phía sau lại đọc:

| Case | SELECT (bản cũ) | Đọc | Hậu quả |
|---|---|---|---|
| `approveMember` (L266) | `id, role_code, full_name` | `$m['status']` (L268) | luôn báo "đã được duyệt rồi" |
| `rejectMember` (L314) | `id, role_code, full_name` | `$m['status']`, `$m['phone']` | luôn báo "chỉ từ chối được..." |
| `resetPassword` (L327) | `id, role_code, full_name` | `$m['phone']` (L343) | trả về SĐT rỗng |

Vì `$m['status']` là `null`, phép so sánh

```php
if ($m['status'] !== 'chờ duyệt') json_fail('Tài khoản này đã được duyệt rồi.');
```

thành `null !== 'chờ duyệt'` → **luôn đúng** → chặn mọi lần duyệt.

### Bằng chứng

Log Apache ghi nhận đúng 3 cảnh báo này:

```
[Sun Sep 20 05:56:40 2026] PHP Warning: Undefined array key "status" in .../org.php on line 268
[Sun Sep 20 05:58:02 2026] PHP Warning: Undefined array key "status" in .../org.php on line 316
[Sun Sep 20 05:58:09 2026] PHP Warning: Undefined array key "phone"  in .../org.php on line 343
```

Và mô phỏng lại hai truy vấn (`scratch/prove-org-bug.php`):

```
OLD query (as committed):
  columns returned : id, role_code, full_name
  has 'status' key : false
  guard $m['status'] !== 'chờ duyệt'  => true   -> CHẶN tài khoản đang chờ duyệt

NEW query (working tree fix):
  columns returned : id, role_code, full_name, status
  status value     : 'chờ duyệt'
  guard => false  -> QUA, duyệt được (ĐÃ SỬA)
```

---

## 4. Trạng thái bản sửa

`git status` cho thấy `public/api/org.php` **đã được sửa nhưng chưa commit**:

```diff
-        $m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [$id]);
+        $m  = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [$id]);
```

(3 chỗ, ở cả `approveMember`, `rejectMember`, `resetPassword`.)

Bản sửa này **đúng và đã có hiệu lực** trên file hiện tại — tôi đã kiểm chứng
bằng cách chạy lại truy vấn mới và xác nhận `status = 'chờ duyệt'`.

> Lưu ý: một phiên DSH khác trong workspace này đang audit đúng bug class
> "SELECT thiếu cột nhưng vẫn đọc" trên `public/api/org.php`.
> Mtime của `org.php` là 05:52:31, trong khi cảnh báo xuất hiện lúc 05:56–05:58 —
> tôi chưa giải thích trọn vẹn được thứ tự thời gian này (OPcache đã kiểm tra:
> bị comment hoàn toàn, nên không phải cache bytecode).

**Việc cần làm:** commit bản sửa để không bị mất.

---

## 5. Quét toàn bộ `public/api/*.php` tìm bug cùng loại

Đã quét heuristic toàn bộ 28 file API: **không còn chỗ nào** đọc cột không được
`SELECT`. Hai kết quả ban đầu đều là dương tính giả (cửa sổ quét lẫn sang `case`
kế tiếp dùng `SELECT *`):

- `assignments.php:169` — `$row['to_date']` thực ra thuộc `case 'delete'` dùng `SELECT *`.
- `org.php:252` — `$m['status']` thực ra thuộc `approveMember` (đã sửa).

---

## 6. Lỗi UX (mức nhỏ)

Sau khi đăng ký thành công, bấm "Về màn đăng nhập" rồi vào lại form đăng ký thì
**dữ liệu cũ vẫn còn nguyên**, kể cả mật khẩu.

Nguyên nhân: `public/assets/js/login.js`, hàm `submitRegister()` không reset state
(`rName`, `rPhone`, `rPw`, `rPw2`…) trước khi chuyển `this.step = 'done'`.

---

## 7. Dữ liệu test đã dọn

Đã xoá toàn bộ bản ghi test do quá trình kiểm thử tạo ra
(`0999000123`, `0999000777`, `0999000888`).

```
members remaining: 6
pending rows left: 0
```

Trạng thái cuối: browser ở màn đăng nhập sạch, không còn dữ liệu test.
