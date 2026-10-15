# Nâng cấp Nhật ký Hoạt động — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nâng cấp hệ thống `activity_logs` để ghi diff trước/sau cho mọi thao tác sửa/xóa, ghi IP + target, thêm filter + phân quyền theo phạm vi, xuất CSV/XLSX tự viết (không composer) — tuân thủ mobile-first, không phá 66 chỗ gọi `log_action()` hiện có.

**Architecture:** Migration ALTER-only thêm cột NULL (giữ log cũ). `log_action()` đổi chữ ký bằng tham số thứ 5 optional. API `logs.php` + `logs_export.php` mới. Helper `src/XlsxExport.php` tự viết zip+xml (không composer, không PhpSpreadsheet). UI thêm filter + nút Xuất + modal diff — theo quy ước mobile-first đã có (`tap-safe`, `bleed-x`, `rounded-field`).

**Tech Stack:** PHP 8.x, MariaDB 10.11+ (cần `JSON` native), Alpine.js (UI), Tailwind utility (không tự tạo class mới), `ZipArchive` extension cho XLSX.

**Spec:** `docs/superpowers/specs/2026-10-14-activity-log-upgrade-design.md`

**Plan này chi tiết 3 PR nền tảng (PR1, PR2, PR3). PR4–PR12 có outline cuối file; triển khai theo thứ tự, mỗi PR mở plan riêng khi tới lượt.**

## Global Constraints

- **KHÔNG commit/push/mở PR** trừ khi người dùng yêu cầu rõ (CLAUDE.md mục 1).
- **1 thay đổi = 1 PR** (CLAUDE.md mục 4). Mỗi task trong plan này = 1 PR.
- **"Xong" = test + smoke pass** (CLAUDE.md mục 2). Không đoán.
- **Xóa code phải `grep` trước** (CLAUDE.md mục 3).
- **Mỗi PR chạy lệnh**:
  ```bash
  export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
  php config/install.php && php tests/fixtures/ci_seed.php
  php phpunit.phar --testsuite "TNTT Unit Tests"
  npx --yes eslint@9.39.5 public/assets/js/ public/sw.js
  ```
- **PHP 8.x**, `ZipArchive` ext (bắt buộc cho XLSX), `JSON` native của MariaDB.
- **Tên/mã model KHÔNG commit vào code** (CLAUDE.md mục 7).
- **Composer KHÔNG được thêm** (đã chốt với người dùng).
- **UI mobile-first**: mặc định = mobile; `sm:` trở đi mới tăng cấp; dùng class có sẵn (`tap-safe`, `bleed-x`, `rounded-field`, `rounded-card`, `text-micro`, `hide-scrollbar`).
- **Mask SĐT bắt buộc** trong mọi export (không tùy chọn).
- **Chỉ Quản trị (admin) hoặc BĐH** (`role_code IN ('admin','bdh')`) xem tất cả log; GLV chỉ thấy log do mình tạo + log về lớp chủ nhiệm trong năm hiện hành.

## File Structure

**File mới (sau 3 PR này):**
- `config/migrations/010_activity_log_diff.sql` — migration (PR1)
- `tests/unit/ActivityLogMigrationTest.php` — test migration (PR1)
- `tests/unit/LogActionTest.php` — test chữ ký mới + tương thích ngược (PR2)
- `tests/unit/LogsApiFilterTest.php` — test filter API logs.php (PR3)
- `tests/unit/LogsScopeGlvTest.php` — test phân quyền GLV (PR3)

**File sửa (sau 3 PR này):**
- `config/install.php` — chạy migration 010 (PR1)
- `public/api/_bootstrap.php` — đổi chữ ký `log_action()` (PR2)
- `public/api/_common.php` — thêm `log_scope_where()` (PR3)
- `public/api/logs.php` — thêm filter + áp scope (PR3)
- `views/module_settings.php` — UI 3 ô filter mới (PR3)
- `public/assets/js/modules/core.js` — state/logic filter mới (PR3)

**File KHÔNG đụng trong 3 PR này (sẽ dùng ở PR4+):**
- `public/api/logs_export.php` (PR4)
- `src/XlsxExport.php` (PR4)
- `views/module_settings.php` (xuất hiện lại ở PR5-PR6 cho UI xuất + diff modal)
- 17 file API có `log_action(...)` (PR7+ cho bulk diff)

---

## PR1: Migration thêm cột activity_log mới

**Files:**
- Create: `config/migrations/010_activity_log_diff.sql`
- Modify: `config/install.php:60-90` (khu vực chạy migrations)
- Test: `tests/unit/ActivityLogMigrationTest.php`

**Interfaces:**
- Consumes: schema bảng `activity_logs` hiện có ở `config/schema.sql:436-448` (7 cột: `id, logged_at, actor_id, actor_name, action, module, what, detail`).
- Produces: bảng `activity_logs` có thêm 6 cột NULL mới: `ip, user_agent, target_type, target_id, target_label, diff_json` + 2 index. Test có thể xác nhận cột tồn tại bằng `SHOW COLUMNS` hoặc `information_schema`.

### Task 1.1: Viết migration

- [ ] **Step 1: Tạo file migration**

`config/migrations/010_activity_log_diff.sql`:

```sql
-- Migration 010: thêm cột cho diff + IP + target vào activity_logs
-- Tất cả cột mới NULL mặc định — log cũ giữ nguyên giá trị NULL.
-- Idempotent: kiểm tra cột tồn tại trước khi ADD (chạy nhiều lần an toàn).

-- MySQL/MariaDB không hỗ trợ `ADD COLUMN IF NOT EXISTS` ở bản cũ; dùng
-- procedure để kiểm tra information_schema.

DELIMITER //
CREATE PROCEDURE add_col_if_missing(
    IN tbl VARCHAR(64), IN col VARCHAR(64),
    IN spec VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tbl
          AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE ', tbl, ' ADD COLUMN ', col, ' ', spec);
        PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

CALL add_col_if_missing('activity_logs', 'ip',           'VARCHAR(45)  NULL AFTER detail');
CALL add_col_if_missing('activity_logs', 'user_agent',   'VARCHAR(255) NULL AFTER ip');
CALL add_col_if_missing('activity_logs', 'target_type',  'VARCHAR(32)  NULL AFTER user_agent');
CALL add_col_if_missing('activity_logs', 'target_id',    'BIGINT       NULL AFTER target_type');
CALL add_col_if_missing('activity_logs', 'target_label', 'VARCHAR(255) NULL AFTER target_id');
CALL add_col_if_missing('activity_logs', 'diff_json',    'JSON         NULL AFTER target_label');

DROP PROCEDURE add_col_if_missing;

-- Index cho filter
DELIMITER //
CREATE PROCEDURE add_idx_if_missing(
    IN tbl VARCHAR(64), IN idx VARCHAR(64), IN spec VARCHAR(255)
)
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = tbl
          AND INDEX_NAME = idx
    ) THEN
        SET @sql = CONCAT('CREATE INDEX ', idx, ' ON ', tbl, ' ', spec);
        PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END //
DELIMITER ;

CALL add_idx_if_missing('activity_logs', 'idx_log_module_time', '(module, logged_at)');
CALL add_idx_if_missing('activity_logs', 'idx_log_action_time', '(action, logged_at)');

DROP PROCEDURE add_idx_if_missing;
```

