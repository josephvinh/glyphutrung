<?php
/**
 * BASECONTROLLER - Base class cho cac Controller
 *
 * Cung cap cac helper method chung cho viec xu ly request/response
 */

namespace TNTT\Controllers;

class BaseController
{
    /** @var array Request input */
    protected array $input = [];

    /** @var array Current user */
    protected array $user = [];

    /** @var array|null Current year */
    protected ?array $year = null;

    /** Khoi tao controller - parse input */
    public function __construct()
    {
        $this->input = $this->parseInput();
    }

    /**
     * Parse request input (JSON body or $_POST)
     */
    protected function parseInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return $_POST;
        }
        $data = json_decode($raw, true);
        if (is_array($data)) return $data;
        return $_POST;
    }

    /**
     * Lay gia tri tu input
     */
    protected function get(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * Lay gia tri required - throw neu khong co
     */
    protected function require(string $key): mixed
    {
        $value = $this->get($key);
        if ($value === null) {
            $this->fail('Thiếu thông tin: ' . $key, 400);
        }
        return $value;
    }

    /**
     * Tra ve JSON response
     */
    protected function json(array $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Tra ve JSON success
     */
    protected function ok(array $data = [], int $code = 200): never
    {
        $this->json(array_merge(['ok' => true], $data), $code);
    }

    /**
     * Tra ve JSON error
     */
    protected function fail(string $message, int $code = 400): never
    {
        $this->json(['ok' => false, 'error' => $message], $code);
    }

    /**
     * Require login - tra ve user hien tai
     */
    protected function requireLogin(): array
    {
        if (!function_exists('require_login')) {
            require_once __DIR__ . '/../../public/api/_common.php';
        }
        $this->user = require_login();
        return $this->user;
    }

    /**
     * Require permission cho module
     */
    protected function requirePermission(string $module, string $level = 'view'): array
    {
        if (!function_exists('require_permission')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        $this->user = require_permission($module, $level);
        return $this->user;
    }

    /**
     * Lay current year
     */
    protected function getCurrentYear(): ?array
    {
        if (!function_exists('current_year')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        $this->year = current_year();
        return $this->year;
    }

    /**
     * Validate request method
     */
    protected function requireMethod(string $method): void
    {
        $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($current) !== strtoupper($method)) {
            $this->fail('Phuong thuc ' . $method . ' required.', 405);
        }
    }

    /**
     * Validate CSRF + POST
     */
    protected function requireWrite(): void
    {
        if (!function_exists('require_write')) {
            require_once __DIR__ . '/../../public/api/_bootstrap.php';
        }
        require_write();
    }

    /**
     * Tra ve HTTP response thong thuong (khong exit)
     */
    protected function respond(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
