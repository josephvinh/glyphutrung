-- Migration 010: thêm cột cho diff + IP + target vào activity_logs
-- Tất cả cột mới NULL mặc định — log cũ giữ nguyên giá trị NULL.
-- MariaDB 10.0.2+ hỗ trợ IF NOT EXISTS cho ADD COLUMN; dùng trực tiếp.
-- (KHÔNG dùng DELIMITER — PDO không hỗ trợ.)

ALTER TABLE activity_logs
    ADD COLUMN IF NOT EXISTS ip           VARCHAR(45)  NULL AFTER detail,
    ADD COLUMN IF NOT EXISTS user_agent   VARCHAR(255) NULL AFTER ip,
    ADD COLUMN IF NOT EXISTS target_type  VARCHAR(32)  NULL AFTER user_agent,
    ADD COLUMN IF NOT EXISTS target_id    BIGINT       NULL AFTER target_type,
    ADD COLUMN IF NOT EXISTS target_label VARCHAR(255) NULL AFTER target_id,
    ADD COLUMN IF NOT EXISTS diff_json    JSON         NULL AFTER target_label;

CREATE INDEX IF NOT EXISTS idx_log_module_time ON activity_logs (module, logged_at);
CREATE INDEX IF NOT EXISTS idx_log_action_time ON activity_logs (action, logged_at);
