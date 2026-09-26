<?php
/**
 * EXCEPTION HANDLER
 *
 * Catch all unhandled exceptions, log với stack trace,
 * va tra ve JSON error response.
 */

declare(strict_types=1);

namespace TNTT;

use Throwable;

class ExceptionHandler
{
    private Logger $logger;
    private bool $debug;

    public function __construct(?Logger $logger = null, bool $debug = false)
    {
        $this->logger = $logger ?? Logger::getInstance();
        $this->debug = $debug;
    }

    /** Dang ky handler */
    public function register(): void
    {
        set_exception_handler([$this, 'handle']);
    }

    /**
     * Xu ly exception
     */
    public function handle(Throwable $e): void
    {
        // Log exception
        $this->logException($e);

        // Neu da bat dau output, khong the send JSON nua
        if (headers_sent()) {
            echo "\n<!-- Exception: " . $e->getMessage() . " -->";
            return;
        }

        // Tra ve JSON response
        $this->sendJsonResponse($e);
    }

    /**
     * Log exception voi stack trace
     */
    private function logException(Throwable $e): void
    {
        $context = [
            'type' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ];

        // Bo sung request data trong debug mode
        if ($this->debug) {
            $context['get'] = $_GET;
            $context['post'] = $_POST;
            $context['session'] = $_SESSION ?? [];
        }

        $this->logger->exception($e, $context);
    }

    /**
     * Gui JSON error response
     */
    private function sendJsonResponse(Throwable $e): void
    {
        $statusCode = $this->getStatusCode($e);

        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'ok' => false,
            'error' => $this->getErrorMessage($e),
        ];

        // Chi them debug info trong dev mode
        if ($this->debug) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 10),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Lay HTTP status code phu hop
     */
    private function getStatusCode(Throwable $e): int
    {
        // Xac dinh status code dua tren exception type
        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }
        if ($e instanceof UnauthorizedException) {
            return 401;
        }
        if ($e instanceof ForbiddenException) {
            return 403;
        }
        if ($e instanceof NotFoundException) {
            return 404;
        }
        if ($e instanceof ValidationException) {
            return 422;
        }
        if ($e instanceof \PDOException) {
            return 500;
        }

        return 500;
    }

    /**
     * Lay thong bao loi hien thi cho client
     */
    private function getErrorMessage(Throwable $e): string
    {
        // Exception tu dinh nghia co the co message rieng
        if (method_exists($e, 'getPublicMessage')) {
            return $e->getPublicMessage();
        }

        // Debug mode: hien thi message that
        if ($this->debug) {
            return $e->getMessage();
        }

        // Production: tra ve message chung
        return 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.';
    }

    /**
     * Xu ly fatal errors
     */
    public static function handleFatal(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE], true)) {
            $logger = Logger::getInstance();

            $logger->critical('Fatal error: ' . $error['message'], [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => $error['type'],
            ]);

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'ok' => false,
                    'error' => 'Đã xảy ra lỗi hệ thống. Vui lòng thử lại sau.',
                ], JSON_UNESCAPED_UNICODE);
            }
        }
    }
}

// Custom Exception Classes
class UnauthorizedException extends \Exception
{
    public function getPublicMessage(): string
    {
        return 'Chưa đăng nhập.';
    }
}

class ForbiddenException extends \Exception
{
    public function getPublicMessage(): string
    {
        return 'Bạn không có quyền thực hiện thao tác này.';
    }
}

class NotFoundException extends \Exception
{
    public function getPublicMessage(): string
    {
        return 'Không tìm thấy tài nguyên yêu cầu.';
    }
}

class ValidationException extends \Exception
{
    private array $errors;

    public function __construct(string $message = '', array $errors = [])
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getPublicMessage(): string
    {
        return $this->getMessage() ?: 'Dữ liệu không hợp lệ.';
    }
}
