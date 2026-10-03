<?php
require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/webauthn/WebAuthn.php';

// ============================================================
// CONSTANTS — cấu hình Passkey
// ============================================================
const PASSKEY_TIMEOUT_SECONDS = 240;       // 4 phút timeout
const PASSKEY_THROTTLE_WINDOW = 900;       // 15 phút window
const PASSKEY_MAX_ATTEMPTS_TRACKING = 10; // tối đa / tracking ID
const PASSKEY_MAX_ATTEMPTS_IP = 30;       // tối đa / IP
const PASSKEY_MAX_SIGN_COUNT_JUMP = 10;   // ngưỡng clone detection

// rpId PHẢI là tên miền THUẦN, không kèm cổng/scheme (chuẩn WebAuthn).
// $_SERVER['HTTP_HOST'] có thể kèm cổng (vd 'localhost:8888') -> tách bỏ cổng,
// nếu không trình duyệt từ chối với SecurityError "rpId not a registrable domain".
$rpId = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
$WebAuthn = new \lbuchs\WebAuthn\WebAuthn('TNTT Super App', $rpId);

$action = $_GET['action'] ?? '';

// ============================================================
// HTTPS ENFORCEMENT — yêu cầu secure context trên production
// ============================================================
$isLocalhost = in_array($rpId, ['localhost', '127.0.0.1', '::1'], true);
if (!$isLocalhost && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
    && (!isset($_SERVER['HTTP_X_FORWARDED_PROTO']) || $_SERVER['HTTP_X_FORWARDED_PROTO'] !== 'https')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Yêu cầu kết nối bảo mật HTTPS']);
    exit;
}

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
        $createArgs = $WebAuthn->getCreateArgs(
            (string)$me['id'],
            $me['phone'],
            $me['full_name'],
            PASSKEY_TIMEOUT_SECONDS,
            true
        );
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
        require_write();
        
        $clientDataJSON = base64_decode($in['clientDataJSON']);
        $attestationObject = base64_decode($in['attestationObject']);
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        try {
            // Arg 4 is requireUserVerification. Set to false to match getCreateArgs (which defaults to false).
            $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, false, true, false);

            $credentialId = base64_encode($data->credentialId);
            $publicKey = $data->credentialPublicKey;
            $userHandle = (string)$me['id'];

            // Log nếu không có attestation verification (security warning)
            if (!$data->rootValid && $data->attestationFormat !== 'none') {
                log_action('warn', 'security', 'Passkey registered without full attestation verification', $me['phone']);
            }

            db_run("INSERT INTO member_passkeys (member_id, credential_id, public_key, user_handle) VALUES (?, ?, ?, ?)",
                [$me['id'], $credentialId, $publicKey, $userHandle]);

            json_out(['ok' => true]);
        } catch (\Throwable $ex) {
            // Handle duplicate credential
            if (strpos($ex->getMessage(), 'Duplicate entry') !== false
                || strpos($ex->getMessage(), 'UNIQUE constraint failed') !== false) {
                json_fail('Thiết bị này đã được đăng ký trước đó.');
            }
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
        require_write();
        db_run('DELETE FROM member_passkeys WHERE member_id = ?', [$me['id']]);
        json_out(['ok' => true]);
        break;

    case 'getLoginArgs':
        // Empty credentialIds = browser tự động show tất cả passkeys đã đăng ký trên device
        // Đây là behavior đúng vì user có thể có nhiều passkeys
        $getArgs = $WebAuthn->getGetArgs(
            [],  // empty = browser show all registered passkeys
            PASSKEY_TIMEOUT_SECONDS,
            true, true, true, true, true
        );
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
        $credentialIdBase64 = $in['id'] ?? '';

        // JS often sends raw base64url. We should decode and encode to standard base64 for DB
        $credentialId = base64_encode(WebAuthn_Base64UrlDecode($credentialIdBase64));
        $challenge = $_SESSION['webauthn_challenge'] ?? '';

        // Query passkey TRƯỚC để lấy member_id, sau đó mới throttle
        $passkey = db_one("SELECT * FROM member_passkeys WHERE credential_id = ?", [$credentialId]);

        // Rate limiting: dùng member_id nếu có, không thì dùng IP để throttle
        // Tránh null access bằng cách check $passkey trước
        $memberIdForThrottle = $passkey ? $passkey['member_id'] : 0;
        passkey_throttle($memberIdForThrottle, $credentialIdBase64, client_ip());

        if (!$passkey) {
            passkey_failed($memberIdForThrottle, $credentialIdBase64, client_ip());
            json_fail('Không tìm thấy dữ liệu sinh trắc học này trên hệ thống.');
        }

        try {
            // Verify userHandle nếu được gửi từ client
            $userHandleFromClient = isset($in['userHandle']) ? base64_encode(WebAuthn_Base64UrlDecode($in['userHandle'])) : null;
            if ($userHandleFromClient) {
                $expectedUserHandle = base64_encode((string)$passkey['member_id']);
                if ($userHandleFromClient !== $expectedUserHandle) {
                    passkey_failed($passkey['member_id'], $credentialIdBase64, client_ip());
                    json_fail('User handle không khớp với credential.');
                }
            }

            // Sử dụng sign_count hiện tại để detect cloning
            $prevSignCount = (int) $passkey['sign_count'];
            $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $passkey['public_key'], $challenge, $prevSignCount, false);

            // Clone detection: nếu sign count tăng đột ngột, cảnh báo
            $newSignCount = $WebAuthn->getSignatureCounter();
            if ($newSignCount !== null && ($newSignCount - $prevSignCount) > PASSKEY_MAX_SIGN_COUNT_JUMP) {
                log_action('warn', 'security', 'Possible passkey clone detected - unusual sign count jump', $passkey['member_id']);
            }

            db_run("UPDATE member_passkeys SET sign_count = ?, last_used_at = NOW() WHERE id = ?",
                [$newSignCount ?? ($prevSignCount + 1), $passkey['id']]);

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

            // Check status TRƯỚC khi gọi login_ok()
            if (!$m || $m['status'] === 'đã nghỉ') {
                passkey_failed($passkey['member_id'], $credentialIdBase64, client_ip());
                json_fail('Tài khoản bị khóa hoặc không tồn tại.');
            }

            // Đang buộc đổi mật khẩu (#83): KHÔNG mở phiên — màn đổi mật khẩu cần
            // mật khẩu hiện tại. Không tính là lần thử sai.
            if (!empty($m['must_change_pw'])) {
                json_out(['ok' => false, 'code' => 'must_change_pw',
                          'error' => 'Tài khoản cần đổi mật khẩu. Vui lòng đăng nhập bằng số điện thoại và mật khẩu (tạm) để đổi.'], 403);
            }

            login_ok($m['phone']);
            session_regenerate_id(true);
            $_SESSION['member_id'] = (int) $m['id'];

            db_run('UPDATE members SET last_login_at = NOW() WHERE id = ?', [$m['id']]);
            log_action('tao', 'auth', 'Đăng nhập bằng Sinh trắc học', $m['phone']);

            json_out(['ok' => true, 'user' => passkey_member_payload($m)]);

        } catch (\Throwable $ex) {
            passkey_failed($passkey['member_id'], $credentialIdBase64, client_ip());
            json_fail('Lỗi xác thực vân tay: ' . $ex->getMessage());
        }
        break;
}

