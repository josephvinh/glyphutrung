<?php
/**
 * Read-only DB check after the delegated QA run.
 * Uses the app's own db() so it hits exactly the database the app uses.
 */
require __DIR__ . '/../config/db.php';

$pdo = db();
$cfg = app_config('db');
fwrite(STDERR, "db name: " . ($cfg['name'] ?? '?') . "  host: " . ($cfg['host'] ?? '?') . "\n");

echo "=== members count ===\n";
echo $pdo->query('SELECT COUNT(*) FROM members')->fetchColumn() . "\n";

echo "\n=== the QA test phone 0999000123 ===\n";
$st = $pdo->prepare('SELECT id, code, full_name, phone, status, registered_at FROM members WHERE phone = ?');
$st->execute(['0999000123']);
$rows = $st->fetchAll(PDO::FETCH_ASSOC);
echo $rows ? json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n" : "NOT FOUND (row was deleted or never created)\n";

echo "\n=== newest 8 members ===\n";
$st = $pdo->query('SELECT id, code, full_name, phone, status, registered_at, created_at FROM members ORDER BY id DESC LIMIT 8');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo sprintf(
        "  id=%-4s code=%-8s %-22s phone=%-12s status=%-10s reg=%s\n",
        $r['id'], $r['code'], $r['full_name'], $r['phone'], $r['status'], $r['registered_at'] ?? '-'
    );
}

echo "\n=== any GLV codes assigned ===\n";
$st = $pdo->query("SELECT code, full_name, phone, status FROM members WHERE code LIKE 'GLV%' ORDER BY code");
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "  {$r['code']}  {$r['full_name']}  {$r['phone']}  [{$r['status']}]\n";
}
