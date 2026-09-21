<?php
/**
 * Kiểm tra + dọn dẹp sau các bài test đăng ký.
 * Chỉ xoá đúng các SĐT test đã biết; in ra tình trạng còn lại để đối chiếu.
 */
require __DIR__ . '/../config/db.php';

$pdo = db();
$TEST_PHONES = ['0999000123', '0999000777', '0999000888', '0999000999'];

echo "=== TRƯỚC KHI DỌN ===\n";
echo "tổng members: " . $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn() . "\n";
echo "đang chờ duyệt: " . $pdo->query("SELECT COUNT(*) FROM members WHERE status='chờ duyệt'")->fetchColumn() . "\n\n";

echo "các dòng test còn sót:\n";
$in = implode(',', array_fill(0, count($TEST_PHONES), '?'));
$st = $pdo->prepare("SELECT id, code, full_name, phone, status FROM members WHERE phone IN ($in) ORDER BY id");
$st->execute($TEST_PHONES);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$rows) {
    echo "  (không có)\n";
} else {
    foreach ($rows as $r) {
        echo "  id={$r['id']} code={$r['code']} {$r['full_name']} phone={$r['phone']} [{$r['status']}]\n";
    }
}

echo "\n=== tất cả mã GLV hiện có ===\n";
foreach ($pdo->query("SELECT code, full_name, phone, status FROM members WHERE code LIKE 'GLV%' ORDER BY code")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['code']}  {$r['full_name']}  {$r['phone']}  [{$r['status']}]\n";
}

// --- DỌN ---
$del = $pdo->prepare("DELETE FROM members WHERE phone IN ($in)");
$del->execute($TEST_PHONES);
echo "\nđã xoá: {$del->rowCount()} dòng test\n";

echo "\n=== SAU KHI DỌN ===\n";
echo "tổng members: " . $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn() . "\n";
echo "đang chờ duyệt: " . $pdo->query("SELECT COUNT(*) FROM members WHERE status='chờ duyệt'")->fetchColumn() . "\n";
