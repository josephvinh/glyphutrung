-- ================================================================
--  Migration 031: Organization & Personal Modules — schema
--
--  Thêm cột `shift` (sáng/chiều) cho classes, cột `glv_year` cho
--  members, và bảng `member_service_history` để lưu lịch sử phục
--  vụ của nhân sự theo từng năm học.
--  Xem design: docs/superpowers/specs/2026-10-25-organization-personal-modules-design.md
--  §5.1
-- ================================================================

SET @dbname = DATABASE();

-- =====================================================
-- 1. Thêm cột shift vào classes
-- =====================================================
SET @tablename = 'classes';
SET @columnname = 'shift';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE classes ADD COLUMN shift ENUM(''sang'',''chieu'') NULL COMMENT ''Ca học: NULL=không phân biệt, sang=buổi sáng, chieu=buổi chiều'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Idempotent: chỉ tạo index nếu chưa có
SET @indexname = 'idx_class_shift';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
    'SELECT 1',
    'CREATE INDEX idx_class_shift ON classes(shift)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2. Thêm cột glv_year vào members
-- =====================================================
SET @tablename = 'members';
SET @columnname = 'glv_year';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE members ADD COLUMN glv_year INT NULL COMMENT ''Năm lên Giáo Lý Viên (yyyy)'''
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 3. Tạo bảng member_service_history
-- =====================================================
SET @tablename = 'member_service_history';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0,
    'SELECT 1',
    'CREATE TABLE member_service_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NOT NULL,
        year_label VARCHAR(20) NOT NULL COMMENT ''VD: 2024-2025'',
        role_code VARCHAR(24) NULL COMMENT ''Vai trò trong năm đó'',
        block_id INT NULL COMMENT ''Khối được phân công'',
        class_id INT NULL COMMENT ''Lớp được phân công'',
        is_auto TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''1=tự động tạo, 0=nhập tay'',
        note VARCHAR(255) NULL COMMENT ''Ghi chú thêm'',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL COMMENT ''Ai tạo'',

        UNIQUE KEY uk_member_year (member_id, year_label),
        INDEX idx_history_member (member_id),
        INDEX idx_history_year (year_label),
        INDEX idx_history_auto (is_auto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
