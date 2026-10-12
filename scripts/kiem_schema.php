<?php
/**
 * KIỂM DB ĐÃ ĐỦ MIGRATION CHƯA (CHỈ ĐỌC)
 *
 *   php scripts/kiem_schema.php          # kiểm DB đang cấu hình (config.local.php / TNTT_DB_*)
 *   php scripts/kiem_schema.php --sql    # in câu SQL tương đương để dán vào phpMyAdmin
 *
 * VÌ SAO: mã mới lên host mà CHƯA chạy migration thì lỗi theo kiểu khó đoán — vd
 * thiếu bảng push_notifications (migration 030) làm mọi trang sau đăng nhập trắng,
 * thiếu cột scanned_at (009) làm điểm danh lỗi. Chạy script này TRƯỚC khi đưa mã
 * lên: nó liệt kê migration nào còn thiếu và file nào cần chạy.
 *
 * AN TOÀN: chỉ SELECT, không ghi gì, chạy được trên DB thật. Mã thoát 0 = đủ,
 * 1 = còn thiếu.
 *
 * KHI THÊM MIGRATION MỚI có bảng/cột mới: thêm một dòng vào kiem_schema_danh_sach()
 * và cập nhật khối SQL trong docs/process/DEPLOY.md (test SchemaCheckTest bắt nếu
 * quên).
 */

require_once __DIR__ . '/../config/db.php';

/**
 * Đối tượng mỗi migration phải tạo ra (chỉ những thứ mã đang dùng; bảng
 * qr_card_* của 002 không có mã nào đọc nên không kiểm).
 * [file migration, bảng, cột|null (null = chỉ cần có bảng)]
 * @return list<array{0:string,1:string,2:?string}>
 */
function kiem_schema_danh_sach(): array
{
    return [
        ['003_stamps_rewards.sql',      'student_stamps',     null],
        ['003_stamps_rewards.sql',      'stamp_transactions', null],
        ['003_stamps_rewards.sql',      'gifts',              null],
        ['003_stamps_rewards.sql',      'gift_orders',        null],
        ['003_stamps_rewards.sql',      'gift_order_items',   null],
        ['004_tracuu_throttle.sql',     'tracuu_attempts',    null],
        ['005_tracuu_code_fails.sql',   'tracuu_code_fails',  null],
        ['006_bible_tracking.sql',      'bible_daily',        null],
        ['007_student_retention.sql',   'students',           'hidden_at'],
        ['007_student_retention.sql',   'students',           'deleted_at'],
        ['009_offline_attendance_flag.sql', 'attendances',    'scanned_at'],
        ['009_offline_attendance_flag.sql', 'attendances',    'offline_marked'],
        ['030_push_notifications.sql',  'push_notifications', null],
        ['030_push_notifications.sql',  'push_outbox',        'notification_id'],
    ];
}

/** Câu SQL (dán vào phpMyAdmin) cho cùng danh sách trên: cột `co` = 1 là có, 0 là THIẾU. */
function kiem_schema_sql(): string
{
    $dong = [];
    foreach (kiem_schema_danh_sach() as [$file, $bang, $cot]) {
        $dong[] = sprintf("SELECT '%s' AS migration, '%s' AS bang, %s AS cot",
            $file, $bang, $cot === null ? 'NULL' : "'$cot'");
    }
    return "SET @db = 'TÊN_DATABASE';   -- ĐỔI thành tên database thật\n"
        . "SELECT x.migration, x.bang, x.cot,\n"
        . "  CASE WHEN x.cot IS NULL\n"
        . "    THEN (SELECT COUNT(*) FROM information_schema.TABLES  t WHERE t.TABLE_SCHEMA = @db AND t.TABLE_NAME = x.bang)\n"
        . "    ELSE (SELECT COUNT(*) FROM information_schema.COLUMNS c WHERE c.TABLE_SCHEMA = @db AND c.TABLE_NAME = x.bang AND c.COLUMN_NAME = x.cot)\n"
        . "  END AS co\n"
        . "FROM (\n  " . implode("\n  UNION ALL ", $dong) . "\n) x\n"
        . "ORDER BY co, x.migration;";
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (in_array('--sql', $argv ?? [], true)) {
    echo kiem_schema_sql(), "\n";
    exit(0);
}

$ten = db_one('SELECT DATABASE() AS d')['d'] ?? '?';
echo "=== Kiểm schema (chỉ đọc) — DB: $ten ===\n";

$thieu = [];
foreach (kiem_schema_danh_sach() as [$file, $bang, $cot]) {
    if ($cot === null) {
        $co = (int) db_one(
            'SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$bang])['c'];
        $nhan = "bảng $bang";
    } else {
        $co = (int) db_one(
            'SELECT COUNT(*) c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            [$bang, $cot])['c'];
        $nhan = "cột $bang.$cot";
    }
    if ($co > 0) {
        echo "  ✅ $file — $nhan\n";
    } else {
        echo "  ❌ $file — THIẾU $nhan\n";
        $thieu[$file] = true;
    }
}

// 008 chỉ gieo dữ liệu quyền (không có bảng/cột mới): kiểm có dòng quyền thu_vien.
$thuVien = (int) db_one("SELECT COUNT(*) c FROM permissions WHERE module_key = 'thu_vien'")['c'];
if ($thuVien > 0) {
    echo "  ✅ 008_thu_vien_permissions.sql — có $thuVien dòng quyền thu_vien\n";
} else {
    echo "  ❌ 008_thu_vien_permissions.sql — THIẾU dòng quyền thu_vien\n";
    $thieu['008_thu_vien_permissions.sql'] = true;
}

echo "\n";
if (!$thieu) {
    echo "✅ Đủ: DB đã có mọi đối tượng của các migration trên.\n";
    exit(0);
}
echo "❌ Còn thiếu. Sao lưu DB rồi chạy các file sau trong config/migrations/ (theo thứ tự):\n";
$ds = array_keys($thieu);
sort($ds);
foreach ($ds as $f) echo "   - $f\n";
exit(1);
