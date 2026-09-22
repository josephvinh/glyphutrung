# Báo cáo rà soát phân quyền thành viên — Hệ thống TNTT

- **Ngày rà soát:** 2026-09-22
- **Phạm vi:** Cơ chế phân quyền theo vai trò/module và thực thi phạm vi (scope) ở tầng API
- **Nhánh:** `claude/member-permissions-review-a3h7bi`
- **Tệp trọng tâm:** `public/api/_common.php`, `public/api/_bootstrap.php`, `public/api/StaffService.php`, `public/api/OrgService.php`, `public/api/org.php`, `public/api/students.php`, `public/api/auth.php`

---

## 1. Tổng quan mô hình phân quyền

Hệ thống dùng mô hình **3 chiều**:

1. **Vai trò (`roles`)** — 6 vai: `admin` (5), `bdh` (4), `truong_khoi` (3), `glv_chu_nhiem` (2), `glv` (1), `du_bi` (1). Mỗi vai có `scope`: `toàn đoàn` / `khối` / `lớp`.
2. **Module × Vai trò (`permissions`)** — mỗi ô có mức `none` / `view` / `edit`.
3. **Phạm vi dữ liệu (`member_assignments`)** — một thành viên có thể kiêm nhiệm nhiều vai/nhiều lớp-khối; phạm vi truy cập dữ liệu tính theo phân công đang hiệu lực (`to_date IS NULL`).

**Điểm thiết kế tốt (giữ nguyên):**
- `can_access_class()` / `accessible_class_ids()` trong `_common.php` xét **từng dòng phân công như một đơn vị** (cùng một dòng phải vừa đủ mức-quyền vừa phủ được lớp) → **chống leo thang** kiểu ghép max-quyền của vai này với phạm vi của vai kia.
- CSRF token bắt buộc cho mọi POST; ép POST cho hành động ghi (`require_write`).
- Chống dò mật khẩu theo cả số điện thoại lẫn IP; `session_regenerate_id(true)` sau đăng nhập.
- `data.php` giới hạn PII thiếu nhi theo `allowed_class_ids()` — GLV chỉ thấy hồ sơ lớp mình.
- Tự đăng ký (`auth.php?action=register`) chỉ cho vai khởi tạo `glv`/`du_bi`, trạng thái `chờ duyệt`, không vào được app cho tới khi BĐH duyệt.

---

## 2. Phát hiện theo mức độ

### 🔴 NGHIÊM TRỌNG

#### F1 — IDOR: sửa hồ sơ & "bắt cóc" thiếu nhi ngoài phạm vi (`students.php`)
- **Vị trí:** `public/api/students.php:109-145` (action `save`), tương tự `:196-236` (action `import`).
- **Vấn đề:** Khi lưu hồ sơ, hệ thống chỉ kiểm tra người dùng có được ghi vào **lớp ĐÍCH** (`$classId` lấy từ `className` client gửi lên) qua `allowed_class_ids()` (`students.php:126-130`). Nhưng khi *sửa* một em đã có, nó tra em theo **mã số client cung cấp** (`students.php:134`) rồi `upsert_student()` ghi đè toàn bộ PII (địa chỉ, tên + SĐT cha mẹ, ngày sinh…) và **chuyển ghi danh** của em sang lớp đích. **Không hề kiểm tra người dùng có quyền trên lớp HIỆN TẠI của em.**
- **Khai thác:** Mã số thiếu nhi tuần tự và dễ đoán (`GDGLPT` + năm + 4 số). Một `glv_chu_nhiem` (students=`edit`, scope `lớp`) hoặc `truong_khoi` (scope `khối`) có thể gửi `save` với mã của **bất kỳ em nào toàn đoàn** kèm `className` là lớp của mình → vừa ghi đè PII em đó, vừa kéo em vào lớp mình. Đây là leo thang chiều ngang + vi phạm quyền riêng tư dữ liệu trẻ em.
- **Ai bị ảnh hưởng:** mọi vai có `students=edit` nhưng phạm vi hẹp hơn toàn đoàn (`glv_chu_nhiem`, `truong_khoi`). `admin`/`bdh` là toàn đoàn nên không phải mục tiêu.
- **Khuyến nghị:** Trước khi `upsert`, nếu em đã tồn tại, kiểm tra thêm `can_access_class($me, 'students', <class_id hiện tại của em>, 'edit')`. Chặn di chuyển ghi danh ra/vào ngoài phạm vi trừ khi người dùng phủ được **cả** lớp nguồn lẫn lớp đích.

