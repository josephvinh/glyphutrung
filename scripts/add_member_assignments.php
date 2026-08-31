<?php
/**
 * Tạo bảng member_assignments + backfill từ members hiện tại.
 * Chạy một lần: php scripts/add_member_assignments.php
 *
 * Bảng này cho phép một thành viên giữ nhiều vai trò và phụ trách
 * nhiều lớp/khối cùng lúc (kiêm nhiệm), kèm lịch sử phân công.
 */

require_once __DIR__ . '/../config/db.php';

echo "=== Tạo bảng member_assignments ===\n";

// 1. Tạo bảng nếu chưa có
db_run("
    CREATE TABLE IF NOT EXISTS member_assignments (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        member_id    INT NOT NULL,
        role_code    VARCHAR(24) NOT NULL,
        block_id     INT NULL,
        class_id     INT NULL,
        is_primary   TINYINT(1) NOT NULL DEFAULT 0
                     COMMENT 'phân công chính = vai trò mặc định khi đăng nhập',
        from_date    DATE NOT NULL,
        to_date      DATE NULL COMMENT 'null = đang hiệu lực',
        assigned_by  INT NOT NULL COMMENT 'BĐH phân công',
        note         VARCHAR(255) NULL,
        created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

        CONSTRAINT fk_assign_member   FOREIGN KEY (member_id)  REFERENCES members(id)  ON DELETE CASCADE,
        CONSTRAINT fk_assign_role     FOREIGN KEY (role_code)  REFERENCES roles(code),
        CONSTRAINT fk_assign_block    FOREIGN KEY (block_id)   REFERENCES blocks(id)   ON DELETE SET NULL,
        CONSTRAINT fk_assign_class    FOREIGN KEY (class_id)   REFERENCES classes(id)  ON DELETE SET NULL,
        CONSTRAINT fk_assign_by       FOREIGN KEY (assigned_by) REFERENCES members(id),

        INDEX idx_assign_member (member_id, to_date),
        INDEX idx_assign_class  (class_id, to_date),
        INDEX idx_assign_block  (block_id, to_date),
        INDEX idx_assign_role   (role_code, to_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "Bảng đã tồn tại hoặc được tạo.\n\n";

// 2. Backfill: di chuyển dữ liệu phân công hiện tại của members sang assignments
$count = db_one("SELECT COUNT(*) AS c FROM member_assignments")['c'];
if ((int) $count === 0) {
    echo "Backfill phân công hiện tại...\n";

    $adminId = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'] ?? 0;

    $members = db_all("
        SELECT id, role_code, block_id, class_id, created_at
          FROM members
    ");

    $inserted = 0;
    foreach ($members as $m) {
        db_run("
            INSERT INTO member_assignments
                (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
            VALUES (?, ?, ?, ?, 1, CURDATE(), ?)
        ", [$m['id'], $m['role_code'], $m['block_id'], $m['class_id'], $adminId]);
        $inserted++;
    }

    echo "Đã backfill $inserted phân công chính.\n";
} else {
    echo "Bảng đã có dữ liệu ($count dòng), bỏ qua backfill.\n";
}

echo "\n=== Hoàn tất ===\n";
