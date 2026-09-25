<?php
/**
 * AUTH MIDDLEWARE
 *
 * Middleware cho viec xac thuc nguoi dung
 */

namespace TNTT\Middleware;

/**
 * Require authenticated user
 */
function requireAuth(): callable
{
    return function() {
        if (!function_exists('require_login')) {
            require_once __DIR__ . '/../../public/api/_common.php';
        }
        $me = require_login();
        return $me;
    };
}

/**
 * Require specific permission
 */
function requirePermission(string $module, string $level = 'view'): callable
{
    return function() use ($module, $level) {
        if (!function_exists('require_permission')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        return require_permission($module, $level);
    };
}

/**
 * Require POST method
 */
function requirePost(): callable
{
    return function() {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'POST required.'], JSON_UNESCAPED_UNICODE);
            return false;
        }
        return true;
    };
}

/**
 * Require CSRF token
 */
function requireCsrf(): callable
{
    return function() {
        if (!function_exists('require_csrf')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        require_csrf();
        return true;
    };
}

/**
 * Require write operation (POST + CSRF)
 */
function requireWrite(): callable
{
    return function() {
        if (!function_exists('require_write')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        require_write();
        return true;
    };
}

/**
 * Validate JSON input
 */
function validateJson(): callable
{
    return function() {
        $raw = file_get_contents('php://input');
        if ($raw === '') return true; // No body is OK

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Invalid JSON body.'], JSON_UNESCAPED_UNICODE);
            return false;
        }
        return true;
    };
}
