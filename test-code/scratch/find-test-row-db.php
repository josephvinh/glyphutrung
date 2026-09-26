<?php
/**
 * Dò xem dòng đăng ký test thực sự nằm ở database nào.
 * Quét MỌI database có bảng `members` và tìm số điện thoại test.
 */
$host = '127.0.0.1'; $port = 3306; $user = 'root'; $pass = '';

$TEST = ['0999000123', '0999000777', '0999000888', '0999000999'];

$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$dbs = $root->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
echo "databases: " . implode(', ', $dbs) . "\n\n";

echo "=== env TNTT_DB_* nhìn từ CLI ===\n";
foreach (['TNTT_DB_HOST','TNTT_DB_PORT','TNTT_DB_NAME','TNTT_DB_USER','TNTT_DB_PASS'] as $e) {
    $v = getenv($e);
    echo "  $e = " . ($v === false ? '(không đặt)' : $v) . "\n";
}

foreach ($dbs as $db) {
    if (in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys', 'phpmyadmin'], true)) continue;
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $has = $pdo->query("SHOW TABLES LIKE 'members'")->fetchAll();
        if (!$has) { echo "\n[$db] không có bảng members\n"; continue; }

        $n = $pdo->query('SELECT COUNT(*) c FROM members')->fetch()['c'];
        echo "\n[$db] members = $n\n";

        $in = implode(',', array_fill(0, count($TEST), '?'));
        $st = $pdo->prepare("SELECT id, code, full_name, phone, status, registered_at FROM members WHERE phone IN ($in) ORDER BY id");
        $st->execute($TEST);
        $rows = $st->fetchAll();
        if ($rows) {
            echo "  >>> TÌM THẤY DÒNG TEST:\n";
            foreach ($rows as $r) {
                echo "      id={$r['id']} code={$r['code']} {$r['full_name']} {$r['phone']} [{$r['status']}] reg={$r['registered_at']}\n";
            }
        }
        echo "  mã GLV: ";
        $g = $pdo->query("SELECT code FROM members WHERE code LIKE 'GLV%' ORDER BY code")->fetchAll(PDO::FETCH_COLUMN);
        echo ($g ? implode(', ', $g) : '(không có)') . "\n";
    } catch (Throwable $e) {
        echo "\n[$db] lỗi: " . $e->getMessage() . "\n";
    }
}