#### F2 — Leo thang lên `admin`/`bdh` trong `StaffService::saveMember()`
- **Vị trí:** `public/api/StaffService.php:35-137`.
- **Vấn đề:** `$role = $this->in('role') ?: 'glv'` (dòng 58) **không có whitelist**. Cơ chế bảo vệ duy nhất là dòng 73-75 (`if ($old && isProtected($old)) $role = $old['role_code']`) — chỉ **khoá không cho hạ vai** một admin/bdh đã có, **không** ngăn việc *gán lên* `admin`/`bdh` cho tài khoản khác hoặc tạo mới.
- **Khai thác (tiềm ẩn):** Người có `staff=edit` (chỉ `bdh`) có thể tạo/sửa một tài khoản bù nhìn thành `role='admin'`, kết hợp cấp lại mật khẩu (mật khẩu mặc định biết trước) → chiếm quyền `admin` (cao hơn `bdh`). Đây là leo thang chiều dọc.
- **Lưu ý quan trọng:** **Hiện chưa khai thác được** vì `org.php` **thiếu `case 'saveMember'`** (xem F4) nên request rơi vào `default → 404`. Tuy nhiên đây là lỗ hổng "ngủ" — sẽ kích hoạt ngay khi ai đó nối lại case này.
- **Khuyến nghị:** Thêm whitelist `role ∈ {truong_khoi, glv_chu_nhiem, glv, du_bi}`; chỉ `admin` mới được tạo/gán vai `admin`/`bdh`.

---

### 🟠 TRUNG BÌNH

#### F3 — `permission_of()` bỏ qua phạm vi (scope-blind)
- **Vị trí:** `public/api/_bootstrap.php:120-160`.
- **Vấn đề:** `permission_of()` lấy **HỢP mức-quyền cao nhất** của mọi vai (gốc + kiêm nhiệm) **không xét scope**. Nó chỉ an toàn KHI **mọi** endpoint tự kiểm phạm vi sau `require_permission()`. F1 chính là bằng chứng pattern này dễ vỡ: chỉ cần một endpoint quên/kiểm thiếu scope là thủng.
- **Khuyến nghị:** Tài liệu hoá rõ "quyền = cổng cấp-module; scope phải kiểm riêng"; ưu tiên buộc mọi thao tác theo-đối-tượng đi qua `can_access_class()` / `can_manage_*()`. Cân nhắc thêm test bảo đảm mỗi endpoint ghi theo-đối-tượng đều gọi hàm kiểm scope.

#### F4 — Chức năng "Nhân sự" (saveMember) đang hỏng + code chết sai lệch
- **Vị trí:** `org.php:52-281` (switch thiếu `case 'saveMember'`); `StaffService.php`.
- **Vấn đề:**
  1. Giao diện gọi `org.php?action=saveMember` (`public/assets/js/modules/org.js:517`) nhưng switch **không có case này** → trả **404** "Hành động không hợp lệ". Thêm/sửa nhân sự từ UI **không hoạt động**.
  2. `StaffService::saveMember()`/`resetPassword()` dùng sai tên cột `password` — cột thật là `password_hash` (`members`); sẽ lỗi SQL.
  3. Gọi `config('default_password')` — hàm `config()` **không tồn tại** (chỉ có `app_config()`) → fatal error.
  4. `approveMember()` set `status='hoạt động'` — **không có** trong ENUM `('chờ duyệt','đang phục vụ','tạm nghỉ','đã nghỉ')`.
- **Kết luận:** Toàn bộ `StaffService::saveMember/resetPassword/approveMember/rejectMember` là code chết & sai; logic thật đang dùng bản inline trong `org.php` (bản inline dùng đúng `password_hash`).
- **Khuyến nghị:** Quyết định một đường: hoặc hoàn thiện & nối `StaffService` (sửa cột/hàm/ENUM + whitelist role của F2), hoặc xoá code chết để tránh nhầm lẫn và tránh vô tình kích hoạt F2.

#### F5 — Mâu thuẫn quyền `org` cho Trưởng Khối giữa migration và DB thật
- **Vị trí:** DB thật: `org.truong_khoi = 'edit'`; `config/migrate_permissions.php:22-25` đặt `org.truong_khoi = 'view'`.
- **Vấn đề:** Hai nguồn "sự thật" xung đột. Chạy lại `migrate_permissions.php` sẽ **hạ** Trưởng Khối xuống `view`, đảo ngược hành vi hiện tại. Ngoài ra `fix_permission.php` (root) lại đặt `org.bdh='edit'`.
- **Về an toàn:** Được kiểm soát — mọi thao tác org của Trưởng Khối đều qua `can_manage_block()`/`can_manage_class()` nên vẫn giới hạn trong khối mình. Đây chủ yếu là rủi ro **vận hành/nhất quán**.
- **Khuyến nghị:** Chốt ý định (Trưởng Khối nên `edit` hay `view` trên `org`?), cập nhật cả migration lẫn seed cho khớp, gỡ file mâu thuẫn.

