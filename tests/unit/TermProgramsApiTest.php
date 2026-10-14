<?php
// tests/unit/TermProgramsApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA term_programs.php:
//   GET  ?term_id=…        → mảng programs theo học kỳ
//   POST { term_id, program_id, is_active } → bật/tắt chương trình
//
// Bảo vệ các luật cứng liên quan:
//   - #5 "Không nuốt lỗi dữ liệu quan trọng": POST phải 404 (KHÔNG 500
//     do FK violation) khi term_id / program_id không tồn tại. Lỗi 500
//     che giấu vấn đề, frontend phải đoán từ stacktrace.
//   - #6 "Không làm yếu phân quyền": POST chỉ admin/bdh; GET cần đăng
//     nhập (require_login).
//
// Cookie/session/CSRF: dùng header thẳng (xem AttendanceClassApiTest
// để biết lý do — CURLOPT_COOKIEJAR không ghi HttpOnly cookies trên
// môi trường PHP/curl này).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class TermProgramsApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private array $sessions = [];
    private array $csrfs = [];
    private int $adminId = 0;
    private int $termId = 0;
    private int $programId = 0;

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
        $term = $year ? db_one("SELECT id FROM terms WHERE year_id = ? ORDER BY id LIMIT 1", [$year['id']]) : null;
        $prog = db_one("SELECT id FROM programs WHERE count_for_attendance = 1 LIMIT 1");

        if (!$year || !$admin || !$term || !$prog) {
            $this->markTestSkipped('Cần niên khoá + admin + học kỳ + program count_for_attendance=1.');
        }
        $this->adminId  = (int) $admin['id'];
        $this->termId   = (int) $term['id'];
        $this->programId = (int) $prog['id'];

        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->adminId) {
            db_run('UPDATE members SET must_change_pw = 1 WHERE id = ?', [$this->adminId]);
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

    // ==================== Tests ====================

    public function test_post_term_programs_rejects_unknown_term(): void
    {
        $r = $this->http('POST', '/api/term_programs.php', [
            'term_id' => 99999999, 'program_id' => 1, 'is_active' => true,
        ]);
        $this->assertSame(404, $r['code'],
            'term_id không tồn tại phải 404 (không phải 500 FK): ' . $r['raw']);
    }

    public function test_post_term_programs_rejects_unknown_program(): void
    {
        $r = $this->http('POST', '/api/term_programs.php', [
            'term_id' => $this->termId, 'program_id' => 99999999, 'is_active' => true,
        ]);
        $this->assertSame(404, $r['code'],
            'program_id không tồn tại phải 404: ' . $r['raw']);
    }
}
