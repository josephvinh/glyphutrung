-- =====================================================================
-- SỬA: icon Thư viện + nhãn "Hướng dẫn" bị sai
--   - Bản lucide rút gọn không có icon 'library' -> đổi sang 'scroll-text'.
--   - Nhãn module 'guide' bị lưu sai mã hoá (mojibake) -> đặt lại đúng.
-- Chạy được nhiều lần.
-- =====================================================================

UPDATE modules SET icon = 'scroll-text' WHERE module_key = 'thu_vien';
UPDATE modules SET label = 'Hướng dẫn'  WHERE module_key = 'guide';
