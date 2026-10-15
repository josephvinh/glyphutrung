-- ================================================================
--  Migration 031: Organization & Personal Modules — schema
--
--  Thêm cột `shift` (sáng/chiều) cho classes, cột `glv_year` cho
--  members, và bảng `member_service_history` (có khoá ngoại tới
--  members/roles/blocks/classes) để lưu lịch sử phục vụ của nhân sự
--  theo từng năm học. Idempotent: chạy lại, hoặc chạy trên DB đã có bảng
--  từ bản cũ (thiếu COLLATE/khoá ngoại), đều an toàn. Không xoá/sửa dòng nào:
--  dữ liệu cũ vi phạm khoá ngoại thì lệnh thêm khoá báo lỗi (xem bước 4b).
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

        CONSTRAINT fk_hist_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
        CONSTRAINT fk_hist_role   FOREIGN KEY (role_code) REFERENCES roles(code),
        CONSTRAINT fk_hist_block  FOREIGN KEY (block_id)  REFERENCES blocks(id)  ON DELETE SET NULL,
        CONSTRAINT fk_hist_class  FOREIGN KEY (class_id)  REFERENCES classes(id) ON DELETE SET NULL,

        UNIQUE KEY uk_member_year (member_id, year_label),
        INDEX idx_history_member (member_id),
        INDEX idx_history_year (year_label),
        INDEX idx_history_auto (is_auto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 4. Bảng đã được tạo từ bản 031 cũ (không có COLLATE, không có khoá ngoại)
--    Chạy lại an toàn: bảng đã đúng thì mọi bước dưới đều là SELECT 1 / không đổi dòng nào.
-- =====================================================

-- 4a. Cùng bảng mã/so sánh với roles, members... (khoá ngoại + JOIN roles cần giống nhau)
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
     WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = 'member_service_history'
       AND TABLE_COLLATION <> 'utf8mb4_unicode_ci') > 0,
    'ALTER TABLE member_service_history CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
    'SELECT 1'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4b. KHÔNG tự xoá/sửa dữ liệu. Nếu bảng cũ có dòng mồ côi (thành viên đã xoá) hoặc tham chiếu
--     hỏng (vai/khối/lớp không còn), bước 4c sẽ BÁO LỖI và dừng — để bạn thấy và quyết định.
--     Dọn tay rồi chạy lại file này, ví dụ:
--       DELETE h FROM member_service_history h LEFT JOIN members m ON m.id = h.member_id WHERE m.id IS NULL
--       UPDATE member_service_history SET block_id = NULL WHERE block_id IS NOT NULL AND block_id NOT IN (SELECT id FROM blocks)
--       UPDATE member_service_history SET class_id = NULL WHERE class_id IS NOT NULL AND class_id NOT IN (SELECT id FROM classes)
--       UPDATE member_service_history SET role_code = NULL WHERE role_code IS NOT NULL AND role_code NOT IN (SELECT code FROM roles)

-- 4c. Thêm khoá ngoại còn thiếu
SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'member_service_history'
       AND CONSTRAINT_NAME = 'fk_hist_member') > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD CONSTRAINT fk_hist_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'member_service_history'
       AND CONSTRAINT_NAME = 'fk_hist_role') > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD CONSTRAINT fk_hist_role FOREIGN KEY (role_code) REFERENCES roles(code)'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'member_service_history'
       AND CONSTRAINT_NAME = 'fk_hist_block') > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD CONSTRAINT fk_hist_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @stmt = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = @dbname AND TABLE_NAME = 'member_service_history'
       AND CONSTRAINT_NAME = 'fk_hist_class') > 0,
    'SELECT 1',
    'ALTER TABLE member_service_history ADD CONSTRAINT fk_hist_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL'
));
PREPARE stmt FROM @stmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
