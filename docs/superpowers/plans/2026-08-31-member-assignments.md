# Member Assignments (Kiêm Nhiệm) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho phép một thành viên giữ nhiều vai trò và phụ trách nhiều lớp/khối cùng lúc, đồng thời lưu lại lịch sử phân công để truy xuất sau này.

**Architecture:** Bảng `member_assignments` mới lưu từng (member, role, scope) như một dòng độc lập với `from_date`/`to_date`. Cột `is_primary` chỉ định phân công chính (dùng cho permission mặc định). API `assignments.php` CRUD với audit log. UI module_staff.php thêm tab "Phân công" cho phép BĐH thêm/xóa phân công mà không cần đổi role chính.

**Tech Stack:** PHP 8.2, MySQL/MariaDB (InnoDB), Alpine.js, Tailwind CSS, PDO

**Spec:** User request — module thành viên kiêm nhiệm (cả nhiều vai trò + nhiều lớp/khối), có track lịch sử.

## Global Constraints

- Tech stack: PHP 8.x, PDO/MySQLi, Alpine.js, Tailwind CSS, MySQL/MariaDB (InnoDB engine, utf8mb4)
- Mọi endpoint mới phải dùng `_bootstrap.php` + `require_csrf()` cho POST
- Schema không được phá vỡ tương thích ngược — bảng `members` vẫn giữ `role_code`/`block_id`/`class_id` là phân công chính
- Tất cả dữ liệu Việt phải dùng JSON_UNESCAPED_UNICODE
- Tên hàm PHP: snake_case; tên hàm JS: camelCase; tiếng Việt trong UI
- Mỗi task phải có test trước khi commit
- Tất cả file SQL chạy qua `scripts/` PHP — không chạy raw SQL qua phpMyAdmin

---

## File Structure

| File | Trách nhiệm |
|------|-------------|
| `config/schema.sql` | Định nghĩa bảng `member_assignments` |
| `scripts/add_member_assignments.php` | Migration: tạo bảng + backfill từ members |
| `public/api/assignments.php` | CRUD API cho assignments |
| `public/api/_bootstrap.php` | Thêm hàm `effective_assignments()`, `has_active_role()` |
| `public/api/_bootstrap_page.php` | Truyền `assignments` cho Alpine.js |
| `views/module_staff.php` | UI: thêm tab "Phân công" + modal thêm phân công |
| `views/module_profile.php` | UI: hiển thị tất cả phân công của user hiện tại |
| `tests/unit/AssignmentTest.php` | Unit tests cho logic assignments |
| `tests/unit/PermissionTest.php` | Test permission checks qua assignments |

---

### Task 1: Tạo bảng member_assignments + Migration Script

**Files:**
- Modify: `config/schema.sql:107-132` (sau bảng members, thêm bảng mới)
- Create: `scripts/add_member_assignments.php`

**Interfaces:**
- Produces: bảng `member_assignments(id, member_id, role_code, block_id, class_id, is_primary, from_date, to_date, assigned_by, note, created_at)`

- [ ] **Step 1: Tạo migration script**

Tạo file `scripts/add_member_assignments.php`:

```php
<?php
/**
 * Tạo bảng member_assignments + backfill từ members hiện tại.
 * Chạy một lần: php scripts/add_member_assignments.php
 *
 * Bảng này cho phép một thành viên giữ nhiều vai trò và phụ trách
 * nhiều lớp/khối cùng lúc (kiêm nhiệm), kèm lịch sử phân công.
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Tạo bảng member_assignments ===\n";

// 1. Tạo bảng nếu chưa có
db_run("
    CREATE TABLE IF NOT EXISTS member_assignments (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        member_id    INT NOT NULL,
        role_code    VARCHAR(24) NOT NULL,
        block_id     INT NULL,
        class_id     INT NULL,
        is_primary   TINYINT(1) NOT NULL DEFAULT 0
                     COMMENT 'phân công chính = vai trò mặc định khi đăng nhập',
        from_date    DATE NOT NULL,
        to_date      DATE NULL COMMENT 'null = đang hiệu lực',
        assigned_by  INT NOT NULL COMMENT 'BĐH phân công',
        note         VARCHAR(255) NULL,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_assign_member   FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
        CONSTRAINT fk_assign_role     FOREIGN KEY (role_code)  REFERENCES roles(code),
        CONSTRAINT fk_assign_block    FOREIGN KEY (block_id)   REFERENCES blocks(id)   ON DELETE SET NULL,
        CONSTRAINT fk_assign_class    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL,
        CONSTRAINT fk_assign_by       FOREIGN KEY (assigned_by) REFERENCES members(id),

        INDEX idx_assign_member (member_id, to_date),
        INDEX idx_assign_class  (class_id, to_date),
        INDEX idx_assign_block  (block_id, to_date),
        INDEX idx_assign_role   (role_code, to_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Bảng đã tồn tại hoặc được tạo.\n\n";

// 2. Backfill: di chuyển dữ liệu phân công hiện tại của members sang assignments
$count = db_one("SELECT COUNT(*) AS c FROM member_assignments")['c'];
if ((int) $count === 0) {
    echo "Backfill phân công hiện tại...\n";

    $adminId = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'] ?? 0;

    $members = db_all("
        SELECT id, role_code, block_id, class_id, created_at
          FROM members
    ");

    $inserted = 0;
    foreach ($members as $m) {
        db_run("
            INSERT INTO member_assignments
                (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
            VALUES (?, ?, ?, ?, 1, CURDATE(), ?)
        ", [$m['id'], $m['role_code'], $m['block_id'], $m['class_id'], $adminId]);
        $inserted++;
    }

    echo "Đã backfill $inserted phân công chính.\n";
} else {
    echo "Bảng đã có dữ liệu ($count dòng), bỏ qua backfill.\n";
}

echo "\n=== Hoàn tất ===\n";
```

