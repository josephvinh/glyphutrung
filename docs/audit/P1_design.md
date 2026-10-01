# P1 — Đặc tả sửa lỗi phân quyền / rò rỉ dữ liệu (#78, #97, #83)

Người thiết kế: agent kiến trúc/soát an ninh (chỉ đọc mã, không sửa repo).
Cơ sở mã: nhánh `audit` (mã ứng dụng = `master`), đọc ngày 30/09/2026.
Người cài đặt: làm đúng đặc tả; chỗ nào đặc tả chưa phủ thì hỏi, không tự mở rộng phạm vi.
Người duyệt: đối chiếu từng mục "Quy tắc" và "Kiểm thử nghiệm thu" bên dưới.

---

## 0. Tóm tắt quyết định

| Issue | Quyết định chính |
|---|---|
| #78 | `scores`, `leaveRequests`, `reports` lọc theo **giao** của phạm vi hồ sơ (`allowed_class_ids`) với phạm vi theo phân công có quyền ≥ view trên module tương ứng (`accessible_class_ids`). Lọc bằng JOIN `enrollments` theo `class_id`, không liệt kê id em. `members`: gửi đủ nếu `permission_of('staff') !== 'none'`, ngược lại gửi `[]`. Người chỉ có `staff=view` không nhận hồ sơ **chờ duyệt** và `registerNote`. `logs`: chỉ Quản trị (vì "quyền settings" trong mã hiện nay là `role_code === 'admin'`), còn lại gửi `[]`. **Mọi khoá vẫn có mặt**, luôn là mảng. Khoá cache đổi sang tiền tố mới `data_v2_`. Admin/BĐH (phạm vi `null`) không đổi hành vi. |
| #97 | Người không phải admin **không** sửa, xoá, cấp lại mật khẩu, duyệt hay từ chối tài khoản admin (trả **404** giống như không tồn tại, nhất quán với F9). BĐH **không** sửa hay xoá BĐH khác (403). BĐH **được** sửa danh tính của **chính mình** (không được đổi chức danh). Các thao tác trên người dưới BĐH giữ nguyên. Gom quy tắc về một hàm `StaffService::guardTarget()`. |
| #83 | `require_login()` trả **403** với body `{ok:false, error, code:"must_change_pw"}` khi `must_change_pw=1`. Chỉ `auth.php?action=password` được mở, qua hàm mới `require_login_pending_pw()`. `me` và `logout` vốn không gọi `require_login`. Đăng nhập Passkey **từ chối** tài khoản đang buộc đổi mật khẩu (không tạo phiên). **Sửa kèm một lỗi ẩn:** màn đổi mật khẩu bắt buộc hiện **không gửi CSRF** nên `auth.php?action=password` luôn 403, xem 3.2. Nếu không sửa lỗi này thì bật cổng #83 sẽ khoá hẳn mọi tài khoản còn cờ. |

---

## 1. Issue #78 — `data.php` trả dữ liệu ngoài phạm vi

### 1.1 Hiện trạng (đã đọc mã)

- `public/api/data.php:288-306` (`$leaves`): `WHERE l.year_id = ?`, không lọc phạm vi, kèm `reason`.
- `data.php:311-323` (`$scores`): `WHERE t.year_id = ?`, không lọc.
- `data.php:333-357` (`$reports`): `WHERE t.year_id = ?`, không lọc (xác nhận **có** rò rỉ; nhận xét giáo viên `remark`, hạnh kiểm, xếp loại).
- `data.php:411-439` (`$members`): mọi người đã đăng nhập đều nhận. Chỉ ẩn admin khi người xem không phải admin (F9). Kèm `phone`, `birthDate`, `registerNote`, cả hồ sơ `chờ duyệt`.
- `data.php:445-454` (`$logs`): 50 dòng nhật ký toàn hệ thống cho mọi người.
- `students`/`attendances`/`stampSummaries` đã lọc qua `allowed_class_ids($me)` và dùng đúng.
- Không có module `settings` trong bảng `permissions`. `settings.php:14` chặn bằng `role_code !== 'admin'`, giao diện ẩn thẻ Nhật ký bằng `isAdmin` (`views/module_settings.php:25`). ⇒ "quyền settings" = **vai gốc admin**.
- `public/api/logs.php:15-16` **hỏng**: đọc `$me['role']` (không tồn tại, cột là `role_code`) và so với `'ban_dieu_hanh'` (mã thật là `bdh`), nên luôn 403 kể cả với admin. Không nơi nào trong client gọi `logs.php`.

### 1.2 Phụ thuộc của client (không được làm vỡ)

`public/assets/js/modules/core.js:243-277` gán thẳng `this.leaveRequests = d.leaveRequests`, `this.reports = d.reports`, `this.members = d.members`, `this.logs = d.logs`, rồi gọi `.filter/.find/.unshift` ở khắp nơi (`org.js`, `birthdays.js`, `leave.js`, `analytics.js`, `stats.js`, `reports.js`, `core.js:567,607`). `this.rebuildReportIndex()` lặp `this.reports`.
⇒ **QUY TẮC BẮT BUỘC:** không bỏ khoá nào. Trường hợp không được xem thì trả `[]`. Nếu bỏ khoá, client gặp `TypeError: Cannot read properties of undefined` và trắng màn hình.

- `leave.js:139-143` đã tự lọc đơn theo `accessibleStudents`, nên lọc ở máy chủ không đổi màn Xin phép.
- `analytics.js:38-44,102,176` đếm **mọi** đơn đã duyệt không lọc. Sau khi sửa, số của GLV chỉ còn trong phạm vi. Đây là thay đổi đúng, nhưng phải kiểm thủ công.
- `core.js:566-577` ("Hoạt động của tôi" ở thẻ Cá nhân) lọc `this.logs` theo tên mình. Khi `logs=[]`, thẻ này sẽ ghi "Chưa có thao tác nào được ghi" sau mỗi lần tải lại. Đây là hệ quả trực tiếp của quyết định Q1 (xem câu hỏi Q-A).
- `dashboard.js:60 recentLogs` không được view nào dùng (mã chết), không ảnh hưởng.
- `module_staff.php:16-18` hiện huy hiệu "n chờ duyệt" cho mọi người xem màn Nhân sự. Khi ẩn hồ sơ chờ duyệt với `staff=view`, huy hiệu biến mất với GLV. Đây là thay đổi mong muốn.

### 1.3 Quy tắc chính xác

