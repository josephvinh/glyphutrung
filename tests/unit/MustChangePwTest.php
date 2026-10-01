<?php
// tests/unit/MustChangePwTest.php
//
// CỔNG `must_change_pw` Ở MÁY CHỦ (P1 / #83) — đặc tả: docs/audit/P1_design.md mục 3.2, 5.4.
//
// Trước #83 cờ này chỉ chặn ở giao diện: ai gọi thẳng API vẫn dùng được tài khoản còn cờ.
// Test gọi HTTP thật (`php -S`, phiên + CSRF thật) để chứng minh:
//  - tài khoản còn cờ, ĐÃ đăng nhập, bị 403 {code:"must_change_pw"} ở đọc (data.php, notes.php,
//    logs.php, passkey.php?status) và ở ghi (attendance, students, org.php, notes, auth.php?profile);
//    dùng cả quản trị (đủ mọi quyền) để thấy chính cờ mới là lý do chặn, không phải thiếu quyền;
//  - auth.php?action=password (có CSRF) là cửa DUY NHẤT mở; đổi xong thì data.php trả 200;
//    thiếu CSRF / sai mật khẩu hiện tại thì không đổi được;
//  - auth.php?action=me và logout vẫn chạy khi còn cờ;
//  - tài khoản must_change_pw=0 không bị chặn (đối chứng);
//  - cờ bật GIỮA phiên (admin cấp lại mật khẩu) chặn ngay request kế tiếp;
//  - Passkey từ chối tài khoản còn cờ và không tạo phiên (AUTH-09); đối chứng: cùng khoá
//    Passkey khi cờ = 0 đăng nhập được, nên việc từ chối là do cờ chứ không do chữ ký hỏng.
//
// Dữ liệu tạo trong từng test, dọn trong tearDown; không phụ thuộc thứ tự chạy.

require_once __DIR__ . '/P1ApiHarness.php';

use PHPUnit\Framework\TestCase;

class MustChangePwTest extends TestCase
{
    use P1ApiHarness;

    private int $classId = 0;
    /** @var int[] */
    private array $passkeyMemberIds = [];

