<?php
/**
 * EXCEPTION HANDLER — Global exception handling
 *
 * Catches all unhandled exceptions and logs them properly.
 * Sends appropriate HTTP response for API endpoints.
 */

require_once __DIR__ . '/Logger.php';

/**
 * Setup global exception handling
 */
function setup_exception_handler(): void
{
    set_exception_handler('tntt_exception_handler');
    set_error_handler('tntt_error_handler');
}

/**
 * Handle uncaught exceptions
 */
function tntt_exception_handler(Throwable $e): void
{
    // Log the exception
    $log = logger();
    $log->exception($e, [
        'uri'    => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    ]);

    // Determine if this is an API request
    $isApi = isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false;

    if ($isApi) {
        // API: Return JSON error
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        $response = [
            'ok'    => false,
            'error' => 'Internal server error',
        ];

        // Show detailed error in debug mode
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $response['debug'] = [
                'message' => $e->getMessage(),
                'file'    => basename($e->getFile()),
                'line'    => $e->getLine(),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        // Web page: Show error page
        http_response_code(500);
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Lỗi - Gia Đình Giáo Lý Phú Trung</title>
            <style>
                body { font-family: system-ui, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
                .error { background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; padding: 20px; }
                .error h1 { color: #dc2626; margin-top: 0; }
                .details { background: #f3f4f6; padding: 10px; border-radius: 4px; font-size: 14px; margin-top: 15px; }
            </style>
        </head>
        <body>
            <div class="error">
                <h1>⚠️ Đã xảy ra lỗi</h1>
                <p>Xin lỗi, hệ thống gặp sự cố. Vui lòng thử lại sau.</p>
                <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <div class="details">
                    <strong>Error:</strong> <?= htmlspecialchars($e->getMessage()) ?><br>
                    <strong>File:</strong> <?= basename($e->getFile()) ?>:<?= $e->getLine() ?>
                </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
    }

    // Exit to prevent further output
    exit(1);
}

/**
 * Handle PHP errors (converted to ErrorException)
 */
function tntt_error_handler(int $severity, string $message, string $file, int $line): bool
{
    // Don't handle if error reporting is disabled
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Convert to ErrorException
    throw new ErrorException($message, 0, $severity, $file, $line);
}
