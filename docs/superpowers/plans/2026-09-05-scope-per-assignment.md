# Phạm Vi Quyền Theo Từng Phân Công (Per-Assignment Scope) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho người kiêm nhiệm thực sự thao tác được trên mọi lớp/khối mình được phân công, đồng thời chặn lỗ hổng "leo thang quyền" khi ghép cấp quyền của vai trò này với phạm vi của vai trò khác.

**Architecture:** Thêm một tầng primitive trong `_common.php` đánh giá quyền **theo từng dòng `member_assignments`** (ghép chặt vai-trò × phạm-vi × cấp-quyền × module). Các điểm **ghi/duyệt/export** chuyển sang gọi `can_access_class()` (per-assignment, chống leo thang). Các điểm **xem danh sách hồ sơ mình phụ trách** chuyển sang hợp phạm vi thuần trên toàn bộ phân công active. Bảng `members` vẫn là fallback khi chưa có assignment (tương thích ngược).

**Tech Stack:** PHP 8.2, MySQL/MariaDB (InnoDB, utf8mb4), PDO, PHPUnit 10.

**Spec:** Brainstorming trong hội thoại — chọn Phương án B (per-assignment capability check). Bối cảnh: [docs/features/kiem-nhiem.md](../../features/kiem-nhiem.md) và plan gốc [2026-08-31-member-assignments.md](2026-08-31-member-assignments.md).

## Global Constraints

- Mọi endpoint dùng `_bootstrap.php`; POST phải `require_csrf()`.
- Không phá tương thích ngược: `members.role_code/block_id/class_id` vẫn là phân công chính, dùng làm **fallback** khi `member_assignments` rỗng.
- Hàm PHP: snake_case. Chuỗi UI/tiếng Việt giữ nguyên dấu, dùng `JSON_UNESCAPED_UNICODE` (đã có sẵn trong `json_out`).
- Enum cấp quyền: `none < view < edit`. Enum phạm vi: `toàn đoàn`, `khối`, `lớp` (đúng chính tả có dấu — so sánh chuỗi phải khớp tuyệt đối).
- Mỗi task có test chạy trước khi commit. Chạy test: `php phpunit10.phar`.
- DB helpers có sẵn: `db_one($sql,$params)`, `db_all(...)`, `db_run(...)`, `db_insert(...)`. `json_fail($msg,$code=400)`, `json_out($arr)`.
- `effective_assignments($memberId)` (trong `public/api/_common.php`) trả mỗi dòng active kèm `role_code`, `role_scope`, `block_id`, `class_id`.

---

## File Structure

| File | Trách nhiệm |
|------|-------------|
| `public/api/_common.php` | **Mới**: `level_rank`, `permission_of_role`, `assignment_covers_class`, `member_scopes`, `can_access_class`, `accessible_class_ids`, `responsible_class_ids` |
| `public/api/_bootstrap.php` | `allowed_class_ids()` / `scan_class_ids()` viết lại để hợp phạm vi trên mọi phân công active |
| `public/api/attendance.php` | Cổng ghi "chạm tay" dùng `can_access_class(...,'edit')` |
| `public/api/leave.php` | Duyệt/từ chối đơn dùng `can_access_class(...,'edit')` |
| `public/api/export.php` | Thêm cổng quyền + giới hạn phạm vi (đang hở hoàn toàn) |
| `tests/unit/ScopeTest.php` | **Mới**: unit test cho primitive + ca leo thang |
| `docs/features/kiem-nhiem.md` | Bổ sung mục "Phạm vi theo phân công" |

---

### Task 1: Primitive per-assignment trong _common.php

**Files:**
- Modify: `public/api/_common.php` (thêm sau `primary_assignment()`, kết thúc dòng 118)
- Test: `tests/unit/ScopeTest.php`