Ký hiệu:
- `P(me) = allowed_class_ids($me)`: phạm vi hồ sơ, đã dùng cho `students`.
- `A(me, mod) = accessible_class_ids($me, mod, 'view')`: hợp phạm vi các phân công có vai ≥ view trên `mod`.

Hàm mới (đặt **trong `data.php`**, tiền tố `data_`, để không đụng `_bootstrap.php`/`_common.php`):

```php
/** Lớp được XEM dữ liệu module $mod: giao P(me) ∩ A(me,mod). null = toàn đoàn, [] = không gì. */
function data_scope_for(array $me, string $mod): ?array
{
    $base = allowed_class_ids($me);
    if ($base === []) return [];
    $m = accessible_class_ids($me, $mod, 'view');
    if ($m === null) return $base;           // base có thể null (toàn đoàn)
    if ($base === null) return $m;
    return array_values(array_intersect($base, $m));
}
```

Lý do lấy giao: (1) không bao giờ gửi dữ liệu của em mà người xem không nhận hồ sơ, vì client ghép theo `studentId` nên dữ liệu thừa chỉ là rò rỉ; (2) tôn trọng ma trận quyền chỉnh được trong app (`settings.php?action=permission`) **theo từng phân công**, đúng nguyên tắc "per-assignment" ở `_common.php:210-218`. Không dùng `permission_of()` vì hàm này gộp quyền mọi vai rồi bỏ qua phạm vi.

Phạm vi ĐỌC, không phải GHI: dùng `'view'`. Vai `view` (vd BĐH với `scores`, GLV với `leave`) vẫn nhận dữ liệu để xem. Quyền ghi vẫn do endpoint ghi kiểm (`scores.php`, `leave.php`, `reports.php`), không đổi trong P1.

Bảng vai × khoá theo ma trận mặc định (`config/install.php:219-247`). "Lớp mình" = hợp các lớp trong phân công hiệu lực.

| Vai (ví dụ kiểm thử) | students / attendances / stampSummaries | scores | leaveRequests | reports | members | logs |
|---|---|---|---|---|---|---|
| admin (không phân công) | toàn đoàn (giữ nguyên) | toàn đoàn | toàn đoàn | toàn đoàn | tất cả, **có** admin | 50 dòng mới nhất (giữ nguyên) |
| bdh | toàn đoàn | toàn đoàn | toàn đoàn | toàn đoàn | tất cả trừ admin (gồm chờ duyệt, `registerNote`) | `[]` |
| truong_khoi (khối X) | các lớp khối X | các lớp khối X | các lớp khối X | các lớp khối X | trừ admin, **trừ chờ duyệt**, `registerNote=''` | `[]` |
| glv_chu_nhiem (lớp A) | lớp A | lớp A | lớp A | lớp A | như trên | `[]` |
| glv (lớp A) | lớp A | lớp A | lớp A | lớp A | như trên | `[]` |
| glv kiêm 2 lớp (A và C, khác khối) | A ∪ C | A ∪ C | A ∪ C | A ∪ C | như trên | `[]` |
| du_bi (lớp A) | lớp A | lớp A (`scores=view`) | lớp A | lớp A | như trên | `[]` |
| thu_thu thuần (toàn đoàn) | `[]` (đã đúng) | `[]` | `[]` | `[]` | **`[]`** (`staff=none`) | `[]` |
| glv lớp A kiêm thu_thu | lớp A | lớp A | lớp A | lớp A | như GLV (quyền staff đến từ vai glv) | `[]` |
| Bất kỳ vai nào, nếu admin chỉnh `scores`=none cho vai đó | không đổi | phân công của vai đó không góp lớp; không vai nào góp thì `[]` | tương tự với `leave` | tương tự với `reports` | tương tự với `staff` | — |

Ghi chú hành vi đã có (không đổi trong P1, chỉ nêu để người duyệt biết): `member_scopes()` (`_common.php:253`) chỉ trả các phân công khi có ít nhất một phân công, **không cộng vai gốc**. Vì vậy admin hoặc BĐH tự thêm mình vào một lớp sẽ thấy `students` chỉ còn lớp đó. Sau P1, `scores/leave/reports` sẽ khớp đúng với `students` (trước đây vẫn nhận toàn đoàn). Việc này thuộc #94 (P6), không sửa ở P1.

### 1.4 Thay đổi từng file / hàm

