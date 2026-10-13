<?php
// tests/unit/ScoreRulesApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA rules.php:
// - GET ?type=score → trả score_types + rules
// - GET ?type=attendance → trả thresholds
// - POST → tạo/cập nhật rule
//
// Bảo vệ LUẬT CỨNG #6:
//   - chỉ admin/bdh mới được POST (require_write)
//
// Lưu ý: login đi qua /api/auth.php?action=login với JSON {phone, password},
// CSRF lấy từ JSON response, session cookie (PHPSESSID) trích từ header
// Set-Cookie và gửi lại qua CURLOPT_COOKIE ở mỗi request sau.
// Vì sao không dùng cookie jar file: trên môi trường PHP/curl này,
// COOKIEJAR không ghi cookie HttpOnly (chỉ "Added cookie" trong verbose
// nhưng file trống) nên phiên bị mất giữa các request.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoreRulesApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    /** Map từ "tên phiên" (admin / nonAdmin) → chuỗi cookie gửi kèm request. */
    private array $sessions = [];
    private array $csrfs = [];
    private int $adminId = 0;
    private int $memberIdNonAdmin = 0;
    private string $phoneNonAdmin = '';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('Cần ext-curl để gọi HTTP.');
        }
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$sock) self::markTestSkipped('Không mở được cổng cục bộ.');
        $port = (int) parse_url('tcp://' . stream_socket_get_name($sock, false), PHP_URL_PORT);
        fclose($sock);

        $root = dirname(__DIR__, 2) . '/public';
        $env = array_merge(getenv(), $_ENV);
        $null = PHP_OS === 'WINNT' ? 'NUL' : '/dev/null';
        self::$proc = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes, $root, $env
        );
        self::$base = "http://127.0.0.1:$port";

        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen('127.0.0.1', $port, $e, $s, 0.1);
            if ($c) { fclose($c); return; }
            usleep(100000);
        }
        self::markTestSkipped('Máy chủ PHP tích hợp không khởi động được.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
    }

    protected function setUp(): void
    {
        $year = current_year();
        $admin = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1");
        if (!$year || !$admin) {
            $this->markTestSkipped('Cần niên khoá hiện tại + admin để chạy test.');
        }
        $this->adminId = (int) $admin['id'];

        // Bỏ cờ buộc đổi mật khẩu của admin (cờ này chặn mọi hành động trừ
        // auth.php?action=password). Phục hồi trong tearDown.
        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        // Tạo tài khoản non-admin để test phân quyền.
        $this->phoneNonAdmin = '09' . random_int(10000000, 99999999);
        $this->memberIdNonAdmin = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'Rules GLV', ?, ?, 'glv', 0)",
            ['RULG' . random_int(100000, 999999), $this->phoneNonAdmin, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->adminId) {
            db_run('UPDATE members SET must_change_pw = 1 WHERE id = ?', [$this->adminId]);
        }
        if ($this->memberIdNonAdmin) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberIdNonAdmin]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberIdNonAdmin]);
        }
    }

    // ==================== HTTP helpers ====================

    /**
     * Gọi HTTP tới server đã spawn. Nhận cả header để bắt Set-Cookie.
     *
     * @param string|null $session Tên phiên ('admin' mặc định, 'nonAdmin' cho user phụ)
     */
    private function http(string $method, string $path, ?array $json = null,
                         ?string $session = null, bool $csrf = true): array
    {
        $session = $session ?? 'admin';
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && !empty($this->csrfs[$session])) {
            $headers[] = 'X-CSRF-TOKEN: ' . $this->csrfs[$session];
        }
        if (!empty($this->sessions[$session])) {
            // Gửi cookie bằng header thẳng (không qua COOKIEFILE/COOKIEJAR — xem docblock).
            $headers[] = 'Cookie: ' . $this->sessions[$session];
        }
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ];
        if ($json !== null) $opts[CURLOPT_POST] = true;
        curl_setopt_array($ch, $opts);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $resp = curl_exec($ch);
        $info = curl_getinfo($ch);
        $code = (int) $info['http_code'];
        $hdr  = substr((string) $resp, 0, (int) $info['header_size']);
        $body = substr((string) $resp, (int) $info['header_size']);
        curl_close($ch);
        // Lưu PHPSESSID mới (session_regenerate_id sau login) cho request sau.
        if (preg_match('/Set-Cookie:\s*(PHPSESSID=[^;]+)/i', $hdr, $m)) {
            $this->sessions[$session] = $m[1];
        }
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    private function loginAdmin(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => '0901000001', 'password' => app_config('default_password')],
            'admin', false);
        $this->assertSame(200, $r['code'],
            'Đăng nhập admin phải thành công: ' . $r['raw']);
        $this->assertNotEmpty($r['json']['csrfToken'] ?? '',
            'Phản hồi đăng nhập phải chứa csrfToken: ' . $r['raw']);
        $this->csrfs['admin'] = $r['json']['csrfToken'];
    }

    private function loginNonAdmin(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $this->phoneNonAdmin, 'password' => 'Mk-Test-1'],
            'nonAdmin', false);
        $this->assertSame(200, $r['code'],
            'Đăng nhập non-admin phải thành công: ' . $r['raw']);
        $this->assertNotEmpty($r['json']['csrfToken'] ?? '',
            'Phản hồi đăng nhập phải chứa csrfToken: ' . $r['raw']);
        $this->csrfs['nonAdmin'] = $r['json']['csrfToken'];
    }

    // ==================== Tests ====================

    public function test_get_score_rules_requires_login(): void
    {
        $r = $this->http('GET', '/api/rules.php?type=score', null, '__noauth__', false);
        $this->assertSame(401, $r['code']);
    }

    public function test_get_score_rules_returns_score_types(): void
    {
        $r = $this->http('GET', '/api/rules.php?type=score');
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('score_types', $r['json']);
        $this->assertIsArray($r['json']['score_types']);

        $first = $r['json']['score_types'][0] ?? null;
        $this->assertNotNull($first, 'Phải có ít nhất 1 loại điểm');
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('weight', $first);
        $this->assertArrayHasKey('max_exams', $first);
    }

    public function test_get_score_rules_returns_rank_thresholds(): void
    {
        $r = $this->http('GET', '/api/rules.php?type=score');
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('rules', $r['json']);
        $this->assertArrayHasKey('rank_thresholds', $r['json']['rules']);

        // rule_value là chuỗi JSON; cần decode để kiểm tra các ngưỡng bên trong.
        $thresholds = json_decode($r['json']['rules']['rank_thresholds']['rule_value'] ?? '{}', true);
        $this->assertIsArray($thresholds, 'rank_thresholds.rule_value phải là JSON hợp lệ');
        $this->assertArrayHasKey('gioi', $thresholds);
        $this->assertArrayHasKey('kha', $thresholds);
        $this->assertArrayHasKey('tb', $thresholds);
    }

    public function test_get_attendance_rules_returns_thresholds(): void
    {
        $r = $this->http('GET', '/api/rules.php?type=attendance');
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('thresholds', $r['json']);
        $this->assertIsArray($r['json']['thresholds']);

        foreach ($r['json']['thresholds'] as $t) {
            $this->assertArrayHasKey('label', $t);
            $this->assertArrayHasKey('min_percent', $t);
            $this->assertIsNumeric($t['min_percent']);
        }
    }

    public function test_post_rules_updates_rank_thresholds(): void
    {
        $newVal = json_encode(['gioi' => 8.5, 'kha' => 6.0, 'tb' => 5.0, 'yeu' => 0]);
        $r = $this->http('POST', '/api/rules.php', [
            'rule_key'   => 'rank_thresholds',
            'rule_value' => $newVal,
            'rule_label' => 'Ngưỡng xếp loại học lực',
            'rule_type'  => 'threshold',
        ]);
        $this->assertSame(200, $r['code'], 'POST rule phải thành công: ' . $r['raw']);

        // Verify đã lưu
        $check = $this->http('GET', '/api/rules.php?type=score');
        $saved = json_decode($check['json']['rules']['rank_thresholds']['rule_value'] ?? '{}', true);
        $this->assertEquals(8.5, $saved['gioi']);

        // Restore default
        $this->http('POST', '/api/rules.php', [
            'rule_key'   => 'rank_thresholds',
            'rule_value' => json_encode(['gioi' => 8, 'kha' => 6.5, 'tb' => 5, 'yeu' => 0]),
            'rule_label' => 'Ngưỡng xếp loại học lực',
            'rule_type'  => 'threshold',
        ]);
    }

    /**
     * Phân quyền: GLV thường (không có quyền scores.edit) → 403 khi POST rules
     * (mục 5 — bảo vệ luật cứng #6).
     */
    public function test_post_rules_rejects_non_admin(): void
    {
        $this->loginNonAdmin();

        $r = $this->http('POST', '/api/rules.php', [
            'rule_key'   => 'rank_thresholds',
            'rule_value' => '{"gioi":9}',
            'rule_label' => 'Test',
            'rule_type'  => 'threshold',
        ], 'nonAdmin');
        // require_write → 403 cho non-admin
        $this->assertSame(403, $r['code'],
            'GLV thường phải bị 403 khi POST rules, body: ' . $r['raw']);
    }
}
