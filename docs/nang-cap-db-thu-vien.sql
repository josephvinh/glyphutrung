-- =====================================================================
-- NÂNG CẤP DB: THƯ VIỆN TÀI LIỆU
-- Chạy được nhiều lần (idempotent): CREATE IF NOT EXISTS + INSERT IGNORE.
-- =====================================================================

-- Chủ đề tài liệu
CREATE TABLE IF NOT EXISTS library_categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tài liệu
CREATE TABLE IF NOT EXISTS library_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    description   TEXT NULL,
    category_id   INT UNSIGNED NULL,
    stored_name   VARCHAR(120) NOT NULL,          -- tên file ngẫu nhiên trên đĩa
    original_name VARCHAR(255) NOT NULL,          -- tên gốc (hiển thị / đặt khi tải)
    mime_type     VARCHAR(100) NOT NULL,
    size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM('cho_duyet','da_duyet','tu_choi') NOT NULL DEFAULT 'cho_duyet',
    uploaded_by   INT NOT NULL,
    approved_by   INT NULL,
    reject_reason VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   DATETIME NULL,
    INDEX idx_status_cat (status, category_id),
    INDEX idx_uploader (uploaded_by),
    CONSTRAINT fk_lib_cat FOREIGN KEY (category_id)
        REFERENCES library_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chủ đề khởi tạo
INSERT IGNORE INTO library_categories (id, name, sort_order) VALUES
    (1, 'Giáo án',              1),
    (2, 'Đào tạo Huynh trưởng', 2),
    (3, 'Bài hát',              3),
    (4, 'Văn kiện',             4),
    (5, 'Sinh hoạt',            5);

-- Đăng ký module (khu 'glv')
INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
VALUES ('thu_vien', 'Thư viện', 'library', 'text-amber-600', 'glv', 7);

-- Quyền: view = xem + đăng (chờ duyệt); edit = duyệt/gỡ/quản chủ đề.
-- Thứ tự vai: admin, bdh, truong_khoi, glv_chu_nhiem, glv, du_bi
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('thu_vien', 'admin',         'edit'),
    ('thu_vien', 'bdh',           'edit'),
    ('thu_vien', 'truong_khoi',   'view'),
    ('thu_vien', 'glv_chu_nhiem', 'view'),
    ('thu_vien', 'glv',           'view'),
    ('thu_vien', 'du_bi',         'view');
