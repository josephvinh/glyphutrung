-- =====================================================================
-- 008: Hỗ trợ nhiều bài kiểm tra cùng loại (VD: 2 bài 15 phút)
--
-- 1. Tạo bảng score_exams — mỗi bài kiểm tra là 1 dòng
-- 2. Thêm cột exam_id vào scores, bỏ UNIQUE KEY cũ
-- 3. Di chuyển dữ liệu scores hiện có sang dạng "1 exam ngầm" mỗi loại
-- =====================================================================

-- 1. Bảng mới: đợt thi / bài kiểm tra
CREATE TABLE IF NOT EXISTS score_exams (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    year_id     INT           NOT NULL,
    term_id     INT           NOT NULL,
    type_code   VARCHAR(16)  NOT NULL,
    name        VARCHAR(64)   NOT NULL DEFAULT '',
    exam_date   DATE          NULL COMMENT 'ngày thi, tùy chọn',
    created_by  INT           NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_se_year (year_id),
    INDEX idx_se_term (term_id),
    INDEX idx_se_type (type_code),
    CONSTRAINT fk_se_year  FOREIGN KEY (year_id)  REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_se_term  FOREIGN KEY (term_id)  REFERENCES terms(id)      ON DELETE CASCADE,
    CONSTRAINT fk_se_type  FOREIGN KEY (type_code) REFERENCES score_types(code),
    CONSTRAINT fk_se_by    FOREIGN KEY (created_by) REFERENCES members(id)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Thêm cột exam_id vào scores (NULL = bài ngầm định kiểu cũ để tương thích)
ALTER TABLE scores
    ADD COLUMN exam_id INT NULL AFTER type_code,
    ADD INDEX idx_s_exam (exam_id);

-- 3. Bỏ UNIQUE KEY cũ (term_id, student_id, type_code)
--    MySQL yêu cầu xóa index trước khi thêm UNIQUE mới
ALTER TABLE scores
    DROP INDEX uq_score,
    ADD UNIQUE KEY uq_score (exam_id, student_id);

-- 4. Di chuyển dữ liệu cũ: tạo 1 exam ngầm cho mỗi (term_id, type_code)
--    rồi cập nhật exam_id cho các scores hiện có
INSERT IGNORE INTO score_exams (year_id, term_id, type_code, name, created_at)
SELECT t.year_id, s.term_id, s.type_code, '', NOW()
FROM (SELECT DISTINCT term_id, type_code FROM scores) s
JOIN terms t ON t.id = s.term_id;

UPDATE scores s
JOIN score_exams e ON e.term_id = s.term_id AND e.type_code = s.type_code
SET s.exam_id = e.id
WHERE s.exam_id IS NULL;
