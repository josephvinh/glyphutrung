<?php
/**
 * LOGGER — PSR-3 compatible logging
 *
 * Features:
 * - Multiple log levels (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * - File-based logging with rotation
 * - Context support
 * - Error tracking for exceptions
 */

class Logger
{
    /** @var string Log directory path */
    private string $logDir;

    /** @var string Current log file */
    private string $logFile;

    /** @var int Max file size in bytes (10MB default) */
    private int $maxFileSize = 10485760;

    /** @var int Number of rotated files to keep */
    private int $maxFiles = 5;

    /** Log levels */
    const DEBUG     = 'DEBUG';
    const INFO      = 'INFO';
    const WARNING   = 'WARNING';
    const ERROR     = 'ERROR';
    const CRITICAL  = 'CRITICAL';

    /**
     * @param string|null $logDir Custom log directory (default: logs/)
     */
    public function __construct(?string $logDir = null)
    {
        $this->logDir = $logDir ?? dirname(__DIR__) . '/logs';
        $this->logFile = $this->logDir . '/app.log';

        // Create log directory if not exists
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }

    /**
     * Log a debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Log an info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    /**
     * Log a warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    /**
     * Log an error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    /**
     * Log a critical message
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    /**
     * Log an exception
     */
    public function exception(Throwable $e, array $context = []): void
    {
        $context['exception'] = [
            'class'   => get_class($e),
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ];

        $this->log(self::ERROR, $e->getMessage(), $context);
    }

    /**
     * Log with custom level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        // Rotate log file if needed
        $this->rotateIfNeeded();

        // Format message
        $timestamp = date('Y-m-d H:i:s.u');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        $logLine = sprintf(
            "[%s] %-8s %s%s\n",
            $timestamp,
            $level,
            $message,
            $contextStr
        );

        // Write to file
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get all log entries (for admin dashboard)
     */
    public function getEntries(int $limit = 100, int $offset = 0, ?string $level = null): array
    {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $entries = [];

        foreach (array_reverse($lines) as $line) {
            $entry = $this->parseLine($line);
            if ($entry && ($level === null || $entry['level'] === $level)) {
                $entries[] = $entry;
            }

            if (count($entries) >= ($offset + $limit)) {
                break;
            }
        }

        return array_slice($entries, $offset, $limit);
    }

    /**
     * Parse a log line back into structured data
     */
    private function parseLine(string $line): ?array
    {
        // Format: [2024-01-15 10:30:45.123456] LEVEL    message {...context}
        if (preg_match('/^\[([^\]]+)\]\s+(\w+)\s+(.+)$/', $line, $matches)) {
            $context = [];
            if (preg_match('/\{.*\}$/', $matches[3], $ctxMatch)) {
                $context = json_decode($ctxMatch[0], true) ?? [];
                $matches[3] = trim(substr($matches[3], 0, -strlen($ctxMatch[0])));
            }

            return [
                'timestamp' => $matches[1],
                'level'    => $matches[2],
                'message'  => trim($matches[3]),
                'context'  => $context,
            ];
        }

        return null;
    }

    /**
     * Rotate log file if it exceeds max size
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) < $this->maxFileSize) {
            return;
        }

        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = "{$this->logFile}.{$i}";
            $newFile = "{$this->logFile}." . ($i + 1);

            if (file_exists($oldFile)) {
                if ($i + 1 > $this->maxFiles) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Rotate current to .1
        rename($this->logFile, "{$this->logFile}.1");

        // Create new empty log
        touch($this->logFile);
    }

    /**
     * Clear all log files
     */
    public function clear(): void
    {
        $files = glob($this->logDir . '/app.log*');
        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * Get log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}

/**
 * Global logger instance (lazy loaded)
 */
function logger(?string $logDir = null): Logger
{
    static $instance = null;

    if ($instance === null) {
        $instance = new Logger($logDir);
    }

    return $instance;
}
