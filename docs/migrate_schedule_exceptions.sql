-- =====================================================================
--  Migration: Ngoại lệ lịch (GĐ 4 - Thời khóa biểu lớp)
--
--  Bảng schedule_exceptions cho phép:
--    - Nghỉ lễ: schedule không diễn ra vào ngày đó
--    - Dời giờ: schedule vẫn diễn ra nhưng giờ khác
--    - Học bù: thêm buổi bù vào ngày không có lịch
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Bảng schedule_exceptions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schedule_exceptions (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id  INT NOT NULL COMMENT 'Lịch bị ảnh hưởng',
    on_date      DATE NOT NULL COMMENT 'Ngày ngoại lệ',
    kind         ENUM('nghỉ','dời_giờ','học_bù') NOT NULL COMMENT 'Loại ngoại lệ',
    new_start    TIME NULL COMMENT 'Giờ mới (dời_giờ hoặc học_bù)',
    new_cutoff   TIME NULL COMMENT 'Giờ chốt mới (dời_giờ hoặc học_bù)',
    note         VARCHAR(255) NULL COMMENT 'Ghi chú (VD: Nghỉ Tết, Học bù...)',

    CONSTRAINT fk_se_schedule FOREIGN KEY (schedule_id) REFERENCES class_schedules(id) ON DELETE CASCADE,
    UNIQUE KEY uq_se (schedule_id, on_date),
    INDEX idx_se_date (on_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Cập nhật migration chính: thêm bảng này sau khi chạy
-- ---------------------------------------------------------------------
-- Sau khi chạy file này, chạy migration chính (GĐ 1) để tạo bảng class_schedules
-- nếu chưa có.
