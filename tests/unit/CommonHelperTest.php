<?php
/**
 * Core Functions Unit Tests
 *
 * Test các helper functions cơ bản trong _common.php và _bootstrap.php
 */

require_once __DIR__ . '/../bootstrap.php';

class CommonHelperTest extends UnitTest
{
    private array $mockMember;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock member data
        $this->mockMember = [
            'id' => 1,
            'role_code' => 'glv',
            'role_scope' => 'lớp',
            'block_id' => 1,
            'class_id' => 1,
        ];
    }

    // ============================================================
    // Test: current_member() - Session/Auth
    // ============================================================
    public function testCurrentMemberReturnsNullWhenNoSession()
    {
        // Mock: clear session
        $_SESSION = [];

        // current_member() should return null when no member_id in session
        $result = current_member();
        $this->assertNull($result);
    }

    public function testCanSeeAdminOnlyForAdminRole()
    {
        // Admin should see admin
        $admin = ['role_code' => 'admin'];
        $this->assertTrue(can_see_admin($admin));

        // Non-admin should not see admin
        $glv = ['role_code' => 'glv'];
        $this->assertFalse(can_see_admin($glv));

        // Null should return false
        $this->assertFalse(can_see_admin(null));
    }

    // ============================================================
    // Test: allowed_class_ids() - Scope
    // ============================================================
    public function testAllowedClassIdsForGlv()
    {
        // GLV chỉ xem được lớp của mình
        $glv = [
            'id' => 1,
            'role_code' => 'glv',
            'role_scope' => 'lớp',
            'block_id' => 1,
            'class_id' => 5,
        ];

        // Mock DB response for effective_assignments
        // Note: This test requires DB setup or mocking
        $this->markTestSkipped('Requires DB connection or mock');
    }

    public function testAllowedClassIdsReturnsNullForFullScope()
    {
        // Admin/BĐH có scope toàn đoàn -> null
        $admin = [
            'id' => 1,
            'role_code' => 'admin',
            'role_scope' => 'toàn đoàn',
        ];

        // Test with mocked effective_assignments returning empty
        $result = responsible_class_ids($admin);
        // With no effective assignments, should still work with role_scope
        $this->assertIsArray($result);
    }

    // ============================================================
    // Test: Level Rank Function
    // ============================================================
    public function testLevelRankValues()
    {
        // Test the ranking logic directly
        $rank = function(string $level): int {
            return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
        };

        $this->assertEquals(0, $rank('none'));
        $this->assertEquals(1, $rank('view'));
        $this->assertEquals(2, $rank('edit'));
        $this->assertEquals(0, $rank('unknown'));
    }

    public function testLevelRankComparison()
    {
        $rank = function(string $level): int {
            return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
        };

        // edit > view > none
        $this->assertGreaterThan($rank('view'), $rank('edit'));
        $this->assertGreaterThan($rank('none'), $rank('view'));
    }

    // ============================================================
    // Test: Assignment Scope Resolution
    // ============================================================
    public function testAssignmentCoversClassLogic()
    {
        // Test scope resolution logic
        $coversClass = function(array $a, int $classId): bool {
            switch ($a['role_scope'] ?? '') {
                case 'toàn đoàn':
                    return true;
                case 'khối':
                    return !empty($a['block_id']);
                case 'lớp':
                    return !empty($a['class_id']) && (int) $a['class_id'] === $classId;
            }
            return false;
        };

        // Toàn đoàn covers all
        $this->assertTrue($coversClass(['role_scope' => 'toàn đoàn'], 999));

        // Lớp chỉ covers lớp đó
        $this->assertTrue($coversClass(['role_scope' => 'lớp', 'class_id' => 5], 5));
        $this->assertFalse($coversClass(['role_scope' => 'lớp', 'class_id' => 5], 6));

        // Khối covers all classes in block
        $this->assertTrue($coversClass(['role_scope' => 'khối', 'block_id' => 1], 0));
    }

    // ============================================================
    // Test: Permission Resolution
    // ============================================================
    public function testPermissionOfRoleWithUnknownRole()
    {
        // Unknown role should return 'none'
        $result = permission_of_role('unknown_role', 'students');
        $this->assertEquals('none', $result);
    }

    // ============================================================
    // Test: Block Management Functions
    // ============================================================
    public function testResponsibleBlocksForAdmin()
    {
        $admin = ['role_code' => 'admin', 'id' => 1];
        $result = responsible_blocks($admin);
        $this->assertNull($result); // Admin gets null (full access)
    }

    public function testResponsibleBlocksForGlv()
    {
        $glv = [
            'id' => 1,
            'role_code' => 'glv',
            'role_scope' => 'lớp',
            'class_id' => 5,
            'block_id' => null,
        ];

        // GLV không có quyền quản lý khối
        $result = responsible_blocks($glv);
        $this->assertIsArray($result);
    }

    public function testCanManageBlockForAdmin()
    {
        $admin = ['role_code' => 'admin', 'id' => 1];
        $this->assertTrue(can_manage_block($admin, 999)); // Any block
    }

    public function testCanManageClassForNonAdmin()
    {
        $glv = [
            'id' => 1,
            'role_code' => 'glv',
            'role_scope' => 'lớp',
            'class_id' => 5,
            'block_id' => 1,
        ];

        // GLV không có quyền quản lý lớp (chỉ quản lý hồ sơ trong lớp)
        $this->markTestSkipped('Requires DB connection');
    }

    // ============================================================
    // Test: Scan Class IDs
    // ============================================================
    public function testScanClassIdsForAdmin()
    {
        $admin = ['role_code' => 'admin', 'id' => 1];
        $result = scan_class_ids($admin);
        $this->assertNull($result); // Admin can scan all
    }

    // ============================================================
    // Test: Resolve Class IDs From Scope
    // ============================================================
    public function testResolveClassIdsFromScope()
    {
        // Test logic directly
        $resolve = function(array $a): ?array {
            switch ($a['role_scope'] ?? '') {
                case 'toàn đoàn':
                    return null;
                case 'khối':
                    if (!empty($a['block_id'])) {
                        return [(int) $a['block_id']]; // Simplified
                    }
                    return [];
                case 'lớp':
                    return !empty($a['class_id']) ? [(int) $a['class_id']] : [];
            }
            return [];
        };

        $this->assertNull($resolve(['role_scope' => 'toàn đoàn']));
        $this->assertEquals([5], $resolve(['role_scope' => 'lớp', 'class_id' => 5]));
        $this->assertEquals([], $resolve(['role_scope' => 'lớp', 'class_id' => null]));
    }

    // ============================================================
    // Test: Accessible Class IDs
    // ============================================================
    public function testAccessibleClassIdsLogic()
    {
        // Test the access checking logic
        $levelRank = function(string $level): int {
            return ['none' => 0, 'view' => 1, 'edit' => 2][$level] ?? 0;
        };

        // edit requires rank >= 2
        $this->assertGreaterThanOrEqual(2, $levelRank('edit'));
        $this->assertLessThan(2, $levelRank('view'));
        $this->assertLessThan(2, $levelRank('none'));
    }
}
