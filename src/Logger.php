<?php
/**
 * LOGGER - PSR-3 Compatible
 *
 * File-based logging với daily rotation.
 * Implements Psr\Log\LoggerInterface
 */

declare(strict_types=1);

namespace TNTT;

use Throwable;

class Logger
{
    public const EMERGENCY = 0;
    public const ALERT     = 1;
    public const CRITICAL  = 2;
    public const ERROR     = 3;
    public const WARNING   = 4;
    public const NOTICE    = 5;
    public const INFO     = 6;
    public const DEBUG    = 7;

    private string $logPath;
    private int $minLevel;
    private static ?Logger $instance = null;

    /** @var array<string, int> */
    private static array $levels = [
        'emergency' => self::EMERGENCY,
        'alert'     => self::ALERT,
        'critical'  => self::CRITICAL,
        'error'     => self::ERROR,
        'warning'   => self::WARNING,
        'notice'    => self::NOTICE,
        'info'      => self::INFO,
        'debug'     => self::DEBUG,
    ];

    /**
     * @param string $logPath Duong dan thu muc log (vd: __DIR__ . '/../logs')
     * @param int    $minLevel Muc log thap nhat can ghi (PSR-3 style: DEBUG=7, INFO=6, WARNING=4, ERROR=3, CRITICAL=2)
     */
    public function __construct(string $logPath, int $minLevel = self::WARNING)
    {
        $this->logPath = rtrim($logPath, '/\\');
        $this->minLevel = $minLevel;

        if (!is_dir($this->logPath)) {
            @mkdir($this->logPath, 0755, true);
        }
    }

    /** Singleton instance */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            $basePath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
            self::$instance = new self($basePath . '/logs', self::WARNING);
        }
        return self::$instance;
    }

    /**
     * Khoi tao Logger tu config
     */
    public static function bootstrap(): void
    {
        $basePath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
        $logPath = $basePath . '/logs';

        // Doc muc log tu config, mac dinh WARNING
        $minLevel = self::WARNING;
        if (function_exists('app_config')) {
            $level = app_config('log_level') ?? 'warning';
            $minLevel = self::$levels[strtolower($level)] ?? self::WARNING;
        }

        self::$instance = new self($logPath, $minLevel);
    }

    /** Ghi log */
    public function log(int $level, string|\Stringable $message, array $context = []): void
    {
        if ($level > $this->minLevel) {
            return;
        }

        $levelName = $this->getLevelName($level);
        $message = $this->interpolate((string) $message, $context);

        $line = sprintf(
            '[%s] %s: %s %s',
            date('Y-m-d H:i:s'),
            $levelName,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        $this->write($line);
    }

    // PSR-3 compatible methods
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /** Ghi exception */
    public function exception(Throwable $e, array $context = []): void
    {
        $this->error($e->getMessage(), array_merge([
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $context));
    }

    private function getLevelName(int $level): string
    {
        foreach (self::$levels as $name => $value) {
            if ($value === $level) {
                return strtoupper($name);
            }
        }
        return 'LOG';
    }

    private function interpolate(string $message, array $context): string
    {
        if (empty($context)) {
            return $message;
        }

        $replace = [];
        foreach ($context as $key => $val) {
            if (is_string($val) || is_numeric($val) || is_bool($val) || is_null($val)) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }

        return strtr($message, $replace);
    }

    /** Ghi vao file voi daily rotation */
    private function write(string $line): void
    {
        $filename = $this->logPath . '/error-' . date('Y-m-d') . '.log';
        $line .= PHP_EOL;

        @file_put_contents($filename, $line, FILE_APPEND | LOCK_EX);
    }

    /** Xoa log cu hon N ngay */
    public static function cleanupOldLogs(string $logPath, int $days = 30): int
    {
        $logPath = rtrim($logPath, '/\\');
        if (!is_dir($logPath)) {
            return 0;
        }

        $count = 0;
        $cutoff = strtotime("-{$days} days");

        foreach (glob($logPath . '/error-*.log') as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }
}
