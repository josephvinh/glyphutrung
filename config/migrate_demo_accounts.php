<?php
/**
 * TẠO 5 TÀI KHOẢN DEMO — xem đủ chức năng, KHÔNG được thao tác  (chạy một lần)
 *
 *   php config/migrate_demo_accounts.php
 *   TNTT_DEMO_PASSWORD='MatKhau123' php config/migrate_demo_accounts.php
 *
 * Idempotent. Vai 'demo' (toàn đoàn) có quyền 'view' trên MỌI module; các hành
 * động ghi còn bị chặn thêm ở require_write() (xem _bootstrap.php).
 * Đăng nhập: SĐT 0900000001..0900000005. Không đặt TNTT_DEMO_PASSWORD thì
 * sinh mật khẩu ngẫu nhiên và in ra một lần. Chạy lại sẽ đặt lại mật khẩu.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/password.php';

db_run("INSERT INTO roles (code, label, level, scope, descr)
        VALUES ('demo', 'Tài khoản Demo', 1, 'toàn đoàn', 'Chỉ xem toàn bộ chức năng, không được thao tác')
        ON DUPLICATE KEY UPDATE label=VALUES(label), scope=VALUES(scope), descr=VALUES(descr)");
db_run("INSERT IGNORE INTO titles (role_code, label, sort_order) VALUES ('demo', 'Demo', 1)");

$mods = array_column(db_all("SELECT module_key FROM modules"), 'module_key');
foreach ($mods as $mod) {
    db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?, 'demo', 'view')
            ON DUPLICATE KEY UPDATE level='view'", [$mod]);
}

$title = db_one("SELECT id FROM titles WHERE role_code = 'demo' LIMIT 1");
$pass  = getenv('TNTT_DEMO_PASSWORD') ?: bin2hex(random_bytes(5));
$hash  = password_hash_upgrade($pass);

for ($i = 1; $i <= 5; $i++) {
    $code  = 'DEMO' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $phone = '090000000' . $i;
    $row   = db_one("SELECT id, role_code FROM members WHERE code = ?", [$code]);
    if ($row && $row['role_code'] !== 'demo') {
        fwrite(STDERR, "Bỏ qua $code: mã đã thuộc tài khoản khác.\n");
        continue;
    }
    if ($row) {
        db_run("UPDATE members SET password_hash = ?, status = 'đang phục vụ', must_change_pw = 0 WHERE id = ?",
               [$hash, $row['id']]);
    } else {
        if (db_one("SELECT id FROM members WHERE phone = ?", [$phone])) {
            fwrite(STDERR, "Bỏ qua $code: SĐT $phone đã có người dùng.\n");
            continue;
        }
        db_run("INSERT INTO members (code, full_name, phone, password_hash, role_code, title_id, status, must_change_pw)
                VALUES (?, ?, ?, ?, 'demo', ?, 'đang phục vụ', 0)",
               [$code, "Tài khoản Demo $i", $phone, $hash, $title['id'] ?? null]);
    }
    echo "  $phone  (Tài khoản Demo $i)\n";
}
echo "Mật khẩu chung: $pass\n";
echo "Quyền: 'view' trên " . count($mods) . " module.\n";
