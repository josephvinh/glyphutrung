-- =====================================================================
-- MỞ RỘNG THƯ VIỆN -> "THƯ VIỆN & SỔ TAY"
-- Thêm loại nội dung 'article' (bài viết chữ, đọc thẳng, tra cứu nhanh)
-- bên cạnh 'file'. Chạy được nhiều lần.
-- (Chạy SAU docs/nang-cap-db-thu-vien.sql)
-- =====================================================================

-- Loại mục + nội dung chữ cho bài viết
ALTER TABLE library_items
    ADD COLUMN IF NOT EXISTS item_type ENUM('file','article') NOT NULL DEFAULT 'file' AFTER title,
    ADD COLUMN IF NOT EXISTS body MEDIUMTEXT NULL AFTER description;

-- Bài viết không có file -> cho phép NULL các cột file (an toàn khi chạy lại)
ALTER TABLE library_items
    MODIFY stored_name   VARCHAR(120) NULL,
    MODIFY original_name VARCHAR(255) NULL,
    MODIFY mime_type     VARCHAR(100) NULL;

-- Chủ đề sổ tay (dùng chung với file). Idempotent: chỉ thêm khi chưa có.
INSERT INTO library_categories (name, sort_order)
SELECT * FROM (SELECT 'Kinh & nghi thức' AS name, 6 AS sort_order) AS t
WHERE NOT EXISTS (SELECT 1 FROM library_categories WHERE name = 'Kinh & nghi thức');

INSERT INTO library_categories (name, sort_order)
SELECT * FROM (SELECT 'Quy trình & hướng dẫn' AS name, 7 AS sort_order) AS t
WHERE NOT EXISTS (SELECT 1 FROM library_categories WHERE name = 'Quy trình & hướng dẫn');
