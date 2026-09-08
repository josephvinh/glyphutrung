<?php
/**
 * LỊCH CÁ NHÂN + THÔNG BÁO HỌP (RSVP)  — chạy MỘT LẦN trên máy chủ thật.
 *
 *   php config/migrate_lich_hop.php
 *
 * Idempotent: tạo bảng nếu chưa có, thêm cột nếu thiếu, đăng ký module
 * "Lịch của tôi" + quyền. Chạy lại vô hại.
 */

require __DIR__ . '/db.php';

/* --- Helper: thêm cột nếu chưa có (MySQL cũ không có ADD COLUMN IF NOT EXISTS) --- */
function them_cot(string $bang, string $cot, string $ddl): void
{
    $co = db_one(
        "SELECT 1 FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$bang, $cot]);
    if (!$co) {
        db_run("ALTER TABLE `$bang` ADD COLUMN $ddl");
        echo "  + $bang.$cot\n";
    }
}

// --- 1. Ghi chú cá nhân ---
db_run("CREATE TABLE IF NOT EXISTS personal_notes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ Bảng personal_notes\n";

// --- 2. Cột buổi họp trên announcements ---
them_cot('announcements', 'is_meeting',    'is_meeting TINYINT NOT NULL DEFAULT 0');
them_cot('announcements', 'meeting_at',    'meeting_at DATETIME NULL');
them_cot('announcements', 'meeting_place', 'meeting_place VARCHAR(255) NULL');
them_cot('announcements', 'reminded_at',   'reminded_at DATETIME NULL');
echo "✓ Cột buổi họp trên announcements\n";

// --- 3. Trả lời họp (RSVP) ---
db_run("CREATE TABLE IF NOT EXISTS meeting_rsvp (
    announcement_id INT NOT NULL,
    member_id       INT NOT NULL,
    status          ENUM('tham gia','không tham gia') NOT NULL,
    responded_at    DATETIME NOT NULL,
    PRIMARY KEY (announcement_id, member_id),
    CONSTRAINT fk_rsvp_ann    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
    CONSTRAINT fk_rsvp_member FOREIGN KEY (member_id)       REFERENCES members(id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✓ Bảng meeting_rsvp\n";

// --- 4. Đăng ký module "Lịch của tôi" + quyền (mọi vai tự quản lịch mình) ---
db_run("INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
        VALUES ('notes', 'Lịch của tôi', 'calendar-check', 'text-teal-600', 'glv', 11)");
foreach (['admin','bdh','truong_khoi','glv_chu_nhiem','glv','du_bi'] as $role) {
    db_run("INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES ('notes', ?, 'edit')", [$role]);
}
echo "✓ Module 'notes' + quyền\n";

echo "Xong. Lịch cá nhân + thông báo họp đã sẵn sàng.\n";
