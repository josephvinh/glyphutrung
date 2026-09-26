-- Migration: 003_stamps_rewards.sql
-- Description: Schema Sổ Mộc Điện Tử (mộc + đổi quà) + vai trò Thủ Thư + phân quyền
-- Created: 2026-09-26
-- Status: PENDING

-- =====================================================================
--  SỔ MỘC — số dư mộc theo từng em, theo từng năm học
--  current_balance = mộc còn tiêu được, held_balance = mộc đang bị giữ
--  (đơn hàng chờ lấy). total_earned dùng để xếp hạng/thống kê, không
--  giảm khi tiêu. current_streak/longest_streak phục vụ thưởng chuỗi
--  điểm danh liên tiếp.
-- =====================================================================
CREATE TABLE IF NOT EXISTS student_stamps (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    year_id              INT NOT NULL,
    student_id           INT NOT NULL,
    current_balance      INT NOT NULL DEFAULT 0 COMMENT 'mộc còn tiêu được',
    held_balance         INT NOT NULL DEFAULT 0 COMMENT 'mộc đang giữ cho đơn hàng chờ lấy',
    total_earned         INT NOT NULL DEFAULT 0 COMMENT 'tổng mộc đã kiếm được, không giảm khi tiêu',
    current_streak       INT NOT NULL DEFAULT 0 COMMENT 'số buổi điểm danh liên tiếp hiện tại',
    longest_streak       INT NOT NULL DEFAULT 0,
    last_attendance_date DATE NULL COMMENT 'ngày điểm danh gần nhất, dùng để tính chuỗi',

    CONSTRAINT fk_stamps_year    FOREIGN KEY (year_id)    REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_stamps_student FOREIGN KEY (student_id) REFERENCES students(id)     ON DELETE CASCADE,
    UNIQUE KEY uq_stamps (year_id, student_id) COMMENT 'một năm một em chỉ một sổ mộc'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  LỊCH SỬ GIAO DỊCH MỘC
--  UNIQUE(ref_attendance_id, type) là chốt chống ghi trùng khi cộng mộc
--  từ điểm danh (attendance/streak_bonus): quét lại cùng một buổi không
--  cộng thêm lần hai. streak_bonus có thể ref_attendance_id NULL khi
--  không gắn với một buổi điểm danh cụ thể — MySQL cho phép nhiều NULL
--  trong unique index nên không xung đột.
-- =====================================================================
CREATE TABLE IF NOT EXISTS stamp_transactions (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    year_id           INT NOT NULL,
    student_id        INT NOT NULL,
    amount            INT NOT NULL COMMENT 'dương = cộng mộc, âm = trừ mộc',
    type              ENUM('attendance','streak_bonus','spend','manual_adjust') NOT NULL,
    ref_attendance_id INT NULL COMMENT 'buổi điểm danh sinh ra giao dịch (earn), NULL nếu không áp dụng',
    ref_order_id      INT NULL COMMENT 'đơn đổi quà sinh ra giao dịch (spend), NULL nếu không áp dụng',
    description       VARCHAR(255) NOT NULL,
    actor_id          INT NULL COMMENT 'người thực hiện (NULL = hệ thống tự động)',
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_sttx_year   FOREIGN KEY (year_id)    REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_sttx_student FOREIGN KEY (student_id) REFERENCES students(id)    ON DELETE CASCADE,
    CONSTRAINT fk_sttx_att    FOREIGN KEY (ref_attendance_id) REFERENCES attendances(id) ON DELETE SET NULL,
    CONSTRAINT fk_sttx_actor  FOREIGN KEY (actor_id)   REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_sttx_earn (ref_attendance_id, type) COMMENT 'chống cộng mộc trùng cho cùng một buổi điểm danh',
    INDEX idx_sttx_student (student_id, year_id),
    INDEX idx_sttx_order (ref_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DANH MỤC QUÀ
-- =====================================================================
CREATE TABLE IF NOT EXISTS gifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    stamp_cost  INT NOT NULL COMMENT 'giá quy đổi ra mộc',
    stock       INT NOT NULL DEFAULT 0,
    image_url   VARCHAR(255) NULL,
    status      ENUM('còn bán','ẩn') NOT NULL DEFAULT 'còn bán',
    sort_order  TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  ĐƠN ĐỔI QUÀ
--  redeem_code_hash: mã lấy quà chỉ lưu dạng băm, tránh lộ mã nếu rò rỉ CSDL.
--  expires_at: quá hạn chưa lấy thì status chuyển 'quá hạn' (xử lý ở tầng ứng dụng).
-- =====================================================================
CREATE TABLE IF NOT EXISTS gift_orders (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    year_id          INT NOT NULL,
    student_id       INT NOT NULL,
    total_cost       INT NOT NULL,
    redeem_code_hash VARCHAR(255) NOT NULL,
    status           ENUM('chờ lấy','đã giao','đã hủy','quá hạn') NOT NULL DEFAULT 'chờ lấy',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at       DATETIME NOT NULL,
    delivered_by     INT NULL COMMENT 'Thủ Thư đã giao quà',
    delivered_at     DATETIME NULL,

    CONSTRAINT fk_gorder_year    FOREIGN KEY (year_id)    REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_gorder_student FOREIGN KEY (student_id) REFERENCES students(id)     ON DELETE CASCADE,
    CONSTRAINT fk_gorder_by      FOREIGN KEY (delivered_by) REFERENCES members(id)    ON DELETE SET NULL,
    INDEX idx_gorder_student (student_id, year_id),
    INDEX idx_gorder_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  CHI TIẾT ĐƠN ĐỔI QUÀ — mỗi đơn có thể gồm nhiều loại quà
-- =====================================================================
CREATE TABLE IF NOT EXISTS gift_order_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    gift_id    INT NOT NULL,
    qty        INT NOT NULL DEFAULT 1,
    unit_cost  INT NOT NULL COMMENT 'giá mộc tại thời điểm đổi, không đổi theo giá gift sau này',
    line_cost  INT NOT NULL COMMENT 'unit_cost * qty, lưu sẵn để khỏi tính lại',

    CONSTRAINT fk_gitem_order FOREIGN KEY (order_id) REFERENCES gift_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_gitem_gift  FOREIGN KEY (gift_id)  REFERENCES gifts(id),
    INDEX idx_gitem_order (order_id),
    INDEX idx_gitem_gift (gift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  VAI TRÒ MỚI: THỦ THƯ — phục vụ đổi quà toàn đoàn
-- =====================================================================
INSERT INTO roles (code, label, level, scope, descr) VALUES
    ('thu_thu', 'Thủ Thư', 1, 'toàn đoàn', 'Phục vụ đổi quà toàn đoàn')
ON DUPLICATE KEY UPDATE label = VALUES(label), level = VALUES(level), scope = VALUES(scope), descr = VALUES(descr);

-- =====================================================================
--  MODULE MỚI: DANH MỤC QUÀ (BĐH quản trị) + ĐỔI QUÀ (GLV/Thủ Thư dùng)
-- =====================================================================
INSERT INTO modules (module_key, label, icon, color, area, sort_order, is_enabled) VALUES
    ('gifts',   'Danh mục quà', 'gift',        'text-pink-600',  'bdh', 13, 1),
    ('rewards', 'Đổi quà',      'shopping-bag','text-amber-600', 'glv', 14, 1)
ON DUPLICATE KEY UPDATE label = VALUES(label), icon = VALUES(icon), color = VALUES(color),
    area = VALUES(area), sort_order = VALUES(sort_order), is_enabled = VALUES(is_enabled);

-- =====================================================================
--  PHÂN QUYỀN — CHỈ seed đúng các hàng brief yêu cầu.
--  Các role còn lại (glv, glv_chu_nhiem, truong_khoi, du_bi) CỐ Ý không
--  seed hàng nào cho 'gifts'/'rewards' → mặc định 'none' (không có hàng
--  trong bảng permissions = permission_of() trả về 'none').
-- =====================================================================
INSERT INTO permissions (module_key, role_code, level) VALUES
    ('gifts',   'admin',   'edit'),
    ('gifts',   'bdh',     'edit'),
    ('gifts',   'thu_thu', 'edit'),
    ('rewards', 'admin',   'edit'),
    ('rewards', 'thu_thu', 'edit')
ON DUPLICATE KEY UPDATE level = VALUES(level);
