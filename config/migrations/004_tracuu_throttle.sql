-- Migration: 004_tracuu_throttle.sql
-- Description: Bảng đếm rate-limit cho cổng tra cứu công khai (public/somoc.php và public/tracuu.php)
-- Created: 2026-09-26
-- Status: PENDING

-- =====================================================================
--  CHỐNG DÒ MÃ THIẾU NHI Ở CỔNG TRA CỨU CÔNG KHAI (SPEC-MOC-DIEN-TU §6.3)
--  Đếm lượt tra cứu theo IP trong một cửa sổ thời gian — mẫu y hệt
--  login_attempts (đếm đăng nhập sai). Không gắn mã thiếu nhi vào bảng
--  đếm: chặn kẻ dò TOÀN BỘ dải mã (HS001, HS002, ...) từ một IP, không chỉ
--  một mã cụ thể.
-- =====================================================================
CREATE TABLE IF NOT EXISTS tracuu_attempts (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    ip       VARCHAR(45) NOT NULL COMMENT 'đủ chỗ cho IPv6',
    tried_at DATETIME    NOT NULL,
    KEY idx_tracuu_ip (ip, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
