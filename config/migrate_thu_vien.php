<?php
/**
 * MODULE THƯ VIỆN & SỔ TAY — dựng bảng + đăng ký module/quyền (chạy một lần).
 *
 *   php config/migrate_thu_vien.php
 *
 * Idempotent: chạy lại vô hại. Trước đây bước này phải làm tay bằng
 * phpMyAdmin với hai tệp docs/nang-cap-db-thu-vien.sql và
 * docs/nang-cap-db-so-tay.sql; nay gộp còn một lệnh, và tự nâng cấp được
 * cả bản cài cũ (chưa có cột item_type/body).
 */

require __DIR__ . '/db.php';

$dbName = app_config('db')['name'];

/** Cột đã tồn tại trong library_items chưa? */
$coCot = fn(string $col): bool => (bool) db_one(
    'SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    [$dbName, 'library_items', $col]);

// ---------------------------------------------------------------- 1. Bảng
db_run('CREATE TABLE IF NOT EXISTS library_categories (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active  TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

db_run('CREATE TABLE IF NOT EXISTS library_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(200) NOT NULL,
    item_type     ENUM(\'file\',\'article\') NOT NULL DEFAULT \'file\',
    description   TEXT NULL,
    body          MEDIUMTEXT NULL,
    category_id   INT UNSIGNED NULL,
    stored_name   VARCHAR(120) NULL,
    original_name VARCHAR(255) NULL,
    mime_type     VARCHAR(100) NULL,
    size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
    status        ENUM(\'cho_duyet\',\'da_duyet\',\'tu_choi\') NOT NULL DEFAULT \'cho_duyet\',
    uploaded_by   INT NOT NULL,
    approved_by   INT NULL,
    reject_reason VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at   DATETIME NULL,
    INDEX idx_status_cat (status, category_id),
    INDEX idx_uploader (uploaded_by),
    CONSTRAINT fk_lib_cat FOREIGN KEY (category_id)
        REFERENCES library_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

// ------------------------------------------- 2. Nâng cấp bản cài cũ (nếu có)
// Bản dựng từ docs/nang-cap-db-thu-vien.sql chỉ có mục 'file'.
if (!$coCot('item_type')) {
    db_run('ALTER TABLE library_items
            ADD COLUMN item_type ENUM(\'file\',\'article\') NOT NULL DEFAULT \'file\' AFTER title');
    echo "  + thêm cột item_type\n";
}
if (!$coCot('body')) {
    db_run('ALTER TABLE library_items ADD COLUMN body MEDIUMTEXT NULL AFTER description');
    echo "  + thêm cột body\n";
}
// Bài viết không có tệp -> các cột tệp phải cho phép NULL.
db_run('ALTER TABLE library_items
        MODIFY stored_name   VARCHAR(120) NULL,
        MODIFY original_name VARCHAR(255) NULL,
        MODIFY mime_type     VARCHAR(100) NULL');

// ------------------------------------------------------------- 3. Chủ đề
$seed = [
    [1, 'Giáo án',               1],
    [2, 'Đào tạo Huynh trưởng',  2],
    [3, 'Bài hát',               3],
    [4, 'Văn kiện',              4],
    [5, 'Sinh hoạt',             5],
    [6, 'Kinh & nghi thức',      6],
    [7, 'Quy trình & hướng dẫn', 7],
];
$nThem = 0;
foreach ($seed as [$id, $ten, $thuTu]) {
    // Thêm theo TÊN (không theo id) để không đè chủ đề người dùng đã tạo,
    // và để chạy lại không nhân bản.
    if (!db_one('SELECT id FROM library_categories WHERE name = ?', [$ten])) {
        db_run('INSERT INTO library_categories (id, name, sort_order) VALUES (?,?,?)',
               [$id, $ten, $thuTu]);
        $nThem++;
    }
}

// --------------------------------------------------- 4. Module + phân quyền
db_run("INSERT IGNORE INTO modules (module_key, label, icon, color, area, sort_order)
        VALUES ('thu_vien', 'Thư viện', 'scroll-text', 'text-amber-600', 'glv', 7)");

// view = xem + đăng (chờ duyệt); edit = duyệt/gỡ/quản chủ đề.
$quyen = [
    'admin'         => 'edit',
    'bdh'           => 'edit',
    'truong_khoi'   => 'view',
    'glv_chu_nhiem' => 'view',
    'glv'           => 'view',
    'du_bi'         => 'view',
];
$nQuyen = 0;
foreach ($quyen as $vai => $muc) {
    $nQuyen += db_run('INSERT IGNORE INTO permissions (module_key, role_code, level) VALUES (?,?,?)',
                      ['thu_vien', $vai, $muc]);
}

echo "Xong: bảng library_categories + library_items, thêm {$nThem} chủ đề, "
   . "module 'thu_vien', {$nQuyen} dòng quyền.\n";
echo "Nhắc: tệp tài liệu lưu ở storage/library/ (ngoài web root) — nhớ nằm trong lịch sao lưu.\n";
