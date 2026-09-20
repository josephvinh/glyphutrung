<?php
/**
 * KIỂM CHỨNG DỮ LIỆU THẬT (scratch, chỉ đọc) — phục vụ rà soát 2 module
 * Khối & Lớp và Danh sách. Không ghi gì vào CSDL.
 */
require __DIR__ . '/../config/db.php';

echo "=== ENGINE + INDEX bảng classes ===\n";
$e = db_one("SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes'");
echo "ENGINE: " . ($e['ENGINE'] ?? '?') . "\n";
foreach (db_all('SHOW INDEX FROM classes') as $r) {
    printf("  %-20s cột %-14s unique=%s\n", $r['Key_name'], $r['Column_name'], $r['Non_unique'] ? 'không' : 'CÓ');
}

echo "\n=== Số dòng các bảng liên quan ===\n";
foreach (['blocks', 'classes', 'members', 'students', 'enrollments', 'member_assignments'] as $t) {
    $c = db_one("SELECT COUNT(*) n FROM `$t`");
    printf("  %-20s %s\n", $t, $c['n']);
}

echo "\n=== Khối / lớp thật ===\n";
foreach (db_all('SELECT b.name AS khoi, COUNT(c.id) AS so_lop
                   FROM blocks b LEFT JOIN classes c ON c.block_id = b.id
                  GROUP BY b.id ORDER BY b.sort_order, b.name') as $r) {
    printf("  %-18s %s lớp\n", $r['khoi'], $r['so_lop']);
}

echo "\n=== Phân công đang hiệu lực theo vai trò (dữ liệu cho assignments list_active) ===\n";
foreach (db_all('SELECT role_code, COUNT(*) n FROM member_assignments
                  WHERE to_date IS NULL GROUP BY role_code') as $r) {
    printf("  %-18s %s\n", $r['role_code'], $r['n']);
}

echo "\n=== Phân công trưởng khối còn hiệu lực nhưng block_id NULL (mồ côi) ===\n";
$m = db_one("SELECT COUNT(*) n FROM member_assignments
              WHERE to_date IS NULL AND role_code = 'truong_khoi' AND block_id IS NULL");
echo "  " . $m['n'] . " bản ghi\n";

echo "\n=== Lớp trùng tên giữa các khối (nếu có) ===\n";
$d = db_all('SELECT name, COUNT(*) n FROM classes GROUP BY name HAVING n > 1');
echo "  " . count($d) . " tên lớp bị trùng\n";

echo "\n=== Tên khối/lớp dài nhất (kiểm tra VARCHAR(64)) ===\n";
foreach (['blocks', 'classes'] as $t) {
    $r = db_one("SELECT MAX(CHAR_LENGTH(name)) mx FROM `$t`");
    printf("  %-10s dài nhất %s ký tự\n", $t, $r['mx'] ?? 0);
}

echo "\n=== Kích thước payload data.php?part=core (ước lượng) ===\n";
$n = db_one("SELECT COUNT(*) n FROM students");
echo "  students toàn đoàn: " . $n['n'] . " hồ sơ\n";
