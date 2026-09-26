<?php
/**
 * TÁI HIỆN LỖI "MẤT KẾT NỐI" KHI DUYỆT THÀNH VIÊN.
 *
 * Mô phỏng đúng thao tác của trình duyệt:
 *   1. Tạo 1 hồ sơ 'chờ duyệt' thật
 *   2. Đăng nhập admin qua HTTP (giữ cookie + CSRF)
 *   3. Gọi POST api/org.php?action=approveMember
 *   4. In HTTP status + raw body (để thấy có phải HTML/500 không)
 *
 * Dọn sạch sau khi chạy.
 */
require __DIR__ . '/../config/db.php';

$BASE = 'http://localhost:8888/tntt/public/';
$PHONE_PENDING = '0999002222';
$ADMIN_PHONE = '0901000001';
$ADMIN_PASS = app_config('default_password') ?: 'tntt@2026';
$COOKIE = sys_get_temp_dir() . '/tntt_cookies_' . getmypid() . '.txt';

function req(string $url, ?array $json = null, string $cookie = ''): array {
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($json !== null) $headers[] = 'Content-Type: application/json';
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($raw, 0, $hsize), 'body' => substr($raw, $hsize)];
}

// ---------- 0. Trạng thái DB ----------
$pdo = db();
echo "DB: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
echo "modules: ";
echo implode(', ', $pdo->query('SELECT module_key FROM modules ORDER BY module_key')->fetchAll(PDO::FETCH_COLUMN)) . "\n";
echo "quyền module 'staff':\n";
foreach ($pdo->query("SELECT role_code, level FROM permissions WHERE module_key='staff' ORDER BY role_code")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "   {$r['role_code']} = {$r['level']}\n";
}

// ---------- 1. Tạo hồ sơ chờ duyệt ----------
$pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE_PENDING]);
$max = db_one("SELECT COALESCE(MAX(CAST(SUBSTRING(code,4) AS UNSIGNED)),0) n FROM members WHERE code LIKE 'GLV%'");
$code = 'GLV' . str_pad((string) ($max['n'] + 1), 3, '0', STR_PAD_LEFT);
$classId = $pdo->query("SELECT id FROM classes LIMIT 1")->fetchColumn();
db_insert('INSERT INTO members (code, holy_name, full_name, phone, birth_date, password_hash,
                                role_code, status, must_change_pw, register_note, registered_at)
           VALUES (?,?,?,?,?,?,?,?,0,?,NOW())',
    [$code, 'Test', 'Repro Duyệt', $PHONE_PENDING, '1995-01-01', password_hash('x', PASSWORD_DEFAULT),
     'glv', 'chờ duyệt', 'repro']);
$pendingId = (int) db()->lastInsertId();
echo "\nhồ sơ chờ duyệt: id=$pendingId code=$code\n";

// ---------- 2. Đăng nhập admin ----------
$login = req($BASE . 'api/auth.php?action=login', ['phone' => $ADMIN_PHONE, 'password' => $ADMIN_PASS], $COOKIE);
echo "\n[1] login admin ($ADMIN_PHONE) -> HTTP {$login['status']}\n";
echo "    body: " . substr(trim($login['body']), 0, 200) . "\n";

// Lấy trang chủ để có CSRF token
$home = req($BASE, null, $COOKIE);
preg_match('/csrfToken["\']?\s*[:=]\s*["\']([^"\']+)/', $home['body'], $m);
$csrf = $m[1] ?? '';
echo "[2] GET trang chủ -> HTTP {$home['status']}, csrf=" . ($csrf ? substr($csrf, 0, 12) . '…' : '(KHÔNG THẤY)') . "\n";

// ---------- 3. Gọi approveMember ----------
$payload = ['id' => $pendingId, 'role' => 'glv', 'className' => '', 'block' => ''];
$ch = curl_init($BASE . 'api/org.php?action=approveMember');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => array_filter(['Content-Type: application/json', $csrf ? "X-CSRF-TOKEN: $csrf" : null]),
    CURLOPT_COOKIEJAR => $COOKIE, CURLOPT_COOKIEFILE => $COOKIE,
]);
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

echo "\n[3] POST approveMember -> HTTP $status\n";
echo "    content-type: " . ($ctype ?: '(none)') . "\n";
echo "    body (400 ký tự đầu):\n";
echo "    " . str_replace("\n", "\n    ", substr($body, 0, 400)) . "\n";

$isJson = json_decode($body, true) !== null;
echo "\n    body là JSON? " . ($isJson ? 'CÓ' : 'KHÔNG  <-- trình duyệt sẽ báo "Mất kết nối"') . "\n";

// ---------- 4. Kiểm tra kết quả ----------
$after = db_one('SELECT status, role_code FROM members WHERE id=?', [$pendingId]);
echo "\n[4] trạng thái sau khi gọi: " . ($after ? "{$after['status']} / {$after['role_code']}" : '(không còn)') . "\n";

// ---------- Dọn ----------
$pdo->prepare('DELETE FROM members WHERE phone = ?')->execute([$PHONE_PENDING]);
@unlink($COOKIE);
echo "\nđã dọn hồ sơ test.\n";
