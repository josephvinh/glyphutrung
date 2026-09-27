# Task 4 Report — Móc `recalc_stamps` vào `attendance.php`

## Fix Round 1 (review feedback)

**Issue (Important):** the error-isolation safety wrapper had zero automated
coverage — `moc_recalc_an_toan()` lived inside `attendance.php` (request-scoped
endpoint) and could not be unit-tested without running the endpoint, so the
hard requirement "a recalc failure must NOT break attendance" was only
verified by inspection.

**Fix:**
1. Moved the wrapper from `attendance.php` into `public/api/StampService.php`
   as a first-class, testable function:
   ```php
   function recalc_stamps_safe(int $studentId, int $yearId, ?callable $fn = null): bool
   {
       $fn ??= 'recalc_stamps';
       try {
           $fn($studentId, $yearId);
           return true;
       } catch (Throwable $e) {
           TNTT\Logger::getInstance()->warning('Sổ Mộc: recalc_stamps lỗi', [
               'student_id' => $studentId,
               'year_id'    => $yearId,
               'error'      => $e->getMessage(),
           ]);
           return false;
       }
   }
   ```
   The optional `$fn` lets tests inject a throwing callable without touching
   the real DB-backed `recalc_stamps()`. Defaults to calling `recalc_stamps`.
   Same logging call as before (unchanged behavior/log format). Returns
   `bool`, never rethrows.
2. `attendance.php`: removed the local `moc_recalc_an_toan()` function
   entirely; the three existing call sites (scan-loop after commit, manual
   DELETE/untoggle, manual INSERT) now call `recalc_stamps_safe($id, $year['id'])`
   directly, still gated by `program_earns_stamps($prog)` and at the exact
   same placement as before (no change to the per-student scan-loop
   behavior, no change to when the gate/commit happens).
3. `tests/unit/StampHookTest.php` — added:
   - `test_recalc_stamps_safe_catches_error_and_returns_false` — injects a
     callable that throws `RuntimeException`, asserts `recalc_stamps_safe()`
     returns `false` and (implicitly, since PHPUnit would report an error
     otherwise) that the exception never propagates out of the function.
     This is the direct proof of the "recalc failure must not break
     attendance" contract.
   - `test_recalc_stamps_safe_success_path_credits_wallet` — default
     callable (real `recalc_stamps`) on a real emulation program/attendance
     row: asserts return value `true` and the wallet is credited (+1).
   - Updated `test_insert_then_recalc_credits_wallet_delete_then_recalc_refunds`
     to call `recalc_stamps_safe()` instead of `recalc_stamps()` directly
     (matches what `attendance.php` now calls) and additionally asserts the
     `true` return value on both the credit and refund legs.
   - Minor cleanup per review: removed the vacuous final assertion in
     `test_non_emulation_program_hook_is_skipped()` (nothing invokes the
     engine in that test, so asserting the wallet stayed empty proved
     nothing); kept the real assertion (`program_earns_stamps($prog) ===
     false`) and added a comment explaining why that's the actual coverage.

### RED (new wrapper tests, before the refactor)
Verified by temporarily stashing the `StampService.php`/`attendance.php`
changes (keeping the new test file) and running:
```
$ git stash push -- public/api/StampService.php public/api/attendance.php
$ php phpunit10.phar tests/unit/StampHookTest.php
..EEE.                                                              6 / 6 (100%)
There were 3 errors:
1) StampHookTest::test_insert_then_recalc_credits_wallet_delete_then_recalc_refunds
Error: Call to undefined function recalc_stamps_safe()
2) StampHookTest::test_recalc_stamps_safe_catches_error_and_returns_false
Error: Call to undefined function recalc_stamps_safe()
3) StampHookTest::test_recalc_stamps_safe_success_path_credits_wallet
Error: Call to undefined function recalc_stamps_safe()
Tests: 6, Assertions: 7, Errors: 3.
$ git stash pop
```
(The other 3 pre-existing tests in the file still passed since they don't
touch `recalc_stamps_safe()`.)

### GREEN (after restoring the refactor)
```
$ php -l public/api/attendance.php
No syntax errors detected in public/api/attendance.php
$ php -l public/api/StampService.php
No syntax errors detected in public/api/StampService.php
$ php phpunit10.phar tests/unit/StampHookTest.php
......                                                              6 / 6 (100%)
Time: 00:00.036, Memory: 22.99 MB
OK (6 tests, 17 assertions)
```

### Regression — StampEngineTest.php
```
$ php phpunit10.phar tests/unit/StampEngineTest.php
..........                                                        10 / 10 (100%)
Time: 00:00.143, Memory: 22.99 MB
OK (10 tests, 48 assertions)
```

