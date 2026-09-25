-- Migration: 002_qrcard_custom_options.sql
-- Description: Thêm bảng lưu presets và logos cho Custom QR Card
-- Created: 2026-09-26
-- Status: PENDING

-- =====================================================================
--  CUSTOM QR CARD - PRESETS & LOGOS
-- =====================================================================

-- Bảng lưu cấu hình preset tùy chỉnh
CREATE TABLE IF NOT EXISTS qrcard_presets (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier   VARCHAR(128) NOT NULL COMMENT 'user_ID hoặc session_ID',
    name              VARCHAR(50)  NOT NULL COMMENT 'Tên preset',
    config            JSON         NOT NULL COMMENT 'Cấu hình JSON',
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user (user_identifier),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bảng lưu logo đã upload
CREATE TABLE IF NOT EXISTS qrcard_logos (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    user_identifier   VARCHAR(128) NOT NULL COMMENT 'user_ID hoặc session_ID',
    filename          VARCHAR(128) NOT NULL COMMENT 'Tên file đã lưu',
    original_name     VARCHAR(255) NOT NULL COMMENT 'Tên gốc khi upload',
    size              INT          NOT NULL COMMENT 'Kích thước bytes',
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_identifier),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
