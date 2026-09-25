<?php
/**
 * RATE LIMIT MIDDLEWARE
 *
 * Gioi han so request trong mot khoang thoi gian
 */

namespace TNTT\Middleware;

/**
 * Rate limit by IP address
 *
 * @param int $maxRequests So request toi da
 * @param int $windowSeconds Khoang thoi gian (giay)
 */
function rateLimit(int $maxRequests = 60, int $windowSeconds = 60): callable
{
    return function() use ($maxRequests, $windowSeconds) {
        if (!function_exists('client_ip')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }

        $ip = client_ip();
        $key = 'rate_limit:' . $ip;
        $now = time();

        // Su dung file-based cache don gian
        $cacheFile = sys_get_temp_dir() . '/tntt_rate_' . md5($key) . '.json';

        $data = [];
        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content) {
                $data = json_decode($content, true) ?: [];
            }
        }

        // Loc cac request cu
        $data = array_filter($data, fn($t) => $t > ($now - $windowSeconds));
        $data[] = $now;

        // Luu lai
        @file_put_contents($cacheFile, json_encode($data));

        // Kiem tra
        if (count($data) > $maxRequests) {
            $retryAfter = $windowSeconds - ($now - $data[0]);
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . max(1, $retryAfter));
            echo json_encode([
                'ok' => false,
                'error' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                'retry_after' => max(1, $retryAfter),
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    };
}

/**
 * Rate limit by user (sau khi dang nhap)
 *
 * @param int $maxRequests So request toi da
 * @param int $windowSeconds Khoang thoi gian (giay)
 */
function rateLimitUser(int $maxRequests = 100, int $windowSeconds = 60): callable
{
    return function() use ($maxRequests, $windowSeconds) {
        if (!function_exists('current_member')) {
            require_once __DIR__ . '/../../public/api/_common.php';
        }

        $me = current_member();
        if (!$me) return true; // Chua dang nhap, bo qua

        $key = 'rate_user:' . $me['id'];
        $now = time();

        $cacheFile = sys_get_temp_dir() . '/tntt_rate_' . md5($key) . '.json';

        $data = [];
        if (file_exists($cacheFile)) {
            $content = @file_get_contents($cacheFile);
            if ($content) {
                $data = json_decode($content, true) ?: [];
            }
        }

        $data = array_filter($data, fn($t) => $t > ($now - $windowSeconds));
        $data[] = $now;

        @file_put_contents($cacheFile, json_encode($data));

        if (count($data) > $maxRequests) {
            $retryAfter = $windowSeconds - ($now - $data[0]);
            http_response_code(429);
            header('Content-Type: application/json; charset=utf-8');
            header('Retry-After: ' . max(1, $retryAfter));
            echo json_encode([
                'ok' => false,
                'error' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                'retry_after' => max(1, $retryAfter),
            ], JSON_UNESCAPED_UNICODE);
            return false;
        }

        return true;
    };
}
