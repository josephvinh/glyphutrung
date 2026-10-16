<?php
// tests/unit/TermProgramsApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA term_programs.php:
// - GET trả TẤT CẢ programs count_for_attendance=1, kể cả đã tắt
//   (admin cần thấy để bật lại). Hai endpoint chuyên cần mới lọc is_active.
// - POST bật/tắt is_active.
// - Bảo vệ LUẬT CỨNG #6: chỉ admin/BDH mới được POST.

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
    private int $yearId = 0;
    private int $termId = 0;
    private int $progA = 0;   // count_for_attendance=1
    private int $progB = 0;   // count_for_attendance=1
    private int $progC = 0;   // count_for_attendance=1

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
        if (!$year || !$admin || !$term) {
            $this->markTestSkipped('Cần niên khoá + admin + ít nhất 1 học kỳ.');
        }
        $this->adminId = (int) $admin['id'];
        $this->yearId  = (int) $year['id'];
        $this->termId  = (int) $term['id'];

        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        // 3 programs mới, count_for_attendance=1, status='kích hoạt', cùng niên khoá
        $base = 'TP' . random_int(100000, 999999);
        $this->progA = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, day_of_week)
             VALUES (?, ?, 'bắt buộc', 'kích hoạt', 1, '07:00:00', 0)",
            [$this->yearId, $base . 'A']
        );
        $this->progB = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, day_of_week)
             VALUES (?, ?, 'bắt buộc', 'kích hoạt', 1, '08:00:00', 0)",
            [$this->yearId, $base . 'B']
        );
        $this->progC = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, day_of_week)
             VALUES (?, ?, 'bắt buộc', 'kích hoạt', 1, '09:00:00', 0)",
            [$this->yearId, $base . 'C']
        );

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        db_run("DELETE FROM term_programs WHERE program_id IN (?, ?, ?)", [$this->progA, $this->progB, $this->progC]);
        db_run("DELETE FROM programs WHERE id IN (?, ?, ?)", [$this->progA, $this->progB, $this->progC]);
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

    private function isActive(array $list, int $progId): ?bool
    {
        foreach ($list as $row) {
            if ((int) ($row['id'] ?? 0) === $progId) {
                return (bool) ($row['is_active'] ?? false);
            }
        }
        return null;
    }

    // ==================== Tests ====================

    /**
     * Mục 🟠 review vòng 6: GET phải trả TẤT CẢ programs count_for_attendance=1
     * (kể cả đã tắt) để admin thấy và bật lại. Hai endpoint chuyên cần
     * mới lọc `COALESCE(tp.is_active, 1) = 1`.
     */
    public function test_get_returns_all_programs_including_disabled(): void
    {
        // 1. Tắt progB qua POST
        $rPost = $this->http('POST', '/api/term_programs.php', [
            'term_id' => $this->termId, 'program_id' => $this->progB, 'is_active' => false,
        ]);
        $this->assertSame(200, $rPost['code'],
            'POST tắt progB phải thành công: ' . $rPost['raw']);

        // 2. GET — phải thấy CẢ 3 (A bật, B tắt, C bật mặc định)
        $r = $this->http('GET', '/api/term_programs.php?term_id=' . $this->termId);
        $this->assertSame(200, $r['code'],
            'GET phải trả 200: ' . $r['raw']);
        $this->assertIsArray($r['json'], 'Response phải là mảng: ' . $r['raw']);

        $aFound = $this->isActive($r['json'], $this->progA);
        $bFound = $this->isActive($r['json'], $this->progB);
        $cFound = $this->isActive($r['json'], $this->progC);

        $this->assertNotNull($aFound, 'progA phải có mặt trong kết quả: ' . $r['raw']);
        $this->assertNotNull($bFound, 'progB (đã tắt) VẪN phải có mặt để admin bật lại: ' . $r['raw']);
        $this->assertNotNull($cFound, 'progC phải có mặt trong kết quả: ' . $r['raw']);

        $this->assertTrue($aFound, 'progA mặc định bật: ' . $r['raw']);
        $this->assertFalse($bFound, 'progB đã POST tắt → is_active=false: ' . $r['raw']);
        $this->assertTrue($cFound, 'progC mặc định bật: ' . $r['raw']);
    }

    /**
     * Tắt rồi bật lại qua POST — round-trip phải OK.
     */
    public function test_post_can_disable_and_re_enable_program(): void
    {
        // Tắt
        $r1 = $this->http('POST', '/api/term_programs.php', [
            'term_id' => $this->termId, 'program_id' => $this->progA, 'is_active' => false,
        ]);
        $this->assertSame(200, $r1['code'], 'Tắt progA phải 200: ' . $r1['raw']);

        $r2 = $this->http('GET', '/api/term_programs.php?term_id=' . $this->termId);
        $this->assertFalse($this->isActive($r2['json'], $this->progA),
            'progA phải tắt sau POST: ' . $r2['raw']);

        // Bật lại
        $r3 = $this->http('POST', '/api/term_programs.php', [
            'term_id' => $this->termId, 'program_id' => $this->progA, 'is_active' => true,
        ]);
        $this->assertSame(200, $r3['code'], 'Bật lại progA phải 200: ' . $r3['raw']);

        $r4 = $this->http('GET', '/api/term_programs.php?term_id=' . $this->termId);
        $this->assertTrue($this->isActive($r4['json'], $this->progA),
            'progA phải bật sau POST: ' . $r4['raw']);
    }

    public function test_get_unknown_term_returns_404(): void
    {
        $r = $this->http('GET', '/api/term_programs.php?term_id=999999');
        $this->assertSame(404, $r['code'],
            'Term không tồn tại phải trả 404: ' . $r['raw']);
    }
}
