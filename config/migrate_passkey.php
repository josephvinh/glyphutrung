<?php
/**
 * ĐĂNG NHẬP SINH TRẮC HỌC (Passkey / WebAuthn) — chạy MỘT LẦN trên máy chủ thật.
 *
 *   php config/migrate_passkey.php
 *
 * Tạo bảng member_passkeys lưu khoá công khai (public key) của vân tay/FaceID
 * mà thành viên đã đăng ký. KHÔNG lưu vân tay/khuôn mặt — chuẩn WebAuthn chỉ
 * lưu khoá công khai, phần bí mật nằm trong thiết bị người dùng.
 * Idempotent: chạy lại vô hại.
 */

require __DIR__ . '/db.php';

db_run("CREATE TABLE IF NOT EXISTS member_passkeys (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    member_id     INT          NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key    TEXT         NOT NULL,
    user_handle   VARCHAR(255) NOT NULL,
    sign_count    INT          DEFAULT 0,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
    last_used_at  DATETIME     NULL,
    UNIQUE KEY uq_credential (credential_id),
    INDEX idx_pk_member (member_id),
    CONSTRAINT fk_pk_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "✓ Bảng member_passkeys (đăng nhập sinh trắc học)\n";
