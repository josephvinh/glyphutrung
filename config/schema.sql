-- =====================================================================
--  TNTT SUPER APP — LƯỢC ĐỒ CƠ SỞ DỮ LIỆU
--  MariaDB / MySQL · utf8mb4 để chứa đủ tiếng Việt và emoji
--
--  BA NGUYÊN TẮC XUYÊN SUỐT
--
--  1. MỌI DỮ LIỆU PHÁT SINH ĐỀU GẮN NIÊN KHOÁ.
--     Không có bảng nghiệp vụ nào không truy được về một năm học.
--     Nhờ vậy khoá sổ năm cũ không đụng tới năm mới.
--
--  2. LỚP CỦA MỘT EM LÀ THEO TỪNG NĂM, không phải thuộc tính của em.
--     Nằm ở bảng enrollments. Lên lớp = tạo bản ghi ghi danh năm sau,
--     không ghi đè năm cũ — nên lịch sử học của em còn nguyên.
--
--  3. VẮNG MẶT KHÔNG LƯU.
--     Bảng attendances chỉ chứa các em CÓ TỚI. Vắng có phép hay vắng
--     không phép được suy ra lúc đọc, đúng như bản chạy thử.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- KHÔNG tạo và KHÔNG chọn cơ sở dữ liệu ở đây.
--
-- Trước đây chỗ này ghi cứng "CREATE DATABASE tntt_app; USE tntt_app;".
-- Trên hosting dùng chung điều đó hỏng theo hai hướng cùng lúc:
--   1. tên CSDL do cPanel đặt (kiểu taikhoan_tenapp), không phải tntt_app
--   2. tài khoản CSDL không có quyền CREATE DATABASE, nên báo
--      "Access denied ... to database ..." rồi dừng
-- Kết nối đã chọn sẵn đúng CSDL qua chuỗi DSN, nên hai lệnh đó thừa.


