<?php
/**
 * Export API Tests — gọi export.php qua HTTP thật
 *
 * #115: ExportTest cũ không chạy dòng nào của export.php.
 * File NÀY khởi động php -S và gọi ĐÚNG endpoint api/export.php
 * bằng phiên đăng nhập + CSRF thật.
 *
 * Các chốt được kiểm:
 *   - accessible_class_ids: từ chối 403 khi không có quyền lớp
 *   - date range: từ chối ngày bắt đầu > ngày kết thúc, khoảng > 365 ngày
 *   - authorization: chỉ admin/BĐH được export toàn đoàn
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ExportApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';

    private int $yearId = 0;
    private int $adminId = 0;
    private int $memberIdGlv = 0;   // GLV chỉ có quyền lớp A
    private string $phoneGlv = '';
    private array $classA;            // lớp GLV được phân vào
    private array $classB;            // lớp KHÁC (GLV không có quyền)
    private array $createdStudentIds = [];
    private string $cookieJarGlv = '';
    private string $csrfGlv = '';

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
        $a = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1");
        $b = $a ? db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND id <> ? LIMIT 1",
            [$a['id']]
        ) : null;

        if (!$year || !$admin || !$a || !$b) {
            $this->markTestSkipped('Cần niên khoá hiện tại, admin, và ≥2 lớp ở 2 khối.');
        }
        $this->yearId  = (int) $year['id'];
        $this->adminId = (int) $admin['id'];
        [$this->classA, $this->classB] = [$a, $b];

        // Tạo GLV chỉ có quyền lớp A
        $this->phoneGlv = '09' . random_int(10000000, 99999999);
        $this->memberIdGlv = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'Export GLV', ?, ?, 'glv', 0)",
            ['EXPG' . random_int(100000, 999999), $this->phoneGlv, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', NULL, ?, 1, CURDATE(), ?)",
            [$this->memberIdGlv, $this->classA['id'], $this->adminId]
        );

        // Tạo học sinh để export
        $this->createdStudentIds[] = $this->makeStudent($this->classA['id']);

        $this->cookieJarGlv = tempnam(sys_get_temp_dir(), 'expapi');
        $this->loginGlv();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdStudentIds as $sid) {
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM students WHERE id = ?", [$sid]);
        }
        if ($this->memberIdGlv) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberIdGlv]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberIdGlv]);
        }
        if ($this->cookieJarGlv && file_exists($this->cookieJarGlv)) @unlink($this->cookieJarGlv);
        $this->createdStudentIds = [];
    }

    // ==================== HTTP helpers ====================

    private function http(string $method, string $path, ?array $json = null, bool $csrf = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && $this->csrfGlv) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrfGlv;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJarGlv);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJarGlv);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    private function loginGlv(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $this->phoneGlv, 'password' => 'Mk-Test-1'], false);
        $this->assertSame(200, $r['code'], 'Đăng nhập GLV test phải thành công: ' . $r['raw']);

        $page = $this->http('GET', '/index.php', null, false);
        $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m),
            'Không lấy được csrfToken');
        $this->csrfGlv = $m[1];
    }

    private function makeStudent(int $classId): int
    {
        $code = 'EXP' . random_int(100000, 999999);
        $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, 'Export Test Em', 1)", [$code]);
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$this->yearId, $sid, $classId, 'đang sinh hoạt']);
        return $sid;
    }

    // ==================== Tests ====================

    /**
     * accessible_class_ids: GLV chỉ có quyền lớp A phải bị 403 khi export lớp B
     */
    public function test_export_attendance_rejects_class_outside_scope(): void
    {
        // GLV chỉ có quyền lớp A, thử export lớp B
        $r = $this->http('POST', '/api/export.php?action=attendance',
            ['classId' => $this->classB['id'], 'format' => 'xlsx']);

        $this->assertSame(403, $r['code'], 'Phải từ chối 403 khi export lớp không có quyền');
        $this->assertFalse($r['json']['ok'] ?? true, 'Response phải có ok=false');
    }

    /**
     * accessible_class_ids: GLV được export lớp A (trong phạm vi)
     */
    public function test_export_attendance_allows_class_in_scope(): void
    {
        $r = $this->http('POST', '/api/export.php?action=attendance',
            ['classId' => $this->classA['id'], 'format' => 'xlsx']);

        $this->assertSame(200, $r['code'], 'Phải cho phép export lớp trong phạm vi: ' . ($r['json']['error'] ?? ''));
        $this->assertTrue($r['json']['ok'] ?? false, 'Response phải có ok=true');
        $this->assertArrayHasKey('sheet', $r['json'], 'Phải có sheet data');
    }

    /**
     * accessible_class_ids: GLV export không chỉ định lớp → chỉ export lớp trong phạm vi
     */
    public function test_export_attendance_without_class_id_uses_scope(): void
    {
        // Không chỉ định classId → chỉ export các lớp trong phạm vi
        $r = $this->http('POST', '/api/export.php?action=attendance',
            ['format' => 'xlsx']);

        $this->assertSame(200, $r['code'], 'Phải cho phép export với phạm vi: ' . ($r['json']['error'] ?? ''));
        $this->assertTrue($r['json']['ok'] ?? false);
    }

    /**
     * accessible_class_ids: GLV export scores lớp B → 403
     */
    public function test_export_scores_rejects_class_outside_scope(): void
    {
        $term = db_one("SELECT id FROM terms WHERE year_id = ? LIMIT 1", [$this->yearId]);
        if (!$term) {
            $this->markTestSkipped('Cần ít nhất 1 học kỳ trong niên khoá.');
        }

        $r = $this->http('POST', '/api/export.php?action=scores',
            ['termId' => $term['id'], 'classId' => $this->classB['id'], 'format' => 'xlsx']);

        $this->assertSame(403, $r['code'], 'Phải từ chối 403 khi export scores lớp không có quyền');
        $this->assertFalse($r['json']['ok'] ?? true);
    }

    /**
     * accessible_class_ids: attendance-detail action → 403 khi không có quyền
     */
    public function test_export_attendance_detail_rejects_class_outside_scope(): void
    {
        $today = date('Y-m-d');
        $fromDate = date('Y-m-d', strtotime('-30 days'));

        $r = $this->http('GET', "/api/export.php?action=attendance-detail&classId={$this->classB['id']}&fromDate={$fromDate}&toDate={$today}");

        $this->assertSame(403, $r['code'], 'Phải từ chối 403 khi export attendance-detail lớp không có quyền');
        $this->assertFalse($r['json']['ok'] ?? true);
    }

    /**
     * Date validation: fromDate > toDate → 400
     */
    public function test_export_attendance_detail_rejects_invalid_date_range(): void
    {
        $r = $this->http('GET', '/api/export.php?action=attendance-detail&fromDate=2026-09-30&toDate=2026-09-01');

        $this->assertSame(400, $r['code'], 'Phải từ chối 400 khi fromDate > toDate');
        $this->assertFalse($r['json']['ok'] ?? true);
        $this->assertStringContainsString('bắt đầu', strtolower($r['json']['error'] ?? ''));
    }

    /**
     * Date validation: range > 365 days → 400
     */
    public function test_export_attendance_detail_rejects_date_range_over_365_days(): void
    {
        $fromDate = '2025-01-01';
        $toDate = '2026-06-01'; // ~500 days

        $r = $this->http('GET', "/api/export.php?action=attendance-detail&fromDate={$fromDate}&toDate={$toDate}");

        $this->assertSame(400, $r['code'], 'Phải từ chối 400 khi khoảng > 365 ngày');
        $this->assertFalse($r['json']['ok'] ?? true);
        $this->assertStringContainsString('365', strtolower($r['json']['error'] ?? ''));
    }

    /**
     * Date validation: invalid date format → 400
     */
    public function test_export_attendance_detail_rejects_invalid_date_format(): void
    {
        $r = $this->http('GET', '/api/export.php?action=attendance-detail&fromDate=01-09-2026&toDate=30-09-2026');

        $this->assertSame(400, $r['code'], 'Phải từ chối 400 khi định dạng ngày sai');
        $this->assertFalse($r['json']['ok'] ?? true);
    }

    /**
     * Authorization: accessible_class_ids với classId không chỉ định + phạm vi rỗng → 403
     */
    public function test_export_rejects_when_no_class_access(): void
    {
        // Tạo user không có phân công nào
        $phoneNoAsn = '09' . random_int(10000000, 99999999);
        $memberIdNoAsn = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'No Assign', ?, ?, 'glv', 0)",
            ['NOASN' . random_int(100000, 999999), $phoneNoAsn, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        $cookieJar = tempnam(sys_get_temp_dir(), 'expnoasn');

        try {
            // Login
            $ch = curl_init(self::$base . '/api/auth.php?action=login');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['phone' => $phoneNoAsn, 'password' => 'Mk-Test-1']),
                CURLOPT_COOKIEJAR => $cookieJar,
                CURLOPT_COOKIEFILE => $cookieJar,
            ]);
            curl_exec($ch);
            curl_close($ch);

            // Export without classId - should fail because no assignments
            $ch = curl_init(self::$base . '/api/export.php?action=attendance');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS => json_encode(['format' => 'xlsx']),
                CURLOPT_COOKIEFILE => $cookieJar,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $json = json_decode($body, true);

            $this->assertSame(403, $code, 'Phải từ chối 403 khi không có phân công nào');
            $this->assertFalse($json['ok'] ?? true);
        } finally {
            @unlink($cookieJar);
            db_run("DELETE FROM members WHERE id = ?", [$memberIdNoAsn]);
        }
    }

    /**
     * accessible_class_ids unit test: kiểm tra giá trị trả về đúng
     */
    public function test_accessible_class_ids_returns_correct_scope(): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        // GLV chỉ có quyền lớp A
        $meGlv = ['id' => $this->memberIdGlv, 'role_code' => 'glv'];
        $ids = accessible_class_ids($meGlv, 'attendance', 'view');

        $this->assertIsArray($ids, 'Phải trả về mảng');
        $this->assertContains($this->classA['id'], $ids, 'Phải chứa lớp A');
        $this->assertNotContains($this->classB['id'], $ids, 'Không được chứa lớp B');
    }

    /**
     * accessible_class_ids: admin → null (toàn đoàn)
     */
    public function test_accessible_class_ids_returns_null_for_admin(): void
    {
        require_once __DIR__ . '/../../public/api/_bootstrap.php';

        $meAdmin = ['id' => $this->adminId, 'role_code' => 'admin'];
        $ids = accessible_class_ids($meAdmin, 'attendance', 'view');

        $this->assertNull($ids, 'Admin phải nhận null (toàn đoàn)');
    }
}
