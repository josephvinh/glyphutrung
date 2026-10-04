<?php
/**
 * Migration endpoint: Sửa lỗi credential_id bị lưu sai format.
 *
 * NGUYÊN NHÂN: base64_encode(ByteBuffer) gọi __toString() trả về HEX,
 * không phải raw binary. Database lưu base64(hex(credential)) thay vì base64(credential).
 *
 * TRUY CẬP: http://your-domain/api/migrate-passkeys.php
 * CHỈ ADMIN: Cần đăng nhập với tài khoản admin trước
 *
 * SAFETY:
 * - Dùng transaction để đảm bảo atomicity
 * - Không xóa record mà chuyển sang bảng backup
 * - Idempotent: chạy lại nhiều lần an toàn
 */

require __DIR__ . '/../public/api/_bootstrap.php';
require_login();
require_csrf(); // Extra safety for data modification

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
            .result { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; white-space: pre-wrap; font-family: monospace; font-size: 13px; max-height: 400px; overflow-y: auto; }
            .success { color: #16a34a; }
            .error { color: #dc2626; }
            .warning { background: #fef3c7; border: 1px solid #f59e0b; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .already-done { background: #f0fdf4; border: 1px solid #16a34a; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .summary { background: #fafafa; border: 1px solid #e5e5e5; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .summary strong { color: #c8203a; }
        </style>
    </head>
    <body>
        <h1>🔧 Fix Passkey Credentials</h1>

        <div class="warning">
            ⚠️ <strong>Backup:</strong> Dữ liệu không hợp lệ sẽ được chuyển sang bảng backup <code>member_passkeys_invalid</code>, không bị xóa.
        </div>

        <div class="info">
            <strong>Nguyên nhân:</strong> Credential ID bị lưu dạng hex thay vì binary.
            <br>Script này sẽ convert hex → binary cho các passkey bị ảnh hưởng.
        </div>

        <button class="btn" id="runBtn" onclick="runMigration()">🚀 Chạy Migration</button>

        <div id="result" class="result" style="display:none"></div>

        <div class="summary" id="summary" style="display:none">
            <h3>📊 Tóm tắt</h3>
            <div>Tổng passkeys: <strong id="total">-</strong></div>
            <div>Đã OK: <strong id="okCount">-</strong></div>
            <div>Đã fix (hex→binary): <strong id="fixedCount">-</strong></div>
            <div>Chuyển sang backup: <strong id="invalidCount">-</strong></div>
        </div>

        <script>
            async function runMigration() {
                const btn = document.getElementById('runBtn');
                btn.disabled = true;
                btn.textContent = '⏳ Đang chạy...';

                const resultDiv = document.getElementById('result');
                resultDiv.style.display = 'block';
                resultDiv.textContent = 'Đang xử lý...';
                resultDiv.style.color = '#666';

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
                        btn.style.display = 'none';

                        // Show summary
                        document.getElementById('summary').style.display = 'block';
                        document.getElementById('total').textContent = data.total || 0;
                        document.getElementById('okCount').textContent = data.ok_count || 0;
                        document.getElementById('fixedCount').textContent = data.fixed_count || 0;
                        document.getElementById('invalidCount').textContent = data.invalid_count || 0;
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
    'invalid_count' => 0,
    'invalid_records' => [],
    'details' => []
];

try {
    $db = db();

    // Tạo bảng backup nếu chưa có
    $db->exec("
        CREATE TABLE IF NOT EXISTS member_passkeys_invalid (
            id INT PRIMARY KEY,
            member_id INT NOT NULL,
            credential_id TEXT NOT NULL,
            public_key TEXT NOT NULL,
            user_handle VARCHAR(255) NOT NULL,
            sign_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used_at DATETIME NULL,
            reason VARCHAR(255),
            migrated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_member (member_id),
            INDEX idx_migrated (migrated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $db->beginTransaction();

    try {
        $all = db_all("SELECT * FROM member_passkeys");
        $result['total'] = count($all);

        if (empty($all)) {
            $result['message'] = 'Không có passkey nào để fix.';
            $db->commit();
            output_json($result);
        }

        $hexPattern = '/^[0-9a-fA-F]+$/';
        $validPattern = '/^[A-Za-z0-9+\/]+=*$/';

        foreach ($all as $pk) {
            $credId = $pk['credential_id'];

            // Case 1: Hex format → convert to binary
            if (preg_match($hexPattern, $credId) && strlen($credId) % 2 == 0) {
                $binary = hex2bin($credId);
                if ($binary !== false) {
                    $correct = base64_encode($binary);
                    if (preg_match($validPattern, $correct)) {
                        // Idempotent: chỉ update nếu thực sự khác
                        if ($credId !== $correct) {
                            db_run("UPDATE member_passkeys SET credential_id = ? WHERE id = ?",
                                [$correct, $pk['id']]);
                            $result['fixed_count']++;
                            $result['details'][] = "Fixed ID={$pk['id']} (hex→binary)";
                        } else {
                            $result['ok_count']++;
                        }
                        continue;
                    }
                }
            }

            // Case 2: Valid base64 format → OK
            if (preg_match($validPattern, $credId)) {
                $result['ok_count']++;
                continue;
            }

            // Case 3: Invalid format → move to backup table
            $stmt = $db->prepare("
                INSERT INTO member_passkeys_invalid
                    (id, member_id, credential_id, public_key, user_handle, sign_count, created_at, last_used_at, reason)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason), migrated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                $pk['id'],
                $pk['member_id'],
                $pk['credential_id'],
                $pk['public_key'],
                $pk['user_handle'],
                $pk['sign_count'],
                $pk['created_at'],
                $pk['last_used_at'],
                'invalid_format'
            ]);

            // Xóa khỏi bảng chính
            db_run("DELETE FROM member_passkeys WHERE id = ?", [$pk['id']]);

            $result['invalid_count']++;
            $result['invalid_records'][] = [
                'id' => $pk['id'],
                'member_id' => $pk['member_id']
            ];
            $result['details'][] = "Moved ID={$pk['id']} to backup (invalid format)";
        }

        // Đếm còn lại
        $remaining = db_one("SELECT COUNT(*) as cnt FROM member_passkeys");
        $result['remaining'] = $remaining['cnt'];

        $result['message'] = "Đã xử lý {$result['total']} passkeys: {$result['ok_count']} OK, {$result['fixed_count']} đã fix, {$result['invalid_count']} chuyển sang backup.";

        // Log
        log_action('migrate', 'system', $result['message']);

        $db->commit();

    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

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
