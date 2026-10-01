<?php
// tests/unit/P1ApiHarness.php  (KHÔNG phải test: tên không kết thúc bằng Test.php)
//
// Đồ nghề dùng chung cho DataScopeTest và StaffGuardTest (gói P1: #78 #97):
//   - tạo thành viên thật (+ phân công) và dọn sạch trong tearDown;
//   - đặt tạm ma trận quyền rồi khôi phục;
//   - khởi động máy chủ PHP tích hợp (`php -S`) để gọi ĐÚNG endpoint
//     data.php / org.php bằng phiên đăng nhập + CSRF thật. data.php và org.php
//     chạy thẳng khi nạp (require_login, exit) nên không nạp trực tiếp được.
//
// Khác QrScanApiTest: nếu máy chủ không khởi động được thì test FAIL (không
// skip) — bộ kiểm thử này là chốt chặn phân quyền, không được im lặng bỏ qua.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

trait P1ApiHarness
{
    private static $proc = null;
    private static string $base = '';

    /** @var int[] */
    private array $madeMemberIds = [];
    /** @var array<int,array{mod:string,role:string,level:?string}> */
    private array $savedPerms = [];
    /** @var array<int,string> memberId => cookie jar */
    private array $jars = [];
    /** @var array<int,string> memberId => csrf */
    private array $csrfs = [];
    /** @var string[] giá trị `what` của nhật ký tự chèn, để xoá */
    private array $madeLogWhats = [];

    // ------------------------------------------------------------------
    //  Máy chủ
    // ------------------------------------------------------------------

    private static function startServer(): void
    {
        if (!function_exists('curl_init')) {
            self::fail('Cần ext-curl để gọi HTTP (CI có cài curl).');
        }
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$sock) self::fail("Không mở được cổng cục bộ: $errstr");
        $port = (int) parse_url('tcp://' . stream_socket_get_name($sock, false), PHP_URL_PORT);
        fclose($sock);

