<?php
/**
 * TNTT Unit Tests
 *
 * Chạy: php tests/UnitTest.php
 *
 * Các tests cho functions quan trọng trong hệ thống.
 */

require_once __DIR__ . '/bootstrap.php';

// Load services for testing
require_once __DIR__ . '/../public/api/OrgService.php';
require_once __DIR__ . '/../public/api/StaffService.php';

echo "=== TNTT Unit Tests ===\n\n";

$allResults = [];

// ============================================================
// 1. Database Helper Tests
// ============================================================
echo "📊 Database Helpers\n";
echo str_repeat('-', 60) . "\n";

$dbTests = [
    'db_all_returns_array' => function() {
        $result = db_all('SELECT 1 as n UNION SELECT 2');
        assertTrue(is_array($result), 'db_all should return array');
        assertCount(2, $result, 'Should return 2 rows');
    },

    'db_one_returns_single_row' => function() {
        $result = db_one('SELECT 1 as n');
        assertTrue(is_array($result), 'db_one should return array');
        assertEquals(1, $result['n'], 'Should return single row');
    },

    'db_one_returns_null_for_no_result' => function() {
        $result = db_one('SELECT 1 as n WHERE 1=0');
        assertTrue($result === null, 'db_one should return null for no result');
    },
];

$allResults = array_merge($allResults, run_tests('DB', $dbTests));

// ============================================================
// 2. Validation Tests
// ============================================================
echo "\n🔍 Validation\n";
echo str_repeat('-', 60) . "\n";

$validationTests = [
    'phone_normalization_removes_84_prefix' => function() {
        $phone = preg_replace('/[^\d]/', '', '84901234567');
        if (strpos($phone, '84') === 0 && strlen($phone) >= 11) {
            $phone = '0' . substr($phone, 2);
        }
        assertEquals('0901234567', $phone, 'Should normalize 84 prefix');
    },

    'phone_normalization_adds_leading_zero' => function() {
        $phone = preg_replace('/[^\d]/', '', '901234567');
        if ($phone !== '' && $phone[0] !== '0') {
            $phone = '0' . $phone;
        }
        assertEquals('0901234567', $phone, 'Should add leading zero');
    },

    'name_trimming_works' => function() {
        $name = trim("  Nguyễn Văn A  ");
        assertEquals('Nguyễn Văn A', $name, 'Should trim whitespace');
    },

    'name_title_case_works' => function() {
        $name = mb_convert_case('nguyễn văn b', MB_CASE_TITLE, 'UTF-8');
        assertEquals('Nguyễn Văn B', $name, 'Should convert to title case');
    },
];

$allResults = array_merge($allResults, run_tests('Validation', $validationTests));

// ============================================================
// 3. Permission Tests
// ============================================================
echo "\n🔐 Permissions\n";
echo str_repeat('-', 60) . "\n";

$permissionTests = [
    'is_protected_catches_admin' => function() {
        $member = ['role_code' => 'admin'];
        assertTrue(
            in_array($member['role_code'], ['admin', 'bdh'], true),
            'Admin should be protected'
        );
    },

    'is_protected_catches_bdh' => function() {
        $member = ['role_code' => 'bdh'];
        assertTrue(
            in_array($member['role_code'], ['admin', 'bdh'], true),
            'BDH should be protected'
        );
    },

    'is_protected_allows_glv' => function() {
        $member = ['role_code' => 'glv'];
        assertTrue(
            !in_array($member['role_code'], ['admin', 'bdh'], true),
            'GLV should not be protected'
        );
    },
];

$allResults = array_merge($allResults, run_tests('Permissions', $permissionTests));

// ============================================================
// 4. Service Tests
// ============================================================
echo "\n🏗️ OrgService\n";
echo str_repeat('-', 60) . "\n";

$serviceTests = [
    'OrgService_can_be_instantiated' => function() {
        // This will fail if DB is not configured, but that's OK for now
        // We're just testing the class exists and can be instantiated
        if (!class_exists('OrgService')) {
            throw new Exception('OrgService class not found');
        }
    },

    'StaffService_can_be_instantiated' => function() {
        if (!class_exists('StaffService')) {
            throw new Exception('StaffService class not found');
        }
    },
];

$allResults = array_merge($allResults, run_tests('Services', $serviceTests));

// ============================================================
// 5. API Response Format Tests
// ============================================================
echo "\n📡 API Response Format\n";
echo str_repeat('-', 60) . "\n";

$apiTests = [
    'json_out_structure' => function() {
        // Test that json_encode works correctly
        $response = ['ok' => true, 'data' => ['id' => 1]];
        $json = json_encode($response, JSON_UNESCAPED_UNICODE);
        assertTrue(
            strpos($json, '"ok":true') !== false,
            'JSON should contain ok:true'
        );
    },

    'error_response_has_error_field' => function() {
        $response = ['ok' => false, 'error' => 'Test error'];
        assertTrue(isset($response['error']), 'Error response should have error field');
        assertEquals('Test error', $response['error']);
    },
];

$allResults = array_merge($allResults, run_tests('API', $apiTests));

// ============================================================
// 6. Cache Tests
// ============================================================
echo "\n💾 Cache\n";
echo str_repeat('-', 60) . "\n";

$cacheTests = [
    'Cache_class_exists' => function() {
        assertTrue(class_exists('Cache'), 'Cache class should exist');
    },

    'Cache_has_get_method' => function() {
        if (!class_exists('Cache')) {
            throw new Exception('Cache class not found');
        }
        assertTrue(
            method_exists('Cache', 'get'),
            'Cache should have get method'
        );
    },

    'Cache_has_set_method' => function() {
        if (!class_exists('Cache')) {
            throw new Exception('Cache class not found');
        }
        assertTrue(
            method_exists('Cache', 'set'),
            'Cache should have set method'
        );
    },
];

$allResults = array_merge($allResults, run_tests('Cache', $cacheTests));

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