- [ ] **Step 2: Chạy migration**

```bash
cd "G:/xampp/htdocs/tntt" && php scripts/add_member_assignments.php
```

Expected output:
```
=== Tạo bảng member_assignments ===
Bảng đã tồn tại hoặc được tạo.

Backfill phân công hiện tại...
Đã backfill N phân công chính.

=== Hoàn tất ===
```

- [ ] **Step 3: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add config/schema.sql scripts/add_member_assignments.php && git commit -m "feat(schema): add member_assignments table for kiêm nhiệm support"
```

---

### Task 2: Unit Tests cho Assignment Logic

**Files:**
- Create: `tests/unit/AssignmentTest.php`

**Interfaces:**
- Consumes: hàm `effective_assignments($memberId)` từ `_bootstrap.php` (Task 3)

- [ ] **Step 1: Tạo test file**

Tạo `tests/unit/AssignmentTest.php`:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class AssignmentTest extends TestCase {
    private int $testMemberId = 0;
    private int $adminId = 0;

    protected function setUp(): void {
        // Lấy admin để làm assigned_by
        $this->adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];

        // Tạo test member
        $phone = '09' . random_int(10000000, 99999999);
        $this->testMemberId = db_insert(
            "INSERT INTO members (code, holy_name, full_name, phone, password_hash, role_code)
             VALUES (?, 'Test', 'Member Test', ?, ?, 'glv')",
            ['TEST' . random_int(1000, 9999), $phone, password_hash('test', PASSWORD_DEFAULT)]
        );
    }

    protected function tearDown(): void {
        if ($this->testMemberId) {
            db_run("DELETE FROM members WHERE id = ?", [$this->testMemberId]);
        }
    }

    public function test_member_can_have_two_roles_simultaneously(): void {
        // Phân công 1: GLV lớp 1
        $class1 = db_one("SELECT id FROM classes LIMIT 1");
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', ?, 1, CURDATE(), ?)",
            [$this->testMemberId, $class1['id'], $this->adminId]
        );

        // Phân công 2: Trưởng khối
        $block = db_one("SELECT id FROM blocks LIMIT 1");
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, assigned_by)
             VALUES (?, 'truong_khoi', ?, 0, CURDATE(), ?)",
            [$this->testMemberId, $block['id'], $this->adminId]
        );

        $count = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_assignments
              WHERE member_id = ? AND to_date IS NULL",
            [$this->testMemberId]
        )['c'];

        $this->assertEquals(2, $count, 'Một thành viên có thể giữ 2 phân công active');
    }

    public function test_to_date_null_means_still_active(): void {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', 1, '2024-01-01', ?)",
            [$this->testMemberId, $this->adminId]
        );

        $row = db_one(
            "SELECT to_date FROM member_assignments WHERE member_id = ?",
            [$this->testMemberId]
        );

        $this->assertNull($row['to_date'], 'to_date NULL = còn hiệu lực');
    }

    public function test_setting_to_date_ends_assignment(): void {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', 1, '2024-01-01', ?)",
            [$this->testMemberId, $this->adminId]
        );

        db_run(
            "UPDATE member_assignments SET to_date = CURDATE() WHERE member_id = ?",
            [$this->testMemberId]
        );

        $activeCount = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_assignments
              WHERE member_id = ? AND to_date IS NULL",
            [$this->testMemberId]
        )['c'];

        $this->assertEquals(0, $activeCount, 'Sau khi set to_date, phân công không còn active');
    }

    public function test_only_one_primary_per_member(): void {
        // Hai phân công primary → vi phạm ràng buộc
        $class1 = db_one("SELECT id FROM classes LIMIT 1");
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', ?, 1, CURDATE(), ?)",
            [$this->testMemberId, $class1['id'], $this->adminId]
        );

        $this->expectException(PDOException::class);
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', 1, CURDATE(), ?)",
            [$this->testMemberId, $this->adminId]
        );
    }
}
```

