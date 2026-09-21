<?php
/**
 * THÍ NGHIỆM DỨT ĐIỂM: đăng ký qua HTTP rồi quét NGAY mọi database
 * để biết web app thực sự ghi vào đâu.
 */
$host = '127.0.0.1'; $port = 3306; $user = 'root'; $pass = '';
$PHONE = '0999001111';

function dbs(PDO $root): array {
    return array_values(array_filter(
        $root->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN),
        fn($d) => !in_array($d, ['information_schema','performance_schema','mysql','sys','phpmyadmin'], true)
    ));
}

function findPhone(PDO $root, array $dbs, string $phone): array {
    $hits = [];
    foreach ($dbs as $db) {
        try {
            $pdo = new PDO("mysql:host=127.0.0.1;port=3306;dbname=$db;charset=utf8mb4", 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $has = $pdo->query("SHOW TABLES LIKE 'members'")->fetchAll();
            if (!$has) continue;
            $st = $pdo->prepare('SELECT id, code, full_name, phone, status FROM members WHERE phone = ?');
            $st->execute([$phone]);
            foreach ($st->fetchAll() as $r) $hits[] = "$db => id={$r['id']} code={$r['code']} {$r['full_name']} [{$r['status']}]";
        } catch (Throwable $e) { /* bỏ qua */ }
    }
    return $hits;
}

$root = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$all = dbs($root);
echo "databases: " . implode(', ', $all) . "\n\n";

// dọn trước nếu còn sót
foreach ($all as $db) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($pdo->query("SHOW TABLES LIKE 'members'")->fetchAll()) {
            $pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE]);
        }
    } catch (Throwable $e) {}
}
echo "trước khi đăng ký, tìm $PHONE: " . (findPhone($root, $all, $PHONE) ?: '(không có)') . "\n\n";

// --- ĐĂNG KÝ QUA HTTP ---
$payload = json_encode([
    'holyName' => 'Maria', 'fullName' => 'HTTP Probe', 'phone' => $PHONE,
    'birthDate' => '1997-02-02', 'password' => 'probe123456',
    'note' => 'xac dinh db', 'danhXung' => 'glv',
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('http://localhost:8888/tntt/public/api/auth.php?action=register');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "POST register -> HTTP $status\nbody: $body\n\n";

// --- QUÉT NGAY ---
$hits = findPhone($root, $all, $PHONE);
echo "SAU khi đăng ký, tìm $PHONE:\n";
echo $hits ? ('  ' . implode("\n  ", $hits) . "\n") : "  (KHÔNG THẤY Ở DB NÀO!)\n";

// --- DỌN ---
echo "\ndọn dẹp:\n";
foreach ($all as $db) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($pdo->query("SHOW TABLES LIKE 'members'")->fetchAll()) {
            $n = $pdo->prepare('DELETE FROM members WHERE phone = ?');
            $n->execute([$PHONE]);
            if ($n->rowCount() > 0) echo "  đã xoá {$n->rowCount()} dòng ở $db\n";
        }
    } catch (Throwable $e) {}
}
echo "còn lại: " . (findPhone($root, $all, $PHONE) ?: '(sạch)') . "\n";