**Interfaces:**
- Consumes: `effective_assignments()`, bảng `permissions(module_key, role_code, level)`, bảng `classes(id, block_id)`.
- Produces:
  - `level_rank(string $level): int` — none=0, view=1, edit=2
  - `permission_of_role(string $roleCode, string $moduleKey): string`
  - `assignment_covers_class(array $a, int $classId): bool`
  - `member_scopes(array $me): array` — assignments active, fallback về members
  - `can_access_class(array $me, string $moduleKey, int $classId, string $need = 'view'): bool`
  - `accessible_class_ids(array $me, string $moduleKey, string $need = 'view'): ?array`
  - `responsible_class_ids(array $me): ?array` — hợp phạm vi thuần, không xét module

- [ ] **Step 1: Viết test đỏ cho `assignment_covers_class` + ca leo thang**

Tạo `tests/unit/ScopeTest.php`:

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScopeTest extends TestCase
{
    private int $memberId = 0;
    private int $adminId  = 0;
    private array $classA;   // lớp thuộc khối A
    private array $classB;   // lớp thuộc khối B (khác khối A)
    private array $savedPerms = [];

    protected function setUp(): void
    {
        $this->adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];

        $this->classA = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL LIMIT 1");
        $this->classB = db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? LIMIT 1",
            [$this->classA['block_id']]
        );
        if (!$this->classA || !$this->classB) {
            $this->markTestSkipped('Cần ít nhất 2 lớp ở 2 khối khác nhau.');
        }

        $phone = '09' . random_int(10000000, 99999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('SCOPE_TEST', 'Scope Test', ?, ?, 'glv')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );
    }

    protected function tearDown(): void
    {
        if ($this->memberId) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
        // Khôi phục permissions đã đổi trong test
        foreach ($this->savedPerms as $p) {
            if ($p['level'] === null) {
                db_run("DELETE FROM permissions WHERE module_key=? AND role_code=?", [$p['mod'], $p['role']]);
            } else {
                db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE level = VALUES(level)", [$p['mod'], $p['role'], $p['level']]);
            }
        }
    }

    /** Ghi đè tạm cấp quyền của một vai trò trên một module, nhớ để khôi phục */
    private function setPerm(string $mod, string $role, string $level): void
    {
        $old = db_one("SELECT level FROM permissions WHERE module_key=? AND role_code=?", [$mod, $role]);
        $this->savedPerms[] = ['mod' => $mod, 'role' => $role, 'level' => $old['level'] ?? null];
        db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = VALUES(level)", [$mod, $role, $level]);
    }

    private function addAssignment(string $role, ?int $blockId, ?int $classId): void
    {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, ?, ?, ?, 0, CURDATE(), ?)",
            [$this->memberId, $role, $blockId, $classId, $this->adminId]
        );
    }

    public function test_assignment_covers_class_by_scope(): void
    {
        $lop = ['role_scope' => 'lớp', 'block_id' => null, 'class_id' => $this->classA['id']];
        $this->assertTrue(assignment_covers_class($lop, (int) $this->classA['id']));
        $this->assertFalse(assignment_covers_class($lop, (int) $this->classB['id']));

        $khoi = ['role_scope' => 'khối', 'block_id' => $this->classA['block_id'], 'class_id' => null];
        $this->assertTrue(assignment_covers_class($khoi, (int) $this->classA['id']));
        $this->assertFalse(assignment_covers_class($khoi, (int) $this->classB['id']));

        $doan = ['role_scope' => 'toàn đoàn', 'block_id' => null, 'class_id' => null];
        $this->assertTrue(assignment_covers_class($doan, (int) $this->classB['id']));
    }

    public function test_kiem_nhiem_two_classes_can_edit_both(): void
    {
        $this->setPerm('attendance', 'glv', 'edit');
        $this->addAssignment('glv', null, (int) $this->classA['id']);
        $this->addAssignment('glv', null, (int) $this->classB['id']);

        $me = ['id' => $this->memberId, 'role_code' => 'glv', 'role_scope' => 'lớp',
               'block_id' => null, 'class_id' => $this->classA['id']];

        $this->assertTrue(can_access_class($me, 'attendance', (int) $this->classA['id'], 'edit'));
        $this->assertTrue(can_access_class($me, 'attendance', (int) $this->classB['id'], 'edit'),
            'Kiêm nhiệm lớp thứ hai phải sửa được lớp đó');
    }

    public function test_no_privilege_escalation_across_assignments(): void
    {
        // Trưởng khối: phủ CẢ khối A nhưng attendance chỉ 'view'
        $this->setPerm('attendance', 'truong_khoi', 'view');
        // GLV: 'edit' nhưng chỉ phủ 1 lớp ở khối B
        $this->setPerm('attendance', 'glv', 'edit');

        $this->addAssignment('truong_khoi', (int) $this->classA['block_id'], null);
        $this->addAssignment('glv', null, (int) $this->classB['id']);

        $me = ['id' => $this->memberId, 'role_code' => 'glv', 'role_scope' => 'lớp',
               'block_id' => null, 'class_id' => $this->classB['id']];

        // Lớp khối A: chỉ có truong_khoi phủ, mà truong_khoi chỉ 'view' → KHÔNG được edit
        $this->assertFalse(can_access_class($me, 'attendance', (int) $this->classA['id'], 'edit'),
            'Không được ghép edit-của-GLV với phạm-vi-của-Trưởng-Khối');
        // Lớp khối B: GLV có edit và phủ đúng lớp → được
        $this->assertTrue(can_access_class($me, 'attendance', (int) $this->classB['id'], 'edit'));
        // Xem thì cả hai đều được (truong_khoi view phủ khối A)
        $this->assertTrue(can_access_class($me, 'attendance', (int) $this->classA['id'], 'view'));
    }

    public function test_accessible_class_ids_unrestricted_for_toan_doan(): void
    {
        $this->setPerm('scores', 'admin', 'edit');
        $this->addAssignment('admin', null, null); // admin scope = toàn đoàn (theo seed)
        $me = ['id' => $this->memberId, 'role_code' => 'admin', 'role_scope' => 'toàn đoàn',
               'block_id' => null, 'class_id' => null];
        $this->assertNull(accessible_class_ids($me, 'scores', 'view'),
            'toàn đoàn → null = không giới hạn');
    }
}
```

- [ ] **Step 2: Chạy test — kỳ vọng FAIL (hàm chưa tồn tại)**

Run: `php phpunit10.phar --filter ScopeTest`
Expected: FAIL — "Call to undefined function assignment_covers_class()"

- [ ] **Step 3: Cài primitive vào `_common.php`**

Mở `public/api/_common.php`, thêm sau hàm `primary_assignment()` (sau dòng 118):

```php

