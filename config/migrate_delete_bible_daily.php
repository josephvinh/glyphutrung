<?php
/**
 * Migration: Xóa table bible_daily
 *
 * Table này không còn cần thiết sau khi chuyển sang nguồn gospel-data.
 * Chạy: php config/migrate_delete_bible_daily.php
 *
 * @see issue #229
 */

require __DIR__ . '/_bootstrap.php';

echo "=== Migration: Xóa table bible_daily ===\n\n";

// Kiểm tra table có tồn tại không
$exists = db_val("SHOW TABLES LIKE 'bible_daily'");

if (!$exists) {
    echo "Table 'bible_daily' không tồn tại. Không cần xóa.\n";
    exit(0);
}

// Đếm số records trước khi xóa
$count = (int)db_val('SELECT COUNT(*) FROM bible_daily');
echo "Records trong bible_daily: {$count}\n";

// Backup data (optional - uncomment if needed)
/*
echo "Tạo backup...\n";
$rows = db_all('SELECT * FROM bible_daily');
$backup = json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents(__DIR__ . '/../storage/backup_bible_daily_' . date('Y-m-d') . '.json', $backup);
echo "Backup saved to storage/backup_bible_daily_" . date('Y-m-d') . ".json\n";
*/

// Xóa table
echo "Xóa table...\n";
try {
    db_run('DROP TABLE IF EXISTS bible_daily');
    echo "✅ Table 'bible_daily' đã được xóa thành công.\n";
} catch (Throwable $e) {
    echo "❌ Lỗi khi xóa table: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n=== Migration hoàn tất ===\n";
