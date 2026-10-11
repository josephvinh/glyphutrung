-- ================================================================
--  Migration 009: Đánh dấu điểm danh offline
--
--  Cờ này = 1 khi bản ghi được gửi từ hàng đợi offline
--  (client gửi offlineMark=true khi mất mạng lúc quét).
--  Giúp BĐH rà soát các trường hợp đi trễ ghi muộn.
-- ================================================================

-- Idempotent: chỉ thêm cột nếu chưa có
SET @dbname = DATABASE();
SET @tablename = 'attendances';
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
