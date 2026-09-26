<?php
/**
 * DATABASE MIGRATIONS
 *
 * Hệ thống migration đơn giản cho TNTT.
 * Chạy: php config/migrations/index.php
 *
 * Cấu trúc:
 * - 001_initial_schema.sql: Schema ban đầu
 * - 002_*.sql: Các migration tiếp theo
 *
 * Theo dõi migration đã chạy trong bảng schema_migrations
 */

require_once __DIR__ . '/../../public/api/_bootstrap.php';

// Migration files directory
define('MIGRATIONS_DIR', __DIR__);

// Bảng tracking migrations
function ensure_migrations_table(): void
{
    db_run("CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        executed_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

// Lấy danh sách migrations đã chạy
function get_executed_migrations(): array
{
    return array_column(db_all('SELECT name FROM schema_migrations ORDER BY id'), 'name');
}

// Lấy danh sách migrations chưa chạy
function get_pending_migrations(): array
{
    $executed = get_executed_migrations();
    $files = glob(MIGRATIONS_DIR . '/*.sql');
    $pending = [];

    foreach ($files as $file) {
        $name = basename($file);
        if (!in_array($name, $executed)) {
            $pending[] = $name;
        }
    }

    sort($pending);
    return $pending;
}

// Chạy một migration
function run_migration(string $file): bool
{
    $sql = file_get_contents($file);
    $name = basename($file);

    try {
        db()->beginTransaction();

        // Chạy SQL - tách thành từng câu lệnh, bỏ qua comment và dòng trống
        // Xử lý comment trên dòng riêng và cuối dòng
        $lines = explode("\n", $sql);
        $statements = [];
        $current = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Bỏ qua dòng comment hoàn toàn
            if (empty($trimmed) || str_starts_with($trimmed, '--')) {
                continue;
            }
            $current .= ' ' . $line;
            // Nếu có dấu ; ở cuối dòng (sau khi trim)
            if (str_ends_with(trim($current), ';')) {
                $stmt = trim($current);
                // Bỏ comment ở cuối dòng
                $semicolonPos = strrpos($stmt, ';');
                if ($semicolonPos !== false) {
                    $stmt = substr($stmt, 0, $semicolonPos);
                }
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $current = '';
            }
        }
        // Xử lý statement cuối cùng nếu không có ;
        if (!empty(trim($current))) {
            $stmt = trim($current);
            $semicolonPos = strrpos($stmt, ';');
            if ($semicolonPos !== false) {
                $stmt = substr($stmt, 0, $semicolonPos);
            }
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
        }

        foreach ($statements as $s) {
            if (!empty(trim($s))) {
                db_run($s);
            }
        }

        // Ghi nhận migration
        db_run('INSERT INTO schema_migrations (name) VALUES (?)', [$name]);

        db()->commit();

        echo "✅ {$name}\n";
        return true;
    } catch (Throwable $e) {
        db()->rollBack();
        echo "❌ {$name}: " . $e->getMessage() . "\n";
        return false;
    }
}

// Rollback một migration (chỉ ghi nhận, không revert SQL)
function rollback_migration(string $name): bool
{
    try {
        db_run('DELETE FROM schema_migrations WHERE name = ?', [$name]);
        echo "↩️ Rollback recorded for {$name}\n";
        return true;
    } catch (Throwable $e) {
        echo "❌ Rollback failed for {$name}: " . $e->getMessage() . "\n";
        return false;
    }
}

// Main
if (php_sapi_name() === 'cli') {
    echo "=== TNTT Database Migrations ===\n\n";

    ensure_migrations_table();

    $pending = get_pending_migrations();

    if (empty($pending)) {
        echo "✅ Không có migration nào chờ.\n";
        exit(0);
    }

    $count = count($pending);
    echo "📋 {$count} migration(s) chờ:\n";
    foreach ($pending as $f) {
        echo "  - {$f}\n";
    }
    echo "\n";

    // Check for rollback command
    if (isset($argv[1]) && $argv[1] === 'rollback') {
        $name = $argv[2] ?? null;
        if (!$name) {
            echo "Usage: php index.php rollback <migration_name>\n";
            exit(1);
        }
        rollback_migration($name);
        exit(0);
    }

    // Check for status command
    if (isset($argv[1]) && $argv[1] === 'status') {
        $executed = get_executed_migrations();
        echo "📋 Đã chạy:\n";
        foreach ($executed as $m) {
            echo "  ✅ {$m}\n";
        }
        exit(0);
    }

    // Run all pending migrations
    echo "🔄 Bắt đầu migration...\n\n";
    $success = 0;
    $failed = 0;

    foreach ($pending as $file) {
        $fullPath = MIGRATIONS_DIR . '/' . $file;
        if (run_migration($fullPath)) {
            $success++;
        } else {
            $failed++;
            break; // Stop on first failure
        }
    }

    echo "\n";
    if ($failed === 0) {
        echo "✅ Hoàn tất! {$success} migration(s) đã chạy thành công.\n";
    } else {
        echo "❌ Có {$failed} migration thất bại. Dừng lại.\n";
        exit(1);
    }
} else {
    // Web access - show status
    ensure_migrations_table();
    $executed = get_executed_migrations();
    $pending = get_pending_migrations();

    header('Content-Type: text/plain; charset=utf-8');
    echo "=== TNTT Database Migrations ===\n\n";
    echo "Đã chạy: " . count($executed) . " migrations\n";
    echo "Chờ: " . count($pending) . " migrations\n\n";

    if (!empty($pending)) {
        echo "CHẠY TỪ COMMAND LINE:\n";
        echo "  php config/migrations/index.php\n";
    }
}
