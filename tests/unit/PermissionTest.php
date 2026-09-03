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
