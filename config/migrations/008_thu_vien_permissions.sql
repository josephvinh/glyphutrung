-- ================================================================
--  Migration 008: Quyền module Thư viện (thu_vien)
--
--  schema.sql seed quyền thu_vien TRƯỚC khi bảng roles có dữ liệu, nên
--  khóa ngoại fk_pm_role hỏng và INSERT IGNORE nuốt lỗi → CSDL cài mới
--  không có dòng quyền nào cho thu_vien. Hậu quả: màn Cài đặt quyền đọc
--  permissions['thu_vien'][vai] bị undefined (lỗi JS "reading 'glv'"),
--  và chỉ Quản trị thấy được Thư viện.
--
--  Idempotent: ON DUPLICATE KEY giữ nguyên mức Quản trị đã chỉnh. Không
--  dùng INSERT IGNORE để lỗi khóa ngoại (nếu có) hiện ra thay vì bị nuốt.
-- ================================================================

INSERT INTO permissions (module_key, role_code, level) VALUES
    ('thu_vien', 'admin',         'edit'),
    ('thu_vien', 'bdh',           'edit'),
    ('thu_vien', 'truong_khoi',   'view'),
    ('thu_vien', 'glv_chu_nhiem', 'view'),
    ('thu_vien', 'glv',           'view'),
    ('thu_vien', 'du_bi',         'view')
ON DUPLICATE KEY UPDATE level = level;
