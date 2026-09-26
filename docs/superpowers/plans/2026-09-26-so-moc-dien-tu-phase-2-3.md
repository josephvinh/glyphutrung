# Sổ Mộc Điện Tử — Phase 2 & 3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Cổng tra cứu công khai `tracuu.php` (Phase 2: xem Sổ Mộc; Phase 3: đặt đổi quà online) + màn xác nhận đơn đặt trước cho Thủ thư.

**Architecture:** Trang public không đăng nhập theo mẫu `public/bxh.php` (noindex, chỉ đọc, prepared statements, ép kiểu, rate-limit theo IP như `login_throttle`). Đặt quà online tạo `gift_orders(status='chờ lấy')` + `gift_order_items`, **giữ Mộc** (`student_stamps.held_balance += total`) và **giữ tồn** (`gifts.stock -= qty`); hủy/quá hạn nhả lại; Thủ thư xác nhận thì chuyển held→spend (ghi `stamp_transactions type='spend'`, `current_balance -= total`, giữ nguyên tồn đã trừ), `status='đã giao'`. Mọi thao tác tiền/tồn chạy trong transaction có `SELECT ... FOR UPDATE` ví + từng quà, cùng thứ tự khóa như `rewards_redeem` để serialize với recalc.

**Tech Stack:** PHP 8 thuần, MySQL/InnoDB utf8mb4, Alpine.js + Tailwind, PHPUnit (`tests/unit`, DB thật).

**Spec:** `SPEC-MOC-DIEN-TU.md` (§6.3 cổng public, §6.4a xác nhận đơn, §6.5 vòng đời đơn, §6bis cô lập quyền)

## Global Constraints

- Bảng đã có (migration 003 / schema.sql): `gift_orders(id,year_id,student_id,total_cost,redeem_code_hash,status ENUM('chờ lấy','đã giao','đã hủy','quá hạn'),created_at,expires_at,delivered_by,delivered_at)`, `gift_order_items(id,order_id,gift_id,qty,unit_cost,line_cost)`, `student_stamps.held_balance`. KHÔNG đổi schema các bảng này (đủ dùng); nếu thật sự thiếu cột, thêm migration `config/migrations/004_*.sql` (idempotent) + cập nhật `config/schema.sql` và `config/install.php` cho khớp.
- **Khả dụng để tiêu/đặt = `current_balance − held_balance`.** Số dư/available hiển thị KHÔNG âm (kẹp `max(0,…)`), giá trị thật giữ trong CSDL (theo `rewards_lookup`/`stamp_summary` đã có).
- Mật mã đổi quà: chỉ lưu **hash** (`password_hash`/`password_verify`) vào `redeem_code_hash`, không lưu thô.
- Trang public: header `X-Robots-Tag: noindex, nofollow` + `Referrer-Policy: no-referrer` (như bxh.php); KHÔNG require_login; chỉ đọc trừ 2 hành động đặt/hủy đơn (có rate-limit + mật mã). Định danh em qua `students.code`, không lộ dữ liệu ngoài tên + Mộc + đơn của chính em.
- Rate-limit theo mẫu `login_throttle`/`register_throttle` trong `_bootstrap.php` (bảng đếm theo IP + cửa sổ thời gian, trả 429 khi vượt). `client_ip()` đã có.
- Module `rewards` ĐOÀN-WIDE: chỉ `require_permission('rewards',…)`, KHÔNG class-scope. Override "quên mật mã" = ai có `edit` trên `rewards`, có ghi log (`log_action`).
- Đổi tại quầy (`rewards_redeem`, Phase 1) giữ nguyên; Phase 3 chỉ THÊM luồng đơn đặt trước.
- Test: `tests/unit/*Test.php` (PHPUnit, require_once bootstrap, setUp/tearDown dọn). Chạy `php phpunit10.phar tests/unit/<File>.php` (named files; KHÔNG `--testsuite`). Nếu thiếu bảng: `bash .superpowers/sdd/2026-09-26-so-moc-dien-tu-phase-1/db-setup.sh`.
- Nhánh `claude/tinh-nang-dien-tu-7b66gi`. Commit tiếng Việt + 2 dòng trailer footer của phiên. KHÔNG mở PR.

