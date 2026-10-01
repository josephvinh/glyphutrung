-- LƯU Ý: module custom-qrcard đã bị gỡ khỏi mã nguồn; giữ tệp này để lịch sử migration nhất quán.
-- Bảng qr_card_* vẫn còn trong DB (không xoá).
-- Migration: 002_qr_card_templates.sql
-- Description: Thêm bảng templates và presets cho Custom QR Card
-- Created: 2026-09-21
-- Status: APPLIED

-- =====================================================================
--  BẢNG QR CARD TEMPLATES
--  Lưu các template có sẵn do hệ thống định nghĩa
-- =====================================================================
CREATE TABLE IF NOT EXISTS qr_card_templates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(50) NOT NULL UNIQUE COMMENT 'basic, classic, badge, compact, minimal',
    name        VARCHAR(100) NOT NULL COMMENT 'Tên hiển thị',
    options     JSON COMMENT 'Default options cho template',
    is_active   TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = đang hoạt động',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default templates
INSERT INTO qr_card_templates (code, name, options) VALUES
('basic', 'Basic', '{"showLogo":false,"logoPosition":"top","qrSize":"25mm","border":"solid","borderRadius":"4mm","padding":"3mm","fields":["code","name","className"]}'),
('classic', 'Classic', '{"showLogo":true,"logoPosition":"top","qrSize":"25mm","border":"double","borderRadius":"6mm","padding":"4mm","fields":["holyName","name","className","code"],"showEmblem":true}'),
('badge', 'Thẻ đeo', '{"showLogo":false,"qrSize":"30mm","border":"solid","borderRadius":"50%","padding":"2mm","fields":["name","className"],"holePunch":true}'),
('compact', 'Gọn nhẹ', '{"showLogo":false,"qrSize":"20mm","border":"dashed","borderRadius":"2mm","padding":"2mm","fields":["code","name"],"gridColumns":3}'),
('minimal', 'Tối giản', '{"showLogo":false,"qrSize":"25mm","border":"none","borderRadius":"0","padding":"1mm","fields":["name"]}');

-- =====================================================================
--  BẢNG QR CARD PRESETS
--  Lưu presets tùy chỉnh của từng người dùng
-- =====================================================================
CREATE TABLE IF NOT EXISTS qr_card_presets (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    member_id   INT NOT NULL COMMENT 'Người tạo preset',
    name        VARCHAR(100) NOT NULL COMMENT 'Tên preset',
    options     JSON NOT NULL COMMENT 'Các tùy chọn đã lưu',
    is_default  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = preset mặc định',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_preset_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_preset_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  BẢNG QR CARD LOGOS
--  Lưu logo tùy chỉnh do người dùng upload
-- =====================================================================
CREATE TABLE IF NOT EXISTS qr_card_logos (
    id             VARCHAR(50) PRIMARY KEY COMMENT 'UUID hoặc unique code',
    member_id      INT NOT NULL COMMENT 'Người upload',
    filename       VARCHAR(255) NOT NULL COMMENT 'Tên file lưu trữ',
    original_name  VARCHAR(255) NOT NULL COMMENT 'Tên gốc khi upload',
    mime_type      VARCHAR(50) NOT NULL COMMENT 'VD: image/png',
    size           INT NOT NULL COMMENT 'Kích thước bytes',
    uploaded_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_logo_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_logo_member (member_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  THƯ MỤC LƯU LOGO
-- =====================================================================
-- Tạo thư mục lưu logo (chạy thủ công nếu cần):
-- mkdir -p public/uploads/qr-logos
