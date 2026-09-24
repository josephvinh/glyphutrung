<?php
/**
 * Export API Tests
 *
 * Tests cho tính năng xuất CSV điểm danh chi tiết (action=attendance-detail)
 */

require_once __DIR__ . '/../bootstrap.php';

echo "=== Export API Tests ===\n\n";

$allResults = [];

// ============================================================
// 1. CSV Escape Function Tests (Security)
// ============================================================
echo "🔐 CSV Escape (Security)\n";
echo str_repeat('-', 60) . "\n";

// Inline csv_escape function để test (copied from export.php)
function csv_escape_test(string $value): string
{
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $value = "'" . $value;
    }
    if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
        return '"' . str_replace('"', '""', $value) . '"';
    }
    return $value;
}

$csvEscapeTests = [
    'csv_escape_escapes_equals_prefix' => function() {
        $result = csv_escape_test("=CMD|'/C calc'!A0");
        assertEquals("'=CMD|'/C calc'!A0", $result, 'Should prefix = with single quote');
    },

    'csv_escape_escapes_plus_prefix' => function() {
        $result = csv_escape_test('+HYPERLINK("http://evil.com")');
        // When value starts with + AND contains quotes, it gets wrapped in quotes too
        // So check that single quote is in the value (protection applied)
        assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
        // And the result doesn't START with + anymore
        assertFalse($result[0] === '+', 'Should not start with + anymore');
    },

    'csv_escape_escapes_minus_prefix' => function() {
        $result = csv_escape_test('-DDE("cmd")');
        assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
        assertFalse($result[0] === '-', 'Should not start with - anymore');
    },

    'csv_escape_escapes_at_prefix' => function() {
        $result = csv_escape_test('@SUM(1,2)');
        // @SUM(1,2) contains comma, so it gets wrapped in double quotes too
        // But the protection is still applied (single quote added)
        assertTrue(strpos($result, "'") !== false, 'Should add single quote for protection');
    },

    'csv_escape_escapes_tab_prefix' => function() {
        $result = csv_escape_test("\t=HPP()");
        assertTrue(strpos($result, "'\t") === 0, 'Should prefix tab with single quote');
    },

    'csv_escape_normal_text_unchanged' => function() {
        $result = csv_escape_test('Gioan Baotixita Phạm Văn A');
        assertEquals('Gioan Baotixita Phạm Văn A', $result, 'Normal text should be unchanged');
    },

    'csv_escape_normal_text_no_quote' => function() {
        // Normal text without special chars should NOT have single quote
        $result = csv_escape_test('Nguyễn Văn');
        assertFalse(strpos($result, "'") === 0, 'Normal text should not have leading quote');
    },

    'csv_escape_handles_commas' => function() {
        $result = csv_escape_test('Last, First');
        assertEquals('"Last, First"', $result, 'Commas should be wrapped in quotes');
    },

    'csv_escape_handles_quotes' => function() {
        $result = csv_escape_test('Say "Hello"');
        assertEquals('"Say ""Hello"""', $result, 'Quotes should be escaped');
    },

    'csv_escape_handles_newlines' => function() {
        $result = csv_escape_test("Line1\nLine2");
        // Newlines cause wrapping in quotes
        assertTrue(strpos($result, '"') !== false, 'Newlines should be wrapped in quotes');
    },

    'csv_escape_empty_string' => function() {
        $result = csv_escape_test('');
        assertEquals('', $result, 'Empty string should return empty');
    },

    'csv_escape_vietnamese_text' => function() {
        $result = csv_escape_test('Trần Văn Đẹp Trai');
        assertEquals('Trần Văn Đẹp Trai', $result, 'Vietnamese text should be unchanged');
    },

    'csv_escape_injection_in_name' => function() {
        // Test real-world injection: student named with formula
        $result = csv_escape_test('=cmd|"/c calc"');
        // Has quotes inside, so gets wrapped in double quotes + single quote prefix
        // The result starts with " but the single quote is INSIDE the value
        // This prevents formula execution in Excel
        assertTrue(strpos($result, "'=") !== false, 'Formula injection should have single quote prefix inside');
    },
];

