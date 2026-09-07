# Chương trình lớp + Phân công (Mục 6) — Kế hoạch triển khai

> **Trạng thái:** PLAN — chưa code. Viết sẵn để thực thi sau.
> **Cho người thực thi:** làm lần lượt từng Task; mỗi Task tự kiểm chứng + commit.

**Mục tiêu:** GVCN lập **chương trình lớp theo từng buổi (Chúa Nhật)** — chủ đề
giảng bài, nội dung sinh hoạt, ghi chú — và **phân công đầu việc** (tự nhập) cho
các GLV trong lớp. GLV trong lớp xem được chương trình + việc mình được giao.

**Yêu cầu đã chốt với chủ đoàn:**
- Nhập **biểu mẫu trong app** (KHÔNG upload file).
- **KHÔNG** có quy trình duyệt trong app (GVCN nhập bản đã được duyệt sẵn).
- Đầu việc **tự nhập tự do**.
- Lập **theo buổi (Chúa Nhật)**.

## Ràng buộc chung
- Theo đúng khuôn mẫu module hiện có (xem `reporthub`/`org` để tham chiếu).
- **Hai trục quyền** sẵn có: quyền module (`permissions`) × phạm vi
  (`can_access_class`/`accessible_class_ids`). GVCN sửa lớp mình; Trưởng khối
  trong khối; BĐH toàn đoàn; GLV/Dự bị **chỉ xem** lớp mình.
- BÀI HỌC QUAN TRỌNG: module hiển thị lấy từ **bảng `modules`** (không phải chỉ
  `moduleDefs`) — phải thêm dòng vào bảng `modules` + `permissions`, và đồng bộ
  `install.php`. Icon phải có trong `assets/js/vendor/lucide-icons.js` (kiểm; thêm
  nếu thiếu). Class Tailwind phải có trong `assets/css/tailwind.css` (build tĩnh).
- CSRF + require_permission + kiểm phạm vi ở MỌI action ghi.
- Kiểm thử: `php -l`, `node --check`, `php phpunit10.phar --no-coverage` xanh,
  E2E qua tài khoản test ZZ (xoá sau).

---

## Task 1 — Cơ sở dữ liệu (bảng + module + quyền)

**Files:** tạo `config/migrate_class_plans.php`; sửa `config/install.php`.

- [ ] **B1.** Migration idempotent `config/migrate_class_plans.php`:
  ```php
  require __DIR__ . '/db.php';
  db_run("CREATE TABLE IF NOT EXISTS class_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    year_id INT NOT NULL,
    class_id INT NOT NULL,
    session_date DATE NOT NULL,
    chu_de VARCHAR(255) DEFAULT '',
    sinh_hoat TEXT NULL,
    ghi_chu TEXT NULL,
    created_by INT NULL,
    updated_by INT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_plan (year_id, class_id, session_date),
    KEY k_class (class_id, session_date)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  db_run("CREATE TABLE IF NOT EXISTS class_plan_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plan_id INT NOT NULL,
    task_name VARCHAR(255) NOT NULL,
    assignee_id INT NULL,
    sort_order INT DEFAULT 0,
    KEY k_plan (plan_id),
    CONSTRAINT fk_task_plan FOREIGN KEY (plan_id) REFERENCES class_plans(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  // module + quyền
  db_run("INSERT IGNORE INTO modules (module_key,label,icon,color,area,sort_order,is_enabled)
          VALUES ('classplan','Chương trình lớp','clipboard-list','text-amber-600','glv',7,1)");
  $vais = array_column(db_all("SELECT code FROM roles"), 'code');
  // edit: admin/bdh/truong_khoi/glv_chu_nhiem ; view: glv/du_bi
  $edit = ['admin','bdh','truong_khoi','glv_chu_nhiem'];
  foreach ($vais as $v) {
    $lv = in_array($v,$edit,true) ? 'edit' : 'view';
    db_run("INSERT INTO permissions (module_key,role_code,level) VALUES ('classplan',?,?)
            ON DUPLICATE KEY UPDATE level=level", [$v,$lv]);
  }
  echo "Xong: class_plans + class_plan_tasks + module classplan + quyền.\n";
  ```
- [ ] **B2.** `install.php`: thêm `['classplan','Chương trình lớp','clipboard-list','text-amber-600','glv',7]`
  vào `$modules`; thêm `'classplan' => ['edit','edit','edit','edit','view','view']` vào `$perms`.
- [ ] **B3.** Chạy `php config/migrate_class_plans.php` trên DB local; kiểm bảng tồn tại.
- [ ] **B4. Commit.**

**Ghi chú:** `clipboard-list` đã có trong bộ lucide (đã dùng chỗ khác). `text-amber-600`
có trong tailwind build. Nếu đổi icon/màu nhớ kiểm tồn tại trước.

---

## Task 2 — API `public/api/classplan.php`

