<?php
/**
 * BASE CONTROLLER — Base class for all controllers
 *
 * Provides common methods for JSON responses, authentication, and logging.
 */
require_once __DIR__ . '/../../public/api/_bootstrap.php';

abstract class BaseController
{
    /** @var array Current logged in user */
    protected ?array $user = null;

    /** @var Logger */
    protected Logger $logger;

    public function __construct()
    {
        $this->logger = logger();
    }

    /**
     * Require authentication and return current user
     */
    protected function requireAuth(): array
    {
        $this->user = require_login();
        return $this->user;
    }

    /**
     * Check if user has permission
     */
    protected function requirePermission(string $module, string $level = 'view'): array
    {
        return require_permission($module, $level);
    }

    /**
     * Return JSON success response
     */
    protected function ok(array $data = [], int $code = 200): never
    {
        json_out(array_merge(['ok' => true], $data), $code);
    }

    /**
     * Return JSON error response
     */
    protected function fail(string $message, int $code = 400): never
    {
        json_fail($message, $code);
    }

    /**
     * Log an action
     */
    protected function log(string $action, string $module, string $what, string $detail = ''): void
    {
        log_action($action, $module, $what, $detail);
        $this->logger->info("$action: $module - $what", ['detail' => $detail]);
    }

    /**
     * Log an error
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * Get request body as array
     */
    protected function body(): array
    {
        return json_input();
    }

    /**
     * Get query parameter
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}