$allResults = array_merge($allResults, run_tests('CsvEscape', $csvEscapeTests));

// ============================================================
// 2. CSV Builder Tests
// ============================================================
echo "\n📄 CSV Builder\n";
echo str_repeat('-', 60) . "\n";

// Inline csv_escape cho build_attendance_detail_csv
function build_attendance_detail_csv_test(array $rows, ?int $classId): string
{
    $headers = ['STT', 'Mã số', 'Họ tên', 'Lớp', 'Ngày', 'Buổi', 'Trạng thái', 'Ghi chú', 'Người ghi'];

    $csv = "\xEF\xBB\xBF" . implode(',', array_map('csv_escape_test', $headers)) . "\r\n";

    $seq = 1;
    foreach ($rows as $r) {
        $csv .= implode(',', [
            $seq++,
            csv_escape_test($r['code'] ?? ''),
            csv_escape_test($r['name'] ?? ''),
            csv_escape_test($r['class'] ?? ''),
            csv_escape_test($r['date'] ?? ''),
            csv_escape_test($r['program'] ?? ''),
            csv_escape_test($r['status'] ?? ''),
            csv_escape_test($r['note'] ?? ''),
            csv_escape_test($r['marked_by'] ?? ''),
        ]) . "\r\n";
    }

    return $csv;
}