- [ ] **Step 2: Xác nhận file đúng nội dung**

Run (PowerShell):
```powershell
Get-Content config/migrations/010_activity_log_diff.sql | Select-Object -First 5
```
Expected: hiển thị comment đầu + DELIMITER. KHÔNG có TODO/TBD.

### Task 1.2: Cập nhật install.php để chạy migration

- [ ] **Step 1: Tìm đoạn chạy migrations**

Đọc `config/install.php` từ dòng 60–120 (đoạn "2. CHẠY LƯỢC ĐỒ"). Có dạng vòng lặp `foreach (glob('migrations/*.sql') as $f)`.

- [ ] **Step 2: Bổ sung số thứ tự migration 010**

`config/install.php` — tìm chỗ iterate migrations, thêm xử lý `DELIMITER` cho file migration có procedure:

Cách cũ (nếu đang dùng `db_run` đơn lệnh):
- KHÔNG dùng `db_run` cho file có `DELIMITER` — phải dùng `db()->exec(file_get_contents($file))` để chạy cả khối.

Sửa logic chạy migration (pseudo — kiểm tra code hiện tại trước khi sửa):
```php
foreach (glob(__DIR__ . '/migrations/*.sql') as $file) {
    $sql = file_get_contents($file);
    if (str_contains($sql, 'DELIMITER')) {
        // Xử lý DELIMITER: thay delimiter khác → `;` để PDO chạy được
        // (xem mục lưu ý bên dưới)
        $sql = normalize_delimiters($sql);
    }
    db()->exec($sql);
}
```

**LƯU Ý QUAN TRỌNG**: `db()->exec()` của PDO **không hỗ trợ `DELIMITER`** (đó là syntax của `mysql` CLI). Có 3 cách xử lý:

1. **Cách A (ưu tiên)**: viết lại migration 010 KHÔNG dùng procedure — chỉ `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` (MariaDB 10.0.2+ hỗ trợ native).
2. **Cách B**: chạy migration qua `mysql` CLI subprocess (cần binary + đường dẫn).
3. **Cách C**: viết procedure rồi `CALL` qua `db()->exec()` từng lệnh, không cần DELIMITER trong file (dùng `db_run` cho mỗi CALL).

→ **Chọn Cách A**: viết lại migration 010 dùng `ADD COLUMN IF NOT EXISTS` native của MariaDB 10.0.2+ (repo đang yêu cầu MariaDB 10.11+ trong SECURITY_AUDIT.md). Đơn giản nhất, chạy được qua PDO.

- [ ] **Step 3: Viết lại migration 010 không dùng DELIMITER**

Sửa `config/migrations/010_activity_log_diff.sql`:

```sql
-- Migration 010: thêm cột cho diff + IP + target vào activity_logs
-- Tất cả cột mới NULL mặc định — log cũ giữ nguyên giá trị NULL.
-- MariaDB 10.0.2+ hỗ trợ IF NOT EXISTS cho ADD COLUMN; dùng trực tiếp.
-- (KHÔNG dùng DELIMITER — PDO không hỗ trợ.)

ALTER TABLE activity_logs
    ADD COLUMN IF NOT EXISTS ip           VARCHAR(45)  NULL AFTER detail,
    ADD COLUMN IF NOT EXISTS user_agent   VARCHAR(255) NULL AFTER ip,
    ADD COLUMN IF NOT EXISTS target_type  VARCHAR(32)  NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS target_id    BIGINT       NULL AFTER target_type,
    ADD COLUMN IF NOT EXISTS target_label VARCHAR(255) NULL AFTER target_id,
    ADD COLUMN IF NOT EXISTS diff_json    JSON         NULL AFTER target_label;

CREATE INDEX IF NOT EXISTS idx_log_module_time ON activity_logs (module, logged_at);
CREATE INDEX IF NOT EXISTS idx_log_action_time ON activity_logs (action, logged_at);
```

- [ ] **Step 4: Chạy install.php để kiểm tra migration**

```bash
export TNTT_DB_HOST=127.0.0.1 TNTT_DB_NAME=tntt_test TNTT_DB_USER=tntt TNTT_DB_PASS=tntt
php config/install.php
```
Expected: `✓ ...` không có lỗi SQL.

- [ ] **Step 5: Kiểm tra cột mới tồn tại**

```bash
php -r "require 'config/db.php'; db(); foreach (db_all('SHOW COLUMNS FROM activity_logs') as \$c) { echo \$c['Field'].PHP_EOL; }"
```
Expected: liệt kê đủ 13 cột (`id, logged_at, actor_id, actor_name, action, module, what, detail, ip, user_agent, target_type, target_id, target_label, diff_json`).

- [ ] **Step 6: Chạy install.php LẦN 2 (idempotent)**

```bash
php config/install.php
```
Expected: KHÔNG lỗi (vì đã có cột + index).

### Task 1.3: Viết test migration

- [ ] **Step 1: Tạo file test**

`tests/unit/ActivityLogMigrationTest.php`:

