<?php
/**
 * Dọn cache Lời Chúa cũ
 *
 * Chạy định kỳ: 0 3 * * * php /path/to/config/cron_loichua_cleanup.php
 *
 * Xóa các file cache cũ hơn 30 ngày.
 */

define('CACHE_DIR', __DIR__ . '/../storage/loichua');
define('MAX_AGE_DAYS', 30);

$cleaned = 0;
$errors = 0;

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    echo "Cache directory not found: " . CACHE_DIR . "\n";
    exit(0);
}

// Get all JSON files
$files = glob(CACHE_DIR . '/*.json');
$cutoff = time() - (MAX_AGE_DAYS * 24 * 60 * 60);

foreach ($files as $file) {
    // Skip if not a file
    if (!is_file($file)) {
        continue;
    }

    // Check modification time
    $mtime = filemtime($file);
    if ($mtime === false) {
        continue;
    }

    if ($mtime < $cutoff) {
        if (@unlink($file)) {
            $cleaned++;
        } else {
            $errors++;
        }
    }
}

echo sprintf(
    "Lời Chúa cache cleanup: cleaned=%d, errors=%d, cutoff=%d days\n",
    $cleaned,
    $errors,
    MAX_AGE_DAYS
);

exit($errors > 0 ? 1 : 0);
