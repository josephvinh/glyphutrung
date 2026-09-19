-- =====================================================================
--  NÂNG CẤP DATABASE — THÊM MỌI CỘT / BẢNG / DỮ LIỆU NỀN CÒN THIẾU
--  Gia Đình Giáo Lý Phú Trung.
--  Gộp tất cả config/migrate_*.php thành 1 file để chạy trên host (không SSH).
--
--  ⚠️ SAO LƯU DATABASE TRƯỚC KHI CHẠY (phpMyAdmin -> Export -> Go).
--
--  Idempotent: chạy lại nhiều lần vẫn ra đúng một kết quả (dùng IF NOT EXISTS,
--  INSERT IGNORE, ON DUPLICATE KEY) -> KHÔNG làm hỏng dữ liệu đang có.
--
--  CÁCH CHẠY:
--    1) phpMyAdmin -> cột trái CHỌN ĐÚNG DATABASE THẬT (không phải demo).
--    2) Tab Import -> chọn file này -> Go.  (Hoặc tab SQL -> dán -> Go.)
--
--  Yêu cầu: MariaDB (AZDIGI dùng MariaDB) hoặc MySQL 8+. Nếu báo lỗi cú pháp
--  "IF NOT EXISTS" ở ALTER (MySQL 5.x cũ), nhắn mình gửi bản dùng thủ tục.
-- =====================================================================

-- ============================================================
--  PHẦN 1 — CỘT & BẢNG (cấu trúc)
-- ============================================================

-- 1a. Giờ chốt riêng cho chương trình (migrate_program_cutoff)
ALTER TABLE programs ADD COLUMN IF NOT EXISTS cutoff_time TIME NULL AFTER start_time;

-- 1b. Cột buổi họp trên thông báo (migrate_lich_hop)
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS is_meeting    TINYINT NOT NULL DEFAULT 0;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS meeting_at    DATETIME NULL;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS meeting_place VARCHAR(255) NULL;
ALTER TABLE announcements ADD COLUMN IF NOT EXISTS reminded_at   DATETIME NULL;

-- 1c. Lịch cá nhân (migrate_lich_hop)
CREATE TABLE IF NOT EXISTS personal_notes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL,
    title       VARCHAR(160) NOT NULL,
    note        TEXT NULL,
    remind_at   DATETIME NOT NULL,
    all_day     TINYINT NOT NULL DEFAULT 0,
    done        TINYINT NOT NULL DEFAULT 0,
    notified_at DATETIME NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    INDEX idx_note_member (member_id, remind_at),
    INDEX idx_note_due (done, notified_at, remind_at),
    CONSTRAINT fk_note_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1d. Trả lời họp - RSVP (migrate_lich_hop)
