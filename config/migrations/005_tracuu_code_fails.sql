-- Migration: 005_tracuu_code_fails.sql
-- Description: Đếm lần nhập sai mật mã (ngày sinh) THEO MÃ thiếu nhi ở trang Tra cứu điểm (public/tracuu.php)
-- Created: 2026-09-29
-- Status: PENDING

-- =====================================================================
--  KHOÁ TẠM THEO MÃ EM
--  tracuu_attempts chỉ đếm theo IP nên không cản được dò phân tán nhiều IP
--  (mã dạng GDGLPT260001 tuần tự, ngày sinh chỉ vài nghìn khả năng).
--  Bảng này đếm số lần SAI theo mã (chuẩn hoá HOA, kể cả mã không tồn tại
--  để không lộ mã nào có thật). Quá ngưỡng trong cửa sổ -> khoá tạm.
-- =====================================================================
CREATE TABLE IF NOT EXISTS tracuu_code_fails (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    code     VARCHAR(32) NOT NULL,
    tried_at DATETIME    NOT NULL,
    KEY idx_tracuu_code (code, tried_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