    public static function setUpBeforeClass(): void
    {
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    protected function setUp(): void
    {
        $c = db_one('SELECT id FROM classes ORDER BY id LIMIT 1');
        self::assertNotNull($c, 'Cần ít nhất một lớp.');
        $this->classId = (int) $c['id'];
        // Quyền tường minh: admin sửa được mọi thứ → 403 chỉ có thể do cờ must_change_pw.
        foreach (['attendance', 'students', 'staff', 'notes'] as $mod) $this->setPerm($mod, 'admin', 'edit');
    }

    protected function tearDown(): void
    {
        foreach ($this->passkeyMemberIds as $id) {
            db_run('DELETE FROM member_passkeys WHERE member_id = ?', [$id]);
        }
        $this->passkeyMemberIds = [];
        $this->cleanupHarness();
    }

    // ------------------------------------------------------------------

    private function admin(int $must): array
    {
        return $this->makeMember('admin', [['admin', null, null]], ['must_change_pw' => $must]);
    }

    private function glv(int $must = 0): array
    {
        return $this->makeMember('glv', [['glv', null, $this->classId]], ['class_id' => $this->classId, 'must_change_pw' => $must]);
    }

    private function mustFlag(int $id): int
    {
        return (int) db_one('SELECT must_change_pw FROM members WHERE id = ?', [$id])['must_change_pw'];
    }

    private function assertBlocked(array $r, string $what, string $error = 'Bạn cần đổi mật khẩu trước khi tiếp tục sử dụng.'): void
    {
        self::assertSame(403, $r['code'], "$what: phải 403 — " . $r['raw']);
        self::assertIsArray($r['json'], "$what: thân phải là JSON");
        self::assertSame(false, $r['json']['ok'] ?? null, $what);
        self::assertSame('must_change_pw', $r['json']['code'] ?? null, "$what: phải có code=must_change_pw — " . $r['raw']);
        self::assertSame($error, $r['json']['error'] ?? null, $what);
    }

    private function assertNotBlockedByFlag(array $r, string $what): void
    {
        self::assertNotSame('must_change_pw', $r['json']['code'] ?? null, "$what: tài khoản must=0 không được bị chặn — " . $r['raw']);
    }

    // ------------------------------------------------------------------
    //  Chặn ở đọc
    // ------------------------------------------------------------------

    public function test_flagged_account_is_blocked_on_data_and_other_read_endpoints(): void
    {
        $u = $this->admin(1);
        $this->loginAs($u);   // đăng nhập vẫn được (login không qua require_login)
        $id = (int) $u['id'];

        foreach (['all', 'core', 'heavy'] as $part) {
            $this->assertBlocked($this->http($id, 'GET', "/api/data.php?part=$part"), "data.php?part=$part");
        }
        $this->assertBlocked($this->http($id, 'GET', '/api/notes.php?action=list'), 'notes.php?action=list');
        $this->assertBlocked($this->http($id, 'GET', '/api/logs.php'), 'logs.php');
        $this->assertBlocked($this->http($id, 'GET', '/api/passkey.php?action=status'), 'passkey.php?action=status');
        $this->assertBlocked($this->http($id, 'GET', '/api/passkey.php?action=getRegisterArgs'), 'passkey.php?action=getRegisterArgs');
    }

    // ------------------------------------------------------------------
    //  Chặn ở ghi (qua require_permission / require_login)
    // ------------------------------------------------------------------

    public function test_flagged_account_is_blocked_on_write_endpoints_even_with_full_permissions(): void
    {
        $u = $this->admin(1);
        $this->loginAs($u);
        $id = (int) $u['id'];
        $target = $this->glv();
        $before = db_one('SELECT full_name, phone FROM members WHERE id = ?', [$target['id']]);

        $calls = [
            'attendance.php?action=toggle' => ['/api/attendance.php?action=toggle', ['programId' => 1, 'studentId' => 1, 'date' => date('Y-m-d')]],
            'students.php?action=save'     => ['/api/students.php?action=save', ['fullName' => 'Khong Duoc Tao', 'classId' => $this->classId]],
            'org.php?action=saveMember'    => ['/api/org.php?action=saveMember', ['id' => (int) $target['id'], 'holyName' => 'X', 'fullName' => 'Bi Doi Ten', 'phone' => '0900000000', 'role' => 'glv']],
            'org.php?action=resetPassword' => ['/api/org.php?action=resetPassword', ['id' => (int) $target['id']]],
            'notes.php?action=save'        => ['/api/notes.php?action=save', ['title' => 'x', 'remindAt' => date('Y-m-d H:i')]],
            'auth.php?action=profile'      => ['/api/auth.php?action=profile', ['fullName' => 'Doi Ten']],
        ];
        $studentsBefore = (int) db_one('SELECT COUNT(*) n FROM students')['n'];
        foreach ($calls as $name => [$path, $body]) {
            $this->assertBlocked($this->http($id, 'POST', $path, $body), $name);
        }
        self::assertSame($before, db_one('SELECT full_name, phone FROM members WHERE id = ?', [$target['id']]), 'saveMember không được ghi');
        self::assertSame($studentsBefore, (int) db_one('SELECT COUNT(*) n FROM students')['n'], 'students.php save không được ghi');
        self::assertSame(1, $this->mustFlag($id));
    }

    public function test_unflagged_account_is_not_blocked(): void
    {
        $u = $this->admin(0);
        $this->loginAs($u);
        $id = (int) $u['id'];
        $target = $this->glv();

        $d = $this->http($id, 'GET', '/api/data.php?part=all');
        self::assertSame(200, $d['code'], $d['raw']);
        self::assertTrue($d['json']['ok']);
        self::assertSame(200, $this->http($id, 'GET', '/api/notes.php?action=list')['code']);
        self::assertSame(200, $this->http($id, 'GET', '/api/logs.php')['code'], 'admin must=0 xem được nhật ký');
        self::assertSame(200, $this->http($id, 'GET', '/api/passkey.php?action=status')['code']);

        $w = $this->http($id, 'POST', '/api/org.php?action=saveMember',
            ['id' => (int) $target['id'], 'holyName' => 'X', 'fullName' => 'Duoc Doi Ten', 'phone' => '0911222333', 'role' => 'glv']);
        self::assertSame(200, $w['code'], $w['raw']);
        self::assertSame('Duoc Doi Ten', db_one('SELECT full_name FROM members WHERE id = ?', [$target['id']])['full_name']);
        foreach ([['/api/attendance.php?action=toggle', ['programId' => 1, 'studentId' => 1, 'date' => date('Y-m-d')]],
                  ['/api/students.php?action=save', ['fullName' => '']]] as [$path, $body]) {
            $this->assertNotBlockedByFlag($this->http($id, 'POST', $path, $body), $path);
        }
    }

    // ------------------------------------------------------------------
    //  Cửa duy nhất mở: đổi mật khẩu
    // ------------------------------------------------------------------

    public function test_password_change_is_the_only_open_door_and_unlocks_data(): void
    {
        $u = $this->glv(1);
        $this->loginAs($u);
        $id = (int) $u['id'];
        $this->assertBlocked($this->http($id, 'GET', '/api/data.php?part=core'), 'trước khi đổi');

        // Thiếu CSRF: không nới lỏng (AUTH-08b).
        $r = $this->http($id, 'POST', '/api/auth.php?action=password', ['current' => $u['password'], 'new' => 'MatKhauMoi-9'], false);
        self::assertSame(403, $r['code'], $r['raw']);
        self::assertNotSame('must_change_pw', $r['json']['code'] ?? null, 'đây là lỗi CSRF, không phải cổng must_change_pw');
        self::assertSame(1, $this->mustFlag($id), 'thiếu CSRF thì không được đổi');

        // Sai mật khẩu hiện tại: không đổi.
        $r = $this->http($id, 'POST', '/api/auth.php?action=password', ['current' => 'sai-het', 'new' => 'MatKhauMoi-9']);
        self::assertFalse($r['json']['ok'] ?? true, $r['raw']);
        self::assertNotSame('must_change_pw', $r['json']['code'] ?? null, 'endpoint password phải tới được logic của nó');
        self::assertSame(1, $this->mustFlag($id));

        // Đúng: 200, cờ về 0, mật khẩu mới có hiệu lực, data.php mở.
        $r = $this->http($id, 'POST', '/api/auth.php?action=password', ['current' => $u['password'], 'new' => 'MatKhauMoi-9']);
        self::assertSame(200, $r['code'], 'password phải gọi được khi còn cờ: ' . $r['raw']);
        self::assertTrue($r['json']['ok']);
        self::assertSame(0, $this->mustFlag($id));
        self::assertTrue(password_verify('MatKhauMoi-9', db_one('SELECT password_hash FROM members WHERE id = ?', [$id])['password_hash']));

        $d = $this->http($id, 'GET', '/api/data.php?part=core');
        self::assertSame(200, $d['code'], 'sau khi đổi mật khẩu data.php phải mở: ' . $d['raw']);
        self::assertTrue($d['json']['ok']);
    }

    // ------------------------------------------------------------------
    //  me / logout vẫn chạy khi còn cờ
    // ------------------------------------------------------------------

    public function test_me_and_logout_still_work_while_flagged(): void
    {
        $u = $this->glv(1);
        $this->loginAs($u);
        $id = (int) $u['id'];

        $me = $this->http($id, 'GET', '/api/auth.php?action=me');
        self::assertSame(200, $me['code'], $me['raw']);
        self::assertTrue($me['json']['ok']);
        self::assertTrue($me['json']['user']['mustChangePw'], 'me phải báo mustChangePw=true');

        $out = $this->http($id, 'POST', '/api/auth.php?action=logout', []);
        self::assertSame(200, $out['code'], $out['raw']);
        self::assertTrue($out['json']['ok']);
        $after = $this->http($id, 'GET', '/api/auth.php?action=me');
        self::assertFalse($after['json']['ok'], 'sau logout phiên phải huỷ');
        self::assertNull($after['json']['user']);
    }

    // ------------------------------------------------------------------
    //  Cờ bật GIỮA phiên
    // ------------------------------------------------------------------

    public function test_flag_set_mid_session_by_admin_reset_blocks_next_request(): void
    {
        $admin = $this->admin(0);
        $victim = $this->glv(0);
        $this->loginAs($victim);
        $vid = (int) $victim['id'];
        self::assertSame(200, $this->http($vid, 'GET', '/api/data.php?part=core')['code'], 'trước khi bị cấp lại');

        $this->loginAs($admin);
        $r = $this->http((int) $admin['id'], 'POST', '/api/org.php?action=resetPassword', ['id' => $vid]);
        self::assertSame(200, $r['code'], $r['raw']);

        $this->assertBlocked($this->http($vid, 'GET', '/api/data.php?part=core'), 'data.php sau reset');
        $this->assertBlocked($this->http($vid, 'GET', '/api/notes.php?action=list'), 'notes.php sau reset');
    }

    // ------------------------------------------------------------------
    //  Passkey (AUTH-09)
    // ------------------------------------------------------------------

    /** Sinh khoá EC P-256 bằng openssl, lưu khoá công khai (PEM) làm Passkey của $memberId. @return array{key:\OpenSSLAsymmetricKey,cred:string} */
    private function enrollPasskey(int $memberId): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key, 'openssl không sinh được khoá EC');
        $pem = openssl_pkey_get_details($key)['key'];
        $cred = random_bytes(16);
        db_run('INSERT INTO member_passkeys (member_id, credential_id, public_key, user_handle) VALUES (?,?,?,?)',
               [$memberId, base64_encode($cred), $pem, (string) $memberId]);
        $this->passkeyMemberIds[] = $memberId;
        return ['key' => $key, 'cred' => $cred];
    }

    private static function b64u(string $b): string { return rtrim(strtr(base64_encode($b), '+/', '-_'), '='); }

    /**
     * Đăng nhập Passkey bằng một phiên curl MỚI (chưa đăng nhập). Chữ ký WebAuthn thật (ES256),
     * rpId = localhost. @return array{login:array, me:array}
     */
    private function passkeyLogin(array $pk, int $counter): array
    {
        $jar = tempnam(sys_get_temp_dir(), 'p1pk');
        $this->jars[-$counter - 1000] = $jar;      // để cleanupHarness xoá file cookie
        // WebAuthn chỉ chấp nhận origin https hoặc localhost: gọi qua tên `localhost` (rpId = localhost).
        $port = (int) parse_url(self::$base, PHP_URL_PORT);
        $origin = "http://localhost:$port";
        $call = function (string $method, string $path, ?array $json = null) use ($jar, $origin, $port): array {
            $ch = curl_init($origin . $path);
            curl_setopt($ch, CURLOPT_RESOLVE, ["localhost:$port:127.0.0.1"]);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
            ]);
            if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
            $body = (string) curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ['code' => $code, 'raw' => $body, 'json' => json_decode($body, true)];
        };

        $args = $call('GET', '/api/passkey.php?action=getLoginArgs');
        self::assertSame(200, $args['code'], $args['raw']);
        $chal = $args['json']['args']['publicKey']['challenge'];
        self::assertStringStartsWith('=?BINARY?B?', $chal);
        $challenge = base64_decode(substr($chal, strlen('=?BINARY?B?'), -2));

        $host = 'localhost';                                            // rpId của passkey.php = host không cổng
        $authData = hash('sha256', $host, true) . chr(0x05) . pack('N', $counter);   // UP + UV
        $cdj = json_encode(['type' => 'webauthn.get', 'challenge' => self::b64u($challenge), 'origin' => $origin]);
        self::assertTrue(openssl_sign($authData . hash('sha256', $cdj, true), $sig, $pk['key'], OPENSSL_ALGO_SHA256));

        $login = $call('POST', '/api/passkey.php?action=processLogin', [
            'id' => self::b64u($pk['cred']), 'clientDataJSON' => base64_encode($cdj),
            'authenticatorData' => base64_encode($authData), 'signature' => base64_encode($sig),
        ]);
        return ['login' => $login, 'me' => $call('GET', '/api/auth.php?action=me')];
    }

    public function test_passkey_login_works_when_unflagged_control(): void
    {
        $u = $this->glv(0);
        $pk = $this->enrollPasskey((int) $u['id']);
        $r = $this->passkeyLogin($pk, 1);
        self::assertSame(200, $r['login']['code'], 'đối chứng: Passkey hợp lệ + must=0 phải vào được — ' . $r['login']['raw']);
        self::assertTrue($r['login']['json']['ok']);
        self::assertTrue($r['me']['json']['ok'], 'phải có phiên');
        self::assertSame((int) $u['id'], $r['me']['json']['user']['id']);
    }

    public function test_passkey_login_is_refused_while_flagged_and_creates_no_session(): void
    {
        $u = $this->glv(0);
        $pk = $this->enrollPasskey((int) $u['id']);
        db_run('UPDATE members SET must_change_pw = 1 WHERE id = ?', [$u['id']]);

        $r = $this->passkeyLogin($pk, 1);
        $this->assertBlocked($r['login'], 'processLogin khi must=1',
            'Tài khoản cần đổi mật khẩu. Vui lòng đăng nhập bằng số điện thoại và mật khẩu (tạm) để đổi.');
        self::assertFalse($r['me']['json']['ok'], 'KHÔNG được tạo phiên');
        self::assertNull($r['me']['json']['user']);
        self::assertSame(1, $this->mustFlag((int) $u['id']));

        // Không tính là lần thử sai: lần sau (cờ về 0) vào được ngay.
        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$u['id']]);
        $r2 = $this->passkeyLogin($pk, 2);
        self::assertSame(200, $r2['login']['code'], 'không bị khoá do thử sai: ' . $r2['login']['raw']);
    }
}
