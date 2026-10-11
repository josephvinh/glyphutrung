-- ================================================================
--  Migration 030: Push Notifications History
--
--  Thêm bảng push_notifications để lưu lịch sử notification
--  và cột notification_id vào push_outbox để track notification nào đã gửi
-- ================================================================

SET @dbname = DATABASE();

-- Bảng lưu lịch sử notification
SET @tablename = 'push_notifications';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0,
    'SELECT 1',
    CONCAT('CREATE TABLE push_notifications (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        member_id  INT UNSIGNED NOT NULL,
        tag        VARCHAR(50) NOT NULL,
        title      VARCHAR(120) NOT NULL,
        body       VARCHAR(255) NOT NULL,
        url        VARCHAR(500) DEFAULT ''/'',
        icon       VARCHAR(255) DEFAULT NULL,
        is_read    TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        INDEX idx_member_read (member_id, is_read),
        INDEX idx_member_created (member_id, created_at DESC),
        FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci')
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm cột notification_id vào push_outbox (link từ outbox -> notification)
SET @tablename = 'push_outbox';
SET @columnname = 'notification_id';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE push_outbox ADD COLUMN notification_id BIGINT UNSIGNED NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Thêm index cho notification_id
SET @tablename = 'push_outbox';
SET @indexname = 'idx_notification';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'ALTER TABLE push_outbox ADD INDEX idx_notification (notification_id)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