## Review Focus

- **Đặt đơn dưới tải đua:** hai lượt đặt đồng thời (hoặc đặt + đổi tại quầy) không được vượt `available` hay bán quá `stock` — FOR UPDATE ví + quà, kiểm trong tx. (Task P3-1)
- **Giữ đúng, nhả đúng:** đặt giữ Mộc+tồn; hủy/quá hạn nhả Mộc+tồn; giao chuyển held→spend (không nhả tồn). Tổng `held_balance` không bao giờ âm, không "rò" (đặt rồi hủy rồi đặt lại về đúng trạng thái). (Task P3-1)
- **1 đơn chờ/em:** không tạo được đơn thứ hai khi đã có đơn `chờ lấy`. (Task P3-1)
- **Mật mã & override:** xác nhận cần mã thiếu nhi + mật mã đúng; `password_verify`; Thủ thư override bỏ qua mật mã có ghi log; mã thiếu nhi + mật mã sai → từ chối. (Task P3-1, P3-2)
- **Public không rò dữ liệu / không leo thang:** tracuu chỉ trả Mộc + đơn của đúng em theo code; rate-limit chặn dò mã; không có đường ghi nào ngoài đặt/hủy đơn đã throttle. (Task P2-1, P3-2)

---

### Task P2-1: Cổng tra cứu công khai — tab Sổ Mộc (đọc)

**Files:**
- Create: `public/tracuu.php` (trang public), `public/api/_tracuu.php` (logic thuần, test được)
- Migration (nếu cần bảng đếm): `config/migrations/004_tracuu_throttle.sql` + cập nhật `config/schema.sql`, `config/install.php`
- Test: `tests/unit/TracuuTest.php`

**Interfaces:**
- Produces:
  - `tracuu_public_summary(string $code, int $yearId): ?array` (trong `_tracuu.php`) — trả `null` nếu không có em mã đó; ngược lại `{ code, full_name, class_name, current_balance, total_earned, current_streak, longest_streak, recent_transactions[] }` — số dư kẹp `max(0,…)`, chỉ lộ đúng các trường này (tái dùng `stamp_summary` từ StampService, thêm tên+lớp từ enrollments năm hiện tại).
  - `tracuu_throttle(): void` — 429 khi vượt (mẫu `login_throttle`, theo `client_ip()`); `tracuu_attempt_record(bool $ok)`.

- [ ] **Step 1: Viết `tests/unit/TracuuTest.php`** — seed ví cho HS001; `tracuu_public_summary('HS001',1)` trả đúng số + tên + lớp, số âm kẹp về 0, chỉ chứa các khóa cho phép (assert `array_keys`); mã không tồn tại → null; (nếu làm throttle) gọi quá ngưỡng → `tracuu_throttle` ném/`json_fail` 429.
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài `_tracuu.php`** (`tracuu_public_summary` + throttle helpers). Nếu cần bảng đếm, viết migration 004 (idempotent) + đồng bộ schema.sql/install.php.
- [ ] **Step 4: Dựng `public/tracuu.php`** theo mẫu `public/bxh.php`: header noindex, ô nhập mã thiếu nhi (GET/POST), gọi throttle + `tracuu_public_summary`, render card Ví + 🔥 + lịch sử; khung 2 tab (Sổ Mộc | Đổi quà) — tab Đổi quà để Task P3-3 điền. Chỉ đọc.
- [ ] **Step 5: Chạy test → PASS.**
- [ ] **Step 6: Commit.**

---

### Task P3-1: Vòng đời đơn đặt quà (logic transaction, test-first)

**Files:**
- Modify: `public/api/_rewards.php` (thêm các hàm dưới; giữ nguyên `rewards_redeem`/`rewards_lookup`)
- Test: `tests/unit/RewardsOrderTest.php`

