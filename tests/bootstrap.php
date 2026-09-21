<?php
/**
 * Test Bootstrap
 *
 * Nạp dependencies cho unit tests
 */

// Load project config
require_once __DIR__ . '/../public/api/_bootstrap.php';

// Custom assertion exception
class TestAssertionError extends Exception {}

// Helper functions for testing
if (!function_exists('assertEquals')) {
    function assertEquals($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected " . var_export($expected, true) .
                ", got " . var_export($actual, true)
            );
        }
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue($value, string $message = ''): void {
        if ($value !== true) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected true, got " . var_export($value, true)
            );
        }
    }
}

if (!function_exists('assertFalse')) {
    function assertFalse($value, string $message = ''): void {
        if ($value !== false) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected false, got " . var_export($value, true)
            );
        }
    }
}

if (!function_exists('assertContains')) {
    function assertContains($needle, array $haystack, string $message = ''): void {
        if (!in_array($needle, $haystack, true)) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected array to contain " . var_export($needle, true)
            );
        }
    }
}

if (!function_exists('assertCount')) {
    function assertCount(int $expected, array $array, string $message = ''): void {
        $actual = count($array);
        if ($expected !== $actual) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected count $expected, got $actual"
            );
        }
    }
}

if (!function_exists('assertNotEmpty')) {
    function assertNotEmpty($value, string $message = ''): void {
        if (empty($value)) {
            throw new TestAssertionError(
                ($message ? "$message: " : '') .
                "Expected non-empty value, got empty"
            );
        }
    }
}

/**
 * Chạy một test và bắt kết quả
 */
function run_test(string $name, callable $test): array {
    $start = microtime(true);
    $error = null;

    try {
        $test();
    } catch (TestAssertionError $e) {
        $error = $e->getMessage();
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $duration = round((microtime(true) - $start) * 1000, 2);

    return [
        'name' => $name,
        'passed' => $error === null,
        'error' => $error,
        'duration' => $duration,
    ];
}

/**
 * Chạy tất cả tests trong một file
 */
function run_tests(string $className, array $tests): array {
    $results = [];
    foreach ($tests as $name => $test) {
        $results[] = run_test("$className::$name", $test);
    }
    return $results;
}

/**
 * In kết quả test
 */
function print_test_results(array $results): void {
    $passed = 0;
    $failed = 0;
    $totalTime = 0;

    foreach ($results as $r) {
        if ($r['passed']) {
            echo "  ✅ {$r['name']} ({$r['duration']}ms)\n";
            $passed++;
        } else {
            echo "  ❌ {$r['name']} - {$r['error']}\n";
            $failed++;
        }
        $totalTime += $r['duration'];
    }

    echo "\n";
    echo "  {$passed} passed, {$failed} failed";
    echo " (" . round($totalTime, 2) . "ms)\n";
    echo str_repeat('-', 60) . "\n";
}
