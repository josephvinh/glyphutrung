<?php
/**
 * GIỜ CHỐT RIÊNG CHO CHƯƠNG TRÌNH — chạy MỘT LẦN trên máy chủ thật.
 *
 *   php config/migrate_program_cutoff.php
 *
 * Trước đây giờ chốt = giờ bắt đầu + 30 phút (cố định, không lưu). Nay cho
 * BĐH nhập giờ chốt riêng cho từng chương trình. Thêm cột cutoff_time:
 *   NULL  -> vẫn dùng mặc định giờ bắt đầu + CUTOFF_MINUTES (tương thích cũ)
 *   HH:MM -> dùng đúng giờ chốt đã nhập
 * Idempotent: chạy lại vô hại.
 */

require __DIR__ . '/db.php';

$co = db_one(
    "SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'programs' AND COLUMN_NAME = 'cutoff_time'");

if (!$co) {
    db_run("ALTER TABLE programs ADD COLUMN cutoff_time TIME NULL AFTER start_time");
    echo "  + programs.cutoff_time\n";
} else {
    echo "  = programs.cutoff_time (đã có)\n";
}

echo "✓ Xong migrate_program_cutoff\n";
