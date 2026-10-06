<?php
/**
 * CRON CLEANUP — xóa dữ liệu cũ theo retention policy
 *
 * Chạy định kỳ (vd 1 lần/ngày) qua cron:
 *   0 3 * * * php /path/to/config/cron_cleanup.php >> /var/log/tntt_cleanup.log 2>&1
 *
 * Retention:
 *   - activity_logs: 6 tháng (giữ audit trail)
 *   - bible_daily: 30 ngày (IP rate-limit)
 *   - students: ẩn sau 12 tháng không hoạt động, xóa sau 7 năm
 *   - tracuu_code_fails: tự dọn trong _tracuu.php
 *   - login_attempts: tự dọn trong _bootstrap.php
 */

require __DIR__ . '/db.php';
require __DIR__ . '/config.php';

$cfg = app_config() ?: [];
$retention = $cfg['retention'] ?? ['hide_after_months' => 12, 'delete_after_years' => 7];

$deleted = [];

function cleanup(string $table, string $column, int $days): int {
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
    $stmt = db()->prepare("DELETE FROM {$table} WHERE {$column} < ?");
    $stmt->execute([$cutoff]);
    return $stmt->rowCount();
}

// PR-2: Student retention policy
// 1. Ẩn các em không hoạt động > 12 tháng (không có enrollment "đang sinh hoạt" gần đây)
try {
    $hideAfterMonths = (int) ($retention['hide_after_months'] ?? 12);
    $hideCutoff = date('Y-m-d H:i:s', strtotime("-{$hideAfterMonths} months"));

    // Tìm các em đang ẩn nhưng không có enrollment "đang sinh hoạt" gần cutoff
    // Dựa trên: students (id), enrollments (student_id, status, year_id), school_years (id, start_date)
    $stmt = db()->prepare("
        UPDATE students s
        SET s.hidden_at = NOW()
        WHERE s.hidden_at IS NULL
          AND s.deleted_at IS NULL
          AND NOT EXISTS (
              SELECT 1 FROM enrollments e
              JOIN school_years y ON y.id = e.year_id
              WHERE e.student_id = s.id
                AND e.status = 'đang sinh hoạt'
                AND y.start_date >= ?
          )
    ");
    $stmt->execute([$hideCutoff]);
    $n = $stmt->rowCount();
    if ($n > 0) $deleted['students_hidden'] = $n;
} catch (Throwable $e) {
    error_log('[cron_cleanup] students hide: ' . $e->getMessage());
}

// 2. Xóa các em đã ẩn > 7 năm
try {
    $deleteAfterYears = (int) ($retention['delete_after_years'] ?? 7);
    $deleteCutoff = date('Y-m-d H:i:s', strtotime("-{$deleteAfterYears} years"));

    // Cập nhật deleted_at cho các em đã ẩn đủ lâu nhưng chưa có deleted_at
    $stmt = db()->prepare("
        UPDATE students
        SET deleted_at = NOW()
        WHERE hidden_at IS NOT NULL
          AND deleted_at IS NULL
          AND hidden_at < ?
    ");
    $stmt->execute([$deleteCutoff]);
    $n = $stmt->rowCount();
    if ($n > 0) $deleted['students_deleted'] = $n;

    // Xóa hẳn các em đã có deleted_at > 7 năm
    // LƯU Ý: Chỉ xóa khi đã có backup!
    $stmt2 = db()->prepare("
        DELETE FROM students
        WHERE deleted_at IS NOT NULL
          AND deleted_at < ?
    ");
    $stmt2->execute([$deleteCutoff]);
    $n2 = $stmt2->rowCount();
    if ($n2 > 0) $deleted['students_purged'] = $n2;
} catch (Throwable $e) {
    error_log('[cron_cleanup] students delete: ' . $e->getMessage());
}

// Activity logs: giữ 6 tháng (dùng cột logged_at, không phải created_at)
try {
    $n = cleanup('activity_logs', 'logged_at', 180);
    if ($n > 0) $deleted['activity_logs'] = $n;
} catch (Throwable $e) {
    error_log('[cron_cleanup] activity_logs: ' . $e->getMessage());
}

// Bible daily: giữ 30 ngày (đã có trong bible.php nhưng cron đảm bảo)
try {
    $n = cleanup('bible_daily', 'fetched_at', 30);
    if ($n > 0) $deleted['bible_daily'] = $n;
} catch (Throwable $e) {
    error_log('[cron_cleanup] bible_daily: ' . $e->getMessage());
}

if (PHP_SAPI === 'cli') {
    if (empty($deleted)) {
        echo "No cleanup needed.\n";
    } else {
        echo "Cleaned up:\n";
        foreach ($deleted as $table => $n) {
            echo "  {$table}: {$n} rows\n";
        }
    }
}
