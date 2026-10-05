-- ================================================================
--  Migration 007: Student Retention Policy
--
--  PR-2: Thêm cột để track retention
--  - hidden_at: ngày bị ẩn (tự động hoặc thủ công)
--  - deleted_at: ngày bị xóa mềm (chờ xóa hẳn sau 7 năm hoặc theo yêu cầu)
-- ================================================================

-- Thêm cột tracking retention vào students
ALTER TABLE students
ADD COLUMN hidden_at TIMESTAMP NULL DEFAULT NULL AFTER status,
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL AFTER hidden_at;

-- Index để query nhanh các em đang ẩn
CREATE INDEX idx_students_hidden ON students(hidden_at);

-- Index để query các em chờ xóa
CREATE INDEX idx_students_deleted ON students(deleted_at);

-- ================================================================
--  Lưu ý:
--  - Cron job chạy định kỳ để:
--    1. Ẩn các em không hoạt động > 12 tháng
--    2. Xóa các em đã bị ẩn > 7 năm
--  - File cron: config/cron_cleanup.php (đã tạo)
-- ================================================================