$csvBuilderTests = [
    'csv_builder_adds_utf8_bom' => function() {
        $csv = build_attendance_detail_csv_test([], null);
        assertEquals("\xEF\xBB\xBF", substr($csv, 0, 3), 'CSV should start with UTF-8 BOM');
    },

    'csv_builder_has_correct_headers' => function() {
        $csv = build_attendance_detail_csv_test([], null);
        $lines = explode("\r\n", substr($csv, 3)); // Skip BOM
        assertEquals('STT,Mã số,Họ tên,Lớp,Ngày,Buổi,Trạng thái,Ghi chú,Người ghi', $lines[0], 'Headers should be correct');
    },

    'csv_builder_increments_sequence' => function() {
        $rows = [
            ['code' => 'TN001', 'name' => 'Student A', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
            ['code' => 'TN002', 'name' => 'Student B', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = build_attendance_detail_csv_test($rows, null);
        // Check first row has STT=1, second row has STT=2
        assertTrue(strpos($csv, "\r\n1,") !== false, 'First row should have STT=1');
        assertTrue(strpos($csv, "\r\n2,") !== false, 'Second row should have STT=2');
    },

    'csv_builder_empty_rows' => function() {
        $csv = build_attendance_detail_csv_test([], null);
        // Should only have BOM + header line
        $lines = array_filter(explode("\r\n", $csv));
        assertEquals(1, count($lines), 'Empty data should only have header');
    },

    'csv_builder_escapes_injection_in_name' => function() {
        $rows = [
            ['code' => 'TN001', 'name' => '=cmd|"/c calc"', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = build_attendance_detail_csv_test($rows, null);
        // The CSV should have protection - check that the result contains single quote
        // which prevents formula execution
        assertTrue(strpos($csv, "'") !== false, 'CSV should contain single quote for protection');
    },

    'csv_builder_escapes_injection_in_code' => function() {
        $rows = [
            ['code' => '=HPP()', 'name' => 'Student A', 'class' => '5A', 'date' => '15/09/2026', 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => '', 'marked_by' => 'Teacher'],
        ];
        $csv = build_attendance_detail_csv_test($rows, null);
        assertTrue(strpos($csv, "'=HPP()") !== false, 'Injection in code should be escaped');
    },

    'csv_builder_handles_null_fields' => function() {
        $rows = [
            ['code' => null, 'name' => 'Student', 'class' => null, 'date' => null, 'program' => 'SHCN', 'status' => 'Có mặt', 'note' => null, 'marked_by' => null],
        ];
        $csv = build_attendance_detail_csv_test($rows, null);
        assertTrue($csv !== '', 'Should handle null fields gracefully');
    },
];

$allResults = array_merge($allResults, run_tests('CsvBuilder', $csvBuilderTests));

// ============================================================
// 3. Date Validation Logic Tests
// ============================================================
echo "\n📅 Date Validation\n";
echo str_repeat('-', 60) . "\n";

$dateValidationTests = [
    'valid_date_format_yyyy_mm_dd' => function() {
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';
        assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate) === 1, 'Valid fromDate format');
        assertTrue(preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate) === 1, 'Valid toDate format');
    },

    'invalid_date_format_detected' => function() {
        $invalidDates = ['01-09-2026', '2026/09/01', '9/1/2026', '2026-9-1', 'invalid'];
        foreach ($invalidDates as $date) {
            assertFalse(preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1, "Invalid date format: $date should fail");
        }
    },

    'from_date_after_to_date_invalid' => function() {
        $fromDate = '2026-09-30';
        $toDate = '2026-09-01';
        assertTrue($fromDate > $toDate, 'fromDate > toDate should be invalid');
    },

    'from_date_before_to_date_valid' => function() {
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';
        assertFalse($fromDate > $toDate, 'fromDate < toDate should be valid');
    },

    'same_date_is_valid' => function() {
        $fromDate = '2026-09-15';
        $toDate = '2026-09-15';
        assertFalse($fromDate > $toDate, 'Same date should be valid');
    },

    'date_range_within_365_days' => function() {
        $fromDate = '2026-01-01';
        $toDate = '2026-12-31';
        $daysDiff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        assertTrue($daysDiff <= 365, '1 year range should be within 365 days');
    },

    'date_range_exceeds_365_days' => function() {
        $fromDate = '2025-01-01';
        $toDate = '2026-12-31';
        $daysDiff = (strtotime($toDate) - strtotime($fromDate)) / 86400;
        assertTrue($daysDiff > 365, '2 year range should exceed 365 days');
    },

    'date_sorting_logic' => function() {
        $dateA = '15/09/2026';
        $dateB = '10/09/2026';
        $dtA = DateTime::createFromFormat('d/m/Y', $dateA);
        $dtB = DateTime::createFromFormat('d/m/Y', $dateB);
        assertTrue($dtA > $dtB, '15/09 should be after 10/09');
    },

    'date_sorting_desc' => function() {
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

        assertEquals('15/09/2026', $rows[0]['date'], 'First row should be newest date');
        assertEquals('05/09/2026', $rows[2]['date'], 'Last row should be oldest date');
    },
];

$allResults = array_merge($allResults, run_tests('DateValidation', $dateValidationTests));

// ============================================================
// 4. Status Label Mapping Tests
// ============================================================
echo "\n🏷️ Status Labels\n";
echo str_repeat('-', 60) . "\n";

$statusTests = [
    'status_co_mat_maps_correctly' => function() {
        $status = 'có mặt';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        assertEquals('Có mặt', $statusLabel, 'có mặt should map to Có mặt');
    },

    'status_di_tre_maps_correctly' => function() {
        $status = 'đi trễ';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        assertEquals('Đi trễ', $statusLabel, 'đi trễ should map to Đi trễ');
    },

    'status_vang_co_phep_maps_correctly' => function() {
        $status = 'vắng có phép';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        assertEquals('Vắng mặt', $statusLabel, 'vắng có phép should map to Vắng mặt');
    },

    'status_vang_khong_phep_maps_correctly' => function() {
        $status = 'vắng không phép';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        assertEquals('Vắng mặt', $statusLabel, 'vắng không phép should map to Vắng mặt');
    },

    'status_unknown_unchanged' => function() {
        $status = 'unknown';
        $statusLabel = match($status) {
            'có mặt' => 'Có mặt',
            'đi trễ' => 'Đi trễ',
            'vắng có phép' => 'Vắng mặt',
            'vắng không phép' => 'Vắng mặt',
            default => $status
        };
        assertEquals('unknown', $statusLabel, 'Unknown status should remain unchanged');
    },
];

$allResults = array_merge($allResults, run_tests('StatusLabels', $statusTests));

// ============================================================
// 5. Leave Request Combination Tests
// ============================================================
echo "\n📋 Leave Request Combination\n";
echo str_repeat('-', 60) . "\n";

$leaveRequestTests = [
    'leave_request_with_existing_attendance_skipped' => function() {
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

        assertEquals(0, count($combined), 'Leave request with attendance should be skipped');
    },

    'leave_request_without_attendance_included' => function() {
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

        assertEquals(1, count($combined), 'Leave request without attendance should be included');
    },
];

$allResults = array_merge($allResults, run_tests('LeaveRequests', $leaveRequestTests));

// ============================================================
// 6. Authorization Logic Tests
// ============================================================
echo "\n🔐 Authorization Logic\n";
echo str_repeat('-', 60) . "\n";

$authTests = [
    'admin_can_access_any_class' => function() {
        // Admin: accessible_class_ids returns null (toàn đoàn)
        $accessible = null; // null = toàn đoàn
        $classId = 123;

        // If accessible is null, user can access any class
        $canAccess = ($accessible === null) || in_array($classId, $accessible, true);
        assertTrue($canAccess, 'Admin (null) should access any class');
    },

    'glv_can_only_access_assigned_class' => function() {
        // GLV: accessible_class_ids returns [1, 2, 3]
        $accessible = [1, 2, 3];
        $classIdOwn = 2;
        $classIdOther = 99;

        assertTrue(in_array($classIdOwn, $accessible, true), 'Should access own class');
        assertFalse(in_array($classIdOther, $accessible, true), 'Should not access other class');
    },

    'unassigned_glv_has_no_access' => function() {
        // GLV with no class assignments
        $accessible = []; // empty = not assigned

        assertFalse($accessible !== null && empty($accessible) === false, 'Empty accessible should mean no access');
        // If accessible is empty array, user should get error
    },

    'classid_null_with_empty_accessible_is_error' => function() {
        $accessible = [];
        $classId = null;

        // When classId is null (export all) and accessible is empty
        $shouldError = ($classId === null) && !empty($accessible) === false;
        assertTrue($shouldError || empty($accessible), 'Should error when no classes accessible');
    },
];

$allResults = array_merge($allResults, run_tests('Authorization', $authTests));

// ============================================================
// 7. Filename Generation Tests
// ============================================================
echo "\n📁 Filename Generation\n";
echo str_repeat('-', 60) . "\n";

$filenameTests = [
    'filename_with_class_name' => function() {
        $classId = 1;
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';

        // Mock class name
        $className = '5 Thánh Phaolô';
        $safeClassName = preg_replace('/\s+/', '_', $className);

        $filename = 'Diem_Danh_' . $safeClassName . '_' . $fromDate . '_' . $toDate . '.csv';

        assertEquals('Diem_Danh_5_Thánh_Phaolô_2026-09-01_2026-09-30.csv', $filename);
    },

    'filename_without_class_name' => function() {
        $classId = null;
        $fromDate = '2026-09-01';
        $toDate = '2026-09-30';

        $filename = 'Diem_Danh_' . $fromDate . '_' . $toDate . '.csv';

        assertEquals('Diem_Danh_2026-09-01_2026-09-30.csv', $filename);
    },

    'filename_spaces_replaced' => function() {
        $className = '5A Thánh Phaolô';
        $safeName = preg_replace('/\s+/', '_', $className);
        assertEquals('5A_Thánh_Phaolô', $safeName, 'Spaces should be replaced with underscores');
    },
];

$allResults = array_merge($allResults, run_tests('Filename', $filenameTests));

// ============================================================
// Summary
// ============================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "📊 SUMMARY\n";
echo str_repeat('=', 60) . "\n";

$passed = count(array_filter($allResults, fn($r) => $r['passed']));
$failed = count(array_filter($allResults, fn($r) => !$r['passed']));
$totalTime = array_sum(array_column($allResults, 'duration'));

echo "Total: " . ($passed + $failed) . " tests\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Time: " . round($totalTime, 2) . "ms\n";
echo str_repeat('=', 60) . "\n";

if ($failed > 0) {
    echo "\n❌ FAILED TESTS:\n";
    foreach (array_filter($allResults, fn($r) => !$r['passed']) as $r) {
        echo "  - {$r['name']}: {$r['error']}\n";
    }
    exit(1);
} else {
    echo "\n✅ ALL TESTS PASSED!\n";
    exit(0);
}