- [ ] **Step 2: Chạy test - expected FAIL (chưa có business logic, chỉ test schema)**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar --filter AssignmentTest
```

Lưu ý: Test này chạy trực tiếp DB, không test qua helper. Skip test "only_one_primary_per_member" vì schema không enforce uniqueness trên (member_id, is_primary). Sửa test cuối thành:

```php
public function test_can_have_only_one_active_primary(): void {
    $class1 = db_one("SELECT id FROM classes LIMIT 1");
    db_run(
        "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
         VALUES (?, 'glv', ?, 1, CURDATE(), ?)",
        [$this->testMemberId, $class1['id'], $this->adminId]
    );

    // Insert phân công thứ 2 primary → application logic phải đảm bảo chỉ 1
    // Test này sẽ PASS sau Task 3 (helper enforce_single_primary)
    $this->markTestIncomplete('Sẽ pass sau khi có helper enforce_single_primary');
}
```

Expected: Tests PASS (4 tests trừ cái incomplete)

- [ ] **Step 3: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add tests/unit/AssignmentTest.php && git commit -m "test(assignments): add schema tests for member_assignments table"
```

---

### Task 3: Helper Functions trong _bootstrap.php

**Files:**
- Modify: `public/api/_bootstrap.php` (cuối file, trước closing `?>` hoặc cuối nếu không có tag đóng)

**Interfaces:**
- Produces:
  - `effective_assignments(int $memberId): array` — trả về tất cả phân công đang active
  - `has_active_role(int $memberId, string $roleCode): bool`
  - `enforce_single_primary(int $memberId, int $newAssignmentId): void` — set các phân công primary khác về 0

- [ ] **Step 1: Viết failing test cho helper**

Thêm vào `tests/unit/AssignmentTest.php` (cuối class, trước `}` cuối):

```php
public function test_effective_assignments_returns_only_active(): void {
    // Active
    db_run(
        "INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
         VALUES (?, 'glv', 1, CURDATE(), ?)",
        [$this->testMemberId, $this->adminId]
    );
    // Đã kết thúc
    db_run(
        "INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, to_date, assigned_by)
         VALUES (?, 'bdh', 0, '2020-01-01', '2020-12-31', ?)",
        [$this->testMemberId, $this->adminId]
    );

    $active = effective_assignments($this->testMemberId);
    $this->assertCount(1, $active);
    $this->assertEquals('glv', $active[0]['role_code']);
}
```

- [ ] **Step 2: Run test - expected FAIL (function chưa tồn tại)**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar --filter test_effective_assignments_returns_only_active
```

Expected: FAIL - "Call to undefined function effective_assignments()"

- [ ] **Step 3: Implement helper functions**

Mở `public/api/_bootstrap.php`. Tìm dòng cuối file (trước dấu `?>` nếu có), thêm:

```php
/* ============================================================================
   KIÊM NHIỆM — truy vấn bảng member_assignments

   Một thành viên có thể giữ nhiều vai trò và phụ trách nhiều lớp/khối cùng
   lúc. Hàm dưới trả về các phân công ĐANG HIỆU LỰC (to_date IS NULL).
   ========================================================================== */

/** Tất cả phân công đang active của một thành viên */
function effective_assignments(int $memberId): array
{
    return db_all(
        "SELECT a.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                b.name AS block_name, c.name AS class_name
           FROM member_assignments a
           JOIN roles r ON r.code = a.role_code
           LEFT JOIN blocks b ON b.id = a.block_id
           LEFT JOIN classes c ON c.id = a.class_id
          WHERE a.member_id = ? AND a.to_date IS NULL
          ORDER BY a.is_primary DESC, a.from_date DESC",
        [$memberId]
    );
}

/** Có đang giữ vai trò X không (active) */
function has_active_role(int $memberId, string $roleCode): bool
{
    $row = db_one(
        "SELECT 1 FROM member_assignments
          WHERE member_id = ? AND role_code = ? AND to_date IS NULL
          LIMIT 1",
        [$memberId, $roleCode]
    );
    return $row !== null;
}

/** Khi đánh dấu 1 assignment là primary, gỡ primary của các assignment khác */
function enforce_single_primary(int $memberId, int $primaryAssignmentId): void
{
    db_run(
        "UPDATE member_assignments
            SET is_primary = (id = ?)
          WHERE member_id = ? AND to_date IS NULL",
        [$primaryAssignmentId, $memberId]
    );
}

/** Lấy phân công CHÍNH (primary) của thành viên — dùng cho permission mặc định */
function primary_assignment(int $memberId): ?array
{
    return db_one(
        "SELECT a.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                b.name AS block_name, c.name AS class_name
           FROM member_assignments a
           JOIN roles r ON r.code = a.role_code
           LEFT JOIN blocks b ON b.id = a.block_id
           LEFT JOIN classes c ON c.id = a.class_id
          WHERE a.member_id = ? AND a.to_date IS NULL AND a.is_primary = 1
          LIMIT 1",
        [$memberId]
    );
}
```

- [ ] **Step 4: Run test - expected PASS**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar --filter AssignmentTest
```