        $root = dirname(__DIR__, 2) . '/public';
        $env = array_merge(getenv(), $_ENV);
        self::$proc = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes, $root, $env
        );
        self::$base = "http://127.0.0.1:$port";

        for ($i = 0; $i < 80; $i++) {                       // chờ tối đa ~8 giây
            $c = @fsockopen('127.0.0.1', $port, $e, $s, 0.1);
            if ($c) { fclose($c); return; }
            usleep(100000);
        }
        self::stopServer();
        self::fail('Máy chủ PHP tích hợp không khởi động được.');
    }

    private static function stopServer(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        self::$proc = null;
    }

    // ------------------------------------------------------------------
    //  Dữ liệu
    // ------------------------------------------------------------------

    /**
     * Tạo thành viên THẬT (đã được duyệt, không buộc đổi mật khẩu) + các phân công.
     * $assignments: [[role_code, block_id|null, class_id|null], ...] (phân công đầu = chính).
     * Không có phân công thì dùng vai/khối/lớp gốc trên bảng members.
     * Trả hàng giống current_member() (có role_scope) kèm 'phone' và 'password'.
     */
    private function makeMember(string $role, array $assignments = [], array $cols = []): array
    {
        $adminId = (int) (db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1")['id'] ?? 0);
        self::assertGreaterThan(0, $adminId, 'Cần admin id nhỏ nhất (install.php) làm người phân công.');

        do {
            $phone = '09' . random_int(10000000, 99999999);
        } while (db_one('SELECT id FROM members WHERE phone = ?', [$phone]));
        $password = 'Mk-Test-1';

        $row = array_merge([
            'code'           => 'P1T' . random_int(100000, 999999),
            'full_name'      => 'P1 Test ' . $role,
            'phone'          => $phone,
            'password_hash'  => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]),   // rẻ: test tạo hàng chục tài khoản
            'role_code'      => $role,
            'must_change_pw' => 0,
        ], $cols);
        $id = db_insert(
            'INSERT INTO members (' . implode(',', array_keys($row)) . ') VALUES ('
            . implode(',', array_fill(0, count($row), '?')) . ')',
            array_values($row)
        );
        $this->madeMemberIds[] = $id;

        foreach ($assignments as $i => [$aRole, $blockId, $classId]) {
            db_run(
                "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
                 VALUES (?, ?, ?, ?, ?, CURDATE(), ?)",
                [$id, $aRole, $blockId, $classId, $i === 0 ? 1 : 0, $adminId]
            );
        }

        $me = $this->meRow($id);
        $me['phone'] = $row['phone'];
        $me['password'] = $password;
        return $me;
    }

    /** Hàng thành viên đúng dạng current_member() trả (data_scope_for/guardTarget nhận dạng này). */
    private function meRow(int $id): array
    {
        $me = db_one(
            'SELECT m.*, r.label AS role_label, r.level AS role_level, r.scope AS role_scope,
                    t.label AS title_label, b.name AS block_name, c.name AS class_name
               FROM members m
               JOIN roles r ON r.code = m.role_code
               LEFT JOIN titles t ON t.id = m.title_id
               LEFT JOIN blocks b ON b.id = m.block_id
               LEFT JOIN classes c ON c.id = m.class_id
              WHERE m.id = ?',
            [$id]
        );
        self::assertNotNull($me, "Không đọc lại được thành viên $id");
        return $me;
    }

    /** Ghi đè tạm cấp quyền của một vai trên một module, nhớ để khôi phục trong tearDown. */
    private function setPerm(string $mod, string $role, string $level): void
    {
        $old = db_one('SELECT level FROM permissions WHERE module_key=? AND role_code=?', [$mod, $role]);
        $this->savedPerms[] = ['mod' => $mod, 'role' => $role, 'level' => $old['level'] ?? null];
        db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = VALUES(level)', [$mod, $role, $level]);
    }

    /** Dọn mọi thứ harness đã tạo. Gọi trong tearDown (an toàn khi gọi lại). */
    private function cleanupHarness(): void
    {
        // Khôi phục quyền theo thứ tự NGƯỢC (ghi đè nhiều lần cùng ô vẫn về đúng giá trị gốc).
        foreach (array_reverse($this->savedPerms) as $p) {
            if ($p['level'] === null) {
                db_run('DELETE FROM permissions WHERE module_key=? AND role_code=?', [$p['mod'], $p['role']]);
            } else {
                db_run('INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE level = VALUES(level)', [$p['mod'], $p['role'], $p['level']]);
            }
        }
        $this->savedPerms = [];

        foreach ($this->madeLogWhats as $w) {
            db_run('DELETE FROM activity_logs WHERE what = ?', [$w]);
        }
        $this->madeLogWhats = [];

        foreach ($this->madeMemberIds as $id) {
            // activity_logs.actor_id là ON DELETE SET NULL: phải xoá tay kẻo để lại dòng mồ côi.
            db_run('DELETE FROM activity_logs WHERE actor_id = ?', [$id]);
            db_run('DELETE FROM member_assignments WHERE member_id = ?', [$id]);
            db_run('DELETE FROM members WHERE id = ?', [$id]);
        }
        $this->madeMemberIds = [];

        foreach ($this->jars as $jar) {
            if ($jar && file_exists($jar)) @unlink($jar);
        }
        $this->jars = $this->csrfs = [];
    }

    // ------------------------------------------------------------------
    //  HTTP
    // ------------------------------------------------------------------

    /** Đăng nhập thật (một lần cho mỗi thành viên trong một test), nhớ cookie + CSRF. */
    private function loginAs(array $member): void
    {
        $id = (int) $member['id'];
        if (isset($this->jars[$id])) return;
        $this->jars[$id] = tempnam(sys_get_temp_dir(), 'p1t');
        $r = $this->http($id, 'POST', '/api/auth.php?action=login',
            ['phone' => $member['phone'], 'password' => $member['password']], false);
        self::assertSame(200, $r['code'], 'Đăng nhập thành viên test phải thành công: ' . $r['raw']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{16,}$/', (string) ($r['json']['csrfToken'] ?? ''),
            'login phải trả csrfToken');
        $this->csrfs[$id] = $r['json']['csrfToken'];
    }

    /** @return array{code:int, raw:string, json:?array} */
    private function http(int $memberId, string $method, string $path, ?array $json = null, bool $csrf = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && !empty($this->csrfs[$memberId])) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrfs[$memberId];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_COOKIEJAR      => $this->jars[$memberId],
            CURLOPT_COOKIEFILE     => $this->jars[$memberId],
        ]);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }
}
