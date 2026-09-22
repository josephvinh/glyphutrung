-- =====================================================================
--  Migration: Thời khóa biểu riêng cho từng lớp (Hướng B)
--  Bước 1 của 4 — GĐ 1: Nền dữ liệu
--
--  Tạo bảng class_schedules để mỗi lớp sở hữu lịch sinh hoạt riêng,
--  và thêm cột schedule_id vào attendances để truy vết slot.
--
--  Tương thích ngược: bản ghi cũ giữ schedule_id = NULL.
-- =====================================================================

-- ---------------------------------------------------------------------
-- 1. Bảng class_schedules — thời khóa biểu tuần của từng lớp
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS class_schedules (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    year_id      INT NOT NULL COMMENT 'Niên khoá',
    class_id     INT NOT NULL COMMENT 'Lớp sở hữu lịch này',
    program_id   INT DEFAULT NULL COMMENT 'Loại buổi: Giáo lý thường / Lễ thứ Năm... NULL = giáo lý mặc định',
    day_of_week  TINYINT NOT NULL COMMENT '0=Chúa Nhật .. 6=Thứ Bảy',
    start_time   TIME NOT NULL COMMENT 'Giờ bắt đầu buổi sinh hoạt',
    cutoff_time  TIME DEFAULT NULL COMMENT 'Giờ chốt sổ riêng cho lớp; NULL = dùng program.cutoff_time',
    slot         ENUM('sáng','chiều','tối') DEFAULT NULL COMMENT 'Nhãn hiển thị: ca sáng / chiều / tối',
    active_from  DATE DEFAULT NULL COMMENT 'Hiệu lực từ ngày (cho đổi lịch giữa năm)',
    active_to    DATE DEFAULT NULL COMMENT 'Hết hiệu lực ngày (NULL = vĩnh viễn)',
    status       ENUM('kích hoạt','tạm ngưng') NOT NULL DEFAULT 'kích hoạt',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_cs_year   FOREIGN KEY (year_id)   REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_class  FOREIGN KEY (class_id)  REFERENCES classes(id)     ON DELETE CASCADE,
    CONSTRAINT fk_cs_prog   FOREIGN KEY (program_id) REFERENCES programs(id)   ON DELETE CASCADE,

    -- Mỗi lớp mỗi ngày mỗi giờ chỉ một dòng (trừ trường hợp học 2 ca cùng ngày)
    UNIQUE KEY uq_cs_slot (year_id, class_id, day_of_week, start_time, program_id),
    INDEX idx_cs_lookup (year_id, day_of_week, status),
    INDEX idx_cs_class (class_id, year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Thêm cột schedule_id vào bảng attendances
-- ---------------------------------------------------------------------
ALTER TABLE attendances
    ADD COLUMN schedule_id INT DEFAULT NULL COMMENT 'Slot điểm danh; NULL = dùng program (backward compatible)'
    AFTER program_id;

-- Khóa ngoại (cho phép NULL để tương thích dữ liệu cũ)
ALTER TABLE attendances
    ADD CONSTRAINT fk_att_schedule FOREIGN KEY (schedule_id)
    REFERENCES class_schedules(id) ON DELETE SET NULL;

-- Index để truy vấn điểm danh theo schedule nhanh
ALTER TABLE attendances
    ADD INDEX idx_att_sched (schedule_id, session_date);

-- ---------------------------------------------------------------------
-- 3. Seed dữ liệu mẫu (tùy chọn — xóa hoặc điều chỉnh theo thực tế)
--
-- Giả sử: year_id = 1 (lấy từ niên khoá hiện tại),
-- program_id = 1 (buổi Giáo lý Chúa Nhật).
-- Sau khi tạo xong, chạy script seed_class_schedules.php để tạo
-- schedule từ program hiện tại cho tất cả các lớp đang hoạt động.
-- ---------------------------------------------------------------------
-- Ví dụ (chạy sau khi biết year_id thực tế):
-- INSERT INTO class_schedules (year_id, class_id, program_id, day_of_week, start_time, slot)
-- SELECT 1, c.id, 1, 0, '08:00:00', 'sáng'
-- FROM classes c
-- WHERE c.id IN (SELECT DISTINCT class_id FROM enrollments WHERE year_id = 1 AND status = 'đang sinh hoạt');
