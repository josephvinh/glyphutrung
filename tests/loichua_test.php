<?php
/**
 * Test script cho loichua service layer
 * Chạy: php tests/loichua_test.php
 */

echo "=== LOI CHUA SERVICE LAYER TESTS ===\n\n";

$passed = 0;
$failed = 0;

function test($name, $condition, $message = '') {
    global $passed, $failed;
    if ($condition) {
        echo "✅ PASS: $name\n";
        $passed++;
    } else {
        echo "❌ FAIL: $name" . ($message ? " - $message" : "") . "\n";
        $failed++;
    }
}

// Load service layer
require __DIR__ . '/../config/loichua.php';

echo "--- Test 1: Date Validation ---\n";

test(
    'Valid date format',
    preg_match('/^\d{4}-\d{2}-\d{2}$/', '2026-10-07') === 1
);

test(
    'Invalid date format (bad separator)',
    preg_match('/^\d{4}-\d{2}-\d{2}$/', '2026/10/07') === 0
);

test(
    'Invalid date format (no separator)',
    preg_match('/^\d{4}-\d{2}-\d{2}$/', '20261007') === 0
);

test(
    'checkdate valid',
    checkdate(10, 7, 2026) === true
);

test(
    'checkdate invalid (Feb 30)',
    checkdate(2, 30, 2026) === false
);

test(
    'checkdate invalid (month 13)',
    checkdate(13, 1, 2026) === false
);

echo "\n--- Test 2: Color Mapping ---\n";

test(
    'Green color map',
    isset(COLOR_MAP['green']) && COLOR_MAP['green']['key'] === 'xanh'
);

test(
    'White color map',
    isset(COLOR_MAP['white']) && COLOR_MAP['white']['key'] === 'trang'
);

test(
    'Red color map',
    isset(COLOR_MAP['red']) && COLOR_MAP['red']['key'] === 'đỏ'
);

echo "\n--- Test 3: Reading Type Mapping ---\n";

test(
    'first_reading maps to Bài Đọc I',
    READING_TYPE_MAP['first_reading'] === 'Bài Đọc I'
);

test(
    'psalm maps to Đáp Ca',
    READING_TYPE_MAP['psalm'] === 'Đáp Ca'
);

test(
    'gospel maps to Tin Mừng',
    READING_TYPE_MAP['gospel'] === 'Tin Mừng'
);

echo "\n--- Test 4: Verse Extraction Logic ---\n";

// Mock verse data
$mockVerses = [
    ['number' => 1, 'text' => 'Câu 1'],
    ['number' => 2, 'text' => 'Câu 2'],
    ['number' => 3, 'text' => 'Câu 3'],
    ['number' => 4, 'text' => 'Câu 4'],
    ['number' => 5, 'text' => 'Câu 5'],
];

// Test với selectedVerses là array
$selected = [1, 2, 3];
$result = loi_chua_extract_verses($mockVerses, $selected);
test(
    'Extract verses 1,2,3',
    $result === 'Câu 1 Câu 2 Câu 3'
);

// Test empty selection (return all)
$selectedEmpty = [];
$resultEmpty = loi_chua_extract_verses($mockVerses, $selectedEmpty);
test(
    'Extract all verses when selection empty',
    strpos($resultEmpty, 'Câu 1') !== false && strpos($resultEmpty, 'Câu 5') !== false
);

echo "\n--- Test 5: Constants Defined ---\n";

test(
    'GOSPEL_DATA_VERSION defined',
    defined('GOSPEL_DATA_VERSION')
);

test(
    'GOSPEL_DATA_CDN contains ndagnhat',
    strpos(GOSPEL_DATA_CDN, 'ndagnhat') !== false
);

test(
    'GOSPEL_DATA_TIMEOUT is 5',
    GOSPEL_DATA_TIMEOUT === 5
);

test(
    'TZ_HCMC defined',
    defined('TZ_HCMC')
);

echo "\n--- Test 6: Cache Path ---\n";

$testDate = '2026-10-07';
$cachePath = loi_chua_cache_path($testDate);
test(
    'Cache path ends with date.json',
    strpos($cachePath, '2026-10-07.json') !== false
);

test(
    'Cache path contains storage/loichua',
    strpos($cachePath, 'storage/loichua') !== false
);

echo "\n=== SUMMARY ===\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";

exit($failed > 0 ? 1 : 0);
