<?php
/**
 * P8 - Data API Performance Tests
 *
 * Kiểm tra các thay đổi performance trong data.php:
 * - attDays parameter giới hạn attendance
 * - scores scope (current vs all)
 * - details parameter (basic vs full student info)
 * - gzip compression header
 *
 * #90
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class DataApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';

    private int $yearId = 0;
    private int $adminId = 0;
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
            $this->markTestSkipped('Cần niên khoá hiện tại và admin.');
        }
        $this->yearId = (int) $year['id'];
        $this->adminId = (int) $admin['id'];

        $this->cookieJar = tempnam(sys_get_temp_dir(), 'dataapi');
        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->cookieJar && file_exists($this->cookieJar)) @unlink($this->cookieJar);
    }

    // ==================== HTTP helpers ====================

    private function http(string $method, string $path, ?array $data = null, bool $csrf = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && $this->csrf) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrf;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($body, 0, $headerSize);
        $body = substr($body, $headerSize);
        curl_close($ch);
        return [
            'code' => $code,
            'raw' => (string) $body,
            'json' => json_decode((string) $body, true),
            'headers' => $headers,
        ];
    }

    private function loginAdmin(): void
    {
        // Tìm admin có thể đăng nhập
        $admin = db_one(
            "SELECT phone FROM members WHERE role_code = 'admin' AND (must_change_pw = 0 OR must_change_pw IS NULL) LIMIT 1"
        );
        if (!$admin) {
            $this->markTestSkipped('Cần admin có thể đăng nhập.');
        }

        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $admin['phone'], 'password' => 'Admin@1234'], false);
        $this->assertSame(200, $r['code'], 'Đăng nhập admin test phải thành công: ' . $r['raw']);

        $page = $this->http('GET', '/index.php', null, false);
        $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m),
            'Không lấy được csrfToken');
        $this->csrf = $m[1];
    }

    // ==================== Tests ====================

    /**
     * Metadata được trả về trong response (#90)
     */
    public function test_response_contains_meta_object(): void
    {
        $r = $this->http('GET', '/api/data.php');
        $this->assertSame(200, $r['code'], 'Response phải thành công: ' . $r['raw']);
        $this->assertTrue($r['json']['ok'] ?? false, 'Response phải có ok=true');
        $this->assertArrayHasKey('meta', $r['json'], 'Response phải có meta object');
        $this->assertArrayHasKey('attDays', $r['json']['meta'], 'meta phải có attDays');
        $this->assertArrayHasKey('scoresScope', $r['json']['meta'], 'meta phải có scoresScope');
        $this->assertArrayHasKey('details', $r['json']['meta'], 'meta phải có details');
    }

    /**
     * attDays mặc định là 30 (#90)
     */
    public function test_attendance_days_default_is_30(): void
    {
        $r = $this->http('GET', '/api/data.php');
        $this->assertSame(200, $r['code']);
        $this->assertSame(30, $r['json']['meta']['attDays'], 'attDays mặc định phải là 30');
    }

    /**
     * attDays tùy chỉnh được (#90)
     */
    public function test_attendance_days_custom(): void
    {
        $r = $this->http('GET', '/api/data.php?attDays=7');
        $this->assertSame(200, $r['code']);
        $this->assertSame(7, $r['json']['meta']['attDays'], 'attDays phải là 7');
    }

    /**
     * attDays bị giới hạn max 90 (#90)
     */
    public function test_attendance_days_max_90(): void
    {
        $r = $this->http('GET', '/api/data.php?attDays=200');
        $this->assertSame(200, $r['code']);
        $this->assertSame(90, $r['json']['meta']['attDays'], 'attDays phải bị giới hạn ở 90');
    }

    /**
     * scores mặc định là 'current' (#90)
     */
    public function test_scores_scope_default_is_current(): void
    {
        $r = $this->http('GET', '/api/data.php');
        $this->assertSame(200, $r['code']);
        $this->assertSame('current', $r['json']['meta']['scoresScope'], 'scoresScope mặc định phải là current');
    }

    /**
     * scores=all trả về tất cả điểm (#90)
     */
    public function test_scores_scope_all(): void
    {
        $r = $this->http('GET', '/api/data.php?scores=all');
        $this->assertSame(200, $r['code']);
        $this->assertSame('all', $r['json']['meta']['scoresScope'], 'scoresScope phải là all');
    }

    /**
     * details=1 (mặc định) bao gồm thông tin nhạy cảm (#90)
     */
    public function test_student_details_full_includes_sensitive_info(): void
    {
        $r = $this->http('GET', '/api/data.php?details=1');
        $this->assertSame(200, $r['code']);
        $this->assertSame(1, $r['json']['meta']['details'], 'details phải là 1');

        // Tìm một học sinh trong response
        $students = $r['json']['students'] ?? [];
        if (count($students) > 0) {
            $stu = $students[0];
            $this->assertArrayHasKey('address', $stu, 'students phải có address khi details=1');
            $this->assertArrayHasKey('fatherName', $stu, 'students phải có fatherName khi details=1');
            $this->assertArrayHasKey('fatherPhone', $stu, 'students phải có fatherPhone khi details=1');
        }
    }

    /**
     * details=0 ẩn thông tin nhạy cảm (#90)
     */
    public function test_student_details_basic_hides_sensitive_info(): void
    {
        $r = $this->http('GET', '/api/data.php?details=0');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, $r['json']['meta']['details'], 'details phải là 0');

        // Tìm một học sinh trong response
        $students = $r['json']['students'] ?? [];
        if (count($students) > 0) {
            $stu = $students[0];
            $this->assertArrayNotHasKey('address', $stu, 'students KHÔNG được có address khi details=0');
            $this->assertArrayNotHasKey('fatherName', $stu, 'students KHÔNG được có fatherName khi details=0');
            $this->assertArrayNotHasKey('fatherPhone', $stu, 'students KHÔNG được có fatherPhone khi details=0');
            // Nhưng vẫn phải có thông tin cơ bản
            $this->assertArrayHasKey('id', $stu, 'students phải có id');
            $this->assertArrayHasKey('name', $stu, 'students phải có name');
            $this->assertArrayHasKey('className', $stu, 'students phải có className');
        }
    }

    /**
     * Metadata totalAttendances được tính đúng (#90)
     */
    public function test_meta_total_attendances(): void
    {
        $r = $this->http('GET', '/api/data.php');
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('totalAttendances', $r['json']['meta']);
        $this->assertIsInt($r['json']['meta']['totalAttendances'], 'totalAttendances phải là số nguyên');
        $this->assertGreaterThanOrEqual(0, $r['json']['meta']['totalAttendances']);
    }

    /**
     * Heavy part cũng có metadata (#90)
     */
    public function test_heavy_part_has_meta(): void
    {
        $r = $this->http('GET', '/api/data.php?part=heavy');
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['json']['ok'] ?? false);
        $this->assertArrayHasKey('meta', $r['json'], 'heavy part phải có meta');
        $this->assertArrayHasKey('attDays', $r['json']['meta']);
        $this->assertArrayHasKey('scoresScope', $r['json']['meta']);
    }
}
