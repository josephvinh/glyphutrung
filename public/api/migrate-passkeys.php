<?php
/**
 * Migration endpoint: Sửa lỗi credential_id bị lưu sai format.
 *
 * NGUYÊN NHÂN: base64_encode(ByteBuffer) gọi __toString() trả về HEX,
 * không phải raw binary. Database lưu base64(hex(credential)) thay vì base64(credential).
 *
 * TRUY CẬP: http://your-domain/api/migrate-passkeys.php
 * CHỈ ADMIN: Cần đăng nhập với tài khoản admin trước
 */

require __DIR__ . '/../public/api/_bootstrap.php';
require_login();

$role = $me['role_code'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => 'Chỉ Quản trị viên mới được truy cập.',
        'hint' => 'Đăng nhập với tài khoản admin trước, sau đó mở lại trang này.'
    ]);
    exit;
}

// Chỉ chấp nhận POST để tránh accidental trigger
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <title>Migration: Fix Passkey Credentials</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
            h1 { color: #c8203a; }
            .info { background: #f0f9ff; border: 1px solid #0ea5e9; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { background: #c8203a; color: white; border: none; padding: 15px 30px; font-size: 16px; border-radius: 8px; cursor: pointer; }
            .btn:hover { background: #a01830; }
            .btn:disabled { background: #ccc; cursor: not-allowed; }
            .result { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; white-space: pre-wrap; font-family: monospace; }
            .success { color: #16a34a; }
            .error { color: #dc2626; }
            .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <h1>🔧 Fix Passkey Credentials</h1>

        <div class="warning">
            ⚠️ <strong>Backup khuyến nghị:</strong> Nên backup database trước khi chạy.
        </div>

        <div class="info">
            <strong>Nguyên nhân:</strong> Credential ID bị lưu dạng hex thay vì binary.
            <br>Script này sẽ convert hex → binary cho các passkey bị ảnh hưởng.
        </div>

        <button class="btn" onclick="runMigration()">🚀 Chạy Migration</button>

        <div id="result" class="result" style="display:none"></div>

        <script>
            async function runMigration() {
                const btn = document.querySelector('.btn');
                btn.disabled = true;
                btn.textContent = '⏳ Đang chạy...';

                const resultDiv = document.getElementById('result');
                resultDiv.style.display = 'block';
                resultDiv.textContent = 'Đang xử lý...';

                try {
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' }
                    });
                    const data = await res.json();

                    resultDiv.textContent = JSON.stringify(data, null, 2);

                    if (data.ok) {
                        resultDiv.style.color = '#16a34a';
                        btn.textContent = '✅ Hoàn thành!';
                    } else {
                        resultDiv.style.color = '#dc2626';
                        btn.textContent = '❌ Lỗi';
                        btn.disabled = false;
                    }
                } catch (e) {
                    resultDiv.textContent = 'Lỗi: ' + e.message;
                    resultDiv.style.color = '#dc2626';
                    btn.textContent = 'Thử lại';
                    btn.disabled = false;
                }
            }
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ========== CHẠY MIGRATION ==========

$result = [
    'ok' => true,
    'total' => 0,
    'ok_count' => 0,
    'fixed_count' => 0,
    'deleted_count' => 0,
    'remaining' => 0,
    'details' => []
];

try {
    $all = db_all("SELECT * FROM member_passkeys");
    $result['total'] = count($all);

    if (empty($all)) {
        $result['message'] = 'Không có passkey nào để fix.';
        output_json($result);
    }

    $hexPattern = '/^[0-9a-fA-F]+$/';
    $validPattern = '/^[A-Za-z0-9+\/]+=*$/';

    foreach ($all as $pk) {
        $credId = $pk['credential_id'];

        // Kiểm tra xem có phải hex không
        if (preg_match($hexPattern, $credId) && strlen($credId) % 2 == 0) {
            $binary = hex2bin($credId);
            if ($binary !== false) {
                $correct = base64_encode($binary);
                if (preg_match($validPattern, $correct)) {
                    db_run("UPDATE member_passkeys SET credential_id = ? WHERE id = ?",
                            [$correct, $pk['id']]);
                    $result['fixed_count']++;
                    $result['details'][] = "✅ Fixed ID={$pk['id']}, member_id={$pk['member_id']} (hex→binary)";
                    continue;
                }
            }
        }

        // Kiểm tra format base64 thường - đã OK
        if (preg_match($validPattern, $credId)) {
            $result['ok_count']++;
            continue;
        }

        // Format lạ - xóa
        db_run("DELETE FROM member_passkeys WHERE id = ?", [$pk['id']]);
        $result['deleted_count']++;
        $result['details'][] = "⚠️ Deleted ID={$pk['id']} (invalid format)";
    }

    // Đếm còn lại
    $remaining = db_one("SELECT COUNT(*) as cnt FROM member_passkeys");
    $result['remaining'] = $remaining['cnt'];

    $result['message'] = "Đã xử lý {$result['total']} passkeys: {$result['ok_count']} OK, {$result['fixed_count']} đã fix, {$result['deleted_count']} đã xóa.";

    // Log
    log_action('migrate', 'system', $result['message']);

} catch (Throwable $e) {
    $result['ok'] = false;
    $result['error'] = $e->getMessage();
}

function output_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

output_json($result);
