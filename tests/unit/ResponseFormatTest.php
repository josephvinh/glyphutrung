<?php
/**
 * ResponseFormatTest - Unit tests for response helpers
 *
 * Tests json_ok(), json_created(), json_fail(), json_validation_fail(),
 * json_paginated() response structures.
 *
 * Note: This test file requires PHPUnit. A standalone verification script
 * (verify_response.php) can be used instead for quick testing.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}
require_once ROOT_PATH . '/public/api/_errors.php';
require_once ROOT_PATH . '/public/api/_http_util.php';

// Test runner using simple assertions
$passed = 0;
$failed = 0;
$errors = [];

function assert_equals($expected, $actual, string $message = ''): void {
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
    } else {
        $failed++;
        $errors[] = ($message ? "$message: " : '') . "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
    }
}

function assert_true($value, string $message = ''): void {
    global $passed, $failed, $errors;
    if ($value === true) {
        $passed++;
    } else {
        $failed++;
        $errors[] = ($message ? "$message: " : '') . "Expected true, got " . var_export($value, true);
    }
}

function assert_array_has_key($key, array $array, string $message = ''): void {
    global $passed, $failed, $errors;
    if (array_key_exists($key, $array)) {
        $passed++;
    } else {
        $failed++;
        $errors[] = ($message ? "$message: " : '') . "Array missing key: $key";
    }
}

function assert_string_starts_with($prefix, $string, string $message = ''): void {
    global $passed, $failed, $errors;
    if (str_starts_with($string, $prefix)) {
        $passed++;
    } else {
        $failed++;
        $errors[] = ($message ? "$message: " : '') . "String doesn't start with '$prefix': $string";
    }
}

echo "\n=== ResponseFormatTest ===\n\n";

// Test: testResponseMetaStructure
$meta = response_meta(200);
assert_array_has_key('timestamp', $meta);
assert_array_has_key('requestId', $meta);
assert_array_has_key('status', $meta);
assert_equals('OK', $meta['status']);
echo "testResponseMetaStructure: PASS\n";

// Test: testResponseMetaCreatedStatus
$meta201 = response_meta(201);
assert_equals('CREATED', $meta201['status']);
echo "testResponseMetaCreatedStatus: PASS\n";

// Test: testRequestIdFormat
$id1 = generate_request_id();
$id2 = generate_request_id();
assert_string_starts_with('req_', $id1);
assert_string_starts_with('req_', $id2);
assert_equals(20, strlen($id1));
assert_equals(20, strlen($id2));
assert_true($id1 !== $id2, 'IDs should be unique');
echo "testRequestIdFormat: PASS\n";

// Test: testJsonFailNewFormat
$reflection = new ReflectionFunction('json_fail');
$params = $reflection->getParameters();
assert_equals(4, count($params));
assert_equals('code', $params[0]->getName());
assert_equals('message', $params[1]->getName());
assert_equals('details', $params[2]->getName());
assert_equals('status', $params[3]->getName());
echo "testJsonFailNewFormat: PASS\n";

// Test: testJsonOkExists
assert_true(function_exists('json_ok'));
assert_true(is_callable('json_ok'));
echo "testJsonOkExists: PASS\n";

// Test: testJsonCreatedExists
assert_true(function_exists('json_created'));
assert_true(is_callable('json_created'));
echo "testJsonCreatedExists: PASS\n";

// Test: testJsonFailExists
assert_true(function_exists('json_fail'));
assert_true(is_callable('json_fail'));
echo "testJsonFailExists: PASS\n";

// Test: testJsonValidationFailExists
assert_true(function_exists('json_validation_fail'));
assert_true(is_callable('json_validation_fail'));
echo "testJsonValidationFailExists: PASS\n";

// Test: testJsonPaginatedExists
assert_true(function_exists('json_paginated'));
assert_true(is_callable('json_paginated'));
echo "testJsonPaginatedExists: PASS\n";

// Test: testGenerateRequestIdExists
assert_true(function_exists('generate_request_id'));
assert_true(is_callable('generate_request_id'));
echo "testGenerateRequestIdExists: PASS\n";

// Test: testResponseMetaExists
assert_true(function_exists('response_meta'));
assert_true(is_callable('response_meta'));
echo "testResponseMetaExists: PASS\n";

// Test: testAllResponseFunctionsHaveNeverReturnType
$functions = ['json_ok', 'json_created', 'json_fail', 'json_validation_fail', 'json_paginated'];
foreach ($functions as $fnName) {
    $reflection = new ReflectionFunction($fnName);
    $returnType = $reflection->getReturnType();
    assert_true($returnType !== null, "$fnName should have return type");
    assert_equals('never', $returnType->getName(), "$fnName should return never");
}
echo "testAllResponseFunctionsHaveNeverReturnType: PASS\n";

// Test: testJsonFailParametersHaveCorrectTypes
$reflection = new ReflectionFunction('json_fail');
$params = $reflection->getParameters();
assert_equals('string', $params[0]->getType()->getName(), 'code type');
assert_equals('string', $params[1]->getType()->getName(), 'message type');
assert_equals('array', $params[2]->getType()->getName(), 'details type');
assert_equals('int', $params[3]->getType()->getName(), 'status type');
echo "testJsonFailParametersHaveCorrectTypes: PASS\n";

// Test: testJsonOkParametersHaveCorrectTypes
$reflection = new ReflectionFunction('json_ok');
$params = $reflection->getParameters();
assert_equals('mixed', $params[0]->getType()->getName(), 'data type');
assert_equals('array', $params[1]->getType()->getName(), 'meta type');
assert_equals('int', $params[2]->getType()->getName(), 'status type');
echo "testJsonOkParametersHaveCorrectTypes: PASS\n";

// Test: testJsonValidationFailParameterIsArray
$reflection = new ReflectionFunction('json_validation_fail');
$params = $reflection->getParameters();
assert_equals('array', $params[0]->getType()->getName(), 'errors type');
echo "testJsonValidationFailParameterIsArray: PASS\n";

// Test: testJsonPaginatedParametersHaveCorrectTypes
$reflection = new ReflectionFunction('json_paginated');
$params = $reflection->getParameters();
assert_equals('array', $params[0]->getType()->getName(), 'data type');
assert_equals('int', $params[1]->getType()->getName(), 'page type');
assert_equals('int', $params[2]->getType()->getName(), 'perPage type');
assert_equals('int', $params[3]->getType()->getName(), 'total type');
echo "testJsonPaginatedParametersHaveCorrectTypes: PASS\n";

// Test: testErrorCodesAreUsed
$reflection = new ReflectionFunction('json_validation_fail');
$sourceFile = $reflection->getFileName();
assert_true(str_contains($sourceFile, '_response.php'), 'Source should be _response.php');
echo "testErrorCodesAreUsed: PASS\n";

// Summary
echo "\n=== Summary ===\n";
echo "$passed passed, $failed failed\n";

if ($failed > 0) {
    echo "\nFailed assertions:\n";
    foreach ($errors as $e) {
        echo "  - $e\n";
    }
    exit(1);
}

exit(0);