/* ============================================================================
   QUYỀN THEO TỪNG PHÂN CÔNG (per-assignment)

   Nguyên tắc: KHÔNG lấy max cấp-quyền của mọi vai trò rồi ghép với hợp
   phạm-vi của mọi vai trò — làm vậy sẽ "lai" thành quyền không vai trò nào
   thực có (leo thang). Thay vào đó, mỗi dòng phân công được xét như một đơn
   vị: chỉ khi CÙNG một dòng vừa đủ cấp-quyền trên module vừa phủ được lớp
   thì mới cho phép.
   ========================================================================== */

/** Thứ hạng cấp quyền để so sánh: none < view < edit */
function level_rank(string $level): int
{
    return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
}

/** Cấp quyền của MỘT vai trò trên MỘT module */
function permission_of_role(string $roleCode, string $moduleKey): string
{
    $row = db_one(
        'SELECT level FROM permissions WHERE module_key = ? AND role_code = ?',
        [$moduleKey, $roleCode]
    );
    return $row['level'] ?? 'none';
}

/** Một dòng phân công (có role_scope/block_id/class_id) có phủ lớp này không */
function assignment_covers_class(array $a, int $classId): bool
{
    switch ($a['role_scope'] ?? '') {
        case 'toàn đoàn':
            return true;
        case 'khối':
            if (empty($a['block_id'])) return false;
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [$classId]);
            return $c && (int) $c['block_id'] === (int) $a['block_id'];
        case 'lớp':
            return !empty($a['class_id']) && (int) $a['class_id'] === $classId;
    }
    return false;
}

