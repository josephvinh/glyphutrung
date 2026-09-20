<?php
/** Kiểm chứng quyền thật trong CSDL (scratch, chỉ đọc) */
require __DIR__ . '/../config/db.php';

echo "=== Các module_key đang khai trong bảng modules ===\n";
foreach (db_all('SELECT module_key, label, is_enabled FROM modules ORDER BY module_key') as $r) {
    printf("  %-14s %-28s enabled=%s\n", $r['module_key'], $r['label'], $r['is_enabled']);
}

echo "\n=== Quyền theo module quan trọng ===\n";
foreach (['org', 'staff', 'students', 'promotion'] as $mk) {
    $rows = db_all('SELECT role_code, level FROM permissions WHERE module_key = ? ORDER BY role_code', [$mk]);
    echo "  [$mk] ";
    if (!$rows) { echo "KHÔNG CÓ DÒNG NÀO TRONG permissions\n"; continue; }
    $parts = [];
    foreach ($rows as $r) $parts[] = $r['role_code'] . '=' . $r['level'];
    echo implode(', ', $parts) . "\n";
}

echo "\n=== Vai trò đang có thật ===\n";
foreach (db_all('SELECT code, label, scope FROM roles ORDER BY level DESC') as $r) {
    printf("  %-16s %-22s scope=%s\n", $r['code'], $r['label'], $r['scope']);
}

echo "\n=== Tài khoản theo vai trò gốc ===\n";
foreach (db_all('SELECT role_code, COUNT(*) n FROM members GROUP BY role_code') as $r) {
    printf("  %-16s %s\n", $r['role_code'], $r['n']);
}