**Interfaces:**
- Consumes: `gifts`, `student_stamps`, `gift_orders`, `gift_order_items`, `stamp_transactions`.
- Produces (tất cả nguyên tử, FOR UPDATE ví + từng quà theo `ksort` id; ném `RewardsError` khi từ chối, rollback):
  - `rewards_place_order(int $studentId, int $yearId, array $items, string $plainCode, int $expireDays = 7): array` — chặn nếu đã có đơn `chờ lấy` (1 đơn/em), `items` rỗng/qty≤0, quà `ẩn`/không tồn tại, `stock < qty`, hoặc `available < total`. Thành công: tạo `gift_orders('chờ lấy', redeem_code_hash=password_hash(plainCode), total_cost, expires_at=now+expireDays)` + items; `held_balance += total`; `stock -= qty` mỗi quà. Trả `{ orderId, total, expiresAt }`.
  - `rewards_cancel_order(int $studentId, int $yearId, string $plainCode): array` — tìm đơn `chờ lấy` của em; `password_verify` (sai → RewardsError); nhả `held_balance -= total` + `stock += qty`; `status='đã hủy'`. (Thủ thư hủy hộ: `rewards_cancel_order_staff(int $orderId, int $actorId)` bỏ qua mật mã, ghi log.)
  - `rewards_confirm_order(int $orderId, ?string $plainCode, int $actorId, bool $override = false): array` — đơn phải `chờ lấy`; nếu không override thì `password_verify` (sai → RewardsError); nếu override thì bỏ qua mật mã (caller đã gác quyền + sẽ log). Thành công: `held_balance -= total`, `current_balance -= total`, ghi `stamp_transactions(type='spend', amount=-total, ref_order_id, actor_id)`, `status='đã giao'`, `delivered_by=actorId`, `delivered_at=now`. Tồn KHÔNG đổi (đã trừ khi đặt).
  - `rewards_expire_due(int $yearId, ?string $now = null): int` — mọi đơn `chờ lấy` có `expires_at < now` → `status='quá hạn'`, nhả `held_balance` + `stock`; trả số đơn đã xử lý. (Gọi lazy trước khi đọc/đặt.)
  - `rewards_pending_order(int $studentId, int $yearId): ?array` — đơn `chờ lấy` hiện tại (kèm items + expiresAt), hoặc null.

- [ ] **Step 1: Viết `tests/unit/RewardsOrderTest.php`** với các ca (mỗi ca seed ví/quà, assert số dư/held/tồn/đơn):
  - `test_place_holds_stamps_and_stock` (held += total, stock -= qty, đơn+items tạo, current_balance KHÔNG đổi)
  - `test_place_rejects_second_pending_order`
  - `test_place_rejects_insufficient_available` / `test_place_rejects_out_of_stock` / `test_place_rejects_hidden_or_missing_gift` / `test_place_rejects_empty_or_nonpositive_qty` (không đổi gì)
  - `test_cancel_releases_hold_and_stock` (+ `password_verify` sai → từ chối, không đổi)
  - `test_confirm_moves_held_to_spend` (held -= total, current_balance -= total, 1 spend tx ref_order_id+actor, tồn giữ nguyên, status 'đã giao')
  - `test_confirm_override_skips_password` / `test_confirm_wrong_password_rejected`
  - `test_expire_releases_hold_and_stock` (đơn quá `expires_at` → 'quá hạn', nhả held+stock)
  - `test_hold_never_leaks` (đặt→hủy→đặt lại: held/stock về đúng)
  - `test_available_never_negative_after_place`
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài các hàm trong `_rewards.php`** (dùng lại kiểu transaction/khóa của `rewards_redeem`). Chú thích rõ recalc không đụng `held_balance`/`spend` nên vòng đời đơn an toàn với recalc.
- [ ] **Step 4: Chạy test → PASS.** Re-run `RewardsRedeemTest.php` (không hồi quy).
- [ ] **Step 5: Commit.**

---

### Task P3-2: API đặt/hủy đơn (public) + xác nhận đơn (Thủ thư)

**Files:**
- Modify: `public/api/rewards.php` (thêm actions), có thể thêm `public/api/tracuu.php` cho luồng public nếu tách sạch hơn (tùy, giữ nhất quán)
- Test: `tests/unit/RewardsOrderApiTest.php`

