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
}
