-- ============================================================
-- Migration: Thêm cột ghi chú cho điểm danh + index tối ưu
-- ============================================================
-- Chạy migration này TRƯỚC KHI deploy feature xuất CSV
--
-- Cách chạy:
--   mysql -u username -p database_name < docs/nang-cap-attendance-note.sql
-- Hoặc chạy qua PHP script migration runner của project

START TRANSACTION;

-- ============================================================
-- 1. Thêm cột note vào bảng attendances
-- ============================================================
-- Cột này cho phép lưu ghi chú khi điểm danh
-- VD: "Đi trễ 5 phút", "Vắng có phép - xin nghỉ ốm"

ALTER TABLE attendances
ADD COLUMN note VARCHAR(255) NULL
AFTER status;

-- ============================================================
-- 2. Thêm index cho truy vấn theo ngày + chương trình
-- ============================================================
-- Index này tối ưu truy vấn:
--   SELECT * FROM attendances
--   WHERE session_date BETWEEN '2026-09-01' AND '2026-09-30'
--     AND program_id = 5

ALTER TABLE attendances
ADD INDEX idx_att_date_prog (session_date, program_id);

-- ============================================================
-- 3. Ghi log migration
-- ============================================================
-- (Nếu project có bảng migrations, thêm vào đây)
-- INSERT INTO migrations (name, applied_at) VALUES ('nang-cap-attendance-note', NOW());

COMMIT;

-- ============================================================
-- Rollback (nếu cần undo)
-- ============================================================
-- ALTER TABLE attendances DROP COLUMN note;
-- ALTER TABLE attendances DROP INDEX idx_att_date_prog;