### Files touched in this round
- `public/api/StampService.php` — added `recalc_stamps_safe()`.
- `public/api/attendance.php` — removed local wrapper, call sites now call
  `recalc_stamps_safe()` directly (same 3 locations/gating, unchanged
  per-student scan-loop behavior).
- `tests/unit/StampHookTest.php` — 2 new tests + 1 updated test + 1 minor
  cleanup (now 6 tests / 17 assertions total, up from 4 / 12).

No other files changed; the per-student scan-loop performance trade-off
was left untouched per instructions (out of scope for this round).

---


## Tóm tắt
Đã móc Engine Sổ Mộc (`recalc_stamps`) vào cả 3 đường ghi điểm danh trong
`public/api/attendance.php`, chỉ khi chương trình `count_for_emulation`
(qua helper mới `program_earns_stamps()`), và bọc an toàn để lỗi Engine
không làm hỏng việc điểm danh.

## Files Changed
- `public/api/StampService.php` — thêm helper `program_earns_stamps(array $prog): bool`.
- `public/api/attendance.php` — require StampService, thêm helper nội bộ
  `moc_recalc_an_toan()`, gọi nó ở 3 điểm ghi.
- `tests/unit/StampHookTest.php` — test mới (TDD).

## Implementation Details

### 1. Helper `program_earns_stamps()` (StampService.php)
```php
function program_earns_stamps(array $prog): bool
{
    return !empty($prog['count_for_emulation']);
}
```
Tách riêng để attendance.php và test dùng chung một nơi quyết định "buổi
này có tính Mộc không", tránh lệch điều kiện giữa các nhánh gọi.

### 2. Helper `moc_recalc_an_toan()` (attendance.php, sau require StampService)
```php
function moc_recalc_an_toan(int $studentId, int $yearId): void
{
    try {
        recalc_stamps($studentId, $yearId);
    } catch (Throwable $e) {
        TNTT\Logger::getInstance()->warning('Sổ Mộc: recalc_stamps lỗi', [
            'student_id' => $studentId,
            'year_id'    => $yearId,
            'error'      => $e->getMessage(),
        ]);
    }
}
```
Dùng `TNTT\Logger` (đã bootstrap sẵn trong `_bootstrap.php`, cùng lớp mà
`ExceptionHandler` dùng) thay vì `log_action()` — vì `log_action()` ghi
vào `activity_logs` (nhật ký nghiệp vụ hiển thị cho người dùng/admin xem,
kiểu "Ghi điểm danh cho X"), còn đây là lỗi kỹ thuật nội bộ của Engine,
hợp với log file (`warning` level) hơn. Attendance vẫn trả về thành công
bình thường dù recalc lỗi — không throw, không json_fail.

### 3. Ba điểm gọi

**a) Nhánh `scan` (batch QR)** — sau khi transaction INSERT IGNORE commit
thành công (dòng ~207-214, ngay sau `$daCo = count($hopLe) - $them;`,
vẫn trong khối `if ($hopLe) { ... }`):
```php
if (program_earns_stamps($prog)) {
    foreach ($hopLe as $sid) {
        moc_recalc_an_toan($sid, $year['id']);
    }
}
```
Dùng `$hopLe` (toàn bộ id hợp lệ đã thử ghi, kể cả những id đã tồn tại từ
trước bị INSERT IGNORE bỏ qua) theo đúng yêu cầu ở task brief. Vì
`recalc_stamps` là idempotent, gọi lại cho các id "đã có" (`$daCo`) không
gây sai lệch — chỉ hơi thừa một lượt tính cho các em không đổi gì.

**b) Nhánh gỡ tay (untoggle/DELETE)** — ngay sau `db_run('DELETE ...')`
và trước `Cache::flush()`:
```php
if (program_earns_stamps($prog)) {
    moc_recalc_an_toan($studentId, $year['id']);
}
```
Đây là chỗ HOÀN Mộc khi gỡ điểm danh.

**c) Nhánh ghi tay mới (INSERT)** — ngay sau `db_run('INSERT ...')`, trước
`Cache::flush()`:
```php
if (program_earns_stamps($prog)) {
    moc_recalc_an_toan($studentId, $year['id']);
}
```

Cả 3 chỗ đều gọi SAU KHI câu lệnh ghi/xoá đã thành công trong CSDL (sau
commit ở nhánh scan; INSERT/DELETE tay vốn không nằm trong transaction rõ
ràng, gọi ngay sau khi PDO thực thi xong). `recalc_stamps()` tự mở
transaction riêng của nó (guard `db()->inTransaction()` từ Task 3) nên
không xung đột.

## TDD

