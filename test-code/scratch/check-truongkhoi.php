<?php
require __DIR__ . '/../config/db.php';
echo "Truong khoi assignments:\n";
foreach (db_all(
    "SELECT ma.id, ma.member_id, ma.role_code, ma.block_id, ma.class_id, m.full_name, b.name blk
       FROM member_assignments ma
       JOIN members m ON m.id = ma.member_id
       LEFT JOIN blocks b ON b.id = ma.block_id
      WHERE ma.role_code = 'truong_khoi' AND ma.to_date IS NULL"
) as $r) {
    printf("  id=%d mid=%d name=%s block_id=%s\n",
        $r['id'], $r['member_id'], $r['full_name'], $r['block_id'] ?? 'NULL');
}
echo "\nBlocks:\n";
foreach (db_all("SELECT id, name FROM blocks ORDER BY id") as $r) {
    printf("  id=%d name=%s\n", $r['id'], $r['name']);
}
echo "\nMember block_id:\n";
foreach (db_all("SELECT id, full_name, role_code, block_id FROM members WHERE role_code='truong_khoi'") as $r) {
    printf("  id=%d name=%s block_id=%s\n", $r['id'], $r['full_name'], $r['block_id'] ?? 'NULL');
}
