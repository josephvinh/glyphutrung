-- =====================================================================
--  Migration: Chương trình gắn lớp + tùy chọn chương trình
--
--  Thay cho "Thời khóa biểu lớp" (class_schedules) trước đây. Nay:
--    - Mỗi CHƯƠNG TRÌNH mang giờ + gắn danh sách LỚP tham gia
--      (bảng program_classes; RỖNG = áp dụng toàn đoàn).
--    - Chương trình có thể LẶP NHIỀU THỨ trong tuần (days_of_week).
--    - Thêm các tùy chọn: cho phép QR, ngưỡng vắng, màu/icon, thứ tự,
--      khoảng ngày áp dụng, tự đóng chiến dịch, cờ "tính thi đua đi lễ".
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Bảng nối chương trình ↔ lớp
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS program_classes (
    program_id INT NOT NULL,
    class_id   INT NOT NULL,
    PRIMARY KEY (program_id, class_id),
    KEY idx_pc_class (class_id),
    CONSTRAINT fk_pc_prog  FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_class FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Tùy chọn thêm cho chương trình
-- ---------------------------------------------------------------------
ALTER TABLE programs
    ADD COLUMN days_of_week           VARCHAR(16) NULL COMMENT 'CSV các thứ 0-6 (lặp nhiều ngày); NULL = dùng day_of_week' AFTER day_of_week,
    ADD COLUMN allow_qr               TINYINT(1)  NOT NULL DEFAULT 1 COMMENT 'Cho phép quét QR',
    ADD COLUMN absent_time            TIME        NULL COMMENT 'Sau giờ này không ghi được (tính vắng); NULL = không dùng',
    ADD COLUMN color                  VARCHAR(20) NULL COMMENT 'Màu nhãn hiển thị (VD amber, blue...)',
    ADD COLUMN icon                   VARCHAR(32) NULL COMMENT 'Tên icon lucide',
    ADD COLUMN sort_order             TINYINT(4)  NOT NULL DEFAULT 1 COMMENT 'Thứ tự hiển thị',
    ADD COLUMN effective_from         DATE        NULL COMMENT 'Áp dụng từ ngày (buổi lặp); NULL = không giới hạn',
    ADD COLUMN effective_to           DATE        NULL COMMENT 'Áp dụng đến ngày; NULL = không giới hạn',
    ADD COLUMN auto_close_after_event TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Chiến dịch tự đóng sau ngày diễn ra',
    ADD COLUMN count_for_emulation    TINYINT(1)  NOT NULL DEFAULT 0 COMMENT 'Tính vào thi đua đi lễ (độc lập chuyên cần)';

-- ---------------------------------------------------------------------
-- 3. (Nếu môi trường ĐÃ chạy thử "Thời khóa biểu lớp" — Hướng B cũ)
--    Chạy các lệnh sau để dọn về program-centric. Bỏ qua nếu deploy mới.
-- ---------------------------------------------------------------------
-- ALTER TABLE attendances DROP FOREIGN KEY fk_att_schedule;
-- ALTER TABLE attendances DROP INDEX uq_att_session;
-- ALTER TABLE attendances DROP COLUMN session_key;
-- ALTER TABLE attendances DROP COLUMN schedule_id;
-- ALTER TABLE attendances MODIFY program_id INT NOT NULL;
-- -- khôi phục khoá chống trùng gốc nếu đã mất:
-- ALTER TABLE attendances ADD UNIQUE KEY uq_att (program_id, session_date, student_id);
-- DROP TABLE IF EXISTS schedule_exceptions;
-- DROP TABLE IF EXISTS class_schedules;
