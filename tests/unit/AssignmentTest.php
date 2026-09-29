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

    public function test_enforce_single_primary_makes_other_assignments_non_primary(): void {
        $class1 = db_one("SELECT id FROM classes LIMIT 1");
        $block = db_one("SELECT id FROM blocks LIMIT 1");

        // Insert 2 assignments: first is primary
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', ?, 1, CURDATE(), ?)",
            [$this->testMemberId, $class1['id'], $this->adminId]
        );
        $assign1Id = (int) db_one(
            "SELECT id FROM member_assignments WHERE member_id = ? ORDER BY id LIMIT 1",
            [$this->testMemberId]
        )['id'];

        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, assigned_by)
             VALUES (?, 'truong_khoi', ?, 0, CURDATE(), ?)",
            [$this->testMemberId, $block['id'], $this->adminId]
        );
        $assign2Id = (int) db_one(
            "SELECT id FROM member_assignments WHERE member_id = ? ORDER BY id DESC LIMIT 1",
            [$this->testMemberId]
        )['id'];

        // enforce: make assign2 primary
        enforce_single_primary($this->testMemberId, $assign2Id);

        // Verify assign2 is now primary
        $assign2 = db_one("SELECT is_primary FROM member_assignments WHERE id = ?", [$assign2Id]);
        $assign1 = db_one("SELECT is_primary FROM member_assignments WHERE id = ?", [$assign1Id]);

        $this->assertEquals(1, $assign2['is_primary'], 'assign2 should be primary');
        $this->assertEquals(0, $assign1['is_primary'], 'assign1 should no longer be primary');
    }

    // ---- recompute_member_primary: member_assignments là nguồn thật ----

    private function addActive(string $role, ?int $blockId, ?int $classId): void {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, ?, ?, ?, 0, CURDATE(), ?)",
            [$this->testMemberId, $role, $blockId, $classId, $this->adminId]
        );
    }

    private function memberRow(): array {
        return db_one("SELECT role_code, block_id, class_id FROM members WHERE id = ?", [$this->testMemberId]);
    }

    public function test_recompute_keeps_du_bi_role_when_no_assignment_left(): void {
        db_run("UPDATE members SET role_code = 'du_bi' WHERE id = ?", [$this->testMemberId]);
        recompute_member_primary($this->testMemberId);
        $this->assertEquals('du_bi', $this->memberRow()['role_code'], 'Dự Bị không bị hạ thành GLV');
    }

    public function test_recompute_picks_highest_assignment_and_demotes_when_lost(): void {
        $class = db_one("SELECT id, block_id FROM classes LIMIT 1");
        $this->addActive('glv', (int) $class['block_id'], (int) $class['id']);
        $this->addActive('truong_khoi', (int) $class['block_id'], null);

        recompute_member_primary($this->testMemberId);
        $m = $this->memberRow();
        $this->assertEquals('truong_khoi', $m['role_code']);
        $this->assertEquals((int) $class['block_id'], (int) $m['block_id']);
        $this->assertNull($m['class_id']);

        db_run("UPDATE member_assignments SET to_date = CURDATE()
                 WHERE member_id = ? AND role_code = 'truong_khoi'", [$this->testMemberId]);
        recompute_member_primary($this->testMemberId);
        $m = $this->memberRow();
        $this->assertEquals('glv', $m['role_code'], 'Mất chức trưởng khối thì về GLV còn phân công');
        $this->assertEquals((int) $class['id'], (int) $m['class_id']);
    }

    public function test_recompute_never_touches_protected_roles(): void {
        db_run("UPDATE members SET role_code = 'bdh' WHERE id = ?", [$this->testMemberId]);
        recompute_member_primary($this->testMemberId);
        $this->assertEquals('bdh', $this->memberRow()['role_code']);
    }

    public function test_assignment_scope_admin_all_and_toan_doan_needs_admin_or_bdh(): void {
        $admin = db_one("SELECT * FROM members WHERE role_code = 'admin' LIMIT 1");
        $block = db_one("SELECT id FROM blocks LIMIT 1");
        $this->assertTrue(can_manage_assignment_scope($admin, (int) $block['id'], null));
        $this->assertTrue(can_manage_assignment_scope($admin, null, null));

        $glv = db_one("SELECT * FROM members WHERE id = ?", [$this->testMemberId]);
        $this->assertFalse(can_manage_assignment_scope($glv, null, null), 'GLV không cấp được phân công toàn đoàn');
    }
}
