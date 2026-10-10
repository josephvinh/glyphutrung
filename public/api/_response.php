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
 * Supports BOTH calling conventions for backward compatibility:
 * - Old: json_fail($message, $code) where $message is string and $code is int HTTP status
 * - New: json_fail($code, $message, $details, $status) where $code is string error code
 *
 * Status is inferred from error code if not explicitly provided.
 */
if (!function_exists('json_fail')) {
    function json_fail(string $code, string|int $message = '', array $details = [], int $status = 400): never
    {
        // Old calling convention: json_fail($message, $code) where $message is string and $code is int
        if (is_int($message)) {
            $status = $message; // second param was HTTP code
            $message = $code; // first param was message string
            $code = 'BAD_REQUEST';
        }

        // Infer HTTP status from error code if default 400 was used
        if ($status === 400) {
            $codeUpper = strtoupper($code);
            if ($codeUpper === 'AUTH_REQUIRED' || str_contains($codeUpper, 'UNAUTHORIZED')) {
                $status = 401;
            } elseif (str_contains($codeUpper, 'FORBIDDEN') || $codeUpper === 'CSRF_INVALID' || $codeUpper === 'ACCOUNT_DISABLED' || $codeUpper === 'PASSWORD_EXPIRED') {
                $status = 403;
            } elseif (str_contains($codeUpper, 'NOT_FOUND')) {
                $status = 404;
            } elseif (str_contains($codeUpper, 'RATE_LIMIT') || str_contains($codeUpper, 'THROTTLE')) {
                $status = 429;
            } elseif ($codeUpper === 'VALIDATION_FAILED' || str_contains($codeUpper, 'INVALID')) {
                $status = 422;
            } elseif ($codeUpper === 'CONFLICT' || str_contains($codeUpper, 'DUPLICATE')) {
                $status = 409;
            }
        }

        $response = [
            'ok' => false,
            'error' => [
                'code' => $code,
                'message' => (string) $message,
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
