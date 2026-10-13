<?php
/**
 * Migration: Xóa table bible_daily
 *
 * LƯU Ý: public/api/bible.php (thẻ "Lời Chúa" trên dashboard) VẪN dùng bảng này để nhớ
 * câu đã trao cho từng IP trong 1 giờ; thiếu bảng thì thẻ vẫn chạy nhưng mất bộ nhớ đó
 * và ghi log mỗi lần gọi. Vì vậy script này KHÔNG xóa trừ khi truyền cờ --xac-nhan.
 * Chạy: php config/migrate_delete_bible_daily.php --xac-nhan
 *
 * @see issue #229
 */

if (!in_array('--xac-nhan', $argv ?? [], true)) {
    echo "Bỏ qua: bible_daily vẫn được public/api/bible.php dùng (thẻ Lời Chúa).\n";
    echo "Muốn xóa thật, chạy lại với cờ --xac-nhan.\n";
    exit(0);
}

require __DIR__ . '/db.php';

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