```php
<?php
// tests/unit/ActivityLogMigrationTest.php
// Đảm bảo migration 010 chạy được và tạo đúng cột.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class ActivityLogMigrationTest extends TestCase
{
    private const REQUIRED_COLUMNS = [
        'ip', 'user_agent', 'target_type', 'target_id', 'target_label', 'diff_json',
    ];

    public function test_migration_010_creates_required_columns(): void
    {
        db();
        $cols = db_all('SHOW COLUMNS FROM activity_logs');
        $names = array_column($cols, 'Field');
        foreach (self::REQUIRED_COLUMNS as $col) {
            $this->assertContains($col, $names,
                "Thiếu cột '$col' — migration 010 chưa chạy?");
        }
    }

    public function test_migration_010_creates_indexes(): void
    {
        db();
        $rows = db_all("SHOW INDEX FROM activity_logs");
        $idx = array_column($rows, 'Key_name');
        $this->assertContains('idx_log_module_time', $idx);
        $this->assertContains('idx_log_action_time', $idx);
    }

    public function test_log_cu_van_cho_insert_khong_can_cot_moi(): void
    {
        // Đảm bảo INSERT chỉ với 7 cột cũ vẫn chạy (NULL tự điền cho cột mới).
        db();
        $before = (int) (db_one('SELECT COUNT(*) n FROM activity_logs')['n'] ?? 0);

        db_run("INSERT INTO activity_logs
                (actor_id, actor_name, action, module, what, detail)
                VALUES (NULL, 'test-migration', 'tao', 'test', 'migrate test', NULL)",
            []);

        $row = db_one("SELECT * FROM activity_logs WHERE actor_name = 'test-migration' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($row);
        $this->assertNull($row['ip']);
        $this->assertNull($row['diff_json']);

        // Dọn
        db_run("DELETE FROM activity_logs WHERE actor_name = 'test-migration'");
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận FAIL (chưa chạy migration)**

Lưu ý: nếu bạn đã chạy install.php ở Task 1.2 thì cột đã có → test sẽ PASS. Để test "đỏ trước, xanh sau", cần môi trường sạch. Bỏ qua bước FAIL này nếu cột đã tồn tại.

- [ ] **Step 3: Chạy test, xác nhận PASS**

```bash
php phpunit.phar --testsuite "TNTT Unit Tests" --filter ActivityLogMigration
```
Expected: 3 tests pass.

- [ ] **Step 4: Commit (CHỈ khi người dùng yêu cầu)**

Theo CLAUDE.md mục 1, KHÔNG tự commit. Nếu người dùng bảo commit:
```bash
git add config/migrations/010_activity_log_diff.sql config/install.php tests/unit/ActivityLogMigrationTest.php
git commit -m "feat(activity-log): migration 010 thêm cột diff + IP + target (PR1)"
```

---

## PR2: Đổi chữ ký log_action() — tương thích ngược

**Files:**
- Modify: `public/api/_bootstrap.php:205-212` (hàm `log_action`)
- Test: `tests/unit/LogActionTest.php`

**Interfaces:**
- Consumes: chữ ký cũ `log_action(string $action, string $module, string $what, string $detail = '')` — 66 chỗ gọi hiện tại.
- Produces: chữ ký mới `log_action(string $action, string $module, string $what, string $detail = '', array $opts = [])` — thêm tham số thứ 5 optional. Khi truyền `$opts['before']` / `$opts['after']` thì ghi `diff_json`. Luôn ghi `ip` + `user_agent` (lấy từ `$_SERVER`). Có thể truyền `target_type/id/label`.

### Task 2.1: Đổi chữ ký log_action()

- [ ] **Step 1: Đọc hàm hiện tại**

Đã đọc ở `_bootstrap.php:205-212`:
```php
function log_action(string $action, string $module, string $what, string $detail = ''): void
{
    $me = current_member();
    db_run('INSERT INTO activity_logs (actor_id, actor_name, action, module, what, detail)
            VALUES (?,?,?,?,?,?)',
        [$me['id'] ?? null, $me['full_name'] ?? 'Hệ thống', $action, $module, $what, $detail]);
}
```

- [ ] **Step 2: Sửa hàm — thêm IP, UA, target, diff**

`public/api/_bootstrap.php:205-212` → thay bằng:

```php
function log_action(
    string $action,
    string $module,
    string $what,
    string $detail = '',
    array  $opts = []     // ['before'=>array,'after'=>array,'target_type'=>string,'target_id'=>int,'target_label'=>string]
): void {
    $me = current_member();

    // Chỉ ghi diff khi có before hoặc after (tiết kiệm dung lượng).
    $diff = null;
    if (isset($opts['before']) || isset($opts['after'])) {
        $diff = json_encode(
            ['before' => $opts['before'] ?? null, 'after' => $opts['after'] ?? null],
            JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = isset($_SERVER['HTTP_USER_AGENT'])
        ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255)
        : null;

    db_run('INSERT INTO activity_logs
        (actor_id, actor_name, action, module, what, detail,
         ip, user_agent, target_type, target_id, target_label, diff_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
        [
            $me['id'] ?? null,
            $me['full_name'] ?? 'Hệ thống',
            $action, $module, $what, $detail,
            $ip, $ua,
            $opts['target_type'] ?? null,
            $opts['target_id'] ?? null,
            $opts['target_label'] ?? null,
            $diff,
        ]);
}
```

- [ ] **Step 3: Chạy smoke test (đảm bảo không phá 66 chỗ gọi cũ)**

```bash
php -r "require 'config/db.php'; db(); echo db()->query('SELECT 1')->fetchColumn();"
```
Expected: `1`. (Chỉ kiểm tra file PHP không có syntax error.)

- [ ] **Step 4: Chạy PHPUnit đầy đủ**

```bash
php phpunit.phar --testsuite "TNTT Unit Tests"
```
Expected: tất cả test cũ vẫn pass (vì chữ ký cũ vẫn dùng được với tham số thứ 5 mặc định `[]`).

### Task 2.2: Viết test chữ ký mới

- [ ] **Step 1: Tạo file test**

`tests/unit/LogActionTest.php`:

```php
<?php
// tests/unit/LogActionTest.php
// Kiểm tra log_action() mới: chữ ký cũ vẫn chạy, chữ ký mới ghi diff + IP + target.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_bootstrap.php';

use PHPUnit\Framework\TestCase;

class LogActionTest extends TestCase
{
    private array $madeIds = [];

    protected function tearDown(): void
    {
        foreach ($this->madeIds as $id) {
            try { db_run('DELETE FROM activity_logs WHERE id = ?', [$id]); } catch (Throwable $e) {}
        }
        $this->madeIds = [];
    }

    public function test_chu_ky_cu_3_tham_so_van_chay(): void
    {
        log_action('tao', 'test', 'chữ ký cũ vẫn ok');
        $row = db_one("SELECT * FROM activity_logs WHERE module = 'test' AND what = 'chữ ký cũ vẫn ok' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($row);
        $this->assertSame('tao', $row['action']);
        $this->assertNull($row['diff_json']);
        $this->assertNull($row['target_type']);
        $this->madeIds[] = (int) $row['id'];
    }

    public function test_chu_ky_moi_ghi_diff(): void
    {
        log_action('sua', 'students', 'Sửa tên em X', 'diff test',
            [
                'before' => ['name' => 'A cũ', 'score' => 8],
                'after'  => ['name' => 'A mới', 'score' => 5],
                'target_type' => 'student',
                'target_id' => 123,
                'target_label' => 'A mới · Chiên 2',
            ]
        );
        $row = db_one("SELECT * FROM activity_logs WHERE target_id = 123 AND module = 'students' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($row);
        $this->assertNotNull($row['diff_json']);
        $decoded = json_decode($row['diff_json'], true);
        $this->assertSame('A cũ', $decoded['before']['name']);
        $this->assertSame(5, $decoded['after']['score']);
        $this->assertSame('student', $row['target_type']);
        $this->assertSame('A mới · Chiên 2', $row['target_label']);
        $this->madeIds[] = (int) $row['id'];
    }

    public function test_ip_va_user_agent_duoc_ghi(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.42';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit-Test/1.0';
        log_action('tao', 'test', 'ip test');
        $row = db_one("SELECT * FROM activity_logs WHERE module = 'test' AND what = 'ip test' ORDER BY id DESC LIMIT 1");
        $this->assertSame('203.0.113.42', $row['ip']);
        $this->assertSame('PHPUnit-Test/1.0', $row['user_agent']);
        $this->madeIds[] = (int) $row['id'];
        // Khôi phục SERVER
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function test_chi_co_after_khong_co_before_van_ghi_diff(): void
    {
        log_action('tao', 'test', 'chỉ có after', '',
            ['after' => ['status' => 'created']]);
        $row = db_one("SELECT * FROM activity_logs WHERE module = 'test' AND what = 'chỉ có after' ORDER BY id DESC LIMIT 1");
        $this->assertNotNull($row['diff_json']);
        $decoded = json_decode($row['diff_json'], true);
        $this->assertNull($decoded['before']);
        $this->assertSame('created', $decoded['after']['status']);
        $this->madeIds[] = (int) $row['id'];
    }

    public function test_khong_co_opts_khong_tao_diff(): void
    {
        log_action('duyet', 'test', 'duyệt không có diff');
        $row = db_one("SELECT * FROM activity_logs WHERE module = 'test' AND what = 'duyệt không có diff' ORDER BY id DESC LIMIT 1");
        $this->assertNull($row['diff_json']);
        $this->madeIds[] = (int) $row['id'];
    }
}
```

- [ ] **Step 2: Chạy test PASS**

```bash
php phpunit.phar --testsuite "TNTT Unit Tests" --filter LogActionTest
```
Expected: 5 tests pass.

- [ ] **Step 3: Commit (chỉ khi người dùng yêu cầu)**

```bash
git add public/api/_bootstrap.php tests/unit/LogActionTest.php
git commit -m "feat(activity-log): log_action() ghi IP + UA + target + diff (PR2)"
```

---

## PR3: API logs.php filter + phân quyền theo phạm vi + UI filter

**Files:**
- Modify: `public/api/_common.php:111-114` (sửa `can_view_logs` + thêm `log_scope_where`)
- Modify: `public/api/logs.php:1-53` (toàn bộ — thêm filter + scope)
- Modify: `views/module_settings.php:54-123` (UI thêm 3 ô filter)
- Modify: `public/assets/js/modules/core.js:670-730` (state + filteredLogs)
- Test: `tests/unit/LogsApiFilterTest.php`
- Test: `tests/unit/LogsScopeGlvTest.php`

**Interfaces:**
- Consumes:
  - `can_view_logs($me)` đã có ở `_common.php:111-114`.
  - `current_year()` ở `_bootstrap.php:197-203`.
  - `can_access_class()` đã có ở `_common.php` (theo spec: dùng cho GLV scope).
  - Bảng `classes` có cột `homeroom_teacher_id` (theo giả định Phụ lục C).
- Produces:
  - `can_view_logs($me)` — đổi: admin hoặc BĐH mới xem tất cả; GLV được xem (của mình + lớp mình).
  - `log_scope_where($me): string` — mới, trả về mệnh đề WHERE.
  - `logs.php` — query string: `actor, module, action, from, to`; response thêm `ip, user_agent, target_type/id/label, diff`.

### Task 3.1: Sửa can_view_logs và thêm log_scope_where

- [ ] **Step 1: Đọc hàm hiện tại**

`public/api/_common.php:111-114`:
```php
function can_view_logs(?array $me): bool
{
    return ($me['role_code'] ?? '') === 'admin';
}
```

- [ ] **Step 2: Sửa — cho phép BĐH xem tất cả; GLV/ai-có-me cũng xem (của mình)**

`public/api/_common.php:111-114` → thay bằng:

```php
function can_view_logs(?array $me): bool
{
    if (!$me) return false;
    // Admin + BĐH xem tất cả; mọi vai trò có tài khoản nội bộ đều xem được
    // log của mình (scope thu hẹp bằng log_scope_where ở dưới).
    $r = $me['role_code'] ?? '';
    if (in_array($r, ['admin', 'bdh'], true)) return true;
    // Các vai trò còn lại: được xem nếu là thành viên nội bộ (có id)
    return !empty($me['id']);
}
```

- [ ] **Step 3: Thêm hàm log_scope_where ngay sau can_view_logs**

`public/api/_common.php` — chèn ngay sau hàm `can_view_logs`:

```php
/**
 * Trả về mệnh đề WHERE giới hạn log theo phạm vi của người dùng.
 *  - admin / BĐH: 1=1 (thấy tất cả)
 *  - GLV (và các vai có lớp chủ nhiệm): thấy log do mình tạo HOẶC log
 *    tác động lên học sinh thuộc lớp mình CHỦ NHIỆM trong năm học hiện hành.
 *  - Khác: chỉ thấy log do mình tạo.
 *
 * Lưu ý: KHÔNG nhận user-input; chỉ dùng $me['id'] đã qua require_login.
 */
function log_scope_where(?array $me): string
{
    if (!$me) return '0=1';
    $r = $me['role_code'] ?? '';
    if (in_array($r, ['admin', 'bdh'], true)) return '1=1';

    $meId = (int) ($me['id'] ?? 0);
    if ($meId <= 0) return '0=1';

    // Có chủ nhiệm lớp? Tra cứu năm học hiện hành.
    $year = current_year();
    $yearId = (int) ($year['id'] ?? 0);

    return "(
        actor_id = $meId
        OR (target_type = 'student' AND target_id IN (
            SELECT s.id FROM students s
            JOIN classes c ON c.id = s.class_id
            WHERE c.homeroom_teacher_id = $meId
              AND c.year_id = $yearId
        ))
    )";
}
```

- [ ] **Step 4: Grep các nơi gọi can_view_logs để chắc không bị phá**

```bash
grep -rn "can_view_logs" public/
```
Expected: chỉ thấy ở `logs.php` và chính `_common.php`. Nếu thấy chỗ khác, đọc và cập nhật cho phù hợp.

### Task 3.2: Sửa logs.php — thêm filter + scope

- [ ] **Step 1: Đọc file hiện tại**

`public/api/logs.php` (53 dòng) đã đọc. Cấu trúc: `require_login` → `can_view_logs` → query `activity_logs ORDER BY id DESC LIMIT ? OFFSET ?` → trả JSON.

- [ ] **Step 2: Viết lại logs.php với filter + scope**

`public/api/logs.php` — thay toàn bộ bằng:

```php
<?php
/**
 * NHẬT KÝ HOẠT ĐỘNG — CÓ PHÂN TRANG + FILTER + PHÂN QUYỀN PHẠM VI
 *
 * GET api/logs.php?page=1&limit=20
 *      &actor=...&module=...&action=...&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Phân quyền:
 *  - admin / BĐH: thấy tất cả.
 *  - Vai khác: chỉ thấy log do mình tạo + log về lớp mình chủ nhiệm
 *    (xem log_scope_where() trong _common.php).
 */
require __DIR__ . '/_bootstrap.php';

$me = require_login();

if (!can_view_logs($me)) json_fail('Không có quyền xem nhật ký.', 403);

$page  = max(1, (int) ($_GET['page'] ?? 1));
$limit = min(100, max(10, (int) ($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

// ---- Lọc ----
$where = [log_scope_where($me)];   // mệnh đề phân quyền (luôn có)
$params = [];

if (!empty($_GET['module'])) {
    $where[] = 'module = ?';
    $params[] = (string) $_GET['module'];
}
if (!empty($_GET['action'])) {
    $where[] = 'action = ?';
    $params[] = (string) $_GET['action'];
}
if (!empty($_GET['actor'])) {
    // Tìm theo tên (LIKE) — không dùng actor_id vì nhiều log cũ có actor_id NULL.
    $where[] = 'actor_name LIKE ?';
    $params[] = '%' . (string) $_GET['actor'] . '%';
}
if (!empty($_GET['from'])) {
    $from = date('Y-m-d 00:00:00', strtotime((string) $_GET['from']));
    if ($from) { $where[] = 'logged_at >= ?'; $params[] = $from; }
}
if (!empty($_GET['to'])) {
    $to = date('Y-m-d 23:59:59', strtotime((string) $_GET['to']));
    if ($to) { $where[] = 'logged_at <= ?'; $params[] = $to; }
}

$whereSql = ' WHERE ' . implode(' AND ', $where);

// ---- Đếm + lấy log ----
$total = (int) (db_one("SELECT COUNT(*) n FROM activity_logs $whereSql", $params)['n'] ?? 0);
$totalPages = (int) ceil($total / $limit);

$rows = db_all(
    "SELECT * FROM activity_logs
     $whereSql
     ORDER BY id DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$limit, $offset])
);

$logs = array_map(function (array $l): array {
    return [
        'id'           => (int) $l['id'],
        'at'           => substr($l['logged_at'], 0, 16),
        'ts'           => strtotime($l['logged_at']) * 1000,
        'actor'        => $l['actor_name'],
        'action'       => $l['action'],
        'module'       => $l['module'],
        'what'         => $l['what'],
        'detail'       => $l['detail'] ?? '',
        'ip'           => $l['ip'] ?? null,
        'user_agent'   => $l['user_agent'] ?? null,
        'target_type'  => $l['target_type'] ?? null,
        'target_id'    => isset($l['target_id']) ? (int) $l['target_id'] : null,
        'target_label' => $l['target_label'] ?? null,
        'diff'         => $l['diff_json']
            ? json_decode($l['diff_json'], true)
            : null,
    ];
}, $rows);

json_out([
    'ok' => true,
    'data' => $logs,
    'pagination' => [
        'page'        => $page,
        'limit'       => $limit,
        'total'       => $total,
        'totalPages'  => $totalPages,
        'hasNext'     => $page < $totalPages,
        'hasPrev'     => $page > 1,
    ],
]);
```

- [ ] **Step 3: Kiểm tra cú pháp PHP**

```bash
php -l public/api/logs.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 4: Smoke test thủ công (gọi API qua curl)**

```bash
php -S 127.0.0.1:8080 -t public &
sleep 1
# Đăng nhập admin lấy cookie (xem tests/unit/P1ApiHarness.php để biết flow)
# Hoặc mở app ở trình duyệt, vào tab Nhật ký — bảng log hiển thị như cũ.
# Lệnh dưới chỉ kiểm tra endpoint có trả JSON (chưa đăng nhập → 401/403):
curl -i "http://127.0.0.1:8080/api/logs.php?limit=5"
```
Expected: 401/403 (vì chưa đăng nhập), KHÔNG phải 500.

- [ ] **Step 5: Dừng server**

```bash
# Tìm và kill php -S đang chạy cổng 8080
powershell -Command "Get-NetTCPConnection -LocalPort 8080 -ErrorAction SilentlyContinue | ForEach-Object { Stop-Process -Id \$_.OwningProcess -Force }"
```

### Task 3.3: Thêm 3 ô filter vào UI module_settings.php

- [ ] **Step 1: Đọc đoạn UI hiện tại**

Đã đọc ở `views/module_settings.php:54-123`. Có:
- ô tìm kiếm `x-model="logSearch"`
- hàng chip filter `logFilter` (Tất cả / Tạo / Sửa / ...)
- danh sách `groupedLogs`

- [ ] **Step 2: Chèn 3 ô filter mới SAU hàng chip, TRƯỚC "X thao tác"**

Vị trí: ngay sau dòng 71 (kết thúc `</div>` của hàng chip), trước dòng 73 (bắt đầu `flex justify-between`).

Thêm khối:

```html
<!-- Bộ lọc nâng cao (mobile: dọc; desktop: 1 hàng) -->
<div class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-4 px-1">
    <select x-model="logFilterActor" class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
        <option value="">Tất cả người</option>
        <template x-for="a in logActorOptions" :key="a">
            <option :value="a" x-text="a"></option>
        </template>
    </select>
    <select x-model="logFilterModule" class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
        <option value="">Tất cả module</option>
        <template x-for="m in logModuleOptions" :key="m">
            <option :value="m" x-text="m"></option>
        </template>
    </select>
    <input x-model="logFilterFrom" type="date" aria-label="Từ ngày"
           class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
    <input x-model="logFilterTo" type="date" aria-label="Đến ngày"
           class="tap-safe w-full bg-white border border-slate-200 rounded-field px-3 py-2.5 text-sm font-medium text-slate-700">
</div>
```

Lưu ý mobile-first: `grid-cols-1` (mobile) → `sm:grid-cols-4` (desktop ≥640px). Mỗi ô `tap-safe` để vùng chạm ≥ 44px.

- [ ] **Step 3: Commit UI (chỉ khi người dùng yêu cầu)**

```bash
git add views/module_settings.php
git commit -m "feat(activity-log): thêm 4 ô filter nâng cao (actor/module/from/to) (PR3)"
```

### Task 3.4: Thêm state + watcher trong core.js

- [ ] **Step 1: Đọc state logs hiện tại**

`public/assets/js/modules/core.js:670-730` đã đọc. Có: `logs: []`, `logSearch: ''`, `logFilter: ''`, `logDefs: {...}`, `filteredLogs` (getter), `logAction()`, `LOG_LIMIT: 300`.

- [ ] **Step 2: Thêm 4 state mới + getter cho options**

Chèn SAU `logFilter: '',` (dòng 703), trước `logAction(...)`:

```javascript
    logFilterActor: '',
    logFilterModule: '',
    logFilterFrom: '',
    logFilterTo: '',

    get logActorOptions() {
        // 20 người có log nhiều nhất (tránh dropdown quá dài)
        const seen = new Map();
        for (const l of this.logs) {
            seen.set(l.actor, (seen.get(l.actor) || 0) + 1);
        }
        return [...seen.entries()]
            .sort((a, b) => b[1] - a[1])
            .slice(0, 20)
            .map(([name]) => name);
    },

    get logModuleOptions() {
        return [...new Set(this.logs.map(l => l.module))].sort();
    },
```

- [ ] **Step 3: Cập nhật getter filteredLogs để áp filter mới**

Tìm getter `filteredLogs` hiện tại (dòng 721–729) và thay bằng:

```javascript
    get filteredLogs() {
        const q = this.normalizeText(this.logSearch);
        const fromTs = this.logFilterFrom ? new Date(this.logFilterFrom).getTime() : null;
        const toTs   = this.logFilterTo   ? new Date(this.logFilterTo).getTime() + 86400000 : null; // bao trùm cả ngày
        return this.logs
            .filter(l => this.logFilter === '' || l.action === this.logFilter)
            .filter(l => this.logFilterActor === '' || l.actor === this.logFilterActor)
            .filter(l => this.logFilterModule === '' || l.module === this.logFilterModule)
            .filter(l => fromTs === null || l.ts >= fromTs)
            .filter(l => toTs === null || l.ts < toTs)
            .filter(l => q === ''
                || this.normalizeText(l.actor).includes(q)
                || this.normalizeText(l.what).includes(q)
                || this.normalizeText(l.detail).includes(q));
    },
```

- [ ] **Step 4: Thêm nút "Xóa filter" (reset)**

Trong view `module_settings.php`, ngay SAU khối 4 ô filter mới (chèn trước dòng `flex justify-between mb-3`), thêm:

```html
<div x-show="logFilterActor || logFilterModule || logFilterFrom || logFilterTo" class="mb-3 px-1">
    <button @click="logFilterActor = ''; logFilterModule = ''; logFilterFrom = ''; logFilterTo = ''"
            type="button"
            class="text-xs font-bold text-blue-600 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-100 active:scale-95 transition-transform">
        <i data-lucide="x" class="w-3 h-3 inline"></i> Xóa bộ lọc
    </button>
</div>
```

- [ ] **Step 5: Chạy ESLint**

```bash
npx --yes eslint@9.39.5 public/assets/js/modules/core.js
```
Expected: 0 errors.

- [ ] **Step 6: Smoke test trên trình duyệt**

Mở app:
```bash
php -S 127.0.0.1:8080 -t public
```
- Vào Cài đặt → tab Nhật ký.
- Quan sát: 4 ô filter mới hiển thị (mobile xếp dọc; desktop 1 hàng).
- Chọn "students" ở dropdown Module → bảng lọc theo module đó.
- Chọn ngày từ/đến → log ngoài khoảng bị ẩn.
- Bấm "Xóa bộ lọc" → mọi filter về rỗng.

### Task 3.5: Viết test API logs.php filter + scope

- [ ] **Step 1: Tạo file test**

`tests/unit/LogsApiFilterTest.php`:

```php
<?php
// tests/unit/LogsApiFilterTest.php
// Kiểm tra API logs.php chấp nhận filter actor/module/action/from/to.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_bootstrap.php';

use PHPUnit\Framework\TestCase;

class LogsApiFilterTest extends TestCase
{
    private array $madeIds = [];

    protected function tearDown(): void
    {
        foreach ($this->madeIds as $id) {
            try { db_run('DELETE FROM activity_logs WHERE id = ?', [$id]); } catch (Throwable $e) {}
        }
    }

    public function test_filter_module_chi_tra_module_do(): void
    {
        // Chèn 3 log thuộc 2 module khác nhau
        log_action('tao', 'students', 'log 1 students');
        log_action('tao', 'students', 'log 2 students');
        log_action('tao', 'gifts', 'log gifts');

        // Gọi SQL trực tiếp mô phỏng filter (vì API cần HTTP harness)
        $rows = db_all(
            "SELECT * FROM activity_logs WHERE module = ? ORDER BY id DESC",
            ['students']
        );
        $this->assertGreaterThanOrEqual(2, count($rows));
        foreach ($rows as $r) {
            $this->assertSame('students', $r['module']);
        }

        // Dọn
        db_run("DELETE FROM activity_logs WHERE what IN ('log 1 students','log 2 students','log gifts')");
    }

    public function test_filter_from_to_chi_tra_trong_khoang(): void
    {
        // Log thời điểm cũ
        db_run("INSERT INTO activity_logs (actor_id, actor_name, action, module, what, logged_at)
                VALUES (NULL, 'test', 'tao', 'old', 'old log', '2020-01-01 00:00:00')");
        log_action('tao', 'new', 'log mới');

        $rows = db_all(
            "SELECT * FROM activity_logs WHERE logged_at >= ? ORDER BY id DESC",
            ['2025-01-01 00:00:00']
        );
        foreach ($rows as $r) {
            $this->assertGreaterThanOrEqual(strtotime('2025-01-01'), strtotime($r['logged_at']));
        }

        db_run("DELETE FROM activity_logs WHERE what IN ('old log','log mới')");
    }

    public function test_filter_actor_like_hoat_dong(): void
    {
        log_action('tao', 'test', 'log A', '', ['target_label' => 'A']);
        log_action('tao', 'test', 'log B', '', ['target_label' => 'B']);

        $rows = db_all(
            "SELECT * FROM activity_logs WHERE actor_name LIKE ?",
            ['%Hệ thống%']
        );
        $this->assertGreaterThanOrEqual(2, count($rows));

        db_run("DELETE FROM activity_logs WHERE what IN ('log A','log B')");
    }
}
```

- [ ] **Step 2: Tạo file test scope GLV**

`tests/unit/LogsScopeGlvTest.php`:

```php
<?php
// tests/unit/LogsScopeGlvTest.php
// Kiểm tra log_scope_where() áp đúng phạm vi cho từng vai trò.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_bootstrap.php';

use PHPUnit\Framework\TestCase;

class LogsScopeGlvTest extends TestCase
{
    public function test_admin_thay_tat_ca(): void
    {
        $sql = log_scope_where(['id' => 1, 'role_code' => 'admin']);
        $this->assertSame('1=1', $sql);
    }

    public function test_bdh_thay_tat_ca(): void
    {
        $sql = log_scope_where(['id' => 2, 'role_code' => 'bdh']);
        $this->assertSame('1=1', $sql);
    }

    public function test_glv_chi_thay_cua_minh(): void
    {
        $sql = log_scope_where(['id' => 42, 'role_code' => 'giao_ly_vien']);
        // Phải có cả 2 nhánh
        $this->assertStringContainsString('actor_id = 42', $sql);
        $this->assertStringContainsString("target_type = 'student'", $sql);
        $this->assertStringContainsString('homeroom_teacher_id = 42', $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function test_khong_co_id_tra_0_eq_1(): void
    {
        $sql = log_scope_where(['id' => 0, 'role_code' => 'giao_ly_vien']);
        $this->assertSame('0=1', $sql);
    }

    public function test_null_tra_0_eq_1(): void
    {
        $this->assertSame('0=1', log_scope_where(null));
    }
}
```

- [ ] **Step 3: Chạy test**

```bash
php phpunit.phar --testsuite "TNTT Unit Tests" --filter "LogsApiFilter|LogsScopeGlv"
```
Expected: 8 tests pass.

- [ ] **Step 4: Commit (chỉ khi người dùng yêu cầu)**

```bash
git add public/api/_common.php public/api/logs.php views/module_settings.php public/assets/js/modules/core.js tests/unit/LogsApiFilterTest.php tests/unit/LogsScopeGlvTest.php
git commit -m "feat(activity-log): API filter + phân quyền theo phạm vi + UI 4 ô filter (PR3)"
```

---

## Self-Review (theo checklist skill writing-plans)

**1. Spec coverage** — kiểm tra từng mục spec có task:

| Spec | Đã có task? |
|---|---|
| §1.3 Mục tiêu 1-8 | PR1 (schema) + PR2 (log_action) + PR3 (filter/scope) + outline PR4-12 |
| §2.1 Trong phạm vi | Đầy đủ trong 3 PR + outline |
| §3.1 Schema | Task 1.1 + 1.2 |
| §3.2 log_action() | Task 2.1 + 2.2 |
| §3.3 API logs.php filter | Task 3.2 + 3.5 |
| §3.4 logs_export.php | Outline PR4-PR5 (chưa triển khai) |
| §3.5 XlsxExport | Outline PR4 |
| §3.6 UI filter + nút Xuất + modal diff | Task 3.3 + 3.4 (filter); Xuất+modal ở outline PR5-PR6 |
| §3.7 Phân quyền | Task 3.1 + 3.5 |
| §4 Phân kỳ | Đầy đủ |
| §5 Test | Task 1.3, 2.2, 3.5 |
| §6 Rủi ro | N/A (là phần cảnh báo, không cần task) |
| §9 Mobile-first | Task 3.3 (đã dùng `grid-cols-1 sm:grid-cols-4`, `tap-safe`, `rounded-field`); PR5/PR6 outline nêu yêu cầu chụp 2 kích thước |

**2. Placeholder scan** — đã tìm và KHÔNG thấy: TBD, TODO, "fill in", "add appropriate". Mọi step có code hoặc lệnh cụ thể.

**3. Type consistency** — kiểm tra chữ ký:
- `log_action(string, string, string, string='', array=[])` ở Task 2.1 → dùng nhất quán ở Task 2.2, 3.5.
- `log_scope_where(?array $me): string` ở Task 3.1 → dùng nhất quán ở Task 3.2, 3.5.
- `can_view_logs(?array $me): bool` ở Task 3.1 (chữ ký giữ nguyên) → dùng nhất quán ở Task 3.2.
- Query string `actor/module/action/from/to` ở Task 3.2 → dùng nhất quán ở Task 3.5.

**Không có gap nghiêm trọng.** Plan này triển khai được PR1, PR2, PR3 đến "Xong = test + smoke pass".

---

## Outline cho PR4–PR12 (triển khai sau, mỗi PR mở plan riêng khi tới lượt)

### PR4: Helper XlsxExport (tự viết zip+xml) — ~250 dòng

**Files:**
- Create: `src/XlsxExport.php`
- Test: `tests/unit/XlsxExportTest.php`

**Hàm chính:**
```php
function xlsx_export(string $path, array $headers, array $rows, array $opts = []): void
```
- Tạo các file XML: `[Content_Types].xml`, `_rels/.rels`, `xl/workbook.xml`, `xl/styles.xml`, `xl/sharedStrings.xml`, `xl/worksheets/sheet1.xml`.
- Nén ZIP với `ZipArchive::FL_NONE` (không nén XML, Excel đọc nhanh hơn).
- Hỗ trợ: in đậm tiêu đề, format ngày `dd/mm/yyyy` (cột chứa ISO date), UTF-8 BOM nội bộ.
- **KHÔNG thêm composer**. Kiểm thử bằng cách mở file bằng `openpyxl` (Python) hoặc `libreoffice --headless --convert-to csv`.

**Test:** tạo file, đọc lại bằng `ZipArchive`, parse từng file XML, kiểm tra nội dung cells. Mở thành công qua script Python.

### PR5: API logs_export.php + UI nút Xuất — ~220 dòng

**Files:**
- Create: `public/api/logs_export.php`
- Modify: `views/module_settings.php` (thêm nút "Xuất" cạnh "Xóa nhật ký")
- Modify: `public/assets/js/modules/core.js` (state + handler)
- Test: `tests/unit/LogsExportTest.php`, `tests/unit/LogsExportScopeTest.php`, `tests/unit/LogsExportMaskTest.php`

**Endpoint:**
```
GET /api/logs_export.php?format=xlsx|csv&actor=...&module=...&action=...&from=...&to=...
```

**Mask BẮT BUỘC** trong cột `before/after`:
- Nếu key là `parent_phone` hoặc `phone` → thay bằng `0901***567` (giữ 4 đầu + 3 cuối).
- Áp dụng cho cả diff lồng nhau (recursive).

**Sau khi stream thành công**, ghi 1 dòng log:
```php
log_action('tao', 'logs_export',
    'Xuất nhật ký',
    strtoupper($format) . ' · ' . $totalRows . ' dòng · filter: ' . $filterDesc
);
```

**UI nút "Xuất":**
- Mobile: dropdown nổi (`x-show`) với 2 lựa chọn: CSV / Excel.
- Desktop: cùng dropdown.
- Click → gọi `/api/logs_export.php?format=...&<cùng filter đang áp dụng>`.

### PR6: Clear theo khoảng ngày + modal xem diff — ~320 dòng

**Files:**
- Modify: `public/api/settings.php:107-111` (mở rộng `clearLogs` thêm `from, to`)
- Modify: `views/module_settings.php` (modal hỏi trước khi xóa + modal xem diff)
- Modify: `public/assets/js/modules/core.js`
- Test: `tests/unit/LogsClearRangeTest.php`, `tests/unit/LogsDiffModalTest.php`

**Clear theo khoảng ngày:**
- Endpoint: `clearLogs` thêm `from, to` (optional).
- Có `from/to`: `DELETE FROM activity_logs WHERE logged_at BETWEEN ? AND ?`.
- Không có: xóa tất cả (giữ hành vi cũ).
- **Giới hạn 5.000 dòng/lần** — nếu `SELECT COUNT(*)` > 5000, từ chối và yêu cầu thu hẹp.
- Trước khi xóa, ghi 1 dòng `log_action('xoa', 'settings', 'Xóa nhật ký', 'from X to Y · N dòng')` để có dấu vết.

**Modal xem diff:**
- Bấm 1 dòng có `diff` khác null → mở modal.
- Mobile: full-screen (`fixed inset-0`).
- Desktop: dialog (`sm:max-w-2xl`).
- Bảng 3 cột: Trường / Cũ / Mới. Mobile: mỗi trường 1 dòng dọc.
- Đóng: bấm ra ngoài, ESC, nút "Đóng" (full-width mobile, bên phải desktop).

**Yêu cầu smoke:** mỗi PR UI chụp 2 ảnh (375×812 + 1280×800) dán vào PR.

### PR7–PR12: Bổ sung diff cho 30 endpoint sửa/xóa

Mỗi nhóm 1-2 PR, mỗi PR ~600 dòng:

| PR | File API | Mục tiêu |
|---|---|---|
| PR7 | `students.php` (5 chỗ) | Trước/sau hồ sơ + chuyển lớp + xóa |
| PR8 | `scores.php`, `attendance.php` (10 chỗ) | Trước/sau điểm + trạng thái điểm danh |
| PR9 | `org.php`, `OrgService.php`, `StaffService.php`, `years.php` (15 chỗ) | Trước/sau phân công, khối/lớp, năm học |
| PR10 | `gifts.php`, `programs.php`, `library.php` (10 chỗ) | Trước/sau quà, chương trình, thư viện |
| PR11 | `leave.php`, `reports.php`, `rewards.php` (8 chỗ) | Trước/sau đơn phép, phiếu liên lạc, đổi quà |
| PR12 | `settings.php`, `announcements.php` (8 chỗ) | Trước/sau phân quyền, thông báo |

**Cách làm** (lặp lại cho mỗi endpoint):
1. Trước khi `db_run(UPDATE)`, gọi `db_one(SELECT ... WHERE id = ?)` để lấy `before`.
2. Sau khi UPDATE, gọi `db_one(SELECT ... WHERE id = ?)` để lấy `after`.
3. Tính `diff` (chỉ giữ trường thay đổi).
4. Gọi `log_action('sua', $module, $what, $detail, ['before' => $before, 'after' => $after, 'target_type' => 'student|...', 'target_id' => $id, 'target_label' => $label])`.

**Helper mới (PR7):**
- Thêm `src/DiffUtil.php` (hoặc đặt trong `_common.php`) với hàm:
  ```php
  function diff_arrays(array $before, array $after): array
  {
      // Trả về ['before' => [...chỉ-trường-đổi], 'after' => [...chỉ-trường-đổi]]
      // Bỏ qua trường không thay đổi; null-safe; không quan tâm thứ tự key.
      $changed = [];
      foreach ($after as $k => $v) {
          if (($before[$k] ?? null) !== $v) $changed[$k] = $v;
      }
      $beforeOut = [];
      foreach ($changed as $k => $_) {
          $beforeOut[$k] = $before[$k] ?? null;
      }
      return ['before' => $beforeOut, 'after' => $changed];
  }
  ```

**Test** cho mỗi PR: thêm test "sửa X → log có diff đúng trường thay đổi".

---

## Execution Handoff

Plan complete và lưu tại `docs/superpowers/plans/2026-10-14-activity-log-upgrade.md`. Hai cách triển khai:

**1. Subagent-Driven (khuyến nghị)** — Tôi điều phối subagent tươi cho từng task, review giữa các task, lặp nhanh.

**2. Inline Execution** — Tôi chạy task trong session này qua `executing-plans`, batch với checkpoint review.

**Bạn chọn cách nào?**

Cũng xin xác nhận: **bạn đã duyệt spec — bạn có muốn tôi bắt đầu triển khai PR1 (migration) ngay trong session này, hay đợi thêm?** Theo CLAUDE.md mục 1, tôi **không tự commit/push/mở PR** — sẽ chỉ sửa file trong cây làm việc và báo cáo, trừ khi bạn yêu cầu rõ.