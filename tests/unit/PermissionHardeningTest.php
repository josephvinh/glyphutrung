<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/StaffService.php';

use PHPUnit\Framework\TestCase;

/**
 * Kiểm thử các bản vá phân quyền:
 *   F9 — can_see_admin(): chỉ admin thấy vai/tài khoản admin.
 *   F2 — StaffService::saveMember() whitelist: bdh không đổi ai thành admin/bdh
 *        và không gán được vai lạ; không có id (tạo mới) thì bị từ chối.
 *   F1 — can_access_class() cho module students: lớp ngoài phạm vi bị chặn sửa.
 *
 * Test can_see_admin và test từ chối tạo mới KHÔNG chạm CSDL nên chạy được cả
 * khi không có DB. Các test có gắn @group db
 * cần CSDL thật (chạy ở CI).
 */
class PermissionHardeningTest extends TestCase
{
    /** Giả lập một POST hợp lệ + CSRF để qua require_write() */
    private function fakePost(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SESSION['csrf_token']    = 'test-token';
        $_POST['_csrf']            = 'test-token';
    }

    // ---------------------------------------------------------------- F9
    public function test_can_see_admin_only_for_admin(): void
    {
        $this->assertTrue(can_see_admin(['role_code' => 'admin']));
        $this->assertFalse(can_see_admin(['role_code' => 'bdh']));
        $this->assertFalse(can_see_admin(['role_code' => 'truong_khoi']));
        $this->assertFalse(can_see_admin(['role_code' => 'glv']));
        $this->assertFalse(can_see_admin(null));
        $this->assertFalse(can_see_admin([]));
    }

    // ---------------------------------------------------------------- F2
    /** Không còn tạo thành viên ở màn Nhân sự: phải tự đăng ký rồi BĐH duyệt */
    public function test_save_member_without_id_is_refused(): void
    {
        $this->fakePost();
        $svc = new StaffService(['id' => 1, 'role_code' => 'admin'], 1, [
            'fullName' => 'Puppet', 'phone' => '0900000001', 'role' => 'glv',
        ]);
        $r = $svc->saveMember();
        $this->assertFalse($r['ok']);
        $this->assertSame(400, $r['code'] ?? 0);
    }

    /** @group db — BĐH không đổi được người có sẵn thành admin/bdh hay vai lạ */
    public function test_bdh_cannot_escalate_or_assign_unknown_role(): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';
        $this->fakePost();

        $mid = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('F2_TEST', 'F2 Test', ?, ?, 'glv')",
            ['09' . random_int(10000000, 99999999), password_hash('x', PASSWORD_DEFAULT)]
        );
        foreach ([['admin', 403], ['bdh', 403], ['superuser', 400]] as [$role, $code]) {
            $svc = new StaffService(['id' => 1, 'role_code' => 'bdh'], 1, [
                'id' => $mid, 'fullName' => 'F2 Test', 'phone' => '0900000009', 'role' => $role,
            ]);
            $r = $svc->saveMember();
            $this->assertFalse($r['ok'], $role);
            $this->assertSame($code, $r['code'] ?? 0, $role);
        }
        db_run("DELETE FROM members WHERE id=?", [$mid]);
    }

    // ------------------------------------------------------------- F1 (DB)
    /** @group db */
    public function test_students_edit_denied_outside_scope(): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $classA = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL LIMIT 1");
        $classB = db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? LIMIT 1",
            [$classA['block_id'] ?? 0]
        );
        if (!$classA || !$classB) {
            $this->markTestSkipped('Cần 2 lớp ở 2 khối khác nhau.');
        }
        $adminId = (int) db_one("SELECT id FROM members WHERE role_code='admin' LIMIT 1")['id'];

        $phone = '09' . random_int(10000000, 99999999);
        $mid = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('F1_TEST', 'F1 Test', ?, ?, 'glv_chu_nhiem')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );

        // Lưu quyền cũ để khôi phục
        $old = db_one("SELECT level FROM permissions WHERE module_key='students' AND role_code='glv_chu_nhiem'");
        db_run("INSERT INTO permissions (module_key, role_code, level) VALUES ('students','glv_chu_nhiem','edit')
                ON DUPLICATE KEY UPDATE level='edit'");
        // Chủ nhiệm CHỈ lớp A
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv_chu_nhiem', ?, 1, CURDATE(), ?)",
            [$mid, $classA['id'], $adminId]
        );

        $me = ['id' => $mid, 'role_code' => 'glv_chu_nhiem', 'role_scope' => 'lớp',
               'block_id' => null, 'class_id' => (int) $classA['id']];

        $this->assertTrue(can_access_class($me, 'students', (int) $classA['id'], 'edit'),
            'Chủ nhiệm sửa được hồ sơ lớp mình');
        $this->assertFalse(can_access_class($me, 'students', (int) $classB['id'], 'edit'),
            'Chủ nhiệm KHÔNG được sửa/chuyển hồ sơ em lớp khác (chặn IDOR F1)');

        // Dọn
        db_run("DELETE FROM member_assignments WHERE member_id=?", [$mid]);
        db_run("DELETE FROM members WHERE id=?", [$mid]);
        if (($old['level'] ?? null) !== null) {
            db_run("UPDATE permissions SET level=? WHERE module_key='students' AND role_code='glv_chu_nhiem'",
                   [$old['level']]);
        }
    }

    // -------------------------------------------------- Finding 4 (A′, DB)
    /** @group db */
    public function test_role_change_on_kiem_nhiem_member_is_blocked(): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';
        $this->fakePost();

        $adminId = (int) db_one("SELECT id FROM members WHERE role_code='admin' LIMIT 1")['id'];
        $classA  = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL LIMIT 1");
        if (!$classA) $this->markTestSkipped('Cần ít nhất 1 lớp.');

        $phone = '09' . random_int(10000000, 99999999);
        $mid = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('F4_TEST', 'F4 Test', ?, ?, 'glv')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );
        // Người này đang kiêm nhiệm (có 1 phân công hiệu lực)
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', ?, 1, CURDATE(), ?)",
            [$mid, $classA['id'], $adminId]
        );

        // Admin cố ĐỔI VAI qua màn Nhân sự → phải bị chặn 409 (không âm thầm no-op)
        $svc = new StaffService(['id' => $adminId, 'role_code' => 'admin'], 1, [
            'id' => $mid, 'fullName' => 'F4 Test', 'phone' => $phone, 'role' => 'glv_chu_nhiem',
        ]);
        $r = $svc->saveMember();
        $this->assertFalse($r['ok']);
        $this->assertSame(409, $r['code'] ?? 0);

        // Nhưng chỉ sửa DANH TÍNH (giữ nguyên vai) thì được
        $svc2 = new StaffService(['id' => $adminId, 'role_code' => 'admin'], 1, [
            'id' => $mid, 'fullName' => 'F4 Test Đổi Tên', 'phone' => $phone, 'role' => 'glv',
        ]);
        $r2 = $svc2->saveMember();
        $this->assertTrue($r2['ok']);
        // Phân công vẫn còn (kiêm nhiệm không bị xoá)
        $n = (int) db_one("SELECT COUNT(*) n FROM member_assignments WHERE member_id=? AND to_date IS NULL", [$mid])['n'];
        $this->assertSame(1, $n, 'saveMember không được xoá phân công (giữ kiêm nhiệm)');

        db_run("DELETE FROM member_assignments WHERE member_id=?", [$mid]);
        db_run("DELETE FROM members WHERE id=?", [$mid]);
    }
}