**Interfaces:**
- Consumes: các hàm Task P3-1; throttle Task P2-1.
- Produces:
  - Public (không login, throttle theo IP, định danh bằng `students.code`): `place` (`{code, items, password}` → `rewards_place_order`), `cancel` (`{code, password}` → `rewards_cancel_order`), `pending` (`{code}` → `rewards_pending_order`, chỉ đọc). Các action này sống ở endpoint public (không require_login); validate + throttle chặt.
  - Thủ thư (đã login): `confirm` trong `rewards.php` — `require_write()` + `require_permission('rewards','edit')`; nhận `{orderId hoặc studentCode, password?, override?}`; gọi `rewards_confirm_order(...)`; nếu `override` → chỉ cho khi có quyền edit, và `log_action('doi-qua','rewards','Giao đơn (override mật mã)',...)`. Cũng expose `staff_pending` (tra đơn theo code cho Thủ thư).

- [ ] **Step 1: Viết `tests/unit/RewardsOrderApiTest.php`** — mức logic: xác nhận gác quyền (`permission_of_role('rewards','thu_thu')==='edit'`, `'glv'==='none'`), và các nhánh gọi hàm P3-1 đúng (đặt/hủy/confirm/override). Vì endpoint request-scoped, test qua các hàm P3-1 + kiểm quyết định gác (không HTTP).
- [ ] **Step 2: Chạy test → FAIL.**
- [ ] **Step 3: Cài các action.** Public place/cancel/pending: throttle + validate; không rò lỗi chi tiết giúp dò mã. Staff confirm/override: gác quyền + log.
- [ ] **Step 4: Chạy test → PASS.**
- [ ] **Step 5: Commit.**

---

### Task P3-3: `tracuu.php` — tab Đổi quà (đặt online) + hiển thị đơn ở tab Sổ Mộc

**Files:**
- Modify: `public/tracuu.php` + JS kèm theo (Alpine)
- Test: (thủ công/UI — không bắt buộc unit; logic đã phủ ở P3-1/P3-2)

**Interfaces:** Consumes API Task P3-2 + `gifts` list (chỉ `còn bán`).

- [ ] **Step 1:** Tab Đổi quà: lưới quà + giỏ nhiều món + số lượng; nút mờ khi `tổng > available`. Đặt đơn: nhập mật mã đổi quà (2 lần xác nhận) → gọi `place` → báo thành công + hiện mã đơn.
- [ ] **Step 2:** Tab Sổ Mộc: nếu có đơn `chờ lấy` → hiện **khu vực thông báo mã đổi quà** (mã đơn, danh sách quà, hạn lấy) + nút Hủy (nhập mật mã → `cancel`).
- [ ] **Step 3:** Kiểm thủ công luồng đặt→hiện→hủy trên trang; xác nhận rate-limit chặn dò. Commit.

---

### Task P3-4: Màn Thủ thư — xác nhận đơn đặt trước (trong module rewards)

**Files:**
- Modify: `views/module_rewards.php` + `public/assets/js/modules/rewards.js`
- Test: (logic đã phủ ở P3-1/P3-2; đây là UI)

**Interfaces:** Consumes API `confirm`/`staff_pending` (Task P3-2).

- [ ] **Step 1:** Thêm luồng "Xác nhận đơn đặt trước" song song với "Đổi tại quầy" đã có: quét thẻ / nhập mã thiếu nhi → hiện đơn `chờ lấy` (quà + tổng Mộc) → nhập **mật mã đổi quà** → xác nhận giao (`confirm`).
- [ ] **Step 2:** Nút **"Em quên mật mã — giao bằng quyền Thủ thư"** → hộp xác nhận → `confirm` với `override=true` (server ghi log). Sau khi giao: phát âm thanh, làm mới số dư, quay lại màn quét.
- [ ] **Step 3:** Kiểm thủ công; commit.

---

## Self-review notes
- Spec coverage: §6.3 → P2-1 + P3-3; §6.4a (xác nhận đơn, override) → P3-1/P3-2/P3-4; §6.5 (giữ Mộc/tồn, hết hạn 7 ngày, 1 đơn/em, tự hủy) → P3-1; §6bis (đoàn-wide, override có quyền+log) → P3-2/P3-4.
- Money/concurrency đóng ở P3-1 (transaction + FOR UPDATE + tests), API/UI chỉ gọi lại.
