<?php
/**
 * Test "CỬA SỬA" — can_override_session_lock().
 *
 * Mặc định khoá cứng sau giờ tính vắng; chỉ admin/bdh/truong_khoi/
 * glv_chu_nhiem được vượt, VÀ chỉ trong phạm vi mình phụ trách.
 * Bảo vệ LUẬT CỨNG #6 (không nới sang lớp/khối ngoài tầm).
 *
 * Dùng phân công FALLBACK (member_scopes suy từ các trường của $me khi
 * member không có dòng member_assignments) nên không cần tạo member thật.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class OverrideLockTest extends TestCase
{
    private int $classId;        // một lớp (khối A)
    private int $blockId;        // khối của lớp đó
    private int $otherClassId;   // lớp ở KHỐI KHÁC

    protected function setUp(): void
    {
        $a = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1");
        $b = $a ? db_one("SELECT id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? ORDER BY id LIMIT 1",
                         [$a['block_id']]) : null;
        if (!$a || !$b) $this->markTestSkipped('Cần ≥ 2 khối trong seed.');
        $this->classId      = (int) $a['id'];
        $this->blockId      = (int) $a['block_id'];
        $this->otherClassId = (int) $b['id'];
    }

    /** $me không có member_assignments -> member_scopes dùng fallback. */
    private function me(string $role, string $scope, ?int $block, ?int $class): array
    {
        return ['id' => 999000001, 'role_code' => $role, 'role_scope' => $scope,
                'block_id' => $block, 'class_id' => $class];
    }

    public function test_admin_vuot_moi_lop(): void
    {
        $me = $this->me('admin', 'toàn đoàn', null, null);
        $this->assertTrue(can_override_session_lock($me, $this->classId));
        $this->assertTrue(can_override_session_lock($me, $this->otherClassId));
    }

    public function test_bdh_vuot_moi_lop(): void
    {
        $me = $this->me('bdh', 'toàn đoàn', null, null);
        $this->assertTrue(can_override_session_lock($me, $this->otherClassId));
    }

    public function test_glv_thuong_bi_khoa(): void
    {
        $me = $this->me('glv', 'lớp', null, $this->classId);
        $this->assertFalse(can_override_session_lock($me, $this->classId));
    }

    public function test_du_bi_bi_khoa(): void
    {
        $me = $this->me('du_bi', 'lớp', null, $this->classId);
        $this->assertFalse(can_override_session_lock($me, $this->classId));
    }

    public function test_glv_chu_nhiem_chi_vuot_lop_minh(): void
    {
        $me = $this->me('glv_chu_nhiem', 'lớp', null, $this->classId);
        $this->assertTrue(can_override_session_lock($me, $this->classId));
        // Lớp KHÁC -> KHÔNG được vượt
        $this->assertFalse(can_override_session_lock($me, $this->otherClassId));
    }

    public function test_truong_khoi_chi_vuot_khoi_minh(): void
    {
        $me = $this->me('truong_khoi', 'khối', $this->blockId, null);
        $this->assertTrue(can_override_session_lock($me, $this->classId));
        // Khối KHÁC -> KHÔNG được vượt
        $this->assertFalse(can_override_session_lock($me, $this->otherClassId));
    }
}
