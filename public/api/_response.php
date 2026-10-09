<?php
/**
 * RESPONSE HELPERS
 *
 * Standardized API response functions.
 * All endpoints MUST use these instead of direct json_out() calls.
 */

if (!defined('ERR_VALIDATION_FAILED')) {
    require_once __DIR__ . '/_errors.php';
}

/**
 * Generate unique request ID for tracing
 */
if (!function_exists('generate_request_id')) {
    function generate_request_id(): string
    {
        return 'req_' . bin2hex(random_bytes(8));
    }
}

/**
 * Get standard meta block
 */
if (!function_exists('response_meta')) {
    function response_meta(int $statusCode = 200): array
    {
        return [
            'timestamp' => time(),
            'requestId' => generate_request_id(),
            'status' => $statusCode === 201 ? 'CREATED' : 'OK',
        ];
    }
}

/**
 * Send success response
 */
if (!function_exists('json_ok')) {
    function json_ok(mixed $data, array $meta = [], int $status = 200): never
    {
        $response = [
            'ok' => true,
            'data' => $data,
            'meta' => array_merge(response_meta($status), $meta),
        ];
        json_out($response, $status);
    }
}

/**
 * Send created response (HTTP 201)
 */
if (!function_exists('json_created')) {
    function json_created(mixed $data, array $meta = []): never
    {
        json_ok($data, $meta, 201);
    }
}

/**
 * Send error response
 *
 * Note: New format is (code, message, details, status)
 * The old json_fail() in _http_util.php had format (message, code)
 */
if (!function_exists('json_fail')) {
    function json_fail(string $code, string $message, array $details = [], int $status = 400): never
    {
        $response = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => response_meta($status),
        ];
        json_out($response, $status);
    }
}

/**
 * Send validation error response (HTTP 422)
 */
if (!function_exists('json_validation_fail')) {
    function json_validation_fail(array $errors): never
    {
        $response = [
            'ok' => false,
            'error' => [
                'code' => defined('ERR_VALIDATION_FAILED') ? ERR_VALIDATION_FAILED : 'VALIDATION_FAILED',
                'message' => 'Dữ liệu không hợp lệ.',
                'details' => ['fields' => $errors],
            ],
            'meta' => response_meta(422),
        ];
        json_out($response, 422);
    }
}

/**
 * Send paginated response
 */
if (!function_exists('json_paginated')) {
    function json_paginated(array $data, int $page, int $perPage, int $total): never
    {
        $totalPages = (int) ceil($total / $perPage);

        $response = [
            'ok' => true,
            'data' => $data,
            'meta' => array_merge(response_meta(), [
                'pagination' => [
                    'page' => $page,
                    'perPage' => $perPage,
                    'total' => $total,
                    'totalPages' => $totalPages,
                    'hasMore' => $page * $perPage < $total,
                ],
            ]),
        ];
        json_out($response);
    }
}
