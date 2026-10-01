<?php
/**
 * RATE LIMITER — giới hạn số request theo IP/user
 *
 * Dùng APCu để lưu trữ request counts (in-memory, nhanh).
 * Fallback: dùng file-based cache nếu không có APCu (#102).
 */

class RateLimiter
{
    private static string $cacheDir = __DIR__ . '/../cache';

    private string $key;
    private int $limit;
    private int $window; // seconds

    /**
     * @param string $name Tên rate limit (vd: 'login', 'api_read', 'api_write')
     * @param int $limit Số request tối đa
     * @param int $window Khoảng thời gian (giây)
     */
    public function __construct(string $name, int $limit, int $window = 60)
    {
        $this->key = "rl_{$name}_" . $this->getIdentifier();
        $this->limit = $limit;
        $this->window = $window;
    }

    /** Lấy identifier: ưu tiên user_id nếu đăng nhập, không thì IP */
    private function getIdentifier(): string
    {
        $me = current_member();
        if ($me) return 'u_' . $me['id'];
        return 'ip_' . $this->getClientIp();
    }

    /** Lấy IP client (hỗ trợ proxy) */
    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        // X-Forwarded-For có thể bị spoof, chỉ dùng khi tin tưởng proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'], true) === false) {
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = $ips[0] ?? $ip;
        }
        return substr($ip, 0, 45);
    }

    /** Kiểm tra và tăng counter. Return true nếu được phép, false nếu vượt limit */
    public function attempt(): bool
    {
        $now = time();
        $data = $this->get();

        // Reset nếu hết cửa sổ
        if ($data === null || $data['reset_at'] <= $now) {
            $data = ['hits' => 0, 'reset_at' => $now + $this->window];
        }

        if ($data['hits'] >= $this->limit) {
            $this->set($data);
            return false;
        }

        $data['hits']++;
        $this->set($data);
        return true;
    }

    /** Lấy thông tin rate limit hiện tại */
    public function info(): array
    {
        $data = $this->get();
        $remaining = $data ? max(0, $this->limit - $data['hits']) : $this->limit;
        $resetAt = $data['reset_at'] ?? (time() + $this->window);

        return [
            'limit'     => $this->limit,
            'remaining' => $remaining,
            'reset'     => $resetAt,
            'resetIn'   => max(0, $resetAt - time()),
        ];
    }

    /** Reset counter */
    public function reset(): void
    {
        $this->set(['hits' => 0, 'reset_at' => time() + $this->window]);
    }

    /** Lấy dữ liệu từ cache */
    private function get(): ?array
    {
        // Ưu tiên APCu
        if (function_exists('apcu_fetch')) {
            $data = @apcu_fetch($this->key, $success);
            return $success ? $data : null;
        }

        // Fallback: file-based cache (#102)
        $file = $this->cacheFile();
        if (!file_exists($file)) return null;

        $content = @file_get_contents($file);
        if ($content === false) return null;

        $data = @json_decode($content, true);
        if (!is_array($data)) return null;

        return $data;
    }

    /** Lưu dữ liệu vào cache */
    private function set(array $data): void
    {
        // Ưu tiên APCu
        if (function_exists('apcu_store')) {
            @apcu_store($this->key, $data, $this->window + 10);
            return;
        }

        // Fallback: file-based cache (#102)
        $file = $this->cacheFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /** Đường dẫn file cache cho key này */
    private function cacheFile(): string
    {
        $hash = md5($this->key);
        return self::$cacheDir . '/rl_' . substr($hash, 0, 2) . '/' . $hash . '.json';
    }
}

/* ================================================================
   MIDDLEWARE: thêm rate limit headers vào response
   ================================================================ */

/** Tạo rate limiter nhanh cho endpoint */
function rate_limit(string $name, int $limit, int $window = 60): RateLimiter
{
    return new RateLimiter($name, $limit, $window);
}

/** Kiểm tra và trả lỗi 429 nếu vượt limit */
function enforce_rate_limit(string $name, int $limit, int $window = 60): void
{
    $rl = new RateLimiter($name, $limit, $window);
    $info = $rl->info();

    header("X-RateLimit-Limit: {$info['limit']}");
    header("X-RateLimit-Remaining: {$info['remaining']}");
    header("X-RateLimit-Reset: {$info['reset']}");

    if (!$rl->attempt()) {
        http_response_code(429);
        header('Retry-After: ' . $info['resetIn']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau ' . $info['resetIn'] . ' giây.',
            'retry_after' => $info['resetIn'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/* ================================================================
   PRESET RATE LIMITERS cho các endpoint phổ biến
   ================================================================ */

/** Rate limit cho login: 5 lần/phút/IP hoặc 3 lần/phút/SĐT */
function enforce_login_rate_limit(): void
{
    $ipRl = new RateLimiter('login_ip', 20, 60);
    $info = $ipRl->info();
    header("X-RateLimit-Limit: {$info['limit']}");
    header("X-RateLimit-Remaining: {$info['remaining']}");
    header("X-RateLimit-Reset: {$info['reset']}");

    if (!$ipRl->attempt()) {
        http_response_code(429);
        header('Retry-After: ' . $info['resetIn']);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Quá nhiều yêu cầu đăng nhập. Vui lòng đợi ' . $info['resetIn'] . ' giây.',
            'retry_after' => $info['resetIn'],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

/** Rate limit cho read APIs: 60 lần/phút/user */
function enforce_api_read_limit(): void
{
    enforce_rate_limit('api_read', 60, 60);
}

/** Rate limit cho write APIs: 30 lần/phút/user */
function enforce_api_write_limit(): void
{
    enforce_rate_limit('api_write', 30, 60);
}

/** Rate limit cho register: 3 lần/giờ/IP */
function enforce_register_rate_limit(): void
{
    enforce_rate_limit('register', 3, 3600);
}