### RED
```
$ php phpunit10.phar tests/unit/StampHookTest.php
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
EEEE                                                                4 / 4 (100%)
There were 4 errors:
1) StampHookTest::test_program_earns_stamps_true_when_count_for_emulation_1
Error: Call to undefined function program_earns_stamps()
... (tương tự cho 3 test còn lại)
ERRORS!
Tests: 4, Assertions: 0, Errors: 4.
```
(Viết test trước khi thêm `program_earns_stamps()` vào StampService.php —
xác nhận test thật sự kiểm tra code chưa tồn tại.)

### GREEN (sau khi thêm helper + wiring)
```
$ php phpunit10.phar tests/unit/StampHookTest.php
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
....                                                                4 / 4 (100%)
Time: 00:00.024, Memory: 22.99 MB
OK (4 tests, 12 assertions)
```

### Hồi quy — StampEngineTest.php vẫn xanh
```
$ php phpunit10.phar tests/unit/StampEngineTest.php
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
..........                                                        10 / 10 (100%)
Time: 00:00.159, Memory: 22.99 MB
OK (10 tests, 48 assertions)
```

### Cú pháp
```
$ php -l public/api/attendance.php
No syntax errors detected in public/api/attendance.php
$ php -l public/api/StampService.php
No syntax errors detected in public/api/StampService.php
```

## Ghi chú về `tests/unit/StampHookTest.php`
Vì `attendance.php` là endpoint request-scoped (đọc `php://input`, cần
auth qua `require_write()`/`require_permission()`), test KHÔNG gọi HTTP.
Thay vào đó test hợp đồng tích hợp ở tầng CSDL — mô phỏng đúng thao tác mà
attendance.php thực hiện (INSERT/DELETE vào `attendances` rồi gọi
`recalc_stamps`) và assert ví đổi/hoàn đúng:
- `test_program_earns_stamps_true_when_count_for_emulation_1` /
  `..._false_when_0_or_absent` — unit test riêng cho luật bật/tắt.
- `test_insert_then_recalc_credits_wallet_delete_then_recalc_refunds` —
  ghi điểm danh buổi emulation → recalc → ví +1; gỡ → recalc → ví về 0.
- `test_non_emulation_program_hook_is_skipped` — chương trình
  `count_for_emulation=0`: khẳng định `program_earns_stamps()` trả về
  false (tức "móc" thật trong attendance.php sẽ không gọi recalc) và ví
  vẫn trống sau khi ghi điểm danh trực tiếp vào bảng (không qua recalc).

## Self-Review
- Không có debug code (`var_dump`, `error_log` thô) — dùng đúng
  `TNTT\Logger` đã có sẵn trong bootstrap.
- Type safety: `program_earns_stamps(array $prog): bool`,
  `moc_recalc_an_toan(int $studentId, int $yearId): void`.
- Không đổi hành vi/response hiện có của endpoint (response JSON giữ
  nguyên ở cả 3 nhánh); chỉ thêm side-effect (cập nhật ví Mộc) sau khi ghi
  thành công.
- Giữ nguyên comment tiếng Việt, phong cách hiện có (`===`, biến tiếng
  Việt như `$hopLe`, `$prog`).
- Đã cân nhắc dùng `log_action()` như brief gợi ý nhưng chọn
  `TNTT\Logger` vì đúng ngữ nghĩa hơn (lỗi kỹ thuật, không phải nhật ký
  nghiệp vụ) — nêu rõ lý do ở trên để review dễ phản biện nếu muốn đổi lại.

## Concerns
- Nhánh scan gọi `recalc_stamps()` tuần tự cho từng `$sid` trong `$hopLe`
  (có thể tới 200 em/lô, tối đa nhiều lô). Mỗi lần recalc là vài truy vấn
  + 1 transaction riêng — với lô lớn (nhiều trăm em) có thể chậm hơn so
  với ghi điểm danh gốc (vốn là 1 câu INSERT hàng loạt). Đây là đánh đổi
  được nêu rõ trong brief ("recalc mỗi `$sid` vừa thêm"); nếu sau này cần
  tối ưu, có thể cân nhắc batch-recalc hoặc queue nền, nhưng nằm ngoài
  phạm vi Task 4.
- `program_earns_stamps()` không kiểm tra `$prog` có tồn tại/đúng cấu
  trúc — nó tin tưởng caller (attendance.php) đã tải đúng `$prog` từ
  `programs`. Đây là giả định hợp lý vì `$prog` luôn được load và kiểm
  tra tồn tại (`json_fail` 404) trước khi tới bất kỳ nhánh ghi nào.
- Không sửa `_bootstrap.php` để autoload `StampService.php` chung — chỉ
  `require_once` tại `attendance.php` như brief yêu cầu ("check whether
  _bootstrap.php already autoloads it; if not, require_once it"). Các
  endpoint khác cần Engine (nếu có, ví dụ reward/redeem sau này) sẽ cần
  tự require tương tự, hoặc một task sau có thể dọn lên bootstrap chung.
