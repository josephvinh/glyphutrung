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
        $sql = log_scope_where(['id' => 42, 'role_code' => 'glv_chu_nhiem']);
        $this->assertStringContainsString('actor_id = 42', $sql);
        $this->assertStringContainsString("target_type = 'student'", $sql);
        $this->assertStringContainsString('OR', $sql);
    }

    public function test_glv_chu_nhiem_khong_co_lop(): void
    {
        // Không có class trong member_assignments → chỉ actor_id
        $sql = log_scope_where(['id' => 99, 'role_code' => 'glv_chu_nhiem']);
        $this->assertStringContainsString('actor_id = 99', $sql);
        $this->assertSame("actor_id = 99", $sql,
            'Khi không có lớp chủ nhiệm, chỉ actor_id = 99 (không có OR).');
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
        $r = log_scope_where_with_params(['id' => 42, 'role_code' => 'glv_chu_nhiem']);
        $this->assertIsArray($r);
        $this->assertCount(2, $r);
        $where = $r[0];
        $params = $r[1];
        $this->assertIsArray($params);
        // Nếu có lớp: params là mảng classIds
        $this->assertStringContainsString('actor_id = 42', $where);
        $this->assertStringContainsString("target_type = 'student'", $where);
    }

    public function test_with_params_null_tra_0_eq_1_va_mang_rong(): void
    {
        $r = log_scope_where_with_params(null);
        $this->assertSame(['0=1', []], $r);
    }
}
