<?php
// tests/unit/AttendanceThresholdsApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA attendance_thresholds.php:
// - POST  { label, min_percent, description }  → thêm mốc chuyên cần
// - PUT   ?id=X { label, min_percent, ... }     → cập nhật mốc
// - GET   (mặc định)                            → danh sách (sort theo min_percent DESC)
//
// Mục 11 review #285 — 4 vấn đề cần chặn:
//   - max(0, min(100, (float)$raw)) clamp TRƯỚC khi kiểm tra →
//     lỗi "ngưỡng phải 0..100" không bao giờ chạy được
//   - (float)$raw nuốt chuỗi rác thành 0 im lặng
//   - sort_order = MAX+1 nên thứ tự phụ thuộc thời điểm thêm, không theo
//     min_percent → xếp loại có thể sai
//   - không chặn trùng label / trùng min_percent
//
// Test ở đây chỉ tập trung vào 3 điểm đầu của mục 11 (validate + chặn trùng).
// Sort theo min_percent DESC đã được bảo vệ bằng test khác (GET trả về
// theo đúng thứ tự).
//
// Cookie/session/CSRF: dùng header thẳng (xem ScoreRulesApiTest để biết lý do —
// CURLOPT_COOKIEJAR không ghi HttpOnly cookies trên môi trường PHP/curl này).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class AttendanceThresholdsApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private array $sessions = [];
    private array $csrfs = [];
    /** Danh sách id mốc đã tạo trong test, để cleanup. */
    private array $cleanupIds = [];
    private int $adminId = 0;

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

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        // Cleanup: xoá các mốc đã tạo trong test (bằng id)
        foreach ($this->cleanupIds as $id) {
            db_run('DELETE FROM attendance_thresholds WHERE id = ?', [$id]);
        }
        $this->cleanupIds = [];

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

    /**
     * Tạo mốc qua POST, trả về id để cleanup. KHÔNG tự register cleanup —
     * gọi addCleanup() nếu muốn.
     */
    private function createThreshold(string $label, float $minPercent): int
    {
        $r = $this->http('POST', '/api/attendance_thresholds.php', [
            'label'       => $label,
            'min_percent' => $minPercent,
        ]);
        $this->assertSame(200, $r['code'],
            'Tạo mốc phải thành công: ' . $r['raw']);
        $id = (int) ($r['json']['id'] ?? 0);
        if ($id <= 0) {
            // Nếu API không trả id, lấy qua GET
            $list = $this->http('GET', '/api/attendance_thresholds.php');
            foreach ((array) $list['json'] as $t) {
                if (($t['label'] ?? '') === $label) {
                    $id = (int) $t['id'];
                    break;
                }
            }
        }
        $this->assertGreaterThan(0, $id, 'Phải tạo được mốc với id hợp lệ');
        return $id;
    }

    private function addCleanup(int $id): void
    {
        $this->cleanupIds[] = $id;
    }

    // ==================== Tests ====================

    public function test_post_rejects_string_min_percent(): void
    {
        // Dùng label ngẫu nhiên để không đụng seed hay test khác
        $label = 'thstr' . substr((string) time(), -5) . random_int(100, 999);
        $r = $this->http('POST', '/api/attendance_thresholds.php', [
            'label'       => $label,
            'min_percent' => 'abc',
        ]);
        $this->assertSame(400, $r['code'],
            'min_percent không phải số phải 400: ' . $r['raw']);
    }

    public function test_post_rejects_out_of_range_min_percent(): void
    {
        $label = 'thoor' . substr((string) time(), -5) . random_int(100, 999);
        $r = $this->http('POST', '/api/attendance_thresholds.php', [
            'label'       => $label,
            'min_percent' => 150,
        ]);
        $this->assertSame(400, $r['code'],
            'min_percent > 100 phải 400: ' . $r['raw']);
    }

    public function test_post_rejects_duplicate_label(): void
    {
        // Tạo 'A' (label ngẫu nhiên tránh đụng seed) trước
        $code = 'att_th' . substr((string) time(), -5) . random_int(100, 999);

        // Lần POST đầu: phải tạo được
        $first = $this->http('POST', '/api/attendance_thresholds.php', [
            'label'       => $code,
            'min_percent' => 50,
        ]);
        $this->assertSame(200, $first['code'],
            'Tạo mốc đầu tiên phải thành công: ' . $first['raw']);

        // Lấy id để cleanup
        $list = $this->http('GET', '/api/attendance_thresholds.php');
        $createdId = 0;
        foreach ((array) $list['json'] as $t) {
            if (($t['label'] ?? '') === $code) {
                $createdId = (int) $t['id'];
                break;
            }
        }
        if ($createdId > 0) {
            $this->addCleanup($createdId);
        }

        // Lần POST thứ hai: cùng label, min_percent khác → 409
        $r = $this->http('POST', '/api/attendance_thresholds.php', [
            'label'       => $code,
            'min_percent' => 60,
        ]);
        $this->assertSame(409, $r['code'],
            'Trùng label phải 409: ' . $r['raw']);
    }
}
