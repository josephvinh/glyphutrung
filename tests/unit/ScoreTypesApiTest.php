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

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoreTypesApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private string $cookieJar = '';
    private string $csrf = '';
    private array $cleanupCodes = [];

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
        sleep(1);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
    }

    protected function setUp(): void
    {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'ck_');
        $ch = curl_init(self::$base . '/api/login.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['username' => 'admin', 'password' => 'admin'],
        ]);
        $out = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] !== 200) {
            $this->markTestSkipped('Không đăng nhập được admin.');
        }

        $ch = curl_init(self::$base . '/api/me.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $me = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $this->csrf = $me['csrf'] ?? '';
    }

    protected function tearDown(): void
    {
        // Cleanup: xoá các test type đã tạo
        foreach ($this->cleanupCodes as $code) {
            $post = ['_csrf' => $this->csrf];
            $ch = curl_init(self::$base . '/api/score_types.php?code=' . urlencode($code));
            curl_setopt_array($ch, [
                CURLOPT_COOKIEFILE => $this->cookieJar,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
        $this->cleanupCodes = [];

        if ($this->cookieJar && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    private function api(string $url, array $post = [], string $method = 'GET'): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $p = $post;
            $p['_csrf'] = $this->csrf;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($p);
        } elseif ($method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $opts[CURLOPT_POST] = true;
            $p = $post;
            $p['_csrf'] = $this->csrf;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($p);
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }
        curl_setopt_array($ch, $opts);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($out, true) ?: $out];
    }

    // ─── GET ───

    public function test_get_score_types_requires_login(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ck_');
        $ch = curl_init(self::$base . '/api/score_types.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $tmp,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($tmp);
        $this->assertEquals(401, $code);
    }

    public function test_get_score_types_returns_array(): void
    {
        $r = $this->api(self::$base . '/api/score_types.php');
        $this->assertEquals(200, $r['code']);
        $this->assertIsArray($r['body']);

        $first = $r['body'][0] ?? null;
        $this->assertNotNull($first);
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('weight', $first);
        $this->assertArrayHasKey('max_exams', $first);
    }

    // ─── POST — thêm loại mới ───

    public function test_post_score_types_creates_new_type(): void
    {
        $testCode = 'test_' . time();
        $this->cleanupCodes[] = $testCode;

        $r = $this->api(self::$base . '/api/score_types.php', [
            'code' => $testCode,
            'label' => 'Kiểm tra',
            'short_label' => 'KT',
            'weight' => 1,
            'max_exams' => 2,
            'category' => 'homework',
        ], 'POST');

        $this->assertEquals(200, $r['code']);
        $this->assertArrayHasKey('id', $r['body']);

        // Verify: GET lại phải thấy type mới
        $list = $this->api(self::$base . '/api/score_types.php');
        $found = array_filter($list['body'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(1, $found);
        $this->assertEquals('Kiểm tra', array_values($found)[0]['label']);
        $this->assertEquals(2, array_values($found)[0]['max_exams']);
    }

    public function test_post_score_types_requires_admin(): void
    {
        $ck = tempnam(sys_get_temp_dir(), 'ck2_');
        $ch = curl_init(self::$base . '/api/login.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEJAR => $ck,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['username' => 'thuky', 'password' => 'thuky'],
        ]);
        curl_exec($ch);
        curl_close($ch);

        $ch = curl_init(self::$base . '/api/score_types.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $ck,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['code' => 'test_fail', 'label' => 'X', 'weight' => 1, 'max_exams' => 1],
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($ck);

        $this->assertEquals(403, $code);
    }

    public function test_post_score_types_validates_required_fields(): void
    {
        $r = $this->api(self::$base . '/api/score_types.php', [
            'code' => '',
            'label' => '',
        ], 'POST');

        $this->assertEquals(400, $r['code']);
        $this->assertArrayHasKey('error', $r['body']);
    }

    // ─── PUT — cập nhật weight và max_exams ───

    public function test_put_score_types_updates_weight_and_max_exams(): void
    {
        // Tạo type tạm
        $testCode = 'test_put_' . time();
        $this->cleanupCodes[] = $testCode;

        $this->api(self::$base . '/api/score_types.php', [
            'code' => $testCode,
            'label' => 'Test Put',
            'short_label' => 'TP',
            'weight' => 1,
            'max_exams' => 1,
            'category' => 'homework',
        ], 'POST');

        // Cập nhật
        $r = $this->api(self::$base . '/api/score_types.php?code=' . urlencode($testCode), [
            'weight' => 3,
            'max_exams' => 5,
        ], 'PUT');

        $this->assertEquals(200, $r['code']);

        // Verify
        $list = $this->api(self::$base . '/api/score_types.php');
        $found = array_filter($list['body'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(1, $found);
        $this->assertEquals(3, array_values($found)[0]['weight']);
        $this->assertEquals(5, array_values($found)[0]['max_exams']);
    }

    // ─── DELETE ───

    public function test_delete_score_types_removes_type(): void
    {
        $testCode = 'test_del_' . time();

        $this->api(self::$base . '/api/score_types.php', [
            'code' => $testCode,
            'label' => 'Test Delete',
            'short_label' => 'TD',
            'weight' => 1,
            'max_exams' => 1,
            'category' => 'homework',
        ], 'POST');
        // Không cần cleanup vì sẽ xoá

        $r = $this->api(self::$base . '/api/score_types.php?code=' . urlencode($testCode), [], 'DELETE');
        $this->assertEquals(200, $r['code']);

        // Verify đã xoá
        $list = $this->api(self::$base . '/api/score_types.php');
        $found = array_filter($list['body'], fn($t) => $t['code'] === $testCode);
        $this->assertCount(0, $found);
    }
}
