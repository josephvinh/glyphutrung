<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/webauthn/WebAuthn.php';

// rpId PHẢI là tên miền THUẦN, không kèm cổng/scheme (chuẩn WebAuthn).
// $_SERVER['HTTP_HOST'] có thể kèm cổng (vd 'localhost:8888') -> tách bỏ cổng,
// nếu không trình duyệt từ chối với SecurityError "rpId not a registrable domain".
$rpId = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$WebAuthn = new \lbuchs\WebAuthn\WebAuthn('TNTT Super App', $rpId);

$action = $_GET['action'] ?? '';
$in = json_input();

function passkey_member_payload(array $m): array {
    return [
        'id'            => (int) $m['id'],
        'code'          => $m['code'],
        'holyName'      => $m['holy_name'],
        'fullName'      => $m['full_name'],
        'phone'         => $m['phone'],
        'role'          => $m['role_code'],
        'roleLabel'     => $m['role_label'],
        'roleLevel'     => (int) $m['role_level'],
        'roleScope'     => $m['role_scope'],
        'roleTitle'     => $m['title_label'] ?? $m['role_label'],
        'birthDate'     => $m['birth_date'] ?? '',
        'managedBlock'  => $m['block_name'] ?? '',
        'assignedClass' => $m['class_name'] ?? '',
        'mustChangePw'  => (bool) $m['must_change_pw'],
    ];
}

switch ($action) {
    case 'getRegisterArgs':
        $me = require_login();
        $createArgs = $WebAuthn->getCreateArgs((string)$me['id'], $me['phone'], $me['full_name'], 60*4, true);
        // Lưu challenge dạng CHUỖI nhị phân, KHÔNG lưu object ByteBuffer.
        // session_start() (trong _common.php) chạy trước khi class ByteBuffer
        // được nạp, nên nếu lưu object thì request sau bung ra thành
        // __PHP_Incomplete_Class -> "could not be converted to string".
        // processCreate/processGet nhận chuỗi nhị phân và tự bọc lại ByteBuffer.
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge()->getBinaryString();
        json_out(['ok' => true, 'args' => json_decode(json_encode($createArgs), true)]);
        break;

    case 'processRegister':
        $me = require_login();
        require_post();
        require_csrf();
        
        $clientDataJSON = base64_decode($in['clientDataJSON']);
        $attestationObject = base64_decode($in['attestationObject']);
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        try {
            // Arg 4 is requireUserVerification. Set to false to match getCreateArgs (which defaults to false).
            $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, false, true, false);
            
            $credentialId = base64_encode($data->credentialId);
            $publicKey = $data->credentialPublicKey;
            $userHandle = (string)$me['id'];

            db_run("INSERT INTO member_passkeys (member_id, credential_id, public_key, user_handle) VALUES (?, ?, ?, ?)", 
                [$me['id'], $credentialId, $publicKey, $userHandle]);
            
            json_out(['ok' => true]);
        } catch (\Throwable $ex) {
            json_fail('Lỗi đăng ký vân tay: ' . $ex->getMessage());
        }
        break;

    // Cho nút gạt ở Hồ sơ biết tài khoản này đã bật sinh trắc chưa.
    case 'status':
        $me = require_login();
        $n = (int) (db_one('SELECT COUNT(*) n FROM member_passkeys WHERE member_id = ?', [$me['id']])['n'] ?? 0);
        json_out(['ok' => true, 'hasPasskey' => $n > 0]);
        break;

    // Gạt TẮT: gỡ mọi khoá sinh trắc của tài khoản này khỏi thiết bị-hệ thống.
    case 'delete':
        $me = require_login();
        require_post();
        require_csrf();
        db_run('DELETE FROM member_passkeys WHERE member_id = ?', [$me['id']]);
        json_out(['ok' => true]);
        break;

    case 'getLoginArgs':
        $getArgs = $WebAuthn->getGetArgs([], 60*4, true, true, true, true);
        // Lưu challenge dạng CHUỖI nhị phân, KHÔNG lưu object ByteBuffer.
        // session_start() (trong _common.php) chạy trước khi class ByteBuffer
        // được nạp, nên nếu lưu object thì request sau bung ra thành
        // __PHP_Incomplete_Class -> "could not be converted to string".
        // processCreate/processGet nhận chuỗi nhị phân và tự bọc lại ByteBuffer.
        $_SESSION['webauthn_challenge'] = $WebAuthn->getChallenge()->getBinaryString();
        json_out(['ok' => true, 'args' => json_decode(json_encode($getArgs), true)]);
        break;

    case 'processLogin':
        require_post();
        $clientDataJSON = base64_decode($in['clientDataJSON']);
        $authenticatorData = base64_decode($in['authenticatorData']);
        $signature = base64_decode($in['signature']);
        $credentialIdBase64 = $in['id']; 
        // JS often sends raw base64url. We should decode and encode to standard base64 for DB
        $credentialId = base64_encode(WebAuthn_Base64UrlDecode($credentialIdBase64));
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        $passkey = db_one("SELECT * FROM member_passkeys WHERE credential_id = ?", [$credentialId]);
        if (!$passkey) {
            json_fail('Không tìm thấy dữ liệu sinh trắc học này trên hệ thống.');
        }

        try {
            $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $passkey['public_key'], $challenge, null, false);
            
            db_run("UPDATE member_passkeys SET sign_count = sign_count + 1, last_used_at = NOW() WHERE id = ?", [$passkey['id']]);
            
            $m = db_one(
                'SELECT m.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                        t.label AS title_label, b.name AS block_name, c.name AS class_name
                   FROM members m
                   JOIN roles r ON r.code = m.role_code
                   LEFT JOIN titles t ON t.id = m.title_id
                   LEFT JOIN blocks b ON b.id = m.block_id
                   LEFT JOIN classes c ON c.id = m.class_id
                  WHERE m.id = ?',
                [$passkey['member_id']]
            );

            if (!$m || $m['status'] === 'đã nghỉ') {
                json_fail('Tài khoản bị khóa hoặc không tồn tại.');
            }

            login_ok($m['phone']);
            session_regenerate_id(true);
            $_SESSION['member_id'] = (int) $m['id'];

            db_run('UPDATE members SET last_login_at = NOW() WHERE id = ?', [$m['id']]);
            log_action('tao', 'auth', 'Đăng nhập bằng Sinh trắc học', $m['phone']);

            json_out(['ok' => true, 'user' => passkey_member_payload($m)]);

        } catch (\Throwable $ex) {
            json_fail('Lỗi xác thực vân tay: ' . $ex->getMessage());
        }
        break;
}

function WebAuthn_Base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
