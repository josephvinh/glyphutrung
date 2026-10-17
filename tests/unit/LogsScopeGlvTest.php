<?php
// tests/unit/LogsScopeGlvTest.php
//
// Verify log_scope_where() và log_scope_where_with_params() trả SQL đúng
// theo vai trò.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class LogsScopeGlvTest extends TestCase
{
    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') {
            $this->markTestSkipped("Chỉ chạy scope test trên DB tntt_test (đang là '$ten').");
        }
    }

    public function test_admin_tra_1_eq_1(): void
    {
        $sql = log_scope_where(['id' => 1, 'role_code' => 'admin']);
        $this->assertSame('1=1', $sql);
    }

    public function test_bdh_tra_1_eq_1(): void
    {
        $sql = log_scope_where(['id' => 2, 'role_code' => 'bdh']);
        $this->assertSame('1=1', $sql);
    }

    public function test_null_tra_0_eq_1(): void
    {
        $this->assertSame('0=1', log_scope_where(null));
    }

    public function test_khong_co_id_tra_0_eq_1(): void
    {
        $this->assertSame('0=1', log_scope_where(['id' => 0, 'role_code' => 'giao_ly_vien']));
        $this->assertSame('0=1', log_scope_where(['id' => null, 'role_code' => 'giao_ly_vien']));
    }

    public function test_vai_khong_chu_nhiem_tra_actor_id(): void
    {
        $sql = log_scope_where(['id' => 42, 'role_code' => 'giao_ly_vien']);
        $this->assertStringContainsString('actor_id = 42', $sql);
        $this->assertStringNotContainsString("target_type = 'student'", $sql,
            'Vai không chủ nhiệm không được có nhánh target_type=student.');
    }

    public function test_glv_chu_nhiem_co_nhanh_student(): void
    {
        // Setup: tạo assignment giả để chắc chắn có lớp chủ nhiệm.
        // Cần member + class thật (FK), dùng admin (id=1) + class 1 có sẵn.
        $memberId = 1;
        $classId = 1;
        $this->taoAssignmentGia($memberId, $classId);
        try {
            $sql = log_scope_where(['id' => $memberId, 'role_code' => 'glv_chu_nhiem']);
            $this->assertStringContainsString("actor_id = $memberId", $sql);
            $this->assertStringContainsString("target_type = 'student'", $sql);
            $this->assertStringContainsString('OR', $sql);
        } finally {
            $this->xoaAssignmentGia($memberId, $classId);
        }
    }

    public function test_glv_chu_nhiem_khong_co_lop(): void
    {
        // Không có class trong member_assignments → chỉ actor_id
        // (Dùng id=999 chắc chắn không có assignment trong DB test)
        $sql = log_scope_where(['id' => 999, 'role_code' => 'glv_chu_nhiem']);
        $this->assertStringContainsString('actor_id = 999', $sql);
        $this->assertSame("actor_id = 999", $sql,
            'Khi không có lớp chủ nhiệm, chỉ actor_id = 999 (không có OR).');
    }

    /** Tạo assignment giả trong member_assignments. Cần member + class thật. */
    private function taoAssignmentGia(int $memberId, int $classId): void
    {
        // Xóa assignment cũ nếu có để idempotent
        $this->xoaAssignmentGia($memberId, $classId);
        try {
            db_run(
                "INSERT INTO member_assignments
                    (member_id, class_id, role_code, is_primary, from_date, to_date, assigned_by)
                 VALUES (?, ?, 'glv_chu_nhiem', 0, CURDATE(), NULL, ?)",
                [$memberId, $classId, $memberId]
            );
        } catch (Throwable $e) {
            // FK hoặc schema không khớp → bỏ qua, test sẽ fail
        }
    }

    private function xoaAssignmentGia(int $memberId, int $classId): void
    {
        try {
            db_run(
                "DELETE FROM member_assignments WHERE member_id = ? AND class_id = ? AND role_code = 'glv_chu_nhiem'",
                [$memberId, $classId]
            );
        } catch (Throwable $e) {}
    }

    // --- log_scope_where_with_params ---

    public function test_with_params_tra_array(): void
    {
        $r = log_scope_where_with_params(['id' => 1, 'role_code' => 'admin']);
        $this->assertIsArray($r);
        $this->assertCount(2, $r);
        $this->assertSame('1=1', $r[0]);
        $this->assertIsArray($r[1]);
    }

    public function test_with_params_glv_chu_nhiem_co_params(): void
    {
        $memberId = 1;
        $classId = 1;
        $this->taoAssignmentGia($memberId, $classId);
        try {
            $r = log_scope_where_with_params(['id' => $memberId, 'role_code' => 'glv_chu_nhiem']);
            $this->assertIsArray($r);
            $this->assertCount(2, $r);
            $where = $r[0];
            $params = $r[1];
            $this->assertIsArray($params);
            $this->assertStringContainsString("actor_id = $memberId", $where);
            $this->assertStringContainsString("target_type = 'student'", $where);
        } finally {
            $this->xoaAssignmentGia($memberId, $classId);
        }
    }

    public function test_with_params_null_tra_0_eq_1_va_mang_rong(): void
    {
        $r = log_scope_where_with_params(null);
        $this->assertSame(['0=1', []], $r);
    }
}
