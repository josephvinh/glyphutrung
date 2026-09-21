<?php
/**
 * KIỂM CHỨNG BẢN VÁ: duyệt thành viên phải THÀNH CÔNG và trả JSON hợp lệ.
 * (Bổ sung cho repro-approve-fail.php: lần này chọn lớp hợp lệ.)
 */
require __DIR__ . '/../config/db.php';

$BASE = 'http://localhost:8888/tntt/public/';
$PHONE_PENDING = '0999003333';
$ADMIN_PHONE = '0901000001';
$ADMIN_PASS = app_config('default_password') ?: 'tntt@2026';
$COOKIE = sys_get_temp_dir() . '/tntt_ck_' . getmypid() . '.txt';

function req(string $url, ?array $json, string $cookie, array $extra = []): array {
    $ch = curl_init($url);
    $h = array_merge(['Accept: application/json'], $json !== null ? ['Content-Type: application/json'] : [], $extra);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
        CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie,
    ]);
    if ($json !== null) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE)); }
    $b = curl_exec($ch);
    $s = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $s, 'body' => $b];
}

$pdo = db();
$cls = db_one("SELECT id, name, block_id FROM classes ORDER BY id LIMIT 1");
$blk = $cls ? db_one('SELECT name FROM blocks WHERE id=?', [$cls['block_id']]) : null;
echo "lớp dùng để duyệt: " . ($cls['name'] ?? '(không có lớp!)') . "  khối: " . ($blk['name'] ?? '?') . "\n";

// tạo hồ sơ chờ duyệt
$pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE_PENDING]);
$max = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n FROM members WHERE code LIKE 'GLV%'");
$code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);
$pid = db_insert('INSERT INTO members (code, holy_name, full_name, phone, birth_date, password_hash,
                                      role_code, status, must_change_pw, register_note, registered_at)
                  VALUES (?,?,?,?,?,?,?,?,0,?,NOW())',
    [$code, 'Test', 'Duyệt Thử', $PHONE_PENDING, '1995-01-01', password_hash('x', PASSWORD_DEFAULT), 'glv', 'chờ duyệt', 'verify']);
echo "hồ sơ: id=$pid code=$code\n\n";

$login = req($BASE . 'api/auth.php?action=login', ['phone' => $ADMIN_PHONE, 'password' => $ADMIN_PASS], $COOKIE);
echo "login -> HTTP {$login['status']}\n";
$home = req($BASE, null, $COOKIE);
preg_match('/csrfToken["\']?\s*[:=]\s*["\']([^"\']+)/', $home['body'], $m);
$csrf = $m[1] ?? '';

// duyệt với vai glv_chu_nhiem (scope lớp) -> cần className
$r = req($BASE . 'api/org.php?action=approveMember',
    ['id' => $pid, 'role' => 'glv_chu_nhiem', 'className' => $cls['name'], 'block' => ''],
    $COOKIE, $csrf ? ["X-CSRF-TOKEN: $csrf"] : []);

echo "POST approveMember -> HTTP {$r['status']}\n";
echo "body: " . substr(trim($r['body']), 0, 300) . "\n";
$j = json_decode($r['body'], true);
echo "JSON hợp lệ? " . ($j !== null ? 'CÓ' : 'KHÔNG') . "\n";

$after = db_one('SELECT status, role_code, class_id FROM members WHERE id=?', [$pid]);
echo "sau khi duyệt: " . ($after ? "status={$after['status']} role={$after['role_code']} class_id={$after['class_id']}" : '(không còn)') . "\n";

$pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE_PENDING]);
@unlink($COOKIE);
echo "\nđã dọn.\n";
