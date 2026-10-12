<?php
/**
 * Truy vấn nhanh DB test cho script trình duyệt (tests/e2e/*.js): in kết quả dạng JSON.
 *
 *   php tests/fixtures/e2e_query.php "SELECT status FROM attendances WHERE ..."
 *
 * Chỉ chạy trên DB tntt_test và chỉ nhận câu SELECT.
 */

require __DIR__ . '/../../config/db.php';

$dbName = db_one('SELECT DATABASE() AS d')['d'] ?? '';
if ($dbName !== 'tntt_test') {
    fwrite(STDERR, "e2e_query: chỉ chạy trên DB tntt_test.\n");
    exit(1);
}
$sql = $argv[1] ?? '';
if (!preg_match('/^\s*SELECT\b/i', $sql)) {
    fwrite(STDERR, "e2e_query: chỉ nhận câu SELECT.\n");
    exit(1);
}
echo json_encode(db_all($sql), JSON_UNESCAPED_UNICODE), "\n";
