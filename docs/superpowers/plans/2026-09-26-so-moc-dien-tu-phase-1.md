# Sổ Mộc Điện Tử — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Xây phần lõi của Sổ Mộc — tự tính Mộc/chuỗi từ điểm danh, danh mục quà, đổi quà tại quầy — và cô lập vai trò Thủ thư để không leo thang quyền.

**Architecture:** `attendances` là nguồn chân lý; hàm `recalc_stamps($studentId,$yearId)` tính lại ví + chuỗi sau mỗi lần điểm danh (móc vào 3 đường ghi của `attendance.php`). Đổi quà là transaction có khóa dòng, trừ `current_balance` + `stock` + ghi audit. Vai trò `thu_thu` (scope toàn đoàn) chỉ mạnh trên 2 module mới `gifts`/`rewards`; 2 hàm gộp phạm vi trong core được vá module-aware để chặn rò rỉ.

**Tech Stack:** PHP 8 (thuần, không framework), MySQL/InnoDB utf8mb4, Alpine.js + Tailwind (view), PHPUnit (`tests/unit`, chạy DB thật).

**Spec:** `SPEC-MOC-DIEN-TU.md` (v4)

## Global Constraints

- Ngôn ngữ: PHP thuần theo phong cách repo — chú thích & nhãn ENUM tiếng Việt (vd `type ENUM('có mặt','đi trễ')`, `status`).
- CSDL: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`. Mọi bảng gắn `year_id` FK `school_years(id)`.
- Migration: file mới `config/migrations/003_stamps_rewards.sql`, chạy bằng `php config/migrations/index.php`. Header 4 dòng như `002_*.sql`.
- API: dùng helpers có sẵn — `json_input()`, `json_out()`, `json_fail()`, `require_write()`, `require_permission($module,$need)`, `current_year()`, `db_one/db_all/db_run/db_insert`, `log_action()`, `Cache::flush()`. Không tự viết truy vấn quyền.
- Ví theo **năm học**: mọi ghi/đọc Mộc lọc theo `current_year()['id']`.
- Test đặt tại `tests/unit/*Test.php` kế thừa `PHPUnit\Framework\TestCase`, tạo dữ liệu tạm trong `setUp`, dọn trong `tearDown` (theo mẫu `ScopeTest.php`).
- Nhánh phát triển: `claude/tinh-nang-dien-tu-7b66gi`. Commit message tiếng Việt, kết bằng footer attribution của phiên. KHÔNG mở PR trừ khi được yêu cầu.
- Module `rewards`/`gifts` phục vụ **toàn đoàn**, KHÔNG chia theo lớp: chỉ gác `require_permission`, cấm gọi `can_access_class`/`allowed_class_ids`/`scan_class_ids`.

## Review Focus

- **Gỡ điểm danh phải hoàn Mộc:** xóa một `attendances` rồi recalc → `total_earned`/`current_balance`/streak trở về đúng như chưa từng có buổi đó. (Task 3, 4)
- **Đua khi đổi quà (double-spend / oversell):** 2 lượt đổi đồng thời không được làm `current_balance` âm hay `stock` âm. (Task 7 — `SELECT ... FOR UPDATE`)
- **Leo thang quyền của `thu_thu`:** GLV kiêm `thu_thu(toàn đoàn)` vẫn chỉ thấy/sửa lớp mình ở students/attendance/qr-card. (Task 2)
- **Đi trễ vào đúng ngày chạm mốc:** giữ chuỗi nhưng mất mốc thưởng hôm đó, không trả bù. (Task 3)
- **Ranh giới `effective_from` & một-ngày-nhiều-buổi:** điểm danh trước ngày bắt đầu không tính; một ngày có ≥1 buổi thi đua chỉ +1 (CN +2), không cộng đôi. (Task 3)

---

### Task 1: Migration — schema Mộc/quà + vai trò + phân quyền

**Files:**
- Create: `config/migrations/003_stamps_rewards.sql`
- Test: `tests/unit/RewardsSchemaTest.php`

**Interfaces:**
- Produces (bảng & cột dùng cho mọi task sau):
  - `student_stamps(id, year_id, student_id, current_balance INT, held_balance INT, total_earned INT, current_streak INT, longest_streak INT, last_attendance_date DATE NULL)` · `UNIQUE(year_id, student_id)`
  - `stamp_transactions(id, year_id, student_id, amount INT, type ENUM('attendance','streak_bonus','spend','manual_adjust'), ref_attendance_id INT NULL, ref_order_id INT NULL, description VARCHAR(255), actor_id INT NULL, created_at DATETIME)` · `UNIQUE(ref_attendance_id, type)` (idempotent earn)
  - `gifts(id, name, stamp_cost INT, stock INT, image_url VARCHAR(255) NULL, status ENUM('còn bán','ẩn') DEFAULT 'còn bán', sort_order TINYINT DEFAULT 1)`
  - `gift_orders(id, year_id, student_id, total_cost INT, redeem_code_hash VARCHAR(255), status ENUM('chờ lấy','đã giao','đã hủy','quá hạn'), created_at, expires_at, delivered_by INT NULL, delivered_at DATETIME NULL)`
  - `gift_order_items(id, order_id, gift_id, qty INT, unit_cost INT, line_cost INT)`
  - Role `('thu_thu','Thủ Thư',1,'toàn đoàn','Phục vụ đổi quà toàn đoàn')`
  - Modules `('gifts','Danh mục quà','gift','text-pink-600','bdh',...)`, `('rewards','Đổi quà','shopping-bag','text-amber-600','glv',...)`
  - Permissions: `gifts` → admin/bdh/thu_thu = edit; `rewards` → admin/thu_thu = edit; các role còn lại không seed (mặc định `none`).

- [ ] **Step 1: Viết test kiểm tra schema & seed sau migration** trong `tests/unit/RewardsSchemaTest.php`

```php
public function test_tables_exist() {
    foreach (['student_stamps','stamp_transactions','gifts','gift_orders','gift_order_items'] as $t)
        $this->assertNotNull(db_one("SHOW TABLES LIKE ?", [$t]), "thiếu bảng $t");
}
public function test_role_and_permissions_seeded() {
    $this->assertNotNull(db_one("SELECT 1 FROM roles WHERE code='thu_thu'"));
    $this->assertSame('edit', db_one("SELECT level FROM permissions WHERE module_key='rewards' AND role_code='thu_thu'")['level']);
    $this->assertNull(db_one("SELECT 1 FROM permissions WHERE module_key='rewards' AND role_code='glv'"));
    $this->assertSame('edit', db_one("SELECT level FROM permissions WHERE module_key='gifts' AND role_code='bdh'")['level']);
}
```

- [ ] **Step 2: Chạy test → FAIL** (`vendor/bin/phpunit tests/unit/RewardsSchemaTest.php` hoặc `php phpunit10.phar ...`) — thiếu bảng.
- [ ] **Step 3: Viết `003_stamps_rewards.sql`** tạo 5 bảng trên (CREATE TABLE IF NOT EXISTS, FK về `school_years`/`students`/`members`/`gifts`/`gift_orders`), rồi `INSERT ... ON DUPLICATE KEY UPDATE` role/modules/permissions. Dùng `INSERT IGNORE`/`ON DUPLICATE` để chạy lại an toàn.
- [ ] **Step 4: Áp migration** — `php config/migrations/index.php`.
- [ ] **Step 5: Chạy test → PASS.**
- [ ] **Step 6: Commit** (`git add config/migrations/003_stamps_rewards.sql tests/unit/RewardsSchemaTest.php`).

---

### Task 2: Cô lập quyền — vá 2 hàm gộp phạm vi + test hồi quy

**Files:**
- Modify: `public/api/_bootstrap.php` (`scan_class_ids`, ~dòng 477)
- Modify: `public/api/_common.php` (`responsible_class_ids`, ~dòng 242)
- Test: `tests/unit/RewardsScopeTest.php`

**Interfaces:**
- Consumes: `permission_of_role($roleCode,$moduleKey)`, `member_scopes($me)`, `resolve_class_ids_from_scope($a)` (đã có trong `_common.php`).
- Produces: hành vi mới của `scan_class_ids`/`responsible_class_ids` (chữ ký giữ nguyên).

- [ ] **Step 1: Viết test hồi quy** `tests/unit/RewardsScopeTest.php` — tạo member `glv` + assignment `glv`(lớp A) + assignment `thu_thu`(toàn đoàn), seed `permission(rewards,thu_thu,edit)`:

```php
// scan_class_ids: chỉ khối của lớp A, KHÔNG phải null (toàn đoàn)
$ids = scan_class_ids($me);
$this->assertNotNull($ids, 'thu_thu không được mở quét toàn đoàn');
$this->assertNotContains((int)$this->classB['id'], $ids);
// responsible_class_ids: chỉ lớp A, không null
$r = responsible_class_ids($me);
$this->assertNotNull($r);
$this->assertContains((int)$this->classA['id'], $r);
$this->assertNotContains((int)$this->classB['id'], $r);
// nhưng quyền trên rewards vẫn có (edit)
$this->assertSame('edit', permission_of_role('thu_thu','rewards'));
```

- [ ] **Step 2: Chạy test → FAIL** (hiện `scan_class_ids` trả null vì gặp scope toàn đoàn).
- [ ] **Step 3: Vá `scan_class_ids($me)`** — trong vòng lặp `member_scopes`, bỏ qua assignment nào `permission_of_role($a['role_code'],'attendance')` = `none` trước khi xét `toàn đoàn`/`block_id`. Giữ nhánh admin/bdh (role gốc) như cũ.
- [ ] **Step 4: Vá `responsible_class_ids($me)`** — chỉ cộng phạm vi của assignment có quyền ≥`view` trên ít nhất một module miền thiếu nhi (`students` hoặc `attendance`); assignment của vai không có quyền nào ở miền này (vd `thu_thu`) bị bỏ qua.
- [ ] **Step 5: Chạy `RewardsScopeTest` + `ScopeTest` cũ → tất cả PASS** (đảm bảo không phá `test_allowed_class_ids_unions_all_assignments`).
- [ ] **Step 6: Commit.**

---

### Task 3: Engine `recalc_stamps` — tính Mộc & chuỗi

**Files:**
- Create: `public/api/StampService.php`
- Test: `tests/unit/StampEngineTest.php`

**Interfaces:**
- Consumes: bảng `attendances`, `programs` (`count_for_emulation`, `days_of_week`/`day_of_week`, `effective_from`/`effective_to`), `student_stamps`, `stamp_transactions` (Task 1).
- Produces:
  - `recalc_stamps(int $studentId, int $yearId): array` — tính lại từ `attendances` của các buổi `count_for_emulation` trong năm; ghi/UPSERT `student_stamps`; đồng bộ các giao dịch `type IN ('attendance','streak_bonus')` cho khớp (xóa & ghi lại theo `ref_attendance_id`); KHÔNG đụng `spend`/`manual_adjust`/`held_balance`. Trả `['current_streak'=>, 'total_earned'=>, 'balance_earn'=>...]`.
  - `stamp_earn_days(int $studentId, int $yearId): array` — trả map `['Y-m-d' => 'có mặt'|'đi trễ']` (đã gộp theo ngày; đúng giờ ưu tiên hơn trễ nếu cùng ngày nhiều buổi).

- [ ] **Step 1: Viết `tests/unit/StampEngineTest.php`** với các ca (mỗi ca dựng program `count_for_emulation`=1, 7 thứ, `effective_from`; chèn `attendances`, gọi `recalc_stamps`, assert):
  - `test_weekday_earns_1_sunday_earns_2`
  - `test_multiple_sessions_same_day_counts_once`
  - `test_streak_increments_consecutive_days`
  - `test_missing_scheduled_day_resets_streak` (kể cả có đơn nghỉ phép đã duyệt vẫn reset)
  - `test_late_keeps_streak_but_forfeits_milestone` (chuỗi chạm mốc 3 đúng ngày trễ → không có `streak_bonus` +1)
  - `test_milestone_bonus_3_7_30`
  - `test_attendance_before_effective_from_not_counted`
  - `test_recalc_idempotent` (gọi 2 lần → tổng không đổi, không nhân đôi giao dịch)
  - `test_untoggle_refunds` (xóa 1 `attendances` rồi recalc → về đúng trạng thái trước đó)

  Assertion mẫu:
```php
$this->assertSame(2, walletOf($sid)['total_earned']); // 1 ngày thường + ... theo ca
$this->assertSame(0, walletOf($sid)['current_streak']); // sau khi bỏ 1 ngày giữa chuỗi
```

- [ ] **Step 2: Chạy test → FAIL** (`StampService` chưa tồn tại).
- [ ] **Step 3: Cài `recalc_stamps` + `stamp_earn_days`** trong `public/api/StampService.php`. Thuật toán (tests chưa cố định hết nên nêu rõ): (a) lấy tập ngày-có-lịch của các buổi emulation trong `[effective_from, min(today, effective_to)]`; (b) lấy `stamp_earn_days`; (c) duyệt ngày tăng dần: ngày có điểm danh → earn +1/+2 (CN); cập nhật streak (đi lễ nối, vắng reset 0); khi streak chạm 3/7/30 và ngày đó `có mặt` → cộng bonus; (d) UPSERT `student_stamps`, đồng bộ giao dịch earn/bonus theo `ref_attendance_id`.
- [ ] **Step 4: Chạy test → PASS.**
- [ ] **Step 5: Commit.**

---

### Task 4: Móc `recalc_stamps` vào `attendance.php`

**Files:**
- Modify: `public/api/attendance.php` (nhánh `scan` ~dòng 164-193; ghi tay ~245-261; gỡ ~224-232)
- Test: `tests/unit/StampHookTest.php`

**Interfaces:**
- Consumes: `recalc_stamps()` (Task 3).
- Produces: sau mỗi thao tác điểm danh của buổi `count_for_emulation`, ví các em liên quan được cập nhật.

- [ ] **Step 1: Viết `tests/unit/StampHookTest.php`** — gọi luồng ghi (mô phỏng insert vào `attendances` qua cùng service, hoặc gọi trực tiếp helper mới) rồi assert ví đổi; gỡ rồi assert hoàn.
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Thêm gọi `recalc_stamps($sid, $year['id'])`** sau commit ở nhánh `scan` (mỗi `$sid` vừa thêm), sau INSERT tay, và sau DELETE — **chỉ khi** `$prog['count_for_emulation']`. Bọc trong try/catch để lỗi Mộc không làm hỏng điểm danh (log cảnh báo).
- [ ] **Step 4: Chạy test → PASS.**
- [ ] **Step 5: Commit.**

---

### Task 5: Module danh mục quà (`gifts`)

**Files:**
- Create: `public/api/gifts.php` · `views/module_gifts.php` · `public/assets/js/modules/gifts.js`
- Modify: `views/module_menu.php` (đăng ký lối vào), `public/api/data.php` (nếu cần trả danh sách quà)
- Test: `tests/unit/GiftsApiTest.php`

**Interfaces:**
- Produces: `gifts.php?action=save|delete|list` gác `require_permission('gifts','edit')` (list cho `view`). Trường: `name, stamp_cost, stock, image_url, status, sort_order`.

- [ ] **Step 1: Viết `GiftsApiTest.php`** — save tạo quà; cập nhật stock; delete; xác nhận role `glv` bị 403 (không có quyền). *(Test quyền theo mẫu ScopeTest: set `current_member` giả lập hoặc kiểm `permission_of_role`.)*
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài `gifts.php`** (save/delete/list) theo mẫu `programs.php`; validate `stamp_cost>0`, `stock>=0`.
- [ ] **Step 4: Dựng `module_gifts.php` + `gifts.js`** (Alpine: bảng quà, form thêm/sửa, nút ẩn/xóa) theo mẫu `module_programs.php`/`programs.js`; thêm lối vào ở `module_menu.php`.
- [ ] **Step 5: Chạy test → PASS.**
- [ ] **Step 6: Commit.**

---

### Task 6: Hồ sơ thiếu nhi — hiển thị Ví Mộc & Lửa Chuỗi

**Files:**
- Modify: `views/module_student_profile.php` (thêm card) + JS tương ứng
- Modify: `public/api/data.php` hoặc endpoint hồ sơ — trả `stamps` summary
- Test: `tests/unit/StampProfileTest.php`

**Interfaces:**
- Consumes: `student_stamps`, `stamp_transactions` (Task 1), `recalc_stamps` (Task 3).
- Produces: payload `{ current_balance, total_earned, current_streak, longest_streak, recent_transactions[] }` cho một `student_id` trong năm hiện tại, **gác theo `allowed_class_ids`** (đây là dữ liệu hồ sơ, có chia lớp — khác rewards).

- [ ] **Step 1: Viết `StampProfileTest.php`** — dựng ví, gọi hàm/endpoint tổng hợp, assert đúng số dư + đúng N giao dịch gần nhất, đúng thứ tự.
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài hàm tổng hợp** (vd `stamp_summary(int $studentId,int $yearId): array` trong `StampService.php`) + gắn vào payload hồ sơ.
- [ ] **Step 4: Thêm card Ví + 🔥 + lịch sử** vào `module_student_profile.php`.
- [ ] **Step 5: Chạy test → PASS.**
- [ ] **Step 6: Commit.**

---

### Task 7: Đổi quà tại quầy (POS) — API + UI

**Files:**
- Create: `public/api/rewards.php`
- Modify: `views/module_library.php` (màn quét thẻ + POS) + JS Alpine tương ứng
- Test: `tests/unit/RewardsRedeemTest.php`

**Interfaces:**
- Consumes: `gifts`, `student_stamps`, `stamp_transactions` (Task 1); lookup em qua `students.code`.
- Produces: `rewards.php?action=lookup` (mã thẻ → tên + số dư khả dụng), `rewards.php?action=redeem` (`{ studentCode, items:[{giftId,qty}] }`) gác `require_permission('rewards','edit')`, **không chia lớp**.

- [ ] **Step 1: Viết `RewardsRedeemTest.php`**:
```php
public function test_redeem_deducts_balance_stock_and_logs() { /* số dư -tổng, stock -qty, có txn type=spend ref_order_id */ }
public function test_redeem_rejects_when_insufficient_balance() { /* json_fail, không đổi gì */ }
public function test_redeem_rejects_when_out_of_stock() { /* không đổi gì */ }
public function test_redeem_multi_gift_cart() { /* nhiều dòng, tổng đúng */ }
public function test_balance_never_negative() { /* sau đổi, current_balance >= 0 */ }
```
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài `rewards.php` `redeem`** — trong `db()->beginTransaction()`: `SELECT ... FOR UPDATE` ví + từng `gifts`; kiểm `available = current_balance - held_balance >= total` và `stock >= qty`; tạo `gift_orders(status='đã giao', delivered_by=$me, redeem_code_hash='')` + `gift_order_items`; trừ `current_balance`, `stock`; ghi `stamp_transactions(type='spend', ref_order_id, actor_id)`; `commit()`; rollback khi lỗi. `lookup` chỉ trả tên + số dư khả dụng.
- [ ] **Step 4: Dựng UI POS** trong `module_library.php` (Alpine): màn 1 quét/nhập mã (tái dùng `qrscan.js`), màn 2 lưới quà + giỏ nhiều món + nút disable khi `cost>available`, xác nhận, âm thanh thành công, quay lại màn quét.
- [ ] **Step 5: Chạy test → PASS.**
- [ ] **Step 6: Commit.**

---

## Ghi chú phân kỳ (ngoài Phase 1)

- **Phase 2 (plan riêng):** `tracuu.php` tab Sổ Mộc (chỉ đọc, mã thiếu nhi + rate-limit).
- **Phase 3 (plan riêng):** đặt quà online (giỏ + mật mã + giữ `held_balance`/tồn kho), xác nhận đơn đặt trước ở `rewards.php` (`action=confirm` bằng mật mã, override khi quên — có log), vòng đời đơn (hết hạn 7 ngày, tự hủy). Bảng `gift_orders`/`gift_order_items`/`held_balance` đã dựng sẵn ở Phase 1 nên không phải đổi schema.