CREATE TABLE IF NOT EXISTS meeting_rsvp (
    announcement_id INT NOT NULL,
    member_id       INT NOT NULL,
    status          ENUM('tham gia','không tham gia') NOT NULL,
    responded_at    DATETIME NOT NULL,
    PRIMARY KEY (announcement_id, member_id),
    CONSTRAINT fk_rsvp_ann    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_rsvp_member FOREIGN KEY (member_id)       REFERENCES members(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1e. Passkey - đăng nhập sinh trắc học (migrate_passkey)
CREATE TABLE IF NOT EXISTS member_passkeys (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    member_id     INT          NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key    TEXT         NOT NULL,
    user_handle   VARCHAR(255) NOT NULL,
    sign_count    INT          DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME     NULL,
    UNIQUE KEY uq_credential (credential_id),
    INDEX idx_pk_member (member_id),
    CONSTRAINT fk_pk_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
--  PHẦN 2 — DỮ LIỆU NỀN (vai / module / quyền)
--  Cột có sẵn nhưng thiếu các dòng này thì menu/vai không hiện.
-- ============================================================

-- 2a. Vai "Dự Bị" + chức danh (migrate_roles_du_bi)
INSERT INTO roles (code, label, level, scope, descr)
     VALUES ('du_bi', 'Dự Bị', 1, 'lớp', 'Hỗ trợ tại lớp được phân công')
     ON DUPLICATE KEY UPDATE label=VALUES(label), scope=VALUES(scope), descr=VALUES(descr);
INSERT IGNORE INTO titles (role_code, label, sort_order) VALUES ('du_bi', 'Dự Bị', 1);

-- 2b. Các module còn thiếu (migrate_modules_sync + lich_hop + guide)
INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order) VALUES
    ('reporthub', 'Báo cáo',     'bar-chart-3',   'text-emerald-600', 'glv', 5),
    ('analytics', 'Phân tích',   'bar-chart-2',   'text-purple-600',  'glv', 6),
    ('calendar',  'Lịch trình',  'calendar-days', 'text-teal-600',    'bdh', 4),
    ('notes',     'Lịch của tôi','calendar-check','text-teal-600',    'glv', 11),
    ('guide',     'Hướng dẫn',   'info',          'text-sky-600',     'glv', 12);

-- Nhân sự + Niên khoá -> khu điều hành
UPDATE modules SET area='bdh' WHERE module_key IN ('staff','years');

-- 2c. Quyền cho Dự Bị trên MỌI module đang có (điểm danh=sửa, chương trình/lên lớp=ẩn, còn lại=xem)
INSERT INTO permissions (module_key, role_code, level)
    SELECT DISTINCT module_key, 'du_bi',
           CASE module_key
               WHEN 'attendance' THEN 'edit'
               WHEN 'programs'   THEN 'none'
               WHEN 'promotion'  THEN 'none'
               ELSE 'view'
           END
      FROM permissions
    ON DUPLICATE KEY UPDATE level=VALUES(level);

-- 2d. Quyền module "Lịch của tôi" (mọi vai tự quản lịch mình = edit)
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('notes','admin','edit'),('notes','bdh','edit'),('notes','truong_khoi','edit'),
    ('notes','glv_chu_nhiem','edit'),('notes','glv','edit'),('notes','du_bi','edit');

-- 2e. Quyền module "Hướng dẫn" (mọi vai xem)
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('guide','admin','view'),('guide','bdh','view'),('guide','truong_khoi','view'),
    ('guide','glv_chu_nhiem','view'),('guide','glv','view'),('guide','du_bi','view');

-- 2f. Quyền xem Báo cáo / Phân tích / Lịch trình cho các vai (không có quyền thì module bị ẩn)
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('reporthub','admin','view'),('reporthub','bdh','view'),('reporthub','truong_khoi','view'),
    ('reporthub','glv_chu_nhiem','view'),('reporthub','glv','view'),('reporthub','du_bi','view'),
    ('analytics','admin','view'),('analytics','bdh','view'),('analytics','truong_khoi','view'),
    ('analytics','glv_chu_nhiem','view'),('analytics','glv','view'),('analytics','du_bi','view'),
    ('calendar','admin','view'),('calendar','bdh','view'),('calendar','truong_khoi','view'),
    ('calendar','glv_chu_nhiem','view'),('calendar','glv','view'),('calendar','du_bi','view');

-- 2g. Tinh chỉnh quyền theo cơ cấu đoàn
--     GVCN: sửa hồ sơ + gửi thông báo lớp mình
UPDATE permissions SET level='edit' WHERE role_code='glv_chu_nhiem' AND module_key IN ('students','announcements');
--     Trưởng khối: sửa hồ sơ thiếu nhi trong khối
UPDATE permissions SET level='edit' WHERE role_code='truong_khoi' AND module_key='students';
--     BĐH: điểm số + phiếu liên lạc -> chỉ xem (giám sát)
UPDATE permissions SET level='view' WHERE role_code='bdh' AND module_key IN ('scores','reports');

-- =====================================================================
--  XONG. Người đang đăng nhập cần tải lại trang (hoặc mở lại app) để nhận
--  quyền/module mới. Không cần chạy lại nếu đã chạy — file này idempotent.
-- =====================================================================