/**
 * Chống brute-force passkey.
 * Giới hạn theo member_id và IP.
 *
 * @param int $memberId ID của thành viên (0 nếu chưa xác định)
 * @param string $credentialIdBase64 Credential ID để throttle nếu memberId = 0
 * @param string|null $ip IP của client (để tránh gọi nhiều lần)
 */
function passkey_throttle(int $memberId, string $credentialIdBase64 = '', ?string $ip = null): void
{
    $ip = $ip ?? client_ip();
    $moc = date('Y-m-d H:i:s', time() - PASSKEY_THROTTLE_WINDOW);

    // Tracking ID: dùng member_id nếu đã xác định, hash credential nếu chưa
    // KHÔNG dùng random token - phá vỡ rate limiting
    if ($memberId > 0) {
        $trackingId = 'pk:' . $memberId;
    } else {
        // Dùng hash của credential + IP để tránh cross-user collision
        $trackingId = 'pk:' . substr(hash('sha256', ($credentialIdBase64 ?: '') . $ip), 0, 16);
    }

    // Throttle theo tracking ID (member_id hoặc credential+IP hash)
    $theoTracking = (int) db_one(
        'SELECT COUNT(*) n FROM login_attempts
         WHERE phone = ? AND ip = ? AND tried_at > ?',
        [$trackingId, $ip, $moc]
    )['n'];

    // Throttle theo IP (chặn tất cả prefix 'pk:')
    $theoIp = (int) db_one(
        'SELECT COUNT(*) n FROM login_attempts
         WHERE phone LIKE ? AND ip = ? AND tried_at > ?',
        ['pk:%', $ip, $moc]
    )['n'];

    // Giới hạn: PASSKEY_MAX_ATTEMPTS_TRACKING / tracking_id, PASSKEY_MAX_ATTEMPTS_IP / IP trong PASSKEY_THROTTLE_WINDOW
    if ($theoTracking >= PASSKEY_MAX_ATTEMPTS_TRACKING || $theoIp >= PASSKEY_MAX_ATTEMPTS_IP) {
        json_fail('Bạn đã thử quá nhiều lần. Vui lòng đợi ' . (PASSKEY_THROTTLE_WINDOW / 60) . ' phút rồi thử lại.', 429);
    }
}

/**
 * Ghi một lần thử passkey thất bại.
 *
 * @param int $memberId ID của thành viên (0 nếu chưa xác định)
 * @param string $credentialIdBase64 Credential ID để track nếu memberId = 0
 * @param string|null $ip IP của client
 */
function passkey_failed(int $memberId, string $credentialIdBase64 = '', ?string $ip = null): void
{
    $ip = $ip ?? client_ip();

    // Lưu với prefix 'pk:' để phân biệt với login thường
    // Dùng hash credential + IP để tránh cross-user collision
    if ($memberId > 0) {
        $trackingId = 'pk:' . $memberId;
    } else {
        $trackingId = 'pk:' . substr(hash('sha256', ($credentialIdBase64 ?: '') . $ip), 0, 16);
    }

    db_run('INSERT INTO login_attempts (phone, ip, tried_at) VALUES (?,?,NOW())',
           [$trackingId, $ip]);

    // Dọn rác - sử dụng constant thay vì magic number
    db_run('DELETE FROM login_attempts WHERE tried_at < ?',
           [date('Y-m-d H:i:s', time() - PASSKEY_THROTTLE_WINDOW)]);
}

function WebAuthn_Base64UrlDecode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}
