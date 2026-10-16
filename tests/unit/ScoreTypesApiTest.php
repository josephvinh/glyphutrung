<?php
// tests/unit/ScoreTypesApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA score_types.php:
// - GET → danh sách loại đầu điểm
// - POST → thêm loại mới
// - PUT ?code=X → cập nhật weight, max_exams
// - DELETE ?code=X → xoá (nếu không có điểm)
//
// Bảo vệ LUẬT CỨNG #6:
//   - chỉ admin/bdh mới được POST/PUT/DELETE
//
// Đăng nhập đi qua /api/auth.php?action=login với JSON {phone, password},
// CSRF lấy từ JSON response, session cookie trích từ header Set-Cookie
// (cookie jar file không đáng tin trên PHP/curl này — HttpOnly không được ghi).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoreTypesApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    /** Map từ tên phiên (admin / nonAdmin) → chuỗi cookie và CSRF token. */
    private array $sessions = [];
    private array $csrfs = [];
    private array $cleanupCodes = [];
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
        $admin = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1");
        if (!$admin) {
            $this->markTestSkipped('Cần admin để chạy test.');
        }
        $this->adminId = (int) $admin['id'];

        // Bỏ cờ buộc đổi mật khẩu của admin mặc định.
        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        $this->phoneNonAdmin = '09' . random_int(10000000, 99999999);
        $this->memberIdNonAdmin = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'ScoreTypes GLV', ?, ?, 'glv', 0)",
            ['STGL' . random_int(100000, 999999), $this->phoneNonAdmin, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        // Cleanup: xoá các test type đã tạo
        foreach ($this->cleanupCodes as $code) {
            $this->http('DELETE', '/api/score_types.php?code=' . urlencode($code));
        }
        $this->cleanupCodes = [];

        if ($this->adminId) {
            db_run('UPDATE members SET must_change_pw = 1 WHERE id = ?', [$this->adminId]);
        }
        if ($this->memberIdNonAdmin) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberIdNonAdmin]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberIdNonAdmin]);
        }
    }

    // ==================== HTTP helpers ====================

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

    public function test_get_score_types_requires_login(): void
    {
        $r = $this->http('GET', '/api/score_types.php', null, '__noauth__', false);
        $this->assertSame(401, $r['code']);
    }

    public function test_get_score_types_returns_array(): void
    {
        $r = $this->http('GET', '/api/score_types.php');
        $this->assertSame(200, $r['code']);
        $this->assertIsArray($r['json']);

        $first = $r['json'][0] ?? null;
        $this->assertNotNull($first, 'Phải có ít nhất 1 loại điểm');
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('weight', $first);
        $this->assertArrayHasKey('max_exams', $first);
    }

    public function test_post_score_types_creates_new_type(): void
    {
        // Mã tối đa 16 ký tự: t(time_5) + rand_3 = ~10 ký tự
        $testCode = 't' . substr((string) time(), -5) . random_int(100, 999);
        $this->cleanupCodes[] = $testCode;

        $r = $this->http('POST', '/api/score_types.php', [
            'code'        => $testCode,
            'label'       => 'Kiểm tra',
            'short_label' => 'KT',
            'weight'      => 1,
            'max_exams'   => 2,
            'category'    => 'homework',
        ]);
        $this->assertSame(200, $r['code'], 'POST score_types phải thành công: ' . $r['raw']);
        $this->assertSame($testCode, $r['json']['code'] ?? null, 'POST phải trả về code vừa tạo');

        // Verify: GET lại phải thấy type mới
        $list = $this->http('GET', '/api/score_types.php');
        $found = array_filter($list['json'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(1, $found);
        $first = array_values($found)[0];
        $this->assertEquals('Kiểm tra', $first['label']);
        $this->assertEquals(2, $first['max_exams']);
    }

    public function test_post_score_types_rejects_non_admin(): void
    {
        $this->loginNonAdmin();
        $r = $this->http('POST', '/api/score_types.php', [
            'code'      => 'f' . substr((string) time(), -5) . random_int(100, 999),
            'label'     => 'X',
            'weight'    => 1,
            'max_exams' => 1,
        ], 'nonAdmin');
        $this->assertSame(403, $r['code'],
            'GLV thường phải bị 403 khi POST score_types, body: ' . $r['raw']);
    }

    public function test_post_score_types_validates_required_fields(): void
    {
        $r = $this->http('POST', '/api/score_types.php', [
            'code'  => '',
            'label' => '',
        ]);
        $this->assertSame(400, $r['code']);
        $this->assertArrayHasKey('error', $r['json']);
    }

    public function test_put_score_types_updates_weight_and_max_exams(): void
    {
        $testCode = 'p' . substr((string) time(), -5) . random_int(100, 999);
        $this->cleanupCodes[] = $testCode;

        $this->http('POST', '/api/score_types.php', [
            'code'        => $testCode,
            'label'       => 'Test Put',
            'short_label' => 'TP',
            'weight'      => 1,
            'max_exams'   => 1,
            'category'    => 'homework',
        ]);

        $r = $this->http('PUT', '/api/score_types.php?code=' . urlencode($testCode), [
            'weight'    => 3,
            'max_exams' => 5,
        ]);
        $this->assertSame(200, $r['code'], 'PUT phải thành công: ' . $r['raw']);

        $list = $this->http('GET', '/api/score_types.php');
        $found = array_filter($list['json'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(1, $found);
        $first = array_values($found)[0];
        $this->assertEquals(3, $first['weight']);
        $this->assertEquals(5, $first['max_exams']);
    }

    public function test_delete_score_types_removes_type(): void
    {
        $testCode = 'd' . substr((string) time(), -5) . random_int(100, 999);
        // Không thêm vào cleanup vì sẽ xoá trong test

        $this->http('POST', '/api/score_types.php', [
            'code'        => $testCode,
            'label'       => 'Test Delete',
            'short_label' => 'TD',
            'weight'      => 1,
            'max_exams'   => 1,
            'category'    => 'homework',
        ]);

        $r = $this->http('DELETE', '/api/score_types.php?code=' . urlencode($testCode));
        $this->assertSame(200, $r['code'], 'DELETE phải thành công: ' . $r['raw']);

        $list = $this->http('GET', '/api/score_types.php');
        $found = array_filter($list['json'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(0, $found);
    }

    public function test_post_short_label_truncates_utf8_correctly(): void
    {
        $testCode = 'u' . substr((string)time(), -5) . random_int(100, 999);
        $this->cleanupCodes[] = $testCode;
        // Label "Điểm học kỳ" — 3 ký tự đầu là "Điể" (UTF-8 nhiều byte)
        $r = $this->http('POST', '/api/score_types.php', [
            'code'   => $testCode,
            'label'  => 'Điểm học kỳ dài',
            'weight' => 1, 'max_exams' => 1,
        ]);
        $this->assertSame(200, $r['code']);
        // short_label auto = substr(label, 0, 3) — bug: cắt giữa "Đi"
        // Sau fix: mb_substr → "Điể"
        $list = $this->http('GET', '/api/score_types.php');
        $found = array_filter($list['json'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(1, $found);
        $first = array_values($found)[0];
        $this->assertEquals('Điể', $first['short_label'],
            'short_label phải cắt UTF-8 đúng bằng mb_substr: ' . $first['short_label']);
    }

    public function test_put_rejects_invalid_code(): void
    {
        // Code có ký tự không hợp lệ (chữ hoa, dấu cách)
        $r = $this->http('PUT', '/api/score_types.php?code=' . urlencode('BAD CODE'), [
            'label' => 'X',
        ]);
        $this->assertSame(400, $r['code'],
            'PUT code không hợp lệ phải 400: ' . $r['raw']);
    }
}
