-- ============================================================
-- Migration 031: Quy tắc điểm tuỳ chỉnh
--
-- 1. score_types: thêm category và max_exams
-- 2. scores: thêm exam_num, cập nhật unique key
-- 3. score_rules: bảng mới cho quy tắc hệ thống
-- 4. term_programs: bảng mới cho chương trình tính CC theo HK
-- 5. attendance_thresholds: bảng mới cho các mốc xếp loại CC
-- ============================================================

-- 1. Thêm cột vào score_types (idempotent — chạy lại không lỗi)
ALTER TABLE score_types ADD COLUMN IF NOT EXISTS category VARCHAR(32) NOT NULL DEFAULT 'homework'
    COMMENT 'homework=điểm BT, exam=điểm thi, attendance=chuyên cần';
ALTER TABLE score_types ADD COLUMN IF NOT EXISTS max_exams TINYINT NOT NULL DEFAULT 1
    COMMENT 'Số bài kiểm tra tối đa cho loại này (1-20)';

-- 2. Thêm exam_num vào scores (idempotent)
ALTER TABLE scores ADD COLUMN IF NOT EXISTS exam_num TINYINT NOT NULL DEFAULT 1
    COMMENT 'Số thứ tự bài KT trong loại (1, 2, 3...)';

-- 3. Cập nhật unique key: (term_id, student_id, type_code) → (term_id, student_id, type_code, exam_num)
--    ALTER tại chỗ, không dùng temp table.
--
--    PROBLEM: FK fk_sc_term dùng cột term_id — MariaDB dùng uq_score
--    (term_id, student_id, type_code) làm index nền. DROP INDEX uq_score
--    sẽ bị lỗi 1553 "Cannot drop index needed by foreign key".
--
--    SOLUTION 2 bước:
--    (a) Tạo index thay thế trên (term_id, student_id) — FK sẽ dùng index mới.
--    (b) DROP uq_score + ADD uq_score 4 cột trong 1 ALTER (MariaDB thực hiện
--        nguyên tử cả hai, không có khoảng trắng giữa DROP và ADD).
--    IF NOT EXISTS / IF EXISTS để chạy lại an toàn.

ALTER TABLE scores
    ADD INDEX IF NOT EXISTS idx_sc_term_student (term_id, student_id);

ALTER TABLE scores
    DROP INDEX uq_score,
    ADD UNIQUE KEY uq_score (term_id, student_id, type_code, exam_num);
-- Nếu muốn thêm CHECK constraint thì làm riêng:
-- ALTER TABLE scores ADD CONSTRAINT chk_sc_value CHECK (value >= 0 AND value <= 10);

-- 4. Tạo bảng score_rules
CREATE TABLE IF NOT EXISTS score_rules (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    rule_key      VARCHAR(64) NOT NULL UNIQUE COMMENT 'Khóa định danh',
    rule_label    VARCHAR(128) NOT NULL COMMENT 'Nhãn hiển thị',
    rule_type     ENUM('weight','threshold','formula') NOT NULL COMMENT 'Loại quy tắc',
    rule_value    TEXT NOT NULL COMMENT 'Giá trị JSON',
    description   VARCHAR(255) NULL COMMENT 'Mô tả ngắn',
    updated_by    INT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sr_by FOREIGN KEY (updated_by) REFERENCES members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tạo bảng term_programs
CREATE TABLE IF NOT EXISTS term_programs (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    term_id         INT NOT NULL COMMENT 'Học kỳ',
    program_id      INT NOT NULL COMMENT 'Chương trình',
    is_active       TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Có tính vào chuyên cần không',
    UNIQUE KEY uq_tp (term_id, program_id),
    CONSTRAINT fk_tp_term FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_tp_prog FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tạo bảng attendance_thresholds
CREATE TABLE IF NOT EXISTS attendance_thresholds (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    sort_order      INT NOT NULL COMMENT 'Thứ tự ưu tiên (1 = cao nhất)',
    label           VARCHAR(32) NOT NULL COMMENT 'Nhãn: A, B, C, Đạt...',
    min_percent     DECIMAL(5,2) NOT NULL COMMENT 'Ngưỡng tối thiểu (%)',
    description     VARCHAR(128) NULL COMMENT 'Mô tả: Xuất sắc, Tốt...',
    CONSTRAINT chk_at_min CHECK (min_percent >= 0 AND min_percent <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DỮ LIỆU MẶC ĐỊNH
-- ============================================================

-- Cập nhật score_types: category và max_exams
UPDATE score_types SET category = 'homework', max_exams = 3 WHERE code = 'mieng';
UPDATE score_types SET category = 'homework', max_exams = 4 WHERE code = 'p15';
UPDATE score_types SET category = 'exam', max_exams = 1 WHERE code = 'giuaky';
UPDATE score_types SET category = 'exam', max_exams = 1 WHERE code = 'cuoiky';

-- Thêm loại "1 tiết" nếu chưa có
INSERT IGNORE INTO score_types (code, label, short_label, weight, max_exams, category, sort_order)
VALUES ('p45', '1 tiết', '1T', 2, 2, 'homework', 3);

-- Seed score_rules mặc định
INSERT IGNORE INTO score_rules (rule_key, rule_label, rule_type, rule_value, description) VALUES
('rank_thresholds', 'Ngưỡng xếp loại học lực', 'threshold', '{"gioi":8,"kha":6.5,"tb":5,"yeu":0}', 'Ngưỡng điểm cho từng xếp loại'),
('passing_min_avg', 'Điểm TB tối thiểu đạt', 'threshold', '{"min":5}', 'Điểm trung bình tối thiểu để xem là đạt');

-- Seed attendance_thresholds mặc định
INSERT IGNORE INTO attendance_thresholds (sort_order, label, min_percent, description) VALUES
(1, 'A', 90.00, 'Xuất sắc'),
(2, 'B', 80.00, 'Tốt'),
(3, 'C', 70.00, 'Khá'),
(4, 'Đạt', 60.00, 'Tối thiểu');

-- term_programs KHÔNG seed ở đây. Endpoint chuyên cần dùng LEFT JOIN
-- với COALESCE(is_active, 1), lọc theo year_id của học kỳ — chưa có
-- dòng = đang bật. Seed ở đây bị lệch year_id (mục 🔴 2 review vòng 5
-- PR #285). Bảng vẫn được tạo ở trên, dùng cho ngoại lệ (tắt CT).
