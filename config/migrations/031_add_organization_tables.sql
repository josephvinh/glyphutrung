-- ================================================================
--  Migration 031: Organization & Personal Modules — schema
--
--  Thêm cột `shift` (sáng/chiều) cho classes, cột `glv_year` cho
--  members, và bảng `member_service_history` để lưu lịch sử phục
--  vụ của nhân sự theo từng năm học.
--  Xem design: docs/superpowers/specs/2026-10-25-organization-personal-modules-design.md
--  §5.1
--
--  Vòng 3 (2026-10-15): bổ sung 3 cột snapshot denormalize
--  (member_holy_name, member_full_name, role_label) để history
--  không mồ côi khi xoá member; đổi member_id FK sang ON DELETE
--  SET NULL; thêm FK rõ ràng; COLLATE utf8mb4_unicode_ci đồng bộ
--  với phần còn lại của schema.
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
--    Vòng 3: bổ sung 3 cột snapshot + FK ON DELETE SET NULL + COLLATE.
-- =====================================================
SET @tablename = 'member_service_history';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename) > 0,
    'SELECT 1',
    'CREATE TABLE member_service_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        member_id INT NULL,
        member_holy_name VARCHAR(64) NULL,
        member_full_name VARCHAR(128) NULL,
        year_label VARCHAR(20) NOT NULL,
        role_code VARCHAR(24) NULL,
        role_label VARCHAR(64) NULL,
        block_id INT NULL,
        class_id INT NULL,
        is_auto TINYINT(1) NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,

        UNIQUE KEY uk_member_year (member_id, year_label),
        INDEX idx_history_member (member_id),
        INDEX idx_history_year (year_label),
        INDEX idx_history_auto (is_auto),
        CONSTRAINT fk_msh_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE SET NULL,
        CONSTRAINT fk_msh_block  FOREIGN KEY (block_id)  REFERENCES blocks(id)  ON DELETE SET NULL,
        CONSTRAINT fk_msh_class  FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE SET NULL,
        CONSTRAINT fk_msh_role   FOREIGN KEY (role_code) REFERENCES roles(code) ON DELETE RESTRICT,
        CONSTRAINT fk_msh_creator FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. Vòng 3: bổ sung 3 cột snapshot cho CSDL đã có bảng
--    (idempotent — bỏ qua nếu đã có). Đây là nhánh nâng cấp
--    cho server đang chạy schema cũ; DB mới dựng từ đầu đã
--    có 3 cột ngay trong CREATE TABLE ở mục 3.
-- =====================================================
SET @tablename = 'member_service_history';
SET @columnname = 'member_holy_name';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD COLUMN member_holy_name VARCHAR(64) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'member_full_name';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD COLUMN member_full_name VARCHAR(128) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'role_label';
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD COLUMN role_label VARCHAR(64) NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 5. Vòng 3: đổi member_id thành NULL (bỏ NOT NULL) nếu schema cũ.
-- =====================================================
SET @tablename = 'member_service_history';
SET @columnname = 'member_id';
SET @stmt = (SELECT IF(
    (SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) = 'NO',
    'ALTER TABLE member_service_history MODIFY COLUMN member_id INT NULL',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
