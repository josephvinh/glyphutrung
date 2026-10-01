<?php
/**
 * Export API Tests
 *
 * Tests cho tính năng xuất CSV điểm danh chi tiết (action=attendance-detail)
 *
 * Trước đây là script kiểu cũ (chạy ngay lúc nạp file rồi exit(0)) nên PHPUnit
 * không chạy được test nào (#82). Nay là TestCase thật; mỗi kiểm tra cũ thành
 * một phương thức test_*. Chỉ dùng API có ở cả PHPUnit 10 lẫn 11.
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    // Inline csv_escape function để test (copied from export.php)
    private static function csv_escape_test(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $value = "'" . $value;
        }
        if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        return $value;
    }

    // Inline csv_escape cho build_attendance_detail_csv
    private static function build_attendance_detail_csv_test(array $rows, ?int $classId): string
    {
        $headers = ['STT', 'Mã số', 'Họ tên', 'Lớp', 'Ngày', 'Buổi', 'Trạng thái', 'Ghi chú', 'Người ghi'];

        $csv = "\xEF\xBB\xBF" . implode(',', array_map(fn($h) => self::csv_escape_test($h), $headers)) . "\r\n";

        $seq = 1;
        foreach ($rows as $r) {
            $csv .= implode(',', [
                $seq++,
                self::csv_escape_test($r['code'] ?? ''),
                self::csv_escape_test($r['name'] ?? ''),
                self::csv_escape_test($r['class'] ?? ''),
                self::csv_escape_test($r['date'] ?? ''),
                self::csv_escape_test($r['program'] ?? ''),
                self::csv_escape_test($r['status'] ?? ''),
                self::csv_escape_test($r['note'] ?? ''),
                self::csv_escape_test($r['marked_by'] ?? ''),
            ]) . "\r\n";
        }

        return $csv;
    }

    // ============================================================
    // CSV Escape Function Tests (Security)
    // ============================================================

    public function test_csv_escape_escapes_equals_prefix(): void
    {
        $result = self::csv_escape_test("=CMD|'/C calc'!A0");
        $this->assertSame("'=CMD|'/C calc'!A0", $result, 'Should prefix = with single quote');
    }

    public function test_csv_escape_escapes_plus_prefix(): void
    {
        $result = self::csv_escape_test('+HYPERLINK("http://evil.com")');
        // When value starts with + AND contains quotes, it gets wrapped in quotes too
        // So check that single quote is in the value (protection applied)
        $this->assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
        // And the result doesn't START with + anymore
        $this->assertFalse($result[0] === '+', 'Should not start with + anymore');
    }

    public function test_csv_escape_escapes_minus_prefix(): void
    {
        $result = self::csv_escape_test('-DDE("cmd")');
        $this->assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
        $this->assertFalse($result[0] === '-', 'Should not start with - anymore');
    }

    public function test_csv_escape_escapes_at_prefix(): void
    {
        $result = self::csv_escape_test('@SUM(1,2)');
        // @SUM(1,2) contains comma, so it gets wrapped in double quotes too
        // But the protection is still applied (single quote added)
        $this->assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
    }

    public function test_csv_escape_escapes_tab_prefix(): void
    {
        $result = self::csv_escape_test("\t=HPP()");
        $this->assertTrue(strpos($result, "'\t") === 0, 'Should prefix tab with single quote');
    }

    public function test_csv_escape_normal_text_unchanged(): void
    {
        $result = self::csv_escape_test('Gioan Baotixita Phạm Văn A');
        $this->assertSame('Gioan Baotixita Phạm Văn A', $result, 'Normal text should be unchanged');
    }

    public function test_csv_escape_normal_text_no_quote(): void
    {
        // Normal text without special chars should NOT have single quote
        $result = self::csv_escape_test('Nguyễn Văn');
        $this->assertFalse(strpos($result, "'") === 0, 'Normal text should not have leading quote');
    }

    public function test_csv_escape_handles_commas(): void
    {
        $result = self::csv_escape_test('Last, First');
        $this->assertSame('"Last, First"', $result, 'Commas should be wrapped in quotes');
    }

    public function test_csv_escape_handles_quotes(): void
    {
        $result = self::csv_escape_test('Say "Hello"');
        $this->assertSame('"Say ""Hello"""', $result, 'Quotes should be escaped');
    }

    public function test_csv_escape_handles_newlines(): void
    {
        $result = self::csv_escape_test("Line1\nLine2");
        // Newlines cause wrapping in quotes
        $this->assertTrue(strpos($result, '"') !== false, 'Newlines should be wrapped in quotes');
    }

    public function test_csv_escape_empty_string(): void
    {
        $result = self::csv_escape_test('');
        $this->assertSame('', $result, 'Empty string should return empty');
    }

    public function test_csv_escape_vietnamese_text(): void
    {
        $result = self::csv_escape_test('Trần Văn Đẹp Trai');
        $this->assertSame('Trần Văn Đẹp Trai', $result, 'Vietnamese text should be unchanged');
    }

    public function test_csv_escape_injection_in_name(): void
    {
        // Test real-world injection: student named with formula
        $result = self::csv_escape_test('=cmd|"/c calc"');
        // Has quotes inside, so gets wrapped in double quotes + single quote prefix
        // The result starts with " but the single quote is INSIDE the value
        // This prevents formula execution in Excel
        $this->assertTrue(strpos($result, "'=") !== false, 'Formula injection should have single quote prefix inside');
    }

    // ============================================================
    // CSV Builder Tests
    // ============================================================

    public function test_csv_builder_adds_utf8_bom(): void
    {
        $csv = self::build_attendance_detail_csv_test([], null);
        $this->assertSame("\xEF\xBB\xBF", substr($csv, 0, 3), 'CSV should start with UTF-8 BOM');
    }

    public function test_csv_builder_has_correct_headers(): void
    {
        $csv = self::build_attendance_detail_csv_test([], null);
        $lines = explode("\r\n", substr($csv, 3)); // Skip BOM
        $this->assertSame('STT,Mã số,Họ tên,Lớp,Ngày,Buổi,Trạng thái,Ghi chú,Người ghi', $lines[0], 'Headers should be correct');
    }

    public function test_csv_builder_increments_sequence(): void
    {
        $rows = [
            ['code' => 'TN001', 'name' => 'Student A', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
            ['code' => 'TN002', 'name' => 'Student B', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = self::build_attendance_detail_csv_test($rows, null);
        // Check first row has STT=1, second row has STT=2
        $this->assertTrue(strpos($csv, "\r\n1,") !== false, 'First row should have STT=1');
        $this->assertTrue(strpos($csv, "\r\n2,") !== false, 'Second row should have STT=2');
    }

    public function test_csv_builder_empty_rows(): void
    {
        $csv = self::build_attendance_detail_csv_test([], null);
        // Should only have BOM + header line
        $lines = array_filter(explode("\r\n", $csv));
        $this->assertSame(1, count($lines), 'Empty data should only have header');
    }

    public function test_csv_builder_escapes_injection_in_name(): void
    {
        $rows = [
            ['code' => 'TN001', 'name' => '=cmd|"/c calc"', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = self::build_attendance_detail_csv_test($rows, null);
        // The CSV should have protection - check that the result contains single quote
        // which prevents formula execution
        $this->assertTrue(strpos($csv, "'") !== false, 'CSV should contain single quote for protection');
    }

    public function test_csv_builder_escapes_injection_in_code(): void
    {
        $rows = [
            ['code' => '=HPP()', 'name' => 'Student A', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = self::build_attendance_detail_csv_test($rows, null);
        $this->assertTrue(strpos($csv, "'=HPP()") !== false, 'Injection in code should be escaped');
    }

    public function test_csv_builder_handles_null_fields(): void
    {
        $rows = [
            ['code' => null, 'name' => 'Student', 'class' => null, 'date' => null, 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => null, 'marked_by' => null],
        ];
        $csv = self::build_attendance_detail_csv_test($rows, null);
        $this->assertTrue($csv !== '', 'Should handle null fields gracefully');
    }

    // ============================================================
    // Date Validation Logic Tests
    // ============================================================

    public function test_valid_date_format_yyyy_mm_dd(): void
    {
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';
        $this->assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) === 1, 'Valid fromDate format');
        $this->assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) === 1, 'Valid toDate format');
    }

    public function test_invalid_date_format_detected(): void
    {
        $invalidDates = ['01-09-2026', '2026/09/01', '9/1/2026', '2026-9-1', 'invalid'];
        foreach ($invalidDates as $date) {
            $this->assertFalse(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, "Invalid date format: $date should fail");
        }
    }

    public function test_from_date_after_to_date_invalid(): void
    {
        $fromDate = '2026-09-30';
        $toDate = '2026-09-01';
        $this->assertTrue($fromDate > $toDate, 'fromDate > toDate should be invalid');
    }

    public function test_from_date_before_to_date_valid(): void
    {
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';
        $this->assertFalse($fromDate > $toDate, 'fromDate < toDate should be valid');
    }

    public function test_same_date_is_valid(): void
    {
        $fromDate = '2026-09-15';
        $toDate = '2026-09-15';
        $this->assertFalse($fromDate > $toDate, 'Same date should be valid');
    }

    public function test_date_range_within_365_days(): void
    {
        $fromDate = '2026-01-01';
        $toDate = '2026-12-31';
        $daysDiff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        $this->assertTrue($daysDiff <= 365, '1 year range should be within 365 days');
    }

    public function test_date_range_exceeds_365_days(): void
    {
        $fromDate = '2025-01-01';
        $toDate = '2026-12-31';
        $daysDiff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        $this->assertTrue($daysDiff > 365, '2 year range should exceed 365 days');
    }

    public function test_date_sorting_logic(): void
    {
        $dateA = '15/09/2026';
        $dateB = '10/09/2026';
        $dtA = DateTime::createFromFormat('d/m/Y', $dateA);
        $dtB = DateTime::createFromFormat('d/m/Y', $dateB);
        $this->assertTrue($dtA > $dtB, '15/09 should be after 10/09');
    }

    public function test_date_sorting_desc(): void
    {
        $rows = [
            ['date' => '10/09/2026', 'class' => '5A', 'code' => 'TN001'],
            ['date' => '15/09/2026', 'class' => '5A', 'code' => 'TN001'],
            ['date' => '05/09/2026', 'class' => '5A', 'code' => 'TN001'],
        ];

        usort($rows, function($a, $b) {
            $dateA = DateTime::createFromFormat('d/m/Y', $a['date']);
            $dateB = DateTime::createFromFormat('d/m/Y', $b['date']);
            return $dateB <=> $dateA; // desc
        });

        $this->assertSame('15/09/2026', $rows[0]['date'], 'First row should be newest date');
        $this->assertSame('05/09/2026', $rows[2]['date'], 'Last row should be oldest date');
    }

    // ============================================================
    // Status Label Mapping Tests
    // ============================================================

    public function test_status_co_mat_maps_correctly(): void
    {
        $status = 'có mặt';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        $this->assertSame('Có mặt', $statusLabel, 'có mặt should map to Có mặt');
    }

    public function test_status_di_tre_maps_correctly(): void
    {
        $status = 'đi trễ';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        $this->assertSame('Đi trễ', $statusLabel, 'đi trễ should map to Đi trễ');
    }

    public function test_status_vang_co_phep_maps_correctly(): void
    {
        $status = 'vắng có phép';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        $this->assertSame('Vắng mặt', $statusLabel, 'vắng có phép should map to Vắng mặt');
    }

    public function test_status_vang_khong_phep_maps_correctly(): void
    {
        $status = 'vắng không phép';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        $this->assertSame('Vắng mặt', $statusLabel, 'vắng không phép should map to Vắng mặt');
    }

    public function test_status_unknown_unchanged(): void
    {
        $status = 'unknown';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        $this->assertSame('unknown', $statusLabel, 'Unknown status should remain unchanged');
    }

    // ============================================================
    // Leave Request Combination Tests
    // ============================================================

    public function test_leave_request_with_existing_attendance_skipped(): void
    {
        // Student has attendance AND leave request
        $records = [
            ['student_code' => 'TN001', 'session_date' => '2026-09-15', 'program_name' => 'SHCN']
        ];
        $leaveRequests = [
            ['student_code' => 'TN001', 'session_date' => '2026-09-15', 'program_name' => 'SHCN', 'reason' => 'Xin nghỉ']
        ];

        // Build attendance index
        $attendedKeys = [];
        foreach ($records as $r) {
            $key = $r['student_code'] . '|' . $r['session_date'] . '|' . $r['program_name'];
            $attendedKeys[$key] = true;
        }

        // Filter out leave requests already in attendance
        $combined = [];
        foreach ($leaveRequests as $lr) {
            $key = $lr['student_code'] . '|' . $lr['session_date'] . '|' . $lr['program_name'];
            if (!isset($attendedKeys[$key])) {
                $combined[] = $lr;
            }
        }

        $this->assertSame(0, count($combined), 'Leave request with attendance should be skipped');
    }

    public function test_leave_request_without_attendance_included(): void
    {
        // Student has leave request but NO attendance
        $records = []; // No attendance
        $leaveRequests = [
            ['student_code' => 'TN001', 'session_date' => '2026-09-15', 'program_name' => 'SHCN', 'reason' => 'Xin nghỉ']
        ];

        $attendedKeys = [];
        foreach ($records as $r) {
            $key = $r['student_code'] . '|' . $r['session_date'] . '|' . $r['program_name'];
            $attendedKeys[$key] = true;
        }

        $combined = [];
        foreach ($leaveRequests as $lr) {
            $key = $lr['student_code'] . '|' . $lr['session_date'] . '|' . $lr['program_name'];
            if (!isset($attendedKeys[$key])) {
                $combined[] = $lr;
            }
        }

        $this->assertSame(1, count($combined), 'Leave request without attendance should be included');
    }

    // ============================================================
    // Authorization Logic Tests
    // ============================================================

    public function test_admin_can_access_any_class(): void
    {
        // Admin: accessible_class_ids returns null (toàn đoàn)
        $accessible = null; // null = toàn đoàn
        $classId = 123;

        // If accessible is null, user can access any class
        $canAccess = ($accessible === null) || in_array($classId, $accessible, true);
        $this->assertTrue($canAccess, 'Admin (null) should access any class');
    }

    public function test_glv_can_only_access_assigned_class(): void
    {
        // GLV: accessible_class_ids returns [1, 2, 3]
        $accessible = [1, 2, 3];
        $classIdOwn = 2;
        $classIdOther = 99;

        $this->assertTrue(in_array($classIdOwn, $accessible, true), 'Should access own class');
        $this->assertFalse(in_array($classIdOther, $accessible, true), 'Should not access other class');
    }

    public function test_unassigned_glv_has_no_access(): void
    {
        // GLV with no class assignments
        $accessible = []; // empty = not assigned

        $this->assertFalse($accessible !== null && empty($accessible) === false, 'Empty accessible should mean no access');
        // If accessible is empty array, user should get error
    }

    public function test_classid_null_with_empty_accessible_is_error(): void
    {
        $accessible = [];
        $classId = null;

        // When classId is null (export all) and accessible is empty
        $shouldError = ($classId === null) && !empty($accessible) === false;
        $this->assertTrue($shouldError || empty($accessible), 'Should error when no classes accessible');
    }

    // ============================================================
    // Filename Generation Tests
    // ============================================================

    public function test_filename_with_class_name(): void
    {
        $classId = 1;
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';

        // Mock class name
        $className = '5 Thánh Phaolô';
        $safeClassName = preg_replace('/\s+/', '_', $className);

        $filename = 'Diem_Danh_' . $safeClassName . '_' . $fromDate . '_' . $toDate . '.csv';

        $this->assertSame('Diem_Danh_5_Thánh_Phaolô_2026-09-01_2026-09-30.csv', $filename);
    }

    public function test_filename_without_class_name(): void
    {
        $classId = null;
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';

        $filename = 'Diem_Danh_' . $fromDate . '_' . $toDate . '.csv';

        $this->assertSame('Diem_Danh_2026-09-01_2026-09-30.csv', $filename);
    }

    public function test_filename_spaces_replaced(): void
    {
        $className = '5A Thánh Phaolô';
        $safeName = preg_replace('/\s+/', '_', $className);
        $this->assertSame('5A_Thánh_Phaolô', $safeName, 'Spaces should be replaced with underscores');
    }
}
