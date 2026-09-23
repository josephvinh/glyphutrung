-- =====================================================================
--  Migration: Ngoại lệ lịch (Thời khóa biểu lớp)
--
--  Bảng schedule_exceptions chỉ dùng để BÁO NGHỈ một buổi vào ngày cụ thể:
--  buổi đó không diễn ra -> không điểm danh và không tính vào mẫu số
--  chuyên cần. (Không có "dời giờ" / "học bù".)
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Bảng schedule_exceptions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedule_exceptions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id  INT NOT NULL COMMENT 'Lịch bị ảnh hưởng',
    on_date      DATE NOT NULL COMMENT 'Ngày báo nghỉ',
    kind         ENUM('nghỉ') NOT NULL DEFAULT 'nghỉ' COMMENT 'Chỉ có: nghỉ',
    note         VARCHAR(255) NULL COMMENT 'Ghi chú (VD: Nghỉ Tết)',

    CONSTRAINT fk_se_schedule FOREIGN KEY (schedule_id) REFERENCES class_schedules(id) ON DELETE CASCADE,
    UNIQUE KEY uq_se (schedule_id, on_date),
    INDEX idx_se_date (on_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Nếu bảng ĐÃ tạo trước đó với 'dời_giờ'/'học_bù' và cột giờ mới,
--    chạy các lệnh sau để dọn về đúng schema (bỏ qua nếu tạo mới):
-- ---------------------------------------------------------------------
-- DELETE FROM schedule_exceptions WHERE kind <> 'nghỉ';
-- ALTER TABLE schedule_exceptions
--   MODIFY kind ENUM('nghỉ') NOT NULL DEFAULT 'nghỉ',
--   DROP COLUMN new_start,
--   DROP COLUMN new_cutoff;