**`public/api/data.php`** (file duy nhất có logic #78 lớn):

1. Thêm hằng `const DATA_CACHE_VER = 'v2';`. Đổi dòng 75 thành `$cacheKey = "data_" . DATA_CACHE_VER . "_{$yid}_{$me['id']}_{$part}";`. Đây là cách vô hiệu hoá cache cũ đang chứa dữ liệu rộng: khoá cũ không bao giờ được đọc lại và tự hết hạn sau 60 giây (TTL hiện tại). Không cần migration. Có thể gọi thêm `Cache::flush()` thủ công khi triển khai, nhưng không bắt buộc.
2. Thêm hàm `data_scope_for()` như 1.3, cùng một hàm dựng mệnh đề lọc dùng chung:
   ```php
   /** Trả [sqlJoinWhere, params] lọc theo lớp qua enrollments năm $yid; null = không lọc. */
   function data_class_filter(?array $ids, string $studentCol, int $yid): ?array
   ```
   Dạng SQL chuẩn cho từng khối (chạy từ `enrollments` để dùng `idx_enr_class (year_id,class_id)`, rồi `idx_sc_student_term` / `idx_lv_student` / `idx_rp_student`):
   - **scores**
     ```sql
     SELECT sc.*, m.full_name AS by_name
       FROM enrollments e
       JOIN scores sc ON sc.student_id = e.student_id
       JOIN terms  t  ON t.id = sc.term_id AND t.year_id = e.year_id
       LEFT JOIN members m ON m.id = sc.updated_by
      WHERE e.year_id = ? AND e.class_id IN (?,?,…)
     ```
   - **leave_requests**: `FROM enrollments e JOIN leave_requests l ON l.student_id = e.student_id AND l.year_id = e.year_id … WHERE e.year_id = ? AND e.class_id IN (…) ORDER BY l.session_date DESC, l.id DESC`
   - **reports**: `FROM enrollments e JOIN reports r ON r.student_id = e.student_id JOIN terms t ON t.id = r.term_id AND t.year_id = e.year_id …`
   - `uq_enr (year_id, student_id)` bảo đảm mỗi em đúng một dòng ghi danh trong năm, nên JOIN không nhân bản dòng.
   - Danh sách `IN` chỉ gồm **id lớp** (vài chục phần tử, bind bằng `?`), không phải id em.
   - Khi `data_scope_for()` trả `null`: **giữ nguyên câu SQL cũ** (bảo đảm yêu cầu (d): admin/BĐH không đổi). Khi trả `[]`: không truy vấn, trả `[]`.
   - Giữ nguyên hình dạng từng phần tử (các khoá `id, studentId, …`) và thứ tự sắp xếp.
3. `$scores`: giữ điều kiện `$part === 'core' ? []`, chỉ thay nguồn dữ liệu bằng truy vấn có lọc theo `data_scope_for($me,'scores')`.
4. `$leaves`: lọc theo `data_scope_for($me,'leave')` (khoá module là `leave`, không phải `leaves`).
5. `$reports`: lọc theo `data_scope_for($me,'reports')`.
6. `$members`:
   ```php
   $staffLv = permission_of('staff');           // none|view|edit (hợp mọi vai — đúng ý Q1: có quyền staff ≥ view)
   if ($staffLv === 'none') $members = [];
   else {
       $where = [];
       if (!can_see_admin($me))  $where[] = "m.role_code <> 'admin'";
       if ($staffLv !== 'edit') $where[] = "m.status <> 'chờ duyệt'";
       … truy vấn cũ + WHERE …;
       nếu $staffLv !== 'edit' thì 'registerNote' => ''.
   }
   ```
   Không lọc `members` theo lớp: Q1 cho phép GLV xem danh bạ toàn bộ nhân sự.
7. `$logs`: `$logs = can_view_logs($me) ? (truy vấn cũ) : [];`
8. Trả thêm trong `$result` **không** có khoá mới (không đổi hợp đồng). Giữ comment giải thích.

**`public/api/_common.php`**: thêm một hàm ngay dưới `can_see_admin()`:
```php
/** Được xem nhật ký thao tác toàn hệ thống — tương đương quyền màn Cài đặt (settings.php: chỉ Quản trị). */
function can_view_logs(?array $me): bool { return ($me['role_code'] ?? '') === 'admin'; }
```
Lý do đặt ở `_common.php` thay vì `_bootstrap.php`: tránh vùng P4 sửa. Nếu chủ dự án trả lời "Có" ở câu Q-C thì chỉ sửa hàm này.

**`public/api/logs.php`**: thay khối dòng 14-19 bằng `if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);`. Hiện file này hỏng với mọi người; sau khi sửa, admin dùng được.

**Client:** không bắt buộc sửa. Nếu Q-A = Có (gửi nhật ký của chính mình), chỉ đổi server: `WHERE actor_id = ? ORDER BY id DESC LIMIT 50`, đã có `idx_log_actor`.

### 1.5 Phương án đã loại

- **`IN (id em…)` như `attendances`**: với Trưởng khối có vài trăm id thì câu SQL dài, mất tác dụng của cache kế hoạch, và lệch khi có phân trang (`$students['data']` chỉ là một trang). Không chọn. Không sửa `attendances` trong P1, để P8 xử lý.
- **Chỉ lọc ở client**: không phải kiểm soát truy cập.
- **Bỏ khoá khỏi JSON**: làm vỡ client (xem 1.2).
- **Gửi `members=[chính mình]` cho Thủ thư**: kịch bản SCOPE-02 và quyết định Q1 yêu cầu rỗng. `currentMember` đã chịu được giá trị `null` (`core.js:449`).
- **Dùng `permission_of('scores')` làm cổng**: gộp quyền mọi vai, nên có thể "lai" phạm vi của vai này với quyền của vai kia. Không chọn.
- **Tạo module `settings` trong `permissions`**: đổi schema và ma trận quyền, không cần cho P1.

### 1.6 Rủi ro hồi quy

1. Client vỡ nếu một khoá thành `null`/thiếu. Kiểm bằng KEYS-01 (mục 4).
2. Số liệu Phân tích/Thống kê của vai lớp/khối thay đổi (nhỏ hơn). Đây là hệ quả đúng, cần ghi trong PR.
3. Admin/BĐH có phân công bị thu hẹp (xem ghi chú 1.3).
4. Bản chụp IndexedDB trên máy (`core.js:213-240`) còn giữ dữ liệu rộng cho tới lần tải kế tiếp thành công, và bị xoá khi đăng xuất (`snap.clear`). Không xử lý thêm được ở máy chủ; ghi nhận trong PR.
5. Hiệu năng: thêm 3 truy vấn JOIN có chỉ mục. Đo bằng `perf` (mục 10 báo cáo): TTFB của `data.php?part=core|heavy` cho GLV không được chậm hơn trước. Với admin, câu SQL không đổi.

### 1.7 Tương tác với P8 (#90)

- Chỉ thêm hàm `data_*` và thay nguồn dữ liệu của 3 khối. Không đổi cấu trúc `core/heavy/all`, ETag, `data_out()`, và không đổi tên khoá.
- P8 có thể dời `leaveRequests`/`reports` sang `heavy` hoặc tách endpoint riêng mà vẫn dùng lại `data_scope_for()`/`data_class_filter()`. P8 nên giữ tiền tố `DATA_CACHE_VER` và tăng lên `v3` nếu đổi hình dạng dữ liệu.

---

## 2. Issue #97 — BĐH sửa được hồ sơ tài khoản Quản trị

### 2.1 Hiện trạng

- `org.php:34-36`: mọi action nhân sự đòi `staff=edit`. Theo mặc định chỉ admin và bdh có quyền này.
- `StaffService::saveMember()` (`StaffService.php:91-99`): với người được bảo vệ (admin/bdh) chỉ khoá **đổi vai**. Họ tên, SĐT (tên đăng nhập) và chức danh vẫn ghi được. ⇒ BĐH đổi SĐT của admin, admin đăng nhập bằng số cũ bị 401 (STAFF-06 FAIL).
- `deleteMember`: chặn xoá admin/bdh, nhưng thông điệp lộ vai của mục tiêu.
- `resetPassword` (`org.php:149-158`): người không phải admin không cấp lại được cho admin/bdh (đúng). Thông điệp lộ vai.
- `approveMember`/`rejectMember`: chỉ xét `status='chờ duyệt'`. Không có chặn theo vai (hiện không khai thác được vì hồ sơ tự đăng ký luôn là glv/du_bi, nhưng thiếu phòng thủ).
- Giao diện (`org.js:95`, `module_staff.php:143-151`) hiện nút Sửa/Cấp lại mật khẩu cho **mọi** dòng khi `canManageOrg`, kể cả BĐH khác.

### 2.2 Quy tắc (ai được làm gì với ai)

"Bảo vệ" = `members.role_code ∈ {admin, bdh}` **hoặc** có phân công hiệu lực với vai `admin`/`bdh` (`has_active_role`). Phần sau là phòng thủ theo chiều sâu, vì `assignments.php` cho admin gán vai bdh qua phân công.

| Người gọi (có `staff=edit`) → Mục tiêu | admin | bdh khác | chính mình (khi mình là bdh) | người thường (tk/gvcn/glv/du_bi/thu_thu) | hồ sơ chờ duyệt |
|---|---|---|---|---|---|
| **admin**: `saveMember` | ✓ (vai vẫn khoá như hiện nay) | ✓ (vai khoá) | — | ✓ | ✓ |
| admin: `deleteMember` | ✗ 400 (giữ nguyên) | ✗ 400 (giữ nguyên) | ✗ | ✓ | ✓ |
| admin: `resetPassword` | ✓ | ✓ | ✓ | ✓ | (UI ẩn) |
| admin: `approve/reject` | ✗ (không ở trạng thái chờ) | ✗ | — | — | ✓ |
| **bdh**: `saveMember` | ✗ **404** | ✗ **403** | ✓ **chỉ danh tính** (họ tên thánh, họ tên, SĐT); chức danh giữ nguyên | ✓ (quy tắc F2/F6 cũ) | ✓ |
| bdh: `deleteMember` | ✗ 404 | ✗ 403 | ✗ 403 | ✓ | ✓ |
| bdh: `resetPassword` | ✗ 404 | ✗ 403 (giữ nguyên) | ✗ 403 (tự đổi ở Cá nhân) | ✓ | — |
| bdh: `approve/reject` | ✗ 404 | ✗ 403 | — | — | ✓ |
| **Vai khác được admin cấp `staff=edit`** (vd truong_khoi) | ✗ 404 | ✗ 403 | theo người thường | ✓ + ràng buộc khối/lớp F6 cũ | ✓ |

Thêm cho mọi người gọi: **không tự xoá chính mình** (400: "Không thể tự xoá tài khoản của chính mình.").

Mã lỗi và thông điệp:
- Mục tiêu là admin, người gọi không phải admin: **404 "Không tìm thấy thành viên."**, giống hệt khi id không tồn tại. Như vậy nhất quán với F9 (`can_see_admin`): người không thấy admin thì cũng không dò ra được admin qua API.
- Mục tiêu là BĐH khác: **403 "Chỉ Quản Trị Hệ Thống mới sửa được hồ sơ thành viên Ban Điều Hành."** (với thao tác xoá: "…mới xoá được…", với cấp lại mật khẩu giữ câu hiện có ở `org.php:157`).
- BĐH sửa chính mình mà gửi chức danh khác: **không lỗi**, bỏ qua trường `title` và giữ `title_id` cũ. Client cũng khoá ô này (xem 2.3).

### 2.3 Thay đổi từng file / hàm

**`public/api/StaffService.php`**
1. `isProtected(array $member): bool`: giữ chữ ký, thêm điều kiện `|| has_active_role((int)$member['id'],'bdh') || has_active_role((int)$member['id'],'admin')`.
2. Hàm mới:
   ```php
   /**
    * Người gọi có được thao tác $op ('edit'|'delete'|'reset'|'approve') trên $target không.
    * Trả null nếu được; ngược lại ['ok'=>false,'error'=>…,'code'=>404|403|400].
    */
   public function guardTarget(array $target, string $op): ?array
   ```
   Hàm cài đúng bảng 2.2. `$target` phải có `id, role_code`. Không truy cập `$this->in`, để kiểm thử đơn vị được.
3. `saveMember()`: sau khi đọc `$old` (dòng 72), gọi `guardTarget($old,'edit')` và trả về ngay nếu bị chặn. Truy vấn `$old` phải lấy thêm `title_id`. Nếu `$this->isProtected($old)` và người gọi không phải admin (tức trường hợp tự sửa), ép `$titleId = $old['title_id']`.
4. `deleteMember()`: thay khối `isProtected` (dòng 222-224) bằng `guardTarget($m,'delete')`. Với admin gọi, thông điệp cũ cho người được bảo vệ vẫn giữ nguyên (admin đã thấy vai).

**`public/api/org.php`**
- `approveMember`/`rejectMember`: sau `db_one(...)` và kiểm 404, gọi `if ($e = $staff->guardTarget($m,'approve')) json_fail($e['error'], $e['code']);`.
- `resetPassword`: thay dòng 155-158 bằng `guardTarget($m,'reset')`.
- Không đụng `setClassHead/setBlockHead` hay phần khối/lớp.

**Client `public/assets/js/modules/org.js` + `views/module_staff.php`**
- Getter mới `canEditMemberRow(m)`: `this.isAdmin || !this.isProtectedMember(m) || m.id === this.user.memberId`.
- Getter mới `canResetMemberPw(m)`: `this.isAdmin || !this.isProtectedMember(m)`.
- `module_staff.php:143`: nút Sửa đổi thành `x-show="canManageOrg && canEditMemberRow(m)"`. Dòng 148: nút Cấp lại đổi thành `x-show="canManageOrg && canResetMemberPw(m) && m.status !== 'chờ duyệt'"`.
- Ô chức danh trong modal: disable khi `isProtectedMember(memberForm) && !isAdmin`.

Không cần migration.

### 2.4 Phương án đã loại

- Trả 403 khi mục tiêu là admin: làm lộ sự tồn tại/vai của admin, trái với F9.
- Chỉ sửa giao diện: không phải kiểm soát truy cập.
- Cấm BĐH sửa cả chính mình: gây phiền và không tăng bảo mật (BĐH vẫn sửa được qua `auth.php?action=profile`).
- Cho BĐH sửa BĐH khác: một BĐH đổi SĐT của đồng cấp khiến người đó bị khoá ngoài (DoS ngang cấp); `resetPassword` vốn đã cấm cặp này. Xem câu hỏi Q-B.

### 2.5 Rủi ro hồi quy

- BĐH mất nút Sửa trên dòng BĐH khác (đúng thiết kế). Cần báo cho người dùng.
- `isProtected` mở rộng: người có vai gốc glv nhưng kiêm phân công bdh (hiếm) sẽ không bị BĐH khác xoá hay đổi vai. Đúng ý đồ.
- P3 (#98, SĐT hợp lệ) cũng sửa `saveMember`: **P1 làm trước**, P3 rebase.

---

## 3. Issue #83 — `must_change_pw` chỉ chặn ở giao diện

### 3.1 Hiện trạng và điểm vào

- `_bootstrap.php:112-118` `require_login()` không xét cờ. `index.php:25` và `login.js:74` chỉ chặn ở giao diện.
- Điểm vào đi qua `require_login()`/`require_permission()` (sẽ tự được che): mọi file trong `public/api/*.php` có nghiệp vụ, `print.php:11`, `library_file.php:12`, `export.php:15`, `logs.php`, `settings.php`, `passkey.php` (`getRegisterArgs`, `processRegister`, `status`, `delete`), `push.php` (`status`, `subscribe`, `unsubscribe`, `test`, và `pending` khi không có endpoint), `auth.php` (`password`, `profile`), `src/Controllers/BaseController.php`.
- Điểm vào **không** qua `require_login` (đã kiểm từng cái):
  - `auth.php?action=login|logout|me|register`: công khai hoặc dùng `current_member()`. Giữ nguyên.
  - `passkey.php?action=getLoginArgs|processLogin`: công khai. `processLogin` **cần sửa** (3.3).
  - `push.php?action=key`: khoá công khai, vô hại.
  - `push.php?action=pending` **có endpoint**: nhận diện theo endpoint đã đăng ký (bí mật thiết bị), không theo phiên. Giữ nguyên; lý do: chỉ trả nội dung thông báo của chính chủ máy.
  - `sync.php`: chỉ trả mốc thời gian, không có dữ liệu.
  - `index.php`: đã chặn.
  - Trang công khai `tracuu.php`, `somoc.php`, `bxh.php`: không dùng phiên.
  - File debug (`check_times.php`, `read_step.php`…) thuộc P6.
  - `permission_of()` / `log_action()` gọi `current_member()` nhưng chỉ chạy sau khi đã qua cổng.
- **Lỗi ẩn (phải sửa trong P1):** token CSRF chỉ được sinh trong `page_bootstrap()` (`_bootstrap_page.php:21`), mà `index.php` gọi hàm này **sau** cổng `must_change_pw`. Màn đổi mật khẩu bắt buộc (`login.js:105-117 submitChange` → `post('password')`) **không gửi `X-CSRF-Token`**. `auth.php?action=password` gọi `require_write()` → `require_csrf()` → `$_SESSION['csrf_token']` rỗng → **403 "CSRF token not found"**. Báo cáo kiểm thử ghi "chưa chạy tay luồng đổi mật khẩu trên UI" (AUTH-04). Suy luận từ mã, cần xác nhận bằng AUTH-08 trước khi sửa.

### 3.2 Quy tắc

- Tài khoản có `must_change_pw = 1` và đã có phiên: mọi request qua `require_login()` (trực tiếp hoặc qua `require_permission()`) nhận
  **HTTP 403** với body `{"ok":false,"error":"Bạn cần đổi mật khẩu trước khi tiếp tục sử dụng.","code":"must_change_pw"}`.
- Danh sách cho phép:
  - `auth.php?action=password` (qua `require_login_pending_pw()`).
  - `auth.php?action=me|logout|login|register`, `passkey.php?action=getLoginArgs`, `push.php?action=key`, `push.php?action=pending` có endpoint, `sync.php`: vốn không gọi `require_login`.
- **Không** mở `auth.php?action=profile` (không cho đổi SĐT trước khi đổi mật khẩu), và không mở đăng ký Passkey.
- Thứ tự kiểm trong `require_login()`: chưa đăng nhập (401) → `đã nghỉ` (403) → `must_change_pw` (403 mã riêng).
- **Passkey:** `processLogin` xác minh chữ ký xong, nếu `must_change_pw=1` thì **không** tạo phiên và trả 403 `{ok:false, code:"must_change_pw", error:"Tài khoản cần đổi mật khẩu. Vui lòng đăng nhập bằng số điện thoại và mật khẩu (tạm) để đổi."}`. Không tính là lần thử sai. Lý do: màn đổi mật khẩu cần mật khẩu hiện tại; nếu cho Passkey mở phiên thì phải cho đổi mật khẩu không cần mật khẩu cũ, tức nới yếu.
- **Đăng nhập bằng mật khẩu:** vẫn tạo phiên như cũ, trả thêm `csrfToken` để màn đổi mật khẩu gọi được `password`.
- **Đăng ký công khai** (`register`): giữ `must_change_pw=0` như hiện nay (mật khẩu do chính người dùng đặt). Không đổi.

### 3.3 Thay đổi từng file / hàm

**`public/api/_bootstrap.php`** (P1 chỉ đụng đúng các chỗ sau):
1. **Sửa thân `require_login()`**: thêm một nhánh sau kiểm `đã nghỉ`:
   ```php
   if (!empty($me['must_change_pw'])) {
       json_out(['ok' => false, 'code' => 'must_change_pw',
                 'error' => 'Bạn cần đổi mật khẩu trước khi tiếp tục sử dụng.'], 403);
   }
   ```
   Tách phần kiểm "đã đăng nhập + không nghỉ" thành hàm riêng để dùng lại:
2. **Hàm mới** đặt ngay sau `require_login()`:
   ```php
   /** Như require_login() nhưng CHO PHÉP tài khoản đang buộc đổi mật khẩu — CHỈ dùng cho auth.php?action=password. */
   function require_login_pending_pw(): array
   ```
3. Không đụng `require_permission`, `require_write`, `require_csrf`, `require_post`, `login_throttle/*`, `register_*`, các hàm phạm vi.

**`public/api/auth.php`**
- `login`: sau `$_SESSION['member_id']=…`, trả `json_out(['ok'=>true,'user'=>…,'csrfToken'=>csrf_token()])`. `csrf.php` đã được `_bootstrap` nạp. Token gắn với phiên mới vì được sinh **sau** `session_regenerate_id(true)`.
- `password`: đổi `require_login()` thành `require_login_pending_pw()`. Giữ `require_write()` ở trước như hiện nay.

**`public/api/passkey.php`**: `processLogin`, sau khi kiểm `đã nghỉ` và **trước** `login_ok()`/`session_regenerate_id`, thêm nhánh từ chối `must_change_pw` như 3.2. P4 (#96/#95) cũng sửa hàm này (sign_count, UV), nên P1 chỉ thêm khối nhỏ đó.

**`public/index.php`**: trước `include layout_login.php` trong nhánh `$me && $me['must_change_pw']`, đặt `$__mustChangePw = true; $__csrf = csrf_token();`. `_bootstrap_page.php` đã nạp `csrf.php`.

**`views/layout_login.php`**: trên thẻ `x-data="loginScreen"` thêm `data-must-change="<?= !empty($__mustChangePw) ? '1' : '0' ?>" data-csrf="<?= htmlspecialchars($__csrf ?? '') ?>"`. Ở bước `changepw`, thêm nút "Đăng xuất / dùng tài khoản khác", gọi `auth.php?action=logout` bằng POST kèm token.

**`public/assets/js/login.js`**
- Thêm trường `csrf: ''`. `init()`: đọc `this.$el.dataset.csrf`; nếu `dataset.mustChange==='1'` thì `step='changepw'`. Khi reload, ô "Mật khẩu hiện tại" để trống cho người dùng tự nhập mật khẩu tạm.
- `post()`: gửi `X-CSRF-Token: this.csrf` nếu có.
- `submitLogin()`: `this.csrf = r.csrfToken || ''` trước khi chuyển sang `changepw`.
- `loginPasskey()`: nếu kết quả có lỗi mã `must_change_pw` thì hiện thông điệp máy chủ (đã có sẵn qua `res.message`, vì `passkey.js:175` trả `verifyRes.error`). Không cần sửa `passkey.js`.

**`public/assets/js/modules/core.js`** (app chính, khi cờ bị bật giữa phiên, ví dụ BĐH vừa cấp lại mật khẩu cho người đang dùng):
- `api()` dòng 156: khi `res.status === 403`, thử `await res.json()`. Nếu `j.code === 'must_change_pw'`: `toast.warning('Bạn cần đổi mật khẩu trước khi tiếp tục.')`, `await window.TNTT.snap.clear()`, `setTimeout(()=>location.reload(),1500)`, rồi trả `{ok:false, error:j.error}`. Các 403 khác **giữ nguyên** câu cũ (không mở rộng phạm vi P1).
- `_fetchPart()` đã xoá bản chụp và reload khi gặp 403, nên không cần sửa. Sau reload, `index.php` đưa vào bước `changepw`. Không lặp reload vì `index.php` không gọi `data.php`.

Không cần migration. **Việc vận hành trước khi triển khai:** chạy `SELECT role_code, COUNT(*) FROM members WHERE must_change_pw=1 AND status='đang phục vụ' GROUP BY role_code;` (cột mặc định `DEFAULT 1`, tài khoản do `install.php`/seed tạo đều mang cờ). Báo trước cho những người này rằng lần mở app kế tiếp sẽ phải đổi mật khẩu.

### 3.4 Phương án đã loại

- Chặn ở `current_member()`: làm hỏng `index.php`, `me`, `logout`, `log_action`.
- Tham số `require_login(bool $allowPending=false)`: dễ bị gọi nhầm `true` ở endpoint khác, lại đổi chữ ký hàm mà P4 cũng sửa. Chọn hàm riêng, tên nói rõ công dụng.
- Trả 401: client hiểu là hết phiên và đá về màn đăng nhập, không phân biệt được.
- Cho Passkey mở phiên rồi đổi mật khẩu không cần mật khẩu cũ: nới yếu (xem 3.2).
- Chặn `push.php?action=pending` có endpoint: không phải xác thực theo phiên. Chặn thì service worker mất thông báo mà không tăng bảo mật.

### 3.5 Rủi ro hồi quy

- Kịch bản e2e hiện dùng admin `tntt@2026` còn cờ trước khi reset (`e2e.py:144-253`). Người cài đặt phải rà các ca trong khoảng đó và bảo đảm chúng chỉ dùng tài khoản `must=0`. AUTH-05 chuyển sang PASS.
- `print.php`/`library_file.php` mở trong tab mới sẽ thấy JSON 403 thay vì trang. Chấp nhận được, vì người dùng không thể tới đây khi chưa đổi mật khẩu.
- Nếu P4 thêm `require_csrf` vào `logout` thì màn đăng nhập đã có token (nhờ 3.3), không vỡ.

---

## 4. Thứ tự commit (một nhánh `fix/p1-authz`, một PR)

1. `test(e2e): mở rộng SCOPE/STAFF/AUTH + dữ liệu mẫu nhiều lớp`. Commit này thêm kịch bản trước (phải **FAIL** trên mã cũ; lưu đầu ra "trước").
2. `fix(data): lọc scores/leaveRequests/reports theo phạm vi; members theo quyền staff; logs chỉ Quản trị; khoá cache v2` (#78). Gồm `data.php`, `_common.php` (`can_view_logs`), `logs.php`.
3. `fix(staff): chặn BĐH sửa/xoá/cấp lại mật khẩu admin & BĐH khác` (#97). Gồm `StaffService.php`, `org.php`.
4. `fix(ui-staff): ẩn nút sửa/cấp lại theo quy tắc bảo vệ` (#97). Gồm `org.js`, `module_staff.php`.
5. `fix(auth): màn đổi mật khẩu bắt buộc gửi CSRF (login trả csrfToken)`. Gồm `auth.php` (login), `index.php`, `layout_login.php`, `login.js`. **Phải nằm trước commit 6**, để không khoá người dùng ngoài.
6. `fix(auth): require_login chặn must_change_pw; passkey từ chối tài khoản cần đổi mật khẩu` (#83). Gồm `_bootstrap.php`, `auth.php` (password), `passkey.php`.
7. `fix(ui): app chính hiểu mã must_change_pw` (#83). Gồm `core.js`.
8. Chạy lại toàn bộ `tests/e2e` và PHPUnit, lưu đầu ra "sau" vào mô tả PR.

**Ghi chú cho P4:** P1 chỉ sửa `_bootstrap.php` ở **thân `require_login()`** và **thêm `require_login_pending_pw()`** ngay sau nó. P4 rebase sau P1 và phải giữ nhánh `must_change_pw` khi sửa `require_login`/`require_write`. P1 cũng thêm một khối nhỏ trong `passkey.php::processLogin` (trước `login_ok`), nên P4 (#96/#95) cần rebase. `auth.php` bị P1 sửa ở `login` và `password`; P3/P4 không sửa song song.

---

## 5. Kiểm thử nghiệm thu

### 5.1 Dữ liệu mẫu bổ sung (trong `e2e2.py`, chạy trên DB thử)

- Lớp A (khối bA), lớp B (khối bB), lớp C (khối khác bA nếu có; nếu không có thì tạo lớp C thuộc bB).
- Với mỗi lớp A/B/C, lấy 2 em có ghi danh năm hiện tại và chèn:
  - `leave_requests`: 2 đơn/lớp, `reason='NHAYCAM-<lớp>'`, trạng thái lẫn chờ duyệt/đã duyệt.
  - `reports`: 1 phiếu/em cho học kỳ đầu năm hiện tại.
  - `scores`: 1 điểm/em.
- Người dùng mới:
  - `0911000007` GLV kiêm **hai lớp A và C** (2 dòng phân công glv).
  - `0911000008` GLV lớp A **kiêm thu_thu**.
  - Tài khoản `0977200001` ở trạng thái `chờ duyệt` (để kiểm lọc members).
  - Tài khoản `0911000009` GLV lớp A có `must_change_pw=1`.

### 5.2 #78 — ma trận vai × khoá (`e2e2.py`, mở rộng SCOPE-01…03)

Chạy với mỗi vai sau, cho từng `part ∈ {core, heavy, all}`: admin, bdh, truong_khoi(bA), glv_chu_nhiem(A), glv(A), glv(A+C), glv+thu_thu, du_bi(A), thu_thu.

| Mã | Khẳng định |
|---|---|
| KEYS-01 | Mọi khoá cũ có mặt; `leaveRequests`, `scores`, `reports`, `members`, `logs` là **list** (không null/thiếu) với mọi vai và mọi `part` có khoá đó. |
| SCOPE-01 | `{studentId của scores} ⊆ {id students}` (part=all). Với heavy thì so với `students` của core. |
| SCOPE-01b | Vai lớp/khối nhận **đủ** điểm mẫu của lớp mình (không lọc quá tay); glv(A+C) nhận điểm của cả A và C, không có B. |
| SCOPE-04 | `leaveRequests`: tương tự SCOPE-01/01b. Không có `reason` chứa `NHAYCAM-B` với vai lớp A. thu_thu nhận `[]`. |
| SCOPE-06 (mới) | `reports`: tương tự. Trưởng khối bA nhận mọi phiếu của các lớp trong bA, không có lớp ngoài. |
| SCOPE-07 (mới) | admin và bdh: số dòng `scores/leaveRequests/reports` **bằng** `COUNT(*)` trong DB cho năm hiện tại (bất biến so với trước). |
| SCOPE-02 | thu_thu: `members == []`. Vai có staff=view: không có `role=admin`, không có `status='chờ duyệt'`, mọi `registerNote==''`. bdh: có `0977200001` (chờ duyệt), không có admin. admin: có admin. |
| SCOPE-03 | Mọi vai ≠ admin: `logs == []`. admin: `len(logs) == min(50, COUNT)`. (Nếu Q-A = Có thì đổi thành: mọi dòng có `actor ==` tên chính mình.) |
| SCOPE-05 (mới) | admin đặt `scores` của `glv`=none qua `settings.php?action=permission`, xoá cache: glv(A) nhận `scores==[]`, glv+thu_thu cũng `[]`, gvcn(A) không đổi. Sau đó đặt lại. |
| SCOPE-08 (mới) | Cache: gọi `data.php` hai lần liên tiếp cho glv(A), kết quả giống nhau và vẫn đúng phạm vi. Header ETag không đổi giữa hai lần. |
| LOG-01 (mới) | `logs.php`: admin → 200 có `data`; bdh/glv → 403. |
| PERF-01 | `perf.py`/ab: TTFB `data.php?part=core` và `heavy` cho glv và admin không tăng quá 10% so với trước. |

Kiểm thử đơn vị (PHPUnit, theo khuôn `tests/unit/ScopeTest.php`): `data_scope_for()` với admin (null), bdh (null), tk (lớp của khối), glv 2 lớp (hợp), glv+thu_thu (chỉ lớp glv), thu_thu ([]), và vai có module = none ([]). Nếu `data.php` khó nạp trong test, người cài đặt được phép chuyển hai hàm `data_*` sang một file `public/api/_data_scope.php` (require từ `data.php`).

### 5.3 #97 — mở rộng STAFF-06 (`extra.py`)

| Mã | Ca | Kỳ vọng |
|---|---|---|
| STAFF-06a | bdh `saveMember` id admin, đổi tên + SĐT | HTTP 404; DB không đổi; admin **vẫn đăng nhập** bằng SĐT cũ (200). |
| STAFF-06b | bdh `saveMember` bdh khác (tạo `0911000010` bdh) | 403; DB không đổi. |
| STAFF-06c | bdh `saveMember` chính mình (đổi họ tên, gửi `title` khác) | 200; họ tên đổi; `title_id` **không đổi**. |
| STAFF-06d | bdh `deleteMember` admin / bdh khác / chính mình | 404 / 403 / 403; không xoá. |
| STAFF-06e | bdh `resetPassword` admin / bdh khác | 404 / 403; `password_hash` không đổi. |
| STAFF-06f | bdh `approveMember` và `rejectMember` với id admin | 404. |
| STAFF-06g | admin `saveMember` bdh (danh tính) | 200 (không hồi quy). |
| STAFF-06h | bdh `saveMember` glv (STAFF-01 với bdh) | 200. |
| STAFF-06i | admin `resetPassword` bdh | 200, `must_change_pw=1`. |
| STAFF-06j | Thông điệp 404 cho id admin **giống hệt** id không tồn tại (so chuỗi `error`). | |

PHPUnit: `StaffService::guardTarget()` theo đủ các ô của bảng 2.2 (dựng `$me`/`$target` bằng mảng; `has_active_role` cần DB test hoặc fixture).

### 5.4 #83 — mở rộng AUTH-05 (`e2e.py`)

Đăng nhập bằng mật khẩu với `0911000009` (`must=1`), rồi:

| Mã | Ca | Kỳ vọng |
|---|---|---|
| AUTH-05 | `GET data.php` | 403, `code=="must_change_pw"`. |
| AUTH-05b | Lặp qua danh sách endpoint: `students.php?action=list`, `attendance.php` (GET), `leave.php`, `scores.php`, `reports.php`, `export.php?action=attendance-detail&classId=A`, `print.php?type=report…`, `library.php?action=list`, `library_file.php?id=1`, `logs.php`, `notes.php`, `announcements.php`, `assignments.php?action=list_active`, `org.php?action=saveMember` (POST), `passkey.php?action=status`, `passkey.php?action=getRegisterArgs`, `push.php?action=status`, `push.php?action=subscribe` (POST), `auth.php?action=profile` (POST), `rewards.php?action=lookup`, `gifts.php?action=list`, `promotion.php`, `programs.php`, `years.php` | Mọi cái trả 403 `must_change_pw`. Cho phép 401/405/404 **chỉ** khi endpoint trả mã đó trước bước đăng nhập; ghi rõ từng ngoại lệ. |
| AUTH-05c | `auth.php?action=me` | 200, `user.mustChangePw == true`. |
| AUTH-08 (mới) | Phản hồi `login` có `csrfToken` 64 hex. POST `auth.php?action=password` với token đó, **không** mở `index.php` trước | 200. Sau đó `data.php` → 200, `must_change_pw=0`. |
| AUTH-08b | POST `password` **không** có token | 403 CSRF (không nới lỏng). |
| AUTH-08c | Mở `GET /` khi `must=1` | HTML có `data-must-change="1"` và `data-csrf` 64 hex. |
| AUTH-09 (mới) | Passkey: đăng ký Passkey cho `0911000009` khi `must=0` (dùng virtual authenticator như `hw.js` PK-*), đặt `must=1`, đăng nhập Passkey | `processLogin` → 403 `must_change_pw`; sau đó `auth.php?action=me` → `ok:false` (không có phiên). |
| AUTH-10 | `push.php?action=pending` với endpoint đã đăng ký của user `must=1`, không có phiên | Vẫn 200 (hành vi thiết kế). |
| AUTH-11 | Đăng ký công khai + duyệt | Tài khoản mới đăng nhập được, `mustChangePw=false`, `data.php` 200. |
| AUTH-12 | Người dùng đang có phiên, admin `resetPassword` họ | Request API kế tiếp của họ → 403 `must_change_pw`. |
| AUTH-13 | `logout` khi `must=1` | 200, phiên huỷ. |

### 5.5 Kiểm thủ công trên giao diện (Chrome desktop + Safari iOS/WebKit)

1. **GLV lớp A:** mở app, xem Trang chủ, Thiếu nhi, Xin phép (danh sách, chấm đỏ đơn chờ), Điểm số, Sổ liên lạc (tạo, sửa phiếu), Báo cáo/Thống kê/Phân tích. Không lỗi console, số liệu chỉ của lớp A. Thẻ Cá nhân → "Hoạt động của tôi" hiện đúng theo quyết định Q-A.
2. **Trưởng khối:** màn Khối & Lớp, danh sách nhân sự (có SĐT, không có hồ sơ chờ duyệt), Sinh nhật (thấy nhân sự).
3. **Thủ thư thuần:** mở app. Không trắng màn; màn Đổi quà và Danh mục quà chạy; không có danh bạ; các thẻ Thiếu nhi/Nhân sự ẩn hoặc rỗng mà không lỗi.
4. **BĐH:** màn Nhân sự không thấy admin. Dòng BĐH khác không có nút Sửa và Cấp lại mật khẩu. Dòng của chính mình có nút Sửa, ô chức danh bị khoá, sửa họ tên thì lưu được. Duyệt hồ sơ chờ vẫn chạy. Số liệu Thống kê toàn đoàn không đổi so với trước.
5. **Admin:** thẻ Nhật ký trong Cài đặt có dữ liệu; sửa được BĐH.
6. **Buộc đổi mật khẩu:**
   - Đăng nhập bằng mật khẩu tạm → màn đổi mật khẩu → đổi thành công → vào app.
   - Tải lại trang giữa chừng → vẫn ở màn đổi mật khẩu; nhập mật khẩu tạm vào ô hiện tại → thành công.
   - Nút Đăng xuất ở màn này hoạt động.
   - Đăng nhập bằng Passkey khi đang buộc đổi → thông báo lỗi rõ ràng, không vào app.
   - Đang dùng app thì bị cấp lại mật khẩu → toast rồi về màn đổi mật khẩu, không lặp reload.
7. **PWA đã cài** (có bản chụp IndexedDB cũ): mở lại sau khi triển khai; dữ liệu mới thay bản cũ, không lỗi.

---

## 6. Câu hỏi có/không còn cần chủ dự án quyết định

- **Q-A.** Người không phải Quản trị có được nhận **nhật ký của chính mình** (chỉ dòng do mình thực hiện), để thẻ "Hoạt động của tôi" vẫn có số không? *Mặc định trong đặc tả: Không (theo đúng Q1: logs chỉ cho quyền cài đặt). Trả lời Có thì chỉ đổi một truy vấn trong `data.php` và kịch bản SCOPE-03.*
- **Q-B.** BĐH có được sửa hồ sơ (họ tên/SĐT) của **BĐH khác** không? *Mặc định: Không, chỉ Quản trị (nhất quán với `resetPassword` hiện có).*
- **Q-C.** BĐH có được xem **nhật ký thao tác** (khoá `logs` và `logs.php`) không? *Mặc định: Không, vì Cài đặt chỉ dành cho Quản trị. Docblock cũ của `logs.php` ghi "admin/BĐH", nên cần xác nhận.*
- **Q-D.** GLV/Trưởng khối (chỉ có `staff=view`) có cần thấy **hồ sơ đang chờ duyệt** (tên, SĐT, lời nhắn đăng ký) không? *Mặc định: Không, vì họ chưa phải nhân sự và chỉ BĐH duyệt.*

(Không cần hỏi về Passkey: đặc tả chọn từ chối đăng nhập Passkey khi còn cờ đổi mật khẩu, vì không có phương án an toàn hơn mà không nới yếu.)

---

## 7. Ngoài phạm vi P1 (ghi nhận để lập issue sau, không sửa trong P1)

- `announcements` gửi mọi thông báo (kể cả thông báo nhắm lớp/khối khác) cho mọi người.
- `member_scopes()` bỏ qua vai gốc khi có phân công, khiến admin/BĐH kiêm lớp bị thu hẹp phạm vi (liên quan #94).
- Đặt lại mật khẩu (`resetPassword`) không thu hồi Passkey. Tài khoản bị chiếm có Passkey của kẻ gian thì vẫn còn khoá đó (liên quan P4).
- BĐH dùng `setClassHead`/`assignments.php` được với tài khoản admin (thêm/kết thúc phân công). Việc này không đổi danh tính nhưng nên cân nhắc chặn theo cùng quy tắc F9.
- Trong `core.js` `api()`, mọi 403 khác đều bị hiển thị là "CSRF", gây hiểu nhầm.