/** Các phân công active; nếu chưa có thì suy từ members (tương thích ngược) */
function member_scopes(array $me): array
{
    $rows = effective_assignments((int) $me['id']);
    if (!empty($rows)) return $rows;

    return [[
        'role_code'  => $me['role_code'],
        'role_scope' => $me['role_scope'] ?? 'lớp',
        'block_id'   => $me['block_id'] ?? null,
        'class_id'   => $me['class_id'] ?? null,
    ]];
}

/** Có được thao tác (need) trên module cho MỘT lớp cụ thể không — chống leo thang */
function can_access_class(array $me, string $moduleKey, int $classId, string $need = 'view'): bool
{
    $needRank = level_rank($need);
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        if (assignment_covers_class($a, $classId)) return true;
    }
    return false;
}

/** Tập lớp được (need) trên module — null nghĩa là không giới hạn (toàn đoàn) */
function accessible_class_ids(array $me, string $moduleKey, string $need = 'view'): ?array
{
    $needRank = level_rank($need);
    $ids = [];
    foreach (member_scopes($me) as $a) {
        if (level_rank(permission_of_role($a['role_code'], $moduleKey)) < $needRank) continue;
        switch ($a['role_scope'] ?? '') {
            case 'toàn đoàn':
                return null;
            case 'khối':
                if (!empty($a['block_id'])) {
                    $ids = array_merge($ids, array_column(
                        db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id'));
                }
                break;
            case 'lớp':
                if (!empty($a['class_id'])) $ids[] = (int) $a['class_id'];
                break;
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}

/** Hợp phạm vi THUẦN (không xét module) — dùng cho ranh giới XEM hồ sơ mình phụ trách */
function responsible_class_ids(array $me): ?array
{
    $ids = [];
    foreach (member_scopes($me) as $a) {
        switch ($a['role_scope'] ?? '') {
            case 'toàn đoàn':
                return null;
            case 'khối':
                if (!empty($a['block_id'])) {
                    $ids = array_merge($ids, array_column(
                        db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id'));
                }
                break;
            case 'lớp':
                if (!empty($a['class_id'])) $ids[] = (int) $a['class_id'];
                break;
        }
    }
    return array_values(array_unique(array_map('intval', $ids)));
}
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php phpunit10.phar --filter ScopeTest`
Expected: PASS (4 test; test có thể SKIP nếu DB chưa đủ 2 khối)

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/_common.php tests/unit/ScopeTest.php && git commit -m "feat(perms): add per-assignment scope primitives (can_access_class)"
```

---

### Task 2: `allowed_class_ids` / `scan_class_ids` hiểu kiêm nhiệm

**Files:**
- Modify: `public/api/_bootstrap.php:229-243` (`allowed_class_ids`)
- Modify: `public/api/_bootstrap.php:259-272` (`scan_class_ids`)

**Interfaces:**
- Consumes: `responsible_class_ids()`, `member_scopes()`.
- Produces: cùng chữ ký cũ `?array` (null = không giới hạn) nhưng hợp trên mọi phân công active.

- [ ] **Step 1: Thay thân `allowed_class_ids`**

Trong `public/api/_bootstrap.php`, thay khối hàm hiện tại:

```php
function allowed_class_ids(array $me): ?array
{
    $scope = $me['role_scope'] ?? 'lớp';

    if ($scope === 'toàn đoàn') return null;             // không giới hạn

    if ($scope === 'khối') {
        if (empty($me['block_id'])) return [];            // chưa phân khối thì không ghi được gì
        return array_map('intval', array_column(
            db_all('SELECT id FROM classes WHERE block_id = ?', [$me['block_id']]), 'id'));
    }

    // phạm vi lớp
    return empty($me['class_id']) ? [] : [(int) $me['class_id']];
}
```

bằng:

```php
function allowed_class_ids(array $me): ?array
{
    // Ranh giới XEM hồ sơ: mọi lớp/khối mình được phân công (kể cả kiêm nhiệm).
    // Chỉ xét phạm vi, không xét module — GLV vẫn xem được hồ sơ lớp mình dù
    // không có quyền quản trị bảng thiếu nhi.
    return responsible_class_ids($me);
}
```

- [ ] **Step 2: Thay thân `scan_class_ids` để hợp khối trên mọi phân công**

Thay khối hàm `scan_class_ids` (dòng 259-272) bằng:

```php
function scan_class_ids(array $me): ?array
{
    if (in_array($me['role_code'] ?? '', ['admin', 'bdh'], true)) return null;

    $blockIds = [];
    foreach (member_scopes($me) as $a) {
        if (($a['role_scope'] ?? '') === 'toàn đoàn') return null;
        if (!empty($a['block_id'])) {
            $blockIds[] = (int) $a['block_id'];
        } elseif (!empty($a['class_id'])) {
            $c = db_one('SELECT block_id FROM classes WHERE id = ?', [(int) $a['class_id']]);
            if ($c && $c['block_id']) $blockIds[] = (int) $c['block_id'];
        }
    }
    if (!$blockIds) return [];

    $ph = implode(',', array_fill(0, count($blockIds), '?'));
    return array_map('intval', array_column(
        db_all("SELECT id FROM classes WHERE block_id IN ($ph)", $blockIds), 'id'));
}
```

- [ ] **Step 3: Viết test cho hợp phạm vi**

Thêm vào `tests/unit/ScopeTest.php` (trước `}` cuối class):

```php
public function test_allowed_class_ids_unions_all_assignments(): void
{
    require_once __DIR__ . '/../../public/api/_bootstrap.php';

    $this->addAssignment('glv', null, (int) $this->classA['id']);
    $this->addAssignment('glv', null, (int) $this->classB['id']);

    $me = ['id' => $this->memberId, 'role_code' => 'glv', 'role_scope' => 'lớp',
           'block_id' => null, 'class_id' => $this->classA['id']];

    $ids = allowed_class_ids($me);
    $this->assertContains((int) $this->classA['id'], $ids);
    $this->assertContains((int) $this->classB['id'], $ids, 'Lớp kiêm nhiệm phải nằm trong phạm vi xem');
}
```

- [ ] **Step 4: Chạy test — kỳ vọng PASS**

Run: `php phpunit10.phar --filter ScopeTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/_bootstrap.php tests/unit/ScopeTest.php && git commit -m "feat(perms): make allowed/scan class scopes kiêm-nhiệm aware"
```

---

### Task 3: Cổng ghi điểm danh "chạm tay" theo per-assignment

**Files:**
- Modify: `public/api/attendance.php:165-171`

**Interfaces:**
- Consumes: `can_access_class($me, 'attendance', $classId, 'edit')`.

- [ ] **Step 1: Thay cổng phạm vi ở nhánh chạm tay**

Trong `public/api/attendance.php`, thay:

```php
// Chạm tay chỉ trong phạm vi mình THẤY được (GLV: lớp mình, Trưởng Khối:
// khối mình) — khớp đúng danh sách hiện trên màn hình. Khác với quét QR,
// vốn rộng ra cả khối vì lúc đó các em xếp hàng theo khối.
$duocSua = allowed_class_ids($me);
if ($duocSua !== null && !in_array((int) $st['class_id'], $duocSua, true)) {
    json_fail('Bạn không phụ trách lớp của em ' . $st['full_name'] . '.', 403);
}
```

bằng:

```php
// Chạm tay = GHI. Xét theo TỪNG phân công: phải có một vai trò vừa được
// 'edit' điểm danh vừa phủ đúng lớp của em này. Tránh ghép 'edit' của vai
// trò lớp khác với phạm vi rộng của vai trò chỉ được xem.
if (!can_access_class($me, 'attendance', (int) $st['class_id'], 'edit')) {
    json_fail('Bạn không phụ trách lớp của em ' . $st['full_name'] . '.', 403);
}
```

- [ ] **Step 2: Kiểm tra cú pháp**

Run: `php -l "G:/xampp/htdocs/tntt/public/api/attendance.php"`
Expected: "No syntax errors detected"

- [ ] **Step 3: Test end-to-end thủ công (đăng nhập GLV kiêm 2 lớp)**

```bash
cd "G:/xampp/htdocs/tntt" && php phpunit10.phar --filter ScopeTest
```

Expected: PASS (logic đã phủ bởi `can_access_class`; endpoint chỉ gọi lại)

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/attendance.php && git commit -m "fix(attendance): gate touch-mark by per-assignment edit scope"
```

---

### Task 4: Duyệt đơn nghỉ phép theo per-assignment

**Files:**
- Modify: `public/api/leave.php:107-114`

**Interfaces:**
- Consumes: `can_access_class($me, 'leave', $classId, 'edit')`.

- [ ] **Step 1: Thay cổng phạm vi duyệt đơn**

Trong `public/api/leave.php`, thay:

```php
        // Chỉ duyệt được đơn trong phạm vi quản lý của mình
        $scope = db_one('SELECT r.scope FROM roles r WHERE r.code = ?', [$me['role_code']])['scope'];
        if ($scope === 'lớp' && (int) $req['class_id'] !== (int) $me['class_id']) {
            json_fail('Đơn này không thuộc lớp bạn phụ trách.', 403);
        }
        if ($scope === 'khối' && (int) $req['block_id'] !== (int) $me['block_id']) {
            json_fail('Đơn này không thuộc khối bạn phụ trách.', 403);
        }
```

bằng:

```php
        // Chỉ duyệt được đơn của lớp mình thực sự phụ trách (xét theo TỪNG
        // phân công: một vai trò vừa được 'edit' đơn phép vừa phủ lớp em đó).
        if (!can_access_class($me, 'leave', (int) $req['class_id'], 'edit')) {
            json_fail('Đơn này không thuộc phạm vi bạn phụ trách.', 403);
        }
```

- [ ] **Step 2: Kiểm tra cú pháp**

Run: `php -l "G:/xampp/htdocs/tntt/public/api/leave.php"`
Expected: "No syntax errors detected"

- [ ] **Step 3: Chạy toàn bộ test**

Run: `php phpunit10.phar`
Expected: PASS

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/leave.php && git commit -m "fix(leave): gate approval by per-assignment edit scope"
```

---

### Task 5: Bịt lỗ hổng phạm vi ở export.php

**Files:**
- Modify: `public/api/export.php` (nhánh `report`, `attendance`, `scores`)

**Interfaces:**
- Consumes: `accessible_class_ids($me, $module, 'view')`, `can_access_class($me, $module, $classId, 'view')`.
- Ghi chú: export hiện chỉ `require_login()` → ai đăng nhập cũng tải được cả đoàn. Thêm cổng phạm vi (đọc nên dùng `view`).

- [ ] **Step 1: Nhánh `report` — chặn theo lớp của em**

Trong `public/api/export.php` nhánh `case 'report':`, ngay sau khối lấy `$student` (sau dòng `if (!$student) json_fail(...)`), thêm:

```php
        // Phiếu liên lạc chứa điểm — chặn theo phạm vi 'scores' của lớp em này
        $enr = db_one('SELECT class_id FROM enrollments WHERE year_id = ? AND student_id = ?',
                      [$year['id'], $studentId]);
        if (!$enr || !can_access_class($me, 'scores', (int) $enr['class_id'], 'view')) {
            json_fail('Bạn không phụ trách lớp của em này.', 403);
        }
```

- [ ] **Step 2: Nhánh `attendance` — giới hạn tập lớp export**

Thay khối:

```php
    case 'attendance':
        $classId = (int) ($in['classId'] ?? 0);

        // Get students
        $dk = '';
        $params = [$year['id']];
        if ($classId > 0) {
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        }
```

bằng:

```php
    case 'attendance':
        $classId = (int) ($in['classId'] ?? 0);
        $allow   = accessible_class_ids($me, 'attendance', 'view'); // null = toàn đoàn

        $dk = '';
        $params = [$year['id']];
        if ($classId > 0) {
            if ($allow !== null && !in_array($classId, $allow, true)) {
                json_fail('Bạn không phụ trách lớp này.', 403);
            }
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        } elseif ($allow !== null) {
            // Không chỉ định lớp: chỉ export các lớp trong phạm vi
            if (!$allow) json_fail('Bạn chưa được phân công lớp nào.', 403);
            $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $params = array_merge($params, $allow);
        }
```

- [ ] **Step 3: Nhánh `scores` — giới hạn tập lớp export**

Thay khối:

```php
        // Get students
        $dk = '';
        $params = [$year['id'], $termId];
        if ($classId > 0) {
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        }
```

(trong `case 'scores':`) bằng:

```php
        // Get students — chặn theo phạm vi 'scores'
        $allow = accessible_class_ids($me, 'scores', 'view'); // null = toàn đoàn
        $dk = '';
        $params = [$year['id'], $termId];
        if ($classId > 0) {
            if ($allow !== null && !in_array($classId, $allow, true)) {
                json_fail('Bạn không phụ trách lớp này.', 403);
            }
            $dk = ' AND e.class_id = ?';
            $params[] = $classId;
        } elseif ($allow !== null) {
            if (!$allow) json_fail('Bạn chưa được phân công lớp nào.', 403);
            $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($allow), '?')) . ')';
            $params = array_merge($params, $allow);
        }
```

- [ ] **Step 4: Kiểm tra cú pháp**

Run: `php -l "G:/xampp/htdocs/tntt/public/api/export.php"`
Expected: "No syntax errors detected"

- [ ] **Step 5: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add public/api/export.php && git commit -m "fix(export): enforce per-assignment view scope on report/attendance/scores"
```

---

### Task 6: Tài liệu + kiểm thử tổng

**Files:**
- Modify: `docs/features/kiem-nhiem.md`

- [ ] **Step 1: Bổ sung mục phạm vi vào tài liệu**

Thêm vào cuối `docs/features/kiem-nhiem.md`:

```markdown

## Phạm vi theo phân công

Quyền **thao tác dữ liệu** được xét theo TỪNG phân công đang hiệu lực, không
gộp chung:

- **Xem hồ sơ**: thấy mọi lớp/khối mình được phân công (kể cả kiêm nhiệm).
- **Ghi / duyệt / export**: chỉ được khi có **một vai trò** vừa đủ cấp quyền
  trên chức năng đó **vừa** phụ trách đúng lớp/khối liên quan.

Ví dụ chống nhầm quyền: một người là Trưởng Khối (được *xem* điểm danh cả
khối) kiêm GLV một lớp khác (được *sửa* điểm danh lớp mình) — người này
KHÔNG thể sửa điểm danh các lớp trong khối mình chỉ được xem.

Hàm nền: `can_access_class()`, `accessible_class_ids()` trong
`public/api/_common.php`.
```

- [ ] **Step 2: Chạy toàn bộ test**

Run: `php phpunit10.phar`
Expected: Tất cả PASS (bộ cũ + ScopeTest mới)

- [ ] **Step 3: Kiểm thử thủ công đầu-cuối**

1. Đăng nhập admin, phân cho một GLV thêm phân công lớp thứ 2 (khác khối).
2. Đăng nhập GLV đó → điểm danh lớp thứ 2 → phải ghi được.
3. Với một người chỉ *xem* được khối nhưng *sửa* được 1 lớp khác → thử sửa lớp trong khối chỉ-xem → phải bị 403.
4. Export điểm danh không chọn lớp bằng tài khoản GLV → chỉ ra lớp mình phụ trách.

- [ ] **Step 4: Commit**

```bash
cd "G:/xampp/htdocs/tntt" && git add docs/features/kiem-nhiem.md && git commit -m "docs: document per-assignment scope rules for kiêm nhiệm"
```

---

## Tác động UI downstream (Phase 2 — sẽ tách plan riêng)

Backend (Phase 1 trên) là ranh giới an toàn và ship độc lập được: UI hiển thị
thừa/thiếu lớp không tạo lỗ hổng (backend 403). Sau khi Phase 1 xong, cần một
plan UI để kiêm nhiệm dùng được trọn vẹn. Các điểm đã khảo sát:

| Điểm | File | Ripple | Hướng xử lý |
|------|------|--------|-------------|
| `myScopeLabel` | `public/assets/js/modules/core.js:198` | nhãn phạm vi giả định 1 lớp | ghép từ `assignments[]` → "Lớp A · Lớp B" |
| số em dashboard | `views/layout_hero.php:90` | `user.assignedClass ? myClassSize` | dùng `accessibleStudents.length` (đã theo scope backend) |
| `myClassSize` | `public/assets/js/modules/birthdays.js:44` | đếm theo 1 lớp | đếm theo `myClasses` |
| picker lớp | Khối/Lớp + Điểm danh | mặc định 1 lớp | liệt kê `myClasses`, mặc định = primary |
| Cá nhân | `views/module_profile.php:209` | **đã** liệt kê đủ | chỉ thêm dấu ★ primary |

**Kiến trúc Phase 2:** thêm getter dẫn xuất `myClasses` / `myScopeLabel` trong
`core.js` (đọc từ `assignments[]`), rồi mỗi module đổi 1 dòng để đọc nguồn này
— khoanh vùng thay đổi, tránh sửa rải rác.

## Self-Review

- **Spec coverage:** Phương án B (per-assignment) → Task 1 (primitive) + Task 3/4/5 (áp dụng ghi/duyệt/export). Lỗ hổng xem hồ sơ kiêm nhiệm → Task 2. Ca leo thang → test ở Task 1. Lỗ hổng export → Task 5. Đủ.
- **Placeholder scan:** mọi step có code thật hoặc lệnh thật; không có "TODO/tương tự".
- **Type consistency:** `can_access_class(array $me, string $moduleKey, int $classId, string $need)` dùng thống nhất ở Task 3/4/5; `accessible_class_ids(array,$module,$need): ?array` (null=không giới hạn) khớp cách gọi `!== null` ở export; `effective_assignments()` trả `role_scope/block_id/class_id` khớp `assignment_covers_class`.

## Rủi ro / lưu ý

- **Hiệu năng:** `can_access_class` gọi `permission_of_role` (1 query/dòng phân công). Số phân công/người rất nhỏ (≤ vài dòng) nên chấp nhận được; nếu cần, cache `effective_assignments` theo request (static) trong `member_scopes`.
- **Seed permissions:** test tự set/hoàn tác cấp quyền nên không phụ thuộc seed; nhưng nếu DB test thiếu 2 khối, các test scope sẽ **SKIP** (không FAIL).
- **`data.php`/`attendance lookup`** không đổi chữ ký `allowed_class_ids`/`scan_class_ids` nên không phải sửa call site — chỉ đổi thân hàm (Task 2).
