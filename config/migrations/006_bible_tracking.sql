-- Migration: 006_bible_tracking
-- Description: Create bible_daily table for tracking daily Bible verses per IP
-- Created: 2025-01-15

CREATE TABLE IF NOT EXISTS bible_daily (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6',
    verse_text TEXT NOT NULL COMMENT 'Nội dung câu Kinh Thánh',
    verse_ref VARCHAR(100) NOT NULL COMMENT 'Tham chiếu vd: John 3:16',
    verse_translation VARCHAR(50) DEFAULT 'vietnamese' COMMENT 'Bản dịch',
    fetched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