-- =====================================================================
--  1. NIÊN KHOÁ
-- =====================================================================
CREATE TABLE IF NOT EXISTS school_years (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(32)  NOT NULL UNIQUE COMMENT 'VD: 2026 - 2027',
    start_date  DATE         NOT NULL,
    end_date    DATE         NOT NULL,
    is_current  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'chỉ một năm được bật',
    status      ENUM('đang mở','đã khóa') NOT NULL DEFAULT 'đang mở',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS terms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    year_id     INT          NOT NULL,
    name        VARCHAR(32)  NOT NULL,
    start_date  DATE         NOT NULL,
    end_date    DATE         NOT NULL,
    sort_order  TINYINT      NOT NULL DEFAULT 1,
    CONSTRAINT fk_term_year FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    UNIQUE KEY uq_term (year_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  2. KHỐI & LỚP
--  Khối và lớp là thực thể bền, dùng lại qua nhiều năm.
--  next_class_id chính là sơ đồ lên lớp.
-- =====================================================================
CREATE TABLE IF NOT EXISTS blocks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(64) NOT NULL UNIQUE,
    sort_order  TINYINT     NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    block_id      INT         NOT NULL,
    name          VARCHAR(64) NOT NULL UNIQUE,
    sort_order    TINYINT     NOT NULL DEFAULT 1,
    next_class_id INT         NULL COMMENT 'lớp kế tiếp khi lên lớp',
    is_final      TINYINT(1)  NOT NULL DEFAULT 0 COMMENT '1 = lớp cuối, lên lớp là ra trường',
    CONSTRAINT fk_class_block FOREIGN KEY (block_id) REFERENCES blocks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Khóa ngoại tự tham chiếu phải thêm sau khi bảng đã tồn tại
ALTER TABLE classes ADD CONSTRAINT fk_class_next
    FOREIGN KEY (next_class_id) REFERENCES classes(id) ON DELETE SET NULL;

-- =====================================================================
--  3. VAI TRÒ & CHỨC DANH
--  Tách bạch: role sinh ra quyền, title chỉ để hiển thị.
-- =====================================================================
CREATE TABLE IF NOT EXISTS roles (
    code   VARCHAR(24) PRIMARY KEY,
    label  VARCHAR(64) NOT NULL,
    level  TINYINT     NOT NULL COMMENT '5 cao nhất',
    scope  ENUM('toàn đoàn','khối','lớp') NOT NULL,
    descr  VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS titles (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    role_code  VARCHAR(24) NOT NULL,
    label      VARCHAR(64) NOT NULL,
    sort_order TINYINT     NOT NULL DEFAULT 1,
    CONSTRAINT fk_title_role FOREIGN KEY (role_code) REFERENCES roles(code) ON DELETE CASCADE,
    UNIQUE KEY uq_title (role_code, label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  4. NHÂN SỰ — cũng là bảng tài khoản đăng nhập
-- =====================================================================
CREATE TABLE IF NOT EXISTS members (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(32)  NOT NULL UNIQUE COMMENT 'mã GLV',
    holy_name      VARCHAR(64)  NULL,
    full_name      VARCHAR(128) NOT NULL,
    phone          VARCHAR(20)  NOT NULL UNIQUE COMMENT 'dùng để đăng nhập',
    email          VARCHAR(128) NULL,
    birth_date     DATE         NULL,
    password_hash  VARCHAR(255) NOT NULL,
    role_code      VARCHAR(24)  NOT NULL,
    title_id       INT          NULL,
    block_id       INT          NULL,
    class_id       INT          NULL,
    status         ENUM('chờ duyệt','đang phục vụ','tạm nghỉ','đã nghỉ') NOT NULL DEFAULT 'đang phục vụ',
    register_note  VARCHAR(255) NULL COMMENT 'lời nhắn khi tự đăng ký, để Ban Điều Hành biết xếp lớp',
    registered_at  DATETIME     NULL COMMENT 'thời điểm tự đăng ký, null nghĩa là do BĐH cấp',
    must_change_pw TINYINT(1)   NOT NULL DEFAULT 1,
    last_login_at  DATETIME     NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_member_role  FOREIGN KEY (role_code) REFERENCES roles(code),
    CONSTRAINT fk_member_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE SET NULL,
    CONSTRAINT fk_member_block FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL,
    CONSTRAINT fk_member_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    INDEX idx_member_role (role_code),
    INDEX idx_member_class (class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  4a. PHÂN CÔNG KIÊM NHIỆM  (added 2026-08-31 — member-assignments plan, Task 1)
--  Một thành viên có thể giữ nhiều vai trò và phụ trách nhiều lớp/khối
--  cùng lúc. Bảng này lưu từng (member, role, scope) như một dòng độc
--  lập với from_date/to_date. is_primary = vai trò mặc định khi đăng nhập.
-- =====================================================================
CREATE TABLE IF NOT EXISTS member_assignments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    member_id    INT NOT NULL,
    role_code    VARCHAR(24) NOT NULL,
    block_id     INT NULL,
    class_id     INT NULL,
    is_primary   TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT 'phân công chính = vai trò mặc định khi đăng nhập',
    from_date    DATE NOT NULL,
    to_date      DATE NULL COMMENT 'null = đang hiệu lực',
    assigned_by  INT NOT NULL COMMENT 'BĐH phân công',
    note         VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_assign_member   FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
    CONSTRAINT fk_assign_role     FOREIGN KEY (role_code)  REFERENCES roles(code),
    CONSTRAINT fk_assign_block    FOREIGN KEY (block_id)   REFERENCES blocks(id)   ON DELETE SET NULL,
    CONSTRAINT fk_assign_class    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL,
    CONSTRAINT fk_assign_by       FOREIGN KEY (assigned_by) REFERENCES members(id),

    INDEX idx_assign_member (member_id, to_date),
    INDEX idx_assign_class  (class_id, to_date),
    INDEX idx_assign_block  (block_id, to_date),
    INDEX idx_assign_role   (role_code, to_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  5. THIẾU NHI & GHI DANH
--  students giữ thông tin bền của em (không đổi theo năm).
--  enrollments giữ chuyện năm nào học lớp nào, kết quả ra sao.
-- =====================================================================
CREATE TABLE IF NOT EXISTS students (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(32)  NOT NULL UNIQUE,
    holy_name    VARCHAR(64)  NULL,
    full_name    VARCHAR(128) NOT NULL,
    gender       TINYINT(1)   NOT NULL DEFAULT 1 COMMENT '1 nam, 0 nữ',
    birth_date   DATE         NULL,
    address      VARCHAR(255) NULL,
    father_name  VARCHAR(128) NULL,
    father_phone VARCHAR(20)  NULL,
    mother_name  VARCHAR(128) NULL,
    mother_phone VARCHAR(20)  NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    hidden_at     TIMESTAMP    NULL DEFAULT NULL COMMENT 'PR-2: ngày bị ẩn (PII không hiện sau khi nghỉ)',
    deleted_at    TIMESTAMP    NULL DEFAULT NULL COMMENT 'PR-2: ngày bị xóa mềm (chờ xóa hẳn sau 7 năm)',
    INDEX idx_student_name (full_name),
    INDEX idx_students_hidden (hidden_at),
    INDEX idx_students_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS enrollments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    year_id     INT NOT NULL,
    student_id  INT NOT NULL,
    class_id    INT NOT NULL,
    status      ENUM('đang sinh hoạt','dừng sinh hoạt','chuyển xứ','đã ra trường')
                NOT NULL DEFAULT 'đang sinh hoạt',
    year_result ENUM('chưa xét','lên lớp','ở lại','ra trường') NOT NULL DEFAULT 'chưa xét',
    note        VARCHAR(255) NULL,
    CONSTRAINT fk_enr_year    FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_enr_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_enr_class   FOREIGN KEY (class_id) REFERENCES classes(id),
    UNIQUE KEY uq_enr (year_id, student_id) COMMENT 'một năm một em chỉ ở một lớp',
    INDEX idx_enr_class (year_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  6. CHƯƠNG TRÌNH
--  bắt buộc  -> lặp theo thứ (day_of_week)
--  chiến dịch -> một ngày cụ thể (event_date)
--  Giờ chốt: nhập riêng ở cutoff_time; để NULL thì mặc định start_time + 30 phút.
-- =====================================================================
CREATE TABLE IF NOT EXISTS programs (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    year_id              INT          NOT NULL,
    name                 VARCHAR(128) NOT NULL,
    type                 ENUM('bắt buộc','chiến dịch') NOT NULL DEFAULT 'bắt buộc',
    status               ENUM('kích hoạt','đã đóng')   NOT NULL DEFAULT 'kích hoạt',
    count_for_attendance TINYINT(1)   NOT NULL DEFAULT 1,
    count_for_emulation  TINYINT(1)   NOT NULL DEFAULT 0 COMMENT 'Tính vào thi đua đi lễ — cũng là cờ tích Mộc (Sổ Mộc)',
    start_time           TIME         NOT NULL,
    cutoff_time          TIME         NULL COMMENT 'Giờ chốt sổ — NULL = start_time + 30 phút',
    absent_time          TIME         NULL COMMENT 'Sau giờ này không ghi có mặt nữa (tính vắng)',
    day_of_week          TINYINT      NULL COMMENT '0 Chúa Nhật ... 6 Thứ Bảy (thứ chính, tương thích cũ)',
    days_of_week         VARCHAR(32)  NULL COMMENT 'CSV nhiều thứ, VD "0,1,2,3,4,5,6"',
    event_date           DATE         NULL,
    effective_from       DATE         NULL COMMENT 'Khoảng ngày áp dụng cho buổi lặp',
    effective_to         DATE         NULL,
    allow_qr             TINYINT(1)   NOT NULL DEFAULT 1,
    auto_close_after_event TINYINT(1) NOT NULL DEFAULT 0,
    color                VARCHAR(48)  NULL,
    icon                 VARCHAR(48)  NULL,
    sort_order           TINYINT      NOT NULL DEFAULT 1,
    CONSTRAINT fk_prog_year FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    INDEX idx_prog_year (year_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lớp tham gia chương trình (rỗng = áp dụng toàn đoàn)
CREATE TABLE IF NOT EXISTS program_classes (
    program_id INT NOT NULL,
    class_id   INT NOT NULL,
    PRIMARY KEY (program_id, class_id),
    CONSTRAINT fk_pc_program FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_class   FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  7. ĐIỂM DANH — chỉ ghi em CÓ TỚI
-- =====================================================================
CREATE TABLE IF NOT EXISTS attendances (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    year_id      INT  NOT NULL,
    program_id   INT  NOT NULL,
    session_date DATE NOT NULL,
    student_id   INT  NOT NULL,
    status       ENUM('có mặt','đi trễ') NOT NULL DEFAULT 'có mặt',
    method       ENUM('tay','qr')        NOT NULL DEFAULT 'tay',
    marked_by    INT  NULL,
    marked_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_att_year    FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_prog    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_att_by      FOREIGN KEY (marked_by) REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_att (program_id, session_date, student_id) COMMENT 'chống quét trùng ở tầng CSDL',
    INDEX idx_att_lookup (year_id, session_date),
    INDEX idx_att_student (student_id, year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  8. XIN PHÉP
-- =====================================================================
CREATE TABLE IF NOT EXISTS leave_requests (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    year_id       INT  NOT NULL,
    student_id    INT  NOT NULL,
    program_id    INT  NOT NULL,
    session_date  DATE NOT NULL,
    reason        VARCHAR(500) NOT NULL,
    status        ENUM('chờ duyệt','đã duyệt','từ chối') NOT NULL DEFAULT 'chờ duyệt',
    created_by    INT  NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by   INT  NULL,
    approved_at   DATETIME NULL,
    reject_reason VARCHAR(500) NULL,
    CONSTRAINT fk_lv_year    FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_lv_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_lv_prog    FOREIGN KEY (program_id) REFERENCES programs(id) ON DELETE CASCADE,
    CONSTRAINT fk_lv_cby     FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL,
    CONSTRAINT fk_lv_aby     FOREIGN KEY (approved_by) REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_lv (program_id, session_date, student_id) COMMENT 'một buổi một em một đơn',
    INDEX idx_lv_status (year_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  9. ĐIỂM SỐ
-- =====================================================================
CREATE TABLE IF NOT EXISTS score_types (
    code        VARCHAR(16) PRIMARY KEY,
    label       VARCHAR(32) NOT NULL,
    short_label VARCHAR(8)  NOT NULL,
    weight      TINYINT     NOT NULL DEFAULT 1,
    sort_order  TINYINT     NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Đợt thi / bài kiểm tra — mỗi bài là 1 dòng, thuộc 1 loại điểm + 1 học kỳ
CREATE TABLE IF NOT EXISTS score_exams (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    year_id    INT          NOT NULL,
    term_id    INT          NOT NULL,
    type_code  VARCHAR(16)  NOT NULL,
    name       VARCHAR(64)  NOT NULL DEFAULT '',
    exam_date  DATE         NULL COMMENT 'ngày thi, tùy chọn',
    created_by INT          NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_se_year  (year_id),
    INDEX idx_se_term  (term_id),
    INDEX idx_se_type  (type_code),
    CONSTRAINT fk_se_year  FOREIGN KEY (year_id)   REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_se_term  FOREIGN KEY (term_id)   REFERENCES terms(id)       ON DELETE CASCADE,
    CONSTRAINT fk_se_type  FOREIGN KEY (type_code) REFERENCES score_types(code),
    CONSTRAINT fk_se_by    FOREIGN KEY (created_by) REFERENCES members(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Điểm số — mỗi em mỗi bài kiểm tra (exam) có 1 điểm.
-- exam_id NULL = bài ngầm định (tương thích dữ liệu cũ, mỗi loại chỉ 1 bài).
CREATE TABLE IF NOT EXISTS scores (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    term_id    INT           NOT NULL,
    student_id INT           NOT NULL,
    type_code  VARCHAR(16)  NOT NULL,
    exam_id    INT           NULL,
    value      DECIMAL(4,2) NOT NULL,
    updated_by INT           NULL,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_s_exam (exam_id),
    CONSTRAINT fk_sc_term    FOREIGN KEY (term_id)    REFERENCES terms(id)         ON DELETE CASCADE,
    CONSTRAINT fk_sc_student FOREIGN KEY (student_id) REFERENCES students(id)      ON DELETE CASCADE,
    CONSTRAINT fk_sc_type    FOREIGN KEY (type_code)  REFERENCES score_types(code),
    CONSTRAINT fk_sc_exam    FOREIGN KEY (exam_id)    REFERENCES score_exams(id)   ON DELETE CASCADE,
    CONSTRAINT fk_sc_by      FOREIGN KEY (updated_by) REFERENCES members(id)      ON DELETE SET NULL,
    CONSTRAINT chk_sc_value  CHECK (value >= 0 AND value <= 10),
    UNIQUE KEY uq_score (exam_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 10. SỔ LIÊN LẠC
--  Các cột att_* là BẢN CHỤP tại thời điểm lập phiếu — cố ý không phải
--  khung nhìn sống, để tờ phiếu đã phát cho phụ huynh không tự đổi số.
-- =====================================================================
CREATE TABLE IF NOT EXISTS reports (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    term_id        INT NOT NULL,
    student_id     INT NOT NULL,
    att_present    SMALLINT NOT NULL DEFAULT 0,
    att_late       SMALLINT NOT NULL DEFAULT 0,
    att_excused    SMALLINT NOT NULL DEFAULT 0,
    att_unexcused  SMALLINT NOT NULL DEFAULT 0,
    att_total      SMALLINT NOT NULL DEFAULT 0,
    att_rate       TINYINT  NOT NULL DEFAULT 0,
    score          DECIMAL(4,2) NULL,
    conduct        ENUM('tốt','khá','trung bình','cần cố gắng') NOT NULL DEFAULT 'tốt',
    rank_label     ENUM('Giỏi','Khá','Trung bình','Yếu') NOT NULL DEFAULT 'Trung bình',
    remark         VARCHAR(1000) NULL,
    status         ENUM('nháp','đã gửi') NOT NULL DEFAULT 'nháp',
    created_by     INT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rp_term    FOREIGN KEY (term_id) REFERENCES terms(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_by      FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_report (term_id, student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 11. THÔNG BÁO
-- =====================================================================
CREATE TABLE IF NOT EXISTS announcements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    year_id        INT NOT NULL,
    title          VARCHAR(255) NOT NULL,
    body           TEXT NOT NULL,
    level          ENUM('thường','quan trọng','khẩn') NOT NULL DEFAULT 'thường',
    audience_type  ENUM('toàn đoàn','khối','lớp') NOT NULL DEFAULT 'toàn đoàn',
    audience_block INT NULL,
    audience_class INT NULL,
    status         ENUM('nháp','đã phát') NOT NULL DEFAULT 'nháp',
    published_at   DATETIME NULL,
    expires_at     DATE NULL,
    created_by     INT NULL,
    -- Buổi họp: vào lịch cá nhân người nhận + hỏi tham gia (RSVP)
    is_meeting     TINYINT NOT NULL DEFAULT 0,
    meeting_at     DATETIME NULL,
    meeting_place  VARCHAR(255) NULL,
    reminded_at    DATETIME NULL,
    CONSTRAINT fk_an_year  FOREIGN KEY (year_id) REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_an_block FOREIGN KEY (audience_block) REFERENCES blocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_an_class FOREIGN KEY (audience_class) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_an_by    FOREIGN KEY (created_by) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_an_live (year_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS announcement_reads (
    member_id       INT NOT NULL,
    announcement_id INT NOT NULL,
    read_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (member_id, announcement_id),
    CONSTRAINT fk_ar_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    CONSTRAINT fk_ar_ann    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trả lời họp (RSVP): mỗi người một dòng cho mỗi buổi họp
CREATE TABLE IF NOT EXISTS meeting_rsvp (
    announcement_id INT NOT NULL,
    member_id       INT NOT NULL,
    status          ENUM('tham gia','không tham gia') NOT NULL,
    responded_at    DATETIME NOT NULL,
    PRIMARY KEY (announcement_id, member_id),
    CONSTRAINT fk_rsvp_ann    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_rsvp_member FOREIGN KEY (member_id)       REFERENCES members(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lịch cá nhân: ghi chú riêng tư của từng thành viên
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

-- =====================================================================
-- 12. CẤU HÌNH HỆ THỐNG
-- =====================================================================
CREATE TABLE IF NOT EXISTS modules (
    module_key VARCHAR(32) PRIMARY KEY,
    label      VARCHAR(64) NOT NULL,
    icon       VARCHAR(48) NOT NULL,
    color      VARCHAR(48) NOT NULL DEFAULT 'text-blue-600',
    area       ENUM('glv','bdh') NOT NULL DEFAULT 'glv',
    sort_order TINYINT     NOT NULL DEFAULT 1,
    is_enabled TINYINT(1)  NOT NULL DEFAULT 1 COMMENT '0 = đang bảo trì'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    module_key VARCHAR(32) NOT NULL,
    role_code  VARCHAR(24) NOT NULL,
    level      ENUM('none','view','edit') NOT NULL DEFAULT 'none',
    PRIMARY KEY (module_key, role_code),
    CONSTRAINT fk_pm_module FOREIGN KEY (module_key) REFERENCES modules(module_key) ON DELETE CASCADE,
    CONSTRAINT fk_pm_role   FOREIGN KEY (role_code) REFERENCES roles(code) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
    id         BIGINT AUTO_INCREMENT PRIMARY KEY,
    logged_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actor_id   INT         NULL,
    actor_name VARCHAR(128) NOT NULL,
    action     VARCHAR(24) NOT NULL,
    module     VARCHAR(32) NOT NULL,
    what       VARCHAR(255) NOT NULL,
    detail     VARCHAR(500) NULL,
    CONSTRAINT fk_log_actor FOREIGN KEY (actor_id) REFERENCES members(id) ON DELETE SET NULL,
    INDEX idx_log_time (logged_at),
    INDEX idx_log_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    k VARCHAR(64) PRIMARY KEY,
    v VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
--  ĐẾM LẦN ĐĂNG NHẬP SAI  (chống dò mật khẩu)
--  Ghi ở tầng CSDL chứ không ở phiên: kẻ dò chỉ cần bỏ cookie
--  là thoát mọi bộ đếm nằm trong session.
-- ============================================================
CREATE TABLE IF NOT EXISTS login_attempts (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    phone    VARCHAR(20)  NOT NULL,
    ip       VARCHAR(45)  NOT NULL COMMENT 'đủ chỗ cho IPv6',
    tried_at DATETIME     NOT NULL,
    KEY idx_phone (phone, tried_at),
    KEY idx_ip    (ip, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ĐẾM LẦN TRA CỨU Ở CỔNG CÔNG KHAI (chống dò mã thiếu nhi)
--  Xem migration 004_tracuu_throttle.sql — mẫu y hệt login_attempts,
--  nhưng đếm theo IP (không theo mã) vì kẻ dò thử LẦN LƯỢT nhiều mã.
-- ============================================================
CREATE TABLE IF NOT EXISTS tracuu_attempts (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    ip       VARCHAR(45) NOT NULL COMMENT 'đủ chỗ cho IPv6',
    tried_at DATETIME    NOT NULL,
    KEY idx_tracuu_ip (ip, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ĐẾM LẦN NHẬP SAI MẬT MÃ THEO MÃ EM (trang Tra cứu điểm)
--  Xem migration 005_tracuu_code_fails.sql — bổ sung cho tracuu_attempts
--  (đếm theo IP) để chặn dò ngày sinh phân tán nhiều IP.
-- ============================================================
CREATE TABLE IF NOT EXISTS tracuu_code_fails (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    code     VARCHAR(32) NOT NULL,
    tried_at DATETIME    NOT NULL,
    KEY idx_tracuu_code (code, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  ĐĂNG KÝ NHẬN THÔNG BÁO ĐẨY
--  Mỗi máy (điện thoại/máy tính) một dòng. Một người dùng có thể
--  có nhiều máy; gỡ app hay xoá dữ liệu thì máy chủ nhận 404/410
--  ở lần gửi sau và tự dọn dòng đó.
-- ============================================================
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT          NOT NULL,
    endpoint    VARCHAR(500) NOT NULL,
    ua          VARCHAR(255) NULL COMMENT 'để người dùng nhận ra máy nào',
    created_at  DATETIME     NOT NULL,
    last_ok_at  DATETIME     NULL,
    token_hash      CHAR(64)          NULL COMMENT 'SHA-256 (hex) của token máy, NULL = dòng cũ trước bản vá',
    ring_seq        INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT 'tăng 1 mỗi lần cần rung máy này',
    ring_done       INT UNSIGNED      NOT NULL DEFAULT 0 COMMENT 'giá trị ring_seq đã rung xong',
    ring_lock_until DATETIME          NULL COMMENT 'đang có tiến trình giữ / chờ thử lại tới lúc này',
    ring_tries      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    last_fail_code  SMALLINT          NULL COMMENT 'mã HTTP lỗi gần nhất (0 = timeout/không kết nối)',
    UNIQUE KEY uq_push (endpoint(255)),
    UNIQUE KEY uq_push_token (token_hash),
    KEY idx_push_member (member_id),
    CONSTRAINT fk_push_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hộp thư đi của thông báo đẩy.
-- Máy chủ đẩy chỉ nhận được cú chuông rỗng; nội dung nằm ở đây, service
-- worker trên máy nhận sẽ gọi về lấy. Mỗi người nhận một dòng.
CREATE TABLE IF NOT EXISTS push_outbox (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    member_id  INT          NOT NULL,
    title      VARCHAR(120) NOT NULL,
    body       VARCHAR(255) NOT NULL,
    url        VARCHAR(120) NOT NULL DEFAULT '/',
    tag        VARCHAR(48)  NOT NULL DEFAULT 'tntt-chung' COMMENT 'cùng tag thì gộp lại, không dội chuông',
    created_at DATETIME     NOT NULL,
    taken_at   DATETIME     NULL COMMENT 'lúc máy người nhận đã lấy về hiện',
    KEY idx_outbox_cho (member_id, taken_at, id),
    CONSTRAINT fk_outbox_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  THƯ VIỆN & SỔ TAY
--  Một mục là TỆP (giáo án, ảnh, Word…) hoặc BÀI VIẾT chữ (kinh,
--  nghi thức, quy trình — tra cứu nhanh, đọc thẳng trong app).
--  Tệp lưu NGOÀI web root, tên ngẫu nhiên; chỉ phục vụ qua
--  api/library_file.php sau khi kiểm quyền (xem public/api/_library.php).
-- ============================================================
CREATE TABLE IF NOT EXISTS library_categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0 = ẩn chủ đề cũ mà không xoá'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS library_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    item_type     ENUM('file','article') NOT NULL DEFAULT 'file',
    description   TEXT NULL,
    body          MEDIUMTEXT NULL COMMENT 'nội dung chữ của bài viết sổ tay',
    category_id   INT UNSIGNED NULL,
    stored_name   VARCHAR(120) NULL COMMENT 'tên file ngẫu nhiên trên đĩa (NULL với bài viết)',
    original_name VARCHAR(255) NULL COMMENT 'tên gốc — chỉ để hiển thị/đặt tên khi tải về',
    mime_type     VARCHAR(100) NULL,
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
    (1, 'Giáo án',                1),
    (2, 'Đào tạo Huynh trưởng',   2),
    (3, 'Bài hát',                3),
    (4, 'Văn kiện',               4),
    (5, 'Sinh hoạt',              5),
    (6, 'Kinh & nghi thức',       6),
    (7, 'Quy trình & hướng dẫn',  7);

-- Đăng ký module (khu 'glv'). icon 'scroll-text' vì bản lucide rút gọn
-- của app không có 'library'.
INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
VALUES ('thu_vien', 'Thư viện', 'scroll-text', 'text-amber-600', 'glv', 7);

-- Quyền: view = xem; edit = sửa/xóa/tạo.
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('org',     'admin',         'edit'),
    ('org',     'bdh',           'edit'),
    ('org',     'truong_khoi',   'view'),
    ('org',     'glv_chu_nhiem', 'view'),
    ('org',     'glv',           'view'),
    ('staff',   'admin',         'edit'),
    ('staff',   'bdh',           'edit'),
    ('staff',   'truong_khoi',   'view'),
    ('staff',   'glv_chu_nhiem', 'view'),
    ('staff',   'glv',           'view'),
    ('staff',   'du_bi',         'view');

-- Quyền Thư viện: view = xem + đăng (chờ duyệt); edit = duyệt/gỡ/quản chủ đề.
INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES
    ('thu_vien', 'admin',         'edit'),
    ('thu_vien', 'bdh',           'edit'),
    ('thu_vien', 'truong_khoi',   'view'),
    ('thu_vien', 'glv_chu_nhiem', 'view'),
    ('thu_vien', 'glv',           'view'),
    ('thu_vien', 'du_bi',         'view');

-- ============================================================
-- Performance Indexes - Add after existing table definitions
-- ============================================================

-- Attendance: lookup by student + date (for statistics)
ALTER TABLE attendances ADD INDEX idx_att_student_date (student_id, session_date);

-- Attendance: lookup by program + date (for session management)
ALTER TABLE attendances ADD INDEX idx_att_program_date (program_id, session_date);

-- Leave requests: lookup by status + date (for approval queue)
ALTER TABLE leave_requests ADD INDEX idx_lv_status_date (status, session_date);

-- Leave requests: lookup by student (for student history)
ALTER TABLE leave_requests ADD INDEX idx_lv_student (student_id);

-- Scores: lookup by student + term (for report cards)
ALTER TABLE scores ADD INDEX idx_sc_student_term (student_id, term_id);

-- Reports: lookup by student (for student history)
ALTER TABLE reports ADD INDEX idx_rp_student (student_id);

-- Members: lookup by phone (for login - critical)
ALTER TABLE members ADD INDEX idx_member_phone (phone);

-- Members: idx_member_role đã khai trong CREATE TABLE members ở trên — không
-- ALTER thêm ở đây (trùng tên index sẽ lỗi khi nạp schema).

-- Enrollments: lookup by year + status (for roster)
ALTER TABLE enrollments ADD INDEX idx_enr_year_status (year_id, status);

-- Announcements: idx_an_live đã khai trong CREATE TABLE announcements ở trên —
-- không ALTER thêm (trùng tên index sẽ lỗi khi nạp schema).

-- ============================================================
--  STUDENT FAVORITES (Stars)
--  Lưu các em được đánh dấu yêu thích bởi từng user
-- ============================================================
CREATE TABLE IF NOT EXISTS student_favorites (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    student_id  INT NOT NULL,
    user_id     INT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (student_id, user_id),
    CONSTRAINT fk_fav_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  SỔ MỘC ĐIỆN TỬ & ĐỔI QUÀ (Sổ Mộc — Phase 1)
--  Cấu trúc trùng với config/migrations/003_stamps_rewards.sql (IF NOT EXISTS
--  nên nạp lại an toàn). Migration 003 còn seed vai trò thu_thu + module
--  gifts/rewards + phân quyền; phần seed đó KHÔNG lặp ở đây.
-- ============================================================
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
    CONSTRAINT fk_sttx_year    FOREIGN KEY (year_id)    REFERENCES school_years(id) ON DELETE CASCADE,
    CONSTRAINT fk_sttx_student FOREIGN KEY (student_id) REFERENCES students(id)     ON DELETE CASCADE,
    CONSTRAINT fk_sttx_att     FOREIGN KEY (ref_attendance_id) REFERENCES attendances(id) ON DELETE SET NULL,
    CONSTRAINT fk_sttx_actor   FOREIGN KEY (actor_id)   REFERENCES members(id) ON DELETE SET NULL,
    UNIQUE KEY uq_sttx_earn (ref_attendance_id, type) COMMENT 'chống cộng mộc trùng cho cùng một buổi điểm danh',
    INDEX idx_sttx_student (student_id, year_id),
    INDEX idx_sttx_order (ref_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gifts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(128) NOT NULL,
    stamp_cost  INT NOT NULL COMMENT 'giá quy đổi ra mộc',
    stock       INT NOT NULL DEFAULT 0,
    image_url   VARCHAR(255) NULL,
    status      ENUM('còn bán','ẩn') NOT NULL DEFAULT 'còn bán',
    sort_order  TINYINT NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS gift_order_items (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    order_id   INT NOT NULL,
    gift_id    INT NOT NULL,
    qty        INT NOT NULL DEFAULT 1,
    unit_cost  INT NOT NULL COMMENT 'giá mộc tại thời điểm đổi',
    line_cost  INT NOT NULL COMMENT 'unit_cost * qty',
    CONSTRAINT fk_gitem_order FOREIGN KEY (order_id) REFERENCES gift_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_gitem_gift  FOREIGN KEY (gift_id)  REFERENCES gifts(id),
    INDEX idx_gitem_order (order_id),
    INDEX idx_gitem_gift (gift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
