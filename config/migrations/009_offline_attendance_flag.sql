-- ================================================================
--  Migration 009: Điểm danh offline — scanned_at + offline_marked
--
--  scanned_at: mốc quét thật từ client (epoch ms → server ghi datetime)
--  offline_marked: cờ = 1 khi mốc lệch >5 phút, BĐH cần rà soát
-- ================================================================

SET @dbname = DATABASE();
SET @tablename = 'attendances';

-- scanned_at
SET @columnname = 'scanned_at';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE attendances ADD COLUMN scanned_at DATETIME NULL COMMENT ''mốc quét thật từ client'''
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- offline_marked
SET @columnname = 'offline_marked';
SET @preparedStatement = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE attendances ADD COLUMN offline_marked TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1 = gửi từ hàng đợi offline'''
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