---

### 🟡 THẤP

#### F6 — Vai `du_bi` có `scope = ''` (rỗng, không hợp lệ với ENUM)
- **Vị trí:** dữ liệu `roles`: `('du_bi','Dự Bị',1,'',...)`; ENUM là `('toàn đoàn','khối','lớp')`.
- **Hệ quả:** `assignment_covers_class()`/`resolve_class_ids_from_scope()` gặp scope rỗng đều trả `false`/`[]`. Do đó `du_bi` tuy có `attendance=edit` nhưng **không phủ được lớp nào** → không điểm danh được. Lỗi chức năng (fail-closed nên không nguy hiểm về an toàn).
- **Khuyến ngh: ** Đặt `du_bi.scope = 'lớp'` cho khớp bản chất "hỗ trợ tại lớp".

#### F7 — Lệch (drift) giữa `schema.sql` và DB thật ở module `thu_vien`
- **Vị trí:** `config/schema.sql:550,568-573` khai báo module `thu_vien` + quyền; nhưng dump DB thật (`permissions`) **không có** dòng `thu_vien` nào.
- **Hệ quả:** `data.php:335` `permission_of('thu_vien')` luôn trả `none` → `libraryPending` luôn `false`; module Thư viện thiếu bản ghi quyền trên production.
- **Khuyến nghị:** Bổ sung quyền `thu_vien` vào DB thật (hoặc chạy migration Thư viện), đồng bộ schema ↔ production.

#### F8 — `fix_permission.php` để lộ ở thư mục gốc, không có guard
- **Vị trí:** `/fix_permission.php`.
- **Vấn đề:** Script `require config/db.php` rồi chạy `UPDATE permissions ...` mà **không** qua `guard_setup()` như các script cài đặt khác. Nếu deploy nhầm lên web-root, bất kỳ ai truy cập URL đều đổi được quyền.
- **Khuyến nghị:** Xoá khỏi repo/production, hoặc chuyển vào `config/` và bọc `guard_setup()`.

---

## 3. Bảng ma trận quyền hiện tại (DB thật)

| Module | admin | bdh | truong_khoi | glv_chu_nhiem | glv | du_bi |
|---|---|---|---|---|---|---|
| org | edit | edit | **edit** ⚠️F5 | view | view | view |
| staff | edit | edit | view | view | view | view |
| students | edit | edit | edit | edit | view | view |
| attendance | edit | edit | edit | edit | edit | edit* |
| scores | edit | view | edit | edit | edit | view |
| leave | edit | edit | edit | edit | view | view |
| announcements | edit | edit | edit | edit | view | view |
| reports | edit | view | edit | edit | view | view |
| programs | edit | edit | none | none | none | none |
| promotion | edit | edit | view | none | none | none |
| notes | edit | edit | edit | edit | edit | view |
| years | edit | edit | view | view | view | view |
| (nhóm xem) analytics/birthdays/calendar/guide/reporthub/stats | view | view | view | view | view | view |

`*` du_bi có `attendance=edit` nhưng không dùng được do F6 (scope rỗng).
`thu_vien` (Thư viện) thiếu trong DB thật — xem F7.

---

## 4. Ưu tiên xử lý

| # | Phát hiện | Mức | Ưu tiên |
|---|---|---|---|
| F1 | IDOR sửa/chuyển hồ sơ thiếu nhi | 🔴 | **Cao nhất** |
| F2 | Leo thang lên admin/bdh (tiềm ẩn) | 🔴 | Cao (sửa cùng F4) |
| F4 | saveMember hỏng + code chết sai | 🟠 | Cao |
| F3 | permission_of scope-blind (tài liệu + rào) | 🟠 | Trung bình |
| F5 | Mâu thuẫn quyền org Trưởng Khối | 🟠 | Trung bình |
| F6 | du_bi scope rỗng | 🟡 | Thấp |
| F7 | Drift thu_vien schema↔DB | 🟡 | Thấp |
| F8 | fix_permission.php lộ ở root | 🟡 | Thấp (xoá ngay) |

---

*Báo cáo chỉ rà soát và đề xuất; chưa thay đổi mã nguồn nghiệp vụ. Cho tôi biết muốn xử lý phát hiện nào để tôi triển khai bản vá.*
