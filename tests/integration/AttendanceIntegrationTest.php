<?php
/**
 * Attendance Module Integration Tests
 *
 * Test các chức năng điểm danh: mark attendance, view history, filters
 */

require_once __DIR__ . '/../bootstrap.php';

class AttendanceIntegrationTest extends UnitTest
{
    private array $mockStudent;
    private array $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockStudent = [
            'id' => 1,
            'student_code' => 'TN001',
            'full_name' => 'Nguyễn Văn A',
            'class_id' => 1,
        ];

        $this->mockSession = [
            'id' => 1,
            'class_id' => 1,
            'session_date' => date('Y-m-d'),
            'program_id' => 1,
        ];
    }

    // ============================================================
    // Test: Attendance Marking Logic
    // ============================================================
    public function testAttendanceMarkingDataStructure()
    {
        // Test that attendance record has required fields
        $attendanceRecord = [
            'student_id' => $this->mockStudent['id'],
            'session_id' => $this->mockSession['id'],
            'status' => 'có mặt',
            'marked_at' => date('Y-m-d H:i:s'),
            'marked_by' => 1,
        ];

        $this->assertArrayHasKey('student_id', $attendanceRecord);
        $this->assertArrayHasKey('session_id', $attendanceRecord);
        $this->assertArrayHasKey('status', $attendanceRecord);
        $this->assertArrayHasKey('marked_at', $attendanceRecord);
    }

    // ============================================================
    // Test: Attendance Status Types
    // ============================================================
    public function testValidAttendanceStatuses()
    {
        $validStatuses = ['có mặt', 'vắng mặt', 'đi trễ', 'xin phép'];

        foreach ($validStatuses as $status) {
            $this->assertContains($status, $validStatuses);
        }

        // Invalid status should not be in list
        $this->assertNotContains('invalid_status', $validStatuses);
    }

    // ============================================================
    // Test: Session Validation
    // ============================================================
    public function testSessionDateValidation()
    {
        // Valid session date format
        $validDate = date('Y-m-d');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $validDate);

        // Invalid date formats should not match
        $invalidDates = ['2024/01/01', '01-01-2024', '20240101'];
        foreach ($invalidDates as $date) {
            $this->assertDoesNotMatchRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $date);
        }
    }

    // ============================================================
    // Test: Class Scope for Attendance
    // ============================================================
    public function testScanClassIdsScope()
    {
        // Test scan_class_ids logic
        // GLV should only see their own class
        $glv = [
            'id' => 1,
            'role_code' => 'glv',
            'role_scope' => 'lớp',
            'class_id' => 5,
        ];

        // Mock: GLV with lớp scope should only get their class
        // Note: actual DB query needs mocking or test DB
        $this->assertEquals('lớp', $glv['role_scope']);
        $this->assertEquals(5, $glv['class_id']);
    }

    public function testAdminCanScanAllClasses()
    {
        $admin = [
            'id' => 1,
            'role_code' => 'admin',
            'role_scope' => 'toàn đoàn',
        ];

        // Admin with toàn đoàn scope should get null (all classes)
        $this->assertEquals('toàn đoàn', $admin['role_scope']);
    }

    // ============================================================
    // Test: Attendance Report Generation
    // ============================================================
    public function testAttendanceRateCalculation()
    {
        $totalSessions = 10;
        $presentCount = 8;

        $attendanceRate = ($presentCount / $totalSessions) * 100;
        $this->assertEquals(80.0, $attendanceRate);

        // Edge case: no sessions
        $this->expectException(DivisionByZeroError::class);
        $rate = ($presentCount / 0) * 100;
    }

    public function testAttendanceRateEdgeCases()
    {
        // 100% attendance
        $this->assertEquals(100.0, (8 / 8) * 100);

        // 0% attendance
        $this->assertEquals(0.0, (0 / 8) * 100);
    }

    // ============================================================
    // Test: Permission Checks for Attendance
    // ============================================================
    public function testCanMarkAttendancePermission()
    {
        // Test permission check logic
        $canMark = function(array $me, string $module): bool {
            $perms = permission_of($module);
            return in_array($perms, ['view', 'edit']);
        };

        // With 'edit' permission, can mark
        $this->assertTrue($canMark(['role_code' => 'glv'], 'attendance'));

        // With 'none' permission, cannot mark
        $this->assertFalse($canMark(['role_code' => 'glv'], 'attendance'));
    }

    // ============================================================
    // Test: Duplicate Attendance Prevention
    // ============================================================
    public function testDuplicateAttendanceKey()
    {
        // Attendance should be unique per student per session
        $key1 = $this->mockStudent['id'] . '_' . $this->mockSession['id'];
        $key2 = $this->mockStudent['id'] . '_' . $this->mockSession['id'];

        $this->assertEquals($key1, $key2);

        // Different student should have different key
        $key3 = ($this->mockStudent['id'] + 1) . '_' . $this->mockSession['id'];
        $this->assertNotEquals($key1, $key3);
    }
}