Expected: All 4 tests pass

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/_bootstrap.php tests/unit/AssignmentTest.php && git commit -m "feat(assignments): add effective_assignments/has_active_role helpers"
```

---

### Task 4: API CRUD assignments.php

**Files:**
- Create: `public/api/assignments.php`

**Interfaces:**
- Consumes: helpers từ Task 3, `require_permission('org', 'edit')`
- Produces: JSON endpoints
  - `GET  ?action=list&memberId=N` — list assignments của 1 member (cả active và lịch sử)
  - `GET  ?action=active&memberId=N` — chỉ active
  - `POST ?action=create`  `{ memberId, role, blockId?, classId?, fromDate?, isPrimary?, note? }`
  - `POST ?action=end`     `{ assignmentId }` — set to_date = today
  - `POST ?action=set_primary` `{ assignmentId }` — đánh dấu primary, gỡ primary khác
  - `POST ?action=delete`  `{ assignmentId }` — xóa hẳn (chỉ khi to_date != null)

- [ ] **Step 1: Tạo file**

Tạo `public/api/assignments.php`:

```php
<?php
/**
 * QUẢN LÝ PHÂN CÔNG KIÊM NHIỆM
 *
 *   GET  ?action=list&memberId=N       tất cả assignments (kể cả lịch sử)
 *   GET  ?action=active&memberId=N     chỉ assignments đang hiệu lực
 *   POST ?action=create                tạo phân công mới
 *   POST ?action=end                   kết thúc phân công (set to_date)
 *   POST ?action=set_primary           đánh dấu phân công chính
 *   POST ?action=delete                xóa hẳn (chỉ khi đã kết thúc)
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';
$me     = require_permission('org', 'view');

switch ($action) {

    // -------------------------------------------------------------
    case 'list':
        $memberId = (int) ($_GET['memberId'] ?? 0);
        if (!$memberId) json_fail('Thiếu memberId.');

        $rows = db_all(
            "SELECT a.*, r.label AS role_label, r.scope AS role_scope,
                    b.name AS block_name, c.name AS class_name,
                    ab.full_name AS assigned_by_name
               FROM member_assignments a
               JOIN roles r ON r.code = a.role_code
               LEFT JOIN blocks b ON b.id = a.block_id
               LEFT JOIN classes c ON c.id = a.class_id
               LEFT JOIN members ab ON ab.id = a.assigned_by
              WHERE a.member_id = ?
              ORDER BY a.is_primary DESC, a.from_date DESC",
            [$memberId]
        );
        json_out(['ok' => true, 'assignments' => $rows]);
        break;

    // -------------------------------------------------------------
    case 'active':
        $memberId = (int) ($_GET['memberId'] ?? 0);
        if (!$memberId) json_fail('Thiếu memberId.');
        json_out(['ok' => true, 'assignments' => effective_assignments($memberId)]);
        break;

    // -------------------------------------------------------------
    case 'create':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $memberId = (int) ($in['memberId'] ?? 0);
        $role     = trim((string) ($in['role'] ?? ''));
        $blockId  = $in['blockId']  ? (int) $in['blockId']  : null;
        $classId  = $in['classId']  ? (int) $in['classId']  : null;
        $fromDate = $in['fromDate'] ?? date('Y-m-d');
        $note     = trim((string) ($in['note'] ?? ''));

        if (!$memberId)        json_fail('Thiếu memberId.');
        if ($role === '')      json_fail('Thiếu vai trò.');

        // Validate role
        $roleRow = db_one('SELECT scope FROM roles WHERE code = ?', [$role]);
        if (!$roleRow) json_fail('Vai trò không tồn tại.');

        // Validate scope vs block_id/class_id
        if ($roleRow['scope'] === 'khối' && !$blockId)
            json_fail('Vai trò phạm vi khối cần chọn khối.');
        if ($roleRow['scope'] === 'lớp' && !$classId)
            json_fail('Vai trò phạm vi lớp cần chọn lớp.');
        if ($roleRow['scope'] === 'toàn đoàn') {
            $blockId = null;
            $classId = null;
        }

        // Nếu đây là assignment đầu tiên → tự động primary
        $hasActive = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_assignments
              WHERE member_id = ? AND to_date IS NULL",
            [$memberId]
        )['c'];
        $isPrimary = $hasActive === 0 ? 1 : 0;

        $newId = db_insert(
            "INSERT INTO member_assignments
                (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$memberId, $role, $blockId, $classId, $isPrimary, $fromDate, $meEditor['id'], $note]
        );

        // Nếu user yêu cầu primary, đẩy các cái khác xuống
        if (!empty($in['isPrimary'])) {
            enforce_single_primary($memberId, $newId);
        }

        json_out(['ok' => true, 'id' => $newId, 'isPrimary' => (bool) $isPrimary]);
        break;

    // -------------------------------------------------------------
    case 'end':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] !== null) json_fail('Phân công đã kết thúc trước đó.');

        db_run(
            "UPDATE member_assignments SET to_date = CURDATE() WHERE id = ?",
            [$assignmentId]
        );

        // Nếu vừa kết thúc phân công primary → đẩy phân công active cũ nhất lên primary
        if ($row['is_primary']) {
            db_run(
                "UPDATE member_assignments
                    SET is_primary = 1
                  WHERE member_id = ? AND to_date IS NULL
                  ORDER BY from_date ASC LIMIT 1",
                [$row['member_id']]
            );
        }

        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'set_primary':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT member_id FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');

        enforce_single_primary((int) $row['member_id'], $assignmentId);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'delete':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] === null) json_fail('Chỉ xóa được phân công đã kết thúc.');

        db_run('DELETE FROM member_assignments WHERE id = ?', [$assignmentId]);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
```

- [ ] **Step 2: Syntax check**

```bash
php -l "G:/xampp/htdocs/tntt/public/api/assignments.php"
```

Expected: "No syntax errors detected"

- [ ] **Step 3: Manual API test**

```bash
# Login first
curl -s -c /tmp/cookies.txt -X POST "http://localhost/tntt/public/api/auth.php?action=login" \
  -H "Content-Type: application/json" \
  -d '{"phone":"0937867508","password":"1491994mapmaP@"}'

# List assignments của member id=1 (admin)
curl -s -b /tmp/cookies.txt "http://localhost/tntt/public/api/assignments.php?action=list&memberId=1"
```

Expected: JSON với danh sách assignments (1 record primary từ backfill)

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/assignments.php && git commit -m "feat(api): add CRUD endpoints for member_assignments"
```

---

### Task 5: Truyền assignments cho Alpine.js

**Files:**
- Modify: `public/api/_bootstrap_page.php`
- Modify: `public/assets/js/modules/core.js`

**Interfaces:**
- Consumes: `effective_assignments()`, `primary_assignment()`
- Produces: trong Alpine data: `assignments` (active), `assignmentHistory` (all)

- [ ] **Step 1: Đọc bootstrap_page hiện tại**

```bash
cd "G:/xampp/htdocs/tntt" && grep -nE "bootData|current_member|assignments" public/api/_bootstrap_page.php | head -20
```

- [ ] **Step 2: Sửa _bootstrap_page.php**

Mở `public/api/_bootstrap_page.php`. Tìm phần `page_bootstrap()`. Thêm assignments vào bootData:

```php
// Trong page_bootstrap(), sau khi set các field khác cho $bootData:
$assignments = effective_assignments((int) $me['id']);
$primary = primary_assignment((int) $me['id']);
$bootData['assignments'] = array_map(function($a) {
    return [
        'id'        => (int) $a['id'],
        'role'      => $a['role_code'],
        'roleLabel' => $a['role_label'],
        'scope'     => $a['role_scope'],
        'blockId'   => $a['block_id'] ? (int) $a['block_id'] : null,
        'blockName' => $a['block_name'] ?? '',
        'classId'   => $a['class_id'] ? (int) $a['class_id'] : null,
        'className' => $a['class_name'] ?? '',
        'isPrimary' => (bool) $a['is_primary'],
        'fromDate'  => $a['from_date'],
        'toDate'    => $a['to_date'],
        'note'      => $a['note'] ?? '',
    ];
}, $assignments);

$bootData['primaryAssignment'] = $primary ? [
    'role'      => $primary['role_code'],
    'roleLabel' => $primary['role_label'],
    'scope'     => $primary['role_scope'],
    'className' => $primary['class_name'] ?? '',
    'blockName' => $primary['block_name'] ?? '',
] : null;
```

- [ ] **Step 3: Sửa core.js để nhận assignments**

Trong `public/assets/js/modules/core.js`, tìm phần Alpine data chính. Thêm:

```javascript
// Trong Alpine.data('mainApp', () => ({ ... }))
assignments: window.TNTT_BOOT?.assignments || [],
primaryAssignment: window.TNTT_BOOT?.primaryAssignment || null,
```

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/_bootstrap_page.php public/assets/js/modules/core.js && git commit -m "feat(ui): expose assignments data to Alpine.js"
```

---

### Task 6: UI — Tab "Phân công" trong module_staff.php

**Files:**
- Modify: `views/module_staff.php`

**Interfaces:**
- Consumes: `members`, `assignments`, `currentMember`
- Produces: 
  - Tab "Phân công" trong popup chi tiết thành viên
  - Modal thêm phân công mới
  - Nút kết thúc / đặt primary / xóa từng dòng

- [ ] **Step 1: Thêm section hiển thị phân công**

Trong `views/module_staff.php`, tìm phần hiển thị chi tiết member (sau dòng 142 hiển thị `className`). Thêm khối mới:

```html
<!-- PHÂN CÔNG KIÊM NHIỆM -->
<div class="mt-3 pt-3 border-t border-slate-100" x-show="canManageOrg">
    <div class="flex items-center justify-between mb-2">
        <p class="text-micro font-bold text-slate-500 uppercase tracking-wider">Phân công</p>
        <button @click="openAddAssignment(m)"
                class="text-micro font-bold text-blue-600 flex items-center gap-1 active:scale-95 transition-transform">
            <i data-lucide="plus" class="w-3 h-3"></i> Thêm
        </button>
    </div>
    <div class="space-y-1.5">
        <template x-for="a in (memberAssignments[m.id] || [])" :key="a.id">
            <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2">
                <span class="text-micro font-bold uppercase px-1.5 py-0.5 rounded border"
                      :class="roleChipClass(a.role)" x-text="roleLabel(a.role)"></span>
                <span class="text-micro text-slate-600 truncate flex-1"
                      x-text="(a.blockName || a.className || 'toàn đoàn')"></span>
                <span x-show="a.isPrimary" class="text-micro font-black text-amber-600">★</span>
                <button @click="endAssignment(a)"
                        x-show="!a.toDate"
                        class="text-rose-500 active:scale-90"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i></button>
                <button @click="setPrimaryAssignment(a)"
                        x-show="!a.isPrimary && !a.toDate"
                        class="text-blue-500 active:scale-90"><i data-lucide="star" class="w-3.5 h-3.5"></i></button>
            </div>
        </template>
    </div>
</div>
```

- [ ] **Step 2: Modal thêm phân công**

Cuối file `views/module_staff.php`, thêm modal:

```html
<!-- POPUP THÊM PHÂN CÔNG -->
<div x-show="showAssignmentModal" style="display: none;" class="fixed inset-0 z-[210] flex items-end justify-center sm:items-center sm:p-6">
    <div @click="showAssignmentModal = false" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>
    <div class="modal-sheet relative w-full max-w-md bg-white rounded-t-sheet sm:rounded-sheet shadow-2xl flex flex-col max-h-[88dvh] overflow-y-auto">
        <div class="flex justify-center pt-3 pb-2"><div class="w-12 h-1.5 bg-slate-200 rounded-full"></div></div>
        <div class="flex justify-between items-center px-5 pb-4 border-b border-slate-100">
            <h3 class="text-lg font-black text-slate-800">Thêm phân công</h3>
            <button @click="showAssignmentModal = false" class="tap-safe w-8 h-8 bg-slate-100 rounded-full text-slate-500 active:scale-90 flex items-center justify-center"><i data-lucide="x" class="w-4 h-4"></i></button>
        </div>
        <div class="p-5 space-y-4">
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Vai trò</label>
                <select x-model="assignmentForm.role" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="r in roleDefs" :key="r.value">
                        <option :value="r.value" x-text="r.label + ' (' + r.scope + ')'"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'khối'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Khối</label>
                <select x-model="assignmentForm.blockId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="b in blocks" :key="b">
                        <option :value="blockIdByName(b)" x-text="b"></option>
                    </template>
                </select>
            </div>
            <div x-show="roleScope(assignmentForm.role) === 'lớp'">
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Lớp</label>
                <select x-model="assignmentForm.classId" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
                    <template x-for="c in classes" :key="c.id">
                        <option :value="c.id" x-text="c.name + ' (' + c.block + ')'"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-micro font-bold text-slate-500 uppercase mb-1">Ghi chú</label>
                <input x-model="assignmentForm.note" type="text" placeholder="Lý do phân công..."
                       class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" x-model="assignmentForm.isPrimary" class="w-4 h-4 rounded">
                Đặt làm phân công chính
            </label>
        </div>
        <div class="p-4 border-t border-slate-100">
            <button @click="saveAssignment()" class="w-full bg-blue-600 text-white font-bold py-3.5 rounded-2xl active:scale-[0.98] transition-transform shadow-md shadow-blue-200 flex justify-center items-center">
                <i data-lucide="save" class="w-5 h-5 mr-2"></i> Lưu phân công
            </button>
        </div>
    </div>
</div>
```

- [ ] **Step 3: Alpine methods**

Trong `public/assets/js/modules/org.js`, thêm vào Alpine object:

```javascript
// State
memberAssignments: {},     // {memberId: [...]}
showAssignmentModal: false,
assignmentForm: { role: '', blockId: '', classId: '', note: '', isPrimary: false },

// Methods
async openAddAssignment(member) {
    this.assignmentForm = {
        memberId: member.id,
        role: 'glv',
        blockId: '',
        classId: '',
        note: '',
        isPrimary: false,
    };
    await this.loadMemberAssignments(member.id);
    this.showAssignmentModal = true;
},

async loadMemberAssignments(memberId) {
    const r = await fetch(`/tntt/public/api/assignments.php?action=list&memberId=${memberId}`, {
        credentials: 'include'
    }).then(r => r.json());
    if (r.ok) {
        this.memberAssignments = { ...this.memberAssignments, [memberId]: r.assignments };
    }
},

async saveAssignment() {
    const r = await fetch('/tntt/public/api/assignments.php?action=create', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(this.assignmentForm)
    }).then(r => r.json());
    if (r.ok) {
        this.showAssignmentModal = false;
        await this.loadMemberAssignments(this.assignmentForm.memberId);
        toast.success('Đã thêm phân công');
    } else {
        toast.error(r.error || 'Lỗi');
    }
},

async endAssignment(a) {
    if (!confirm('Kết thúc phân công này?')) return;
    const r = await fetch('/tntt/public/api/assignments.php?action=end', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ assignmentId: a.id })
    }).then(r => r.json());
    if (r.ok) {
        await this.loadMemberAssignments(a.memberId);
        toast.success('Đã kết thúc phân công');
    }
},

async setPrimaryAssignment(a) {
    const r = await fetch('/tntt/public/api/assignments.php?action=set_primary', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ assignmentId: a.id })
    }).then(r => r.json());
    if (r.ok) {
        await this.loadMemberAssignments(a.memberId);
        toast.success('Đã đặt làm phân công chính');
    }
},
```

- [ ] **Step 4: Test trong browser**

```bash
# Mở trang staff trên browser, click vào một member, kiểm tra:
# - Phân công chính hiện với dấu ★
# - Click "Thêm" → modal hiện ra
# - Chọn vai trò → các field scope tương ứng hiện ra
# - Lưu → danh sách cập nhật
```

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add views/module_staff.php public/assets/js/modules/org.js && git commit -m "feat(ui): add phân công tab in staff module with add/end/set-primary actions"
```

---

### Task 7: Permission check qua assignments

**Files:**
- Modify: `public/api/_bootstrap.php`
- Modify: `views/module_profile.php`

**Interfaces:**
- Consumes: `has_active_role()`, `effective_assignments()`
- Produces:
  - `permission_of_module($me, $moduleKey)` — check quyền dựa trên TẤT CẢ active assignments
  - UI hiển thị tất cả vai trò đang active trong profile

- [ ] **Step 1: Cập nhật permission_of**

Trong `public/api/_bootstrap.php`, tìm hàm `permission_of()`. Sửa để check qua TẤT CẢ active roles:

```php
function permission_of(string $moduleKey): string
{
    $me = current_member();
    if (!$me) return 'none';

    // Lấy tất cả active roles (qua assignments)
    $activeRoles = array_column(effective_assignments((int) $me['id']), 'role_code');

    // Fallback về role_code trong members nếu assignments rỗng (edge case migration)
    if (empty($activeRoles)) {
        $activeRoles = [$me['role_code']];
    }

    $ph = implode(',', array_fill(0, count($activeRoles), '?'));
    $row = db_one(
        "SELECT MAX(level) AS max_level FROM permissions
          WHERE module_key = ? AND role_code IN ($ph)",
        array_merge([$moduleKey], $activeRoles)
    );
    return $row['max_level'] ?? 'none';
}
```

- [ ] **Step 2: Hiển thị trong profile**

Trong `views/module_profile.php`, thêm section:

```html
<div class="bg-white rounded-card p-5 shadow-sm border border-slate-100">
    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Phân công của bạn</h3>
    <div class="space-y-2" x-data="{
        items: window.TNTT_BOOT?.assignments || []
    }">
        <template x-for="a in items" :key="a.id">
            <div class="flex items-center gap-2 bg-slate-50 rounded-xl px-3 py-2">
                <span class="text-micro font-bold uppercase px-2 py-0.5 rounded border"
                      :class="roleChipClass(a.role)" x-text="roleLabel(a.role)"></span>
                <span class="text-micro text-slate-600 flex-1"
                      x-text="(a.blockName || a.className || 'toàn đoàn')"></span>
                <span x-show="a.isPrimary" class="text-micro font-black text-amber-600">Phân công chính</span>
            </div>
        </template>
        <p x-show="items.length === 0" class="text-sm text-slate-400 italic">Chưa có phân công nào.</p>
    </div>
</div>
```

- [ ] **Step 3: Test permission đa vai trò**

Tạo test trong `tests/unit/PermissionTest.php`:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase {
    public function test_admin_keeps_admin_rights_via_assignment(): void {
        $adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];

        $_SESSION = ['member_id' => $adminId];
        $assignments = effective_assignments($adminId);

        $roles = array_column($assignments, 'role_code');
        $this->assertContains('admin', $roles, 'Admin có assignment active');
    }

    public function test_member_with_two_roles_sees_both(): void {
        $adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];

        // Tạo test member
        $phone = '09' . random_int(10000000, 99999999);
        $mid = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('PERM_TEST', 'Perm Test', ?, ?, 'glv')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );

        // Thêm 2 phân công
        db_run("INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
                VALUES (?, 'glv', 1, CURDATE(), ?)", [$mid, $adminId]);
        db_run("INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
                VALUES (?, 'truong_khoi', 0, CURDATE(), ?)", [$mid, $adminId]);

        $_SESSION = ['member_id' => $mid];
        $assignments = effective_assignments($mid);
        $roles = array_column($assignments, 'role_code');

        $this->assertCount(2, $assignments, 'Member có 2 active assignments');
        $this->assertContains('glv', $roles);
        $this->assertContains('truong_khoi', $roles);

        db_run("DELETE FROM member_assignments WHERE member_id = ?", [$mid]);
        db_run("DELETE FROM members WHERE id = ?", [$mid]);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar
```

Expected: All tests pass

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/_bootstrap.php views/module_profile.php tests/unit/PermissionTest.php && git commit -m "feat(permissions): check rights via all active assignments + show in profile"
```

---

### Task 8: Documentation & Final Verification

**Files:**
- Create: `docs/features/kiem-nhiem.md`

**Interfaces:**
- Tài liệu hướng dẫn sử dụng cho BĐH

- [ ] **Step 1: Viết tài liệu**

Tạo `docs/features/kiem-nhiem.md`:

```markdown
# Phân Công Kiêm Nhiệm

## Bối cảnh

Trước đây mỗi thành viên chỉ có **1 vai trò chính** + **1 khối/lớp duy nhất**. Khi một GLV kiêm nhiệm nhiều lớp hoặc một Trưởng Khối cũng là GLV lớp khác, hệ thống không xử lý được.

## Giải pháp

Bảng `member_assignments` cho phép:
- **Nhiều vai trò** đồng thời: VD anh A vừa là Trưởng Khối vừa là GLV
- **Nhiều phạm vi** đồng thời: VD chị B kiêm GLV 2 lớp
- **Lịch sử phân công**: biết ai từng phụ trách lớp nào, khi nào bắt đầu/kết thúc
- **Phân công chính** (`is_primary`): vai trò mặc định khi đăng nhập, dùng cho permission hệ thống

## Cách dùng

### Thêm phân công mới

1. Vào **Nhân sự** (module staff)
2. Chọn thành viên cần phân công
3. Cuộn xuống phần **Phân công** → bấm **Thêm**
4. Chọn:
   - **Vai trò**: GLV / Trưởng Khối / BĐH / ...
   - **Khối** hoặc **Lớp** (tùy scope)
   - **Ghi chú** (lý do phân công, optional)
   - **Đặt làm phân công chính** (optional)
5. Bấm **Lưu**

### Kết thúc phân công

1. Trong danh sách phân công của thành viên
2. Bấm biểu tượng ✕ cạnh phân công cần kết thúc
3. Hệ thống set `to_date = hôm nay`, giữ lại lịch sử

### Xem lịch sử

Vào **Nhân sự** → chọn thành viên → cuộn xuống **Phân công**. Cả phân công active và đã kết thúc đều hiển thị.

## Quy tắc nghiệp vụ

| Quy tắc | Xử lý |
|---------|--------|
| Mỗi thành viên có tối đa 1 phân công primary | Hệ thống tự gỡ primary cũ khi đặt primary mới |
| Phân công đầu tiên tự động là primary | `is_primary = 1` khi tạo record đầu tiên |
| Role cần khối → bắt buộc chọn khối | API validate scope |
| Role cần lớp → bắt buộc chọn lớp | API validate scope |
| Xóa phân công | Chỉ xóa được khi đã `to_date != null` (để giữ audit trail) |

## Tác động kỹ thuật

- **Bảng mới**: `member_assignments` (tham chiếu members, roles, blocks, classes)
- **Backfill tự động**: khi chạy migration lần đầu, mọi member hiện tại có 1 record primary
- **Tương thích ngược**: `members.role_code` / `block_id` / `class_id` vẫn là phân công chính
- **Permission**: check theo TẤT CẢ active roles (không chỉ primary)
- **Audit**: ai phân công, khi nào, lý do — đầy đủ trong `assigned_by`, `note`, `from_date`, `to_date`
```

- [ ] **Step 2: Final test run**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar
```

Expected: All tests pass (13 cũ + 6 mới = 19 tests)

- [ ] **Step 3: Manual end-to-end test**

Test các flow:
1. Login admin
2. Vào Nhân sự → mở 1 thành viên
3. Click **Thêm phân công** → chọn vai trò khác → lưu
4. Verify danh sách hiện 2 phân công
5. Click **Kết thúc** trên phân công phụ
6. Verify phân công phụ hiển thị `to_date`, primary không đổi
7. Click **Đặt primary** trên phân công khác
8. Verify primary chuyển đúng

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add docs/features/kiem-nhiem.md && git commit -m "docs: add user guide for phân công kiêm nhiệm feature"
```

---

## Tổng kết

| Task | Mô tả | Files |
|------|--------|-------|
| 1 | Tạo bảng + migration | `config/schema.sql`, `scripts/add_member_assignments.php` |
| 2 | Schema tests | `tests/unit/AssignmentTest.php` |
| 3 | Helper functions | `public/api/_bootstrap.php` |
| 4 | CRUD API | `public/api/assignments.php` |
| 5 | Truyền data cho Alpine | `public/api/_bootstrap_page.php`, `public/assets/js/modules/core.js` |
| 6 | UI module_staff | `views/module_staff.php`, `public/assets/js/modules/org.js` |
| 7 | Permission đa vai trò | `public/api/_bootstrap.php`, `views/module_profile.php`, `tests/unit/PermissionTest.php` |
| 8 | Documentation | `docs/features/kiem-nhiem.md` |

**Tổng commits: 8**
**Tests: 6 mới (AssignmentTest + PermissionTest)**
**Tương thích ngược: members table không đổi**
