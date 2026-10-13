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

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoreRulesApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private string $cookieJar = '';
    private string $csrf = '';

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
        // Đăng nhập admin
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

        // Lấy CSRF
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
        if ($this->cookieJar && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    private function api(string $path, array $data = [], string $method = 'GET'): array
    {
        $url = self::$base . $path;
        $ch = curl_init($url);
        $opts = [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $post = $data;
            $post['_csrf'] = $this->csrf;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        curl_setopt_array($ch, $opts);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($out, true) ?: $out];
    }

    // ─── GET rules.php?type=score ───

    public function test_get_score_rules_requires_login(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ck_');
        $ch = curl_init(self::$base . '/api/rules.php?type=score');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $tmp,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($tmp);
        $this->assertEquals(401, $code);
    }

    public function test_get_score_rules_returns_score_types(): void
    {
        $r = $this->api('/api/rules.php?type=score');
        $this->assertEquals(200, $r['code']);
        $this->assertArrayHasKey('score_types', $r['body']);
        $this->assertIsArray($r['body']['score_types']);

        // score_types phải có: code, label, weight, max_exams
        $first = $r['body']['score_types'][0] ?? null;
        $this->assertNotNull($first);
        $this->assertArrayHasKey('code', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('weight', $first);
        $this->assertArrayHasKey('max_exams', $first);
    }

    public function test_get_score_rules_returns_rank_thresholds(): void
    {
        $r = $this->api('/api/rules.php?type=score');
        $this->assertEquals(200, $r['code']);
        $this->assertArrayHasKey('rules', $r['body']);
        $this->assertArrayHasKey('rank_thresholds', $r['body']['rules']);

        $thresholds = $r['body']['rules']['rank_thresholds'];
        $this->assertArrayHasKey('gioi', $thresholds);
        $this->assertArrayHasKey('kha', $thresholds);
        $this->assertArrayHasKey('tb', $thresholds);
    }

    // ─── GET rules.php?type=attendance ───

    public function test_get_attendance_rules_returns_thresholds(): void
    {
        $r = $this->api('/api/rules.php?type=attendance');
        $this->assertEquals(200, $r['code']);
        $this->assertArrayHasKey('thresholds', $r['body']);
        $this->assertIsArray($r['body']['thresholds']);

        // Mỗi threshold phải có: label, min_percent
        foreach ($r['body']['thresholds'] as $t) {
            $this->assertArrayHasKey('label', $t);
            $this->assertArrayHasKey('min_percent', $t);
            $this->assertIsNumeric($t['min_percent']);
        }
    }

    // ─── POST rules.php — cập nhật rank_thresholds ───

    public function test_post_rules_updates_rank_thresholds(): void
    {
        $newVal = json_encode(['gioi' => 8.5, 'kha' => 6.0, 'tb' => 5.0, 'yeu' => 0]);
        $r = $this->api('/api/rules.php', [
            'rule_key' => 'rank_thresholds',
            'rule_value' => $newVal,
            'rule_label' => 'Ngưỡng xếp loại học lực',
            'rule_type' => 'threshold',
        ], 'POST');

        $this->assertEquals(200, $r['code']);

        // Verify đã lưu
        $check = $this->api('/api/rules.php?type=score');
        $saved = json_decode($check['body']['rules']['rank_thresholds']['rule_value'] ?? '{}', true);
        $this->assertEquals(8.5, $saved['gioi']);

        // Restore default
        $this->api('/api/rules.php', [
            'rule_key' => 'rank_thresholds',
            'rule_value' => json_encode(['gioi' => 8, 'kha' => 6.5, 'tb' => 5, 'yeu' => 0]),
            'rule_label' => 'Ngưỡng xếp loại học lực',
            'rule_type' => 'threshold',
        ], 'POST');
    }

    // ─── Permission: non-admin không được POST ───

    public function test_post_rules_requires_admin(): void
    {
        // Tạo tài khoản non-admin
        $ch = curl_init(self::$base . '/api/login.php');
        $ck = tempnam(sys_get_temp_dir(), 'ck2_');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEJAR => $ck,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['username' => 'thuky', 'password' => 'thuky'],
        ]);
        $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($loginCode !== 200) {
            $this->markTestSkipped('Không có tài khoản thuky để test.');
        }

        $ch = curl_init(self::$base . '/api/me.php');
        curl_setopt_array($ch, [CURLOPT_COOKIEFILE => $ck, CURLOPT_RETURNTRANSFER => true]);
        $me = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $r = $this->api('/api/rules.php', [
            'rule_key' => 'rank_thresholds',
            'rule_value' => '{"gioi":9}',
            'rule_label' => 'Test',
            'rule_type' => 'threshold',
        ], 'POST');
        // require_write → 403 cho non-admin
        $this->assertEquals(403, $r['code']);
        unlink($ck);
    }
}