**Files:** tạo `public/api/classplan.php`.

**Interfaces (actions, đều `require __DIR__.'/_bootstrap.php'` + `$me=require_login()`):**
- `list` — `{classId, from, to}` → các buổi có/chưa có kế hoạch trong khoảng (để
  hiện danh sách). Kiểm `can_access_class($me,'classplan',$classId,'view')`.
- `get` — `{classId, date}` → 1 plan + tasks (hoặc rỗng). Kiểm view.
- `save` — `{classId, date, chuDe, sinhHoat, ghiChu}` → upsert vào `class_plans`
  theo UNIQUE(year,class,date). **require_csrf + require_permission('classplan','edit')
  + can_access_class(...,'edit')**. Trả về planId.
- `save_tasks` — `{planId, tasks:[{name, assigneeId, sort}]}` → xoá hết task cũ của
  plan rồi chèn lại (đơn giản, ít lỗi). Kiểm quyền edit + plan thuộc lớp trong phạm vi.
- `class_glv` — `{classId}` → danh sách GLV đang phân công lớp đó (từ
  `member_assignments` active của lớp) để đổ dropdown "người phụ trách".

**Bảo mật:** ép kiểu id; prepared; `assignee_id` phải nằm trong danh sách GLV của
lớp (chống gán bừa). `date` validate `Y-m-d`.

- [ ] Viết từng action + kiểm quyền/phạm vi. `php -l`. Commit.

---

## Task 3 — Module frontend + view

**Files:** tạo `public/assets/js/modules/classplan.js`, `views/module_classplan.php`;
sửa `public/assets/asset_manifest.php` (thêm `'classplan'` vào `js_modules`),
`public/index.php` (include view), `core.js` `moduleDefs` (thêm def classplan,
area 'glv', group 'Quản lý', icon/màu khớp bảng modules).

**classplan.js (window.TNTT.classplan):** state `cpClass`, `cpDate`, `cpPlan`,
`cpTasks`, `cpClassGlv`; hàm `openClassPlan()`, `cpLoadDates()` (sinh danh sách
Chúa Nhật trong học kỳ hiện tại), `cpSelectDate(date)` (gọi get), `cpSave()`,
`cpAddTask()/cpSaveTasks()/cpRemoveTask()`, `cpLoadGlv()`.

**module_classplan.php:** chọn Lớp (trong `accessibleClasses`) → chọn buổi (dropdown
Chúa Nhật) → form Chủ đề/Sinh hoạt/Ghi chú (chỉ sửa được nếu `canEditModule('classplan')`
+ trong phạm vi; GLV thường thấy read-only) → danh sách đầu việc: mỗi dòng ô nhập
tên việc + dropdown người phụ trách (GLV lớp) + nút xoá; nút "Thêm việc", "Lưu".

- [ ] Sinh danh sách Chúa Nhật: từ `term.start_date`..`term.end_date`, lọc các ngày
  Chủ Nhật (`date('w')===0`). Hàm thuần → **có thể viết unit test** (Task 5).
- [ ] Thêm vào moduleDefs + đảm bảo `visibleFlat`/sidebar hiện. `node --check`. Commit.

---

## Task 4 — GLV thấy "việc được giao" (nhẹ)

**Files:** sửa `core.js` `myTasks` (hoặc partial_my_tasks) — tuỳ chọn.

- [ ] Nếu GLV có việc được giao ở buổi sắp tới → thêm 1 dòng "Việc cần làm":
  "Bạn phụ trách: <task> · <lớp> · <ngày>". Chỉ hiện việc của chính mình
  (`assignee_id = me`). Không bắt buộc cho bản đầu — có thể để Task sau.

---

## Task 5 — Kiểm thử + E2E

- [ ] Unit (PHPUnit) cho hàm sinh danh sách Chúa Nhật (nếu tách được sang PHP) và
  cho kiểm phạm vi lưu plan (GVCN lớp A không lưu được lớp B).
- [ ] E2E (tài khoản ZZ): GVCN mở Chương trình lớp → chọn buổi → nhập chủ đề +
  2 đầu việc gán 2 GLV → Lưu → tải lại thấy đúng; đăng nhập GLV khác thấy read-only +
  việc mình được giao. Xoá tài khoản test.
- [ ] `php phpunit10.phar --no-coverage` xanh. Commit.

---

## Việc cần nhắc khi thực thi
- Chạy `php config/migrate_class_plans.php` trên **máy chủ thật** (một lần).
- Kiểm icon `clipboard-list` + class màu có trong build trước khi dùng.
- Đây là feature có GHI dữ liệu → giữ nguyên quy tắc CSRF + phạm vi + dọn data test.

## Ngoài phạm vi (bản sau)
- Upload file giáo án; quy trình duyệt trong app; danh mục đầu việc cố định;
  nhắc việc qua push cho người được phân công; sao chép chương trình từ buổi trước.
