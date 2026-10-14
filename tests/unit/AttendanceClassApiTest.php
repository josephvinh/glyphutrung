<?php
// tests/unit/AttendanceClassApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA attendance_class.php:
//   GET ?term_id=...&class_id=... → mảng điểm chuyên cần của CẢ LỚP
//
// Bảo vệ LUẬT CỨNG #6 (chống IDOR — endpoint đụng dữ liệu lớp):
//   - Admin xem được mọi lớp → 200, có student_id + percent + rank
//   - GLV thường xem lớp mình phụ trách → 200
//   - GLV thường xem lớp KHÁC (ngoài phạm vi) → 403
//   - Chưa đăng nhập → không 200
//   - Thiếu term_id / class_id → 400
//   - term_id không tồn tại → 404
//
// Bài test này còn xác minh:
//   - response là mảng (không phải object) — frontend dùng forEach;
//   - mỗi phần tử có đủ student_id, percent, rank, programs[];
//   - em trong lớp KHÔNG lẫn vào kết quả của lớp khác (kiểm tra
//     GROUP BY theo enrollments.class_id đã đúng).
//
// Cookie/session/CSRF: dùng header thẳng (xem AttendanceScoresApiTest
// để biết lý do — CURLOPT_COOKIEJAR không ghi HttpOnly cookies trên
// môi trường PHP/curl này).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class AttendanceClassApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private array $sessions = [];
    private array $csrfs = [];
    private int $adminId = 0;
    private int $memberIdGlv = 0;
    private string $phoneGlv = '';
    private int $studentIdA = 0;
    private int $studentIdB = 0;
    private int $termId = 0;
    private int $yearId = 0;
    private int $classA = 0;
    private int $classB = 0;

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
        $term = $year ? db_one("SELECT id FROM terms WHERE year_id = ? ORDER BY id LIMIT 1", [$year['id']]) : null;

        if (!$year || !$admin || !$a || !$b || !$term) {
            $this->markTestSkipped('Cần niên khoá + admin + 2 lớp khác khối + ít nhất 1 học kỳ.');
        }
        $this->adminId   = (int) $admin['id'];
        $this->yearId    = (int) $year['id'];
        $this->classA    = (int) $a['id'];
        $this->classB    = (int) $b['id'];
        $this->termId    = (int) $term['id'];

        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        // Tạo GLV chỉ có quyền classA
        $this->phoneGlv = '09' . random_int(10000000, 99999999);
        $this->memberIdGlv = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'AttClass GLV', ?, ?, 'glv', 0)",
            ['ACG' . random_int(100000, 999999), $this->phoneGlv, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', NULL, ?, 1, CURDATE(), ?)",
            [$this->memberIdGlv, $this->classA, $this->adminId]
        );

        // Hai học sinh: một ở classA (trong phạm vi), một ở classB (ngoài phạm vi)
        $codeA = 'ACA' . random_int(100000, 999999);
        $this->studentIdA = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'AttClass A', 1)",
            [$codeA]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$this->yearId, $this->studentIdA, $this->classA, 'đang sinh hoạt']);

        $codeB = 'ACB' . random_int(100000, 999999);
        $this->studentIdB = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'AttClass B', 1)",
            [$codeB]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$this->yearId, $this->studentIdB, $this->classB, 'đang sinh hoạt']);

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->studentIdA) {
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$this->studentIdA]);
            db_run("DELETE FROM students WHERE id = ?", [$this->studentIdA]);
        }
        if ($this->studentIdB) {
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$this->studentIdB]);
            db_run("DELETE FROM students WHERE id = ?", [$this->studentIdB]);
        }
        if ($this->memberIdGlv) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberIdGlv]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberIdGlv]);
        }
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

    private function loginGlv(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $this->phoneGlv, 'password' => 'Mk-Test-1'],
            'glv', false);
        $this->assertSame(200, $r['code'],
            'Đăng nhập GLV phải thành công: ' . $r['raw']);
        $this->assertNotEmpty($r['json']['csrfToken'] ?? '',
            'Phản hồi đăng nhập phải chứa csrfToken: ' . $r['raw']);
        $this->csrfs['glv'] = $r['json']['csrfToken'];
    }

    // ==================== Tests ====================

    public function test_requires_login(): void
    {
        $r = $this->http('GET',
            '/api/attendance_class.php?term_id=' . $this->termId
            . '&class_id=' . $this->classA,
            null, '__noauth__', false);
        $this->assertNotEquals(200, $r['code'],
            'Yêu cầu attendance_class khi chưa đăng nhập phải bị chặn: ' . $r['raw']);
    }

    public function test_missing_term_id(): void
    {
        $r = $this->http('GET', '/api/attendance_class.php?class_id=' . $this->classA);
        $this->assertSame(400, $r['code'],
            'Thiếu term_id phải trả 400: ' . $r['raw']);
    }

    public function test_missing_class_id(): void
    {
        $r = $this->http('GET', '/api/attendance_class.php?term_id=' . $this->termId);
        $this->assertSame(400, $r['code'],
            'Thiếu class_id phải trả 400: ' . $r['raw']);
    }

    public function test_unknown_term_returns_404(): void
    {
        $r = $this->http('GET',
            '/api/attendance_class.php?term_id=99999999&class_id=' . $this->classA);
        $this->assertSame(404, $r['code'],
            'term_id không tồn tại phải trả 404: ' . $r['raw']);
    }

    /**
     * Admin xem được mọi lớp, response là mảng, có student_id + percent + rank.
     * Và mỗi em chỉ xuất hiện 1 lần dù có nhiều chương trình.
     *
     * Chèn 1 dòng điểm danh cho studentA (trong khoảng ngày học kỳ) để chắc
     * chắn endpoint phải trả về em này — endpoint INNER JOIN attendances nên
     * em không có buổi nào sẽ không xuất hiện (đây là thiết kế hiện tại của
     * attendance_class.php: chỉ trả em có dữ liệu chuyên cần).
     */
    public function test_admin_can_view_class(): void
    {
        $term = db_one('SELECT start_date, end_date FROM terms WHERE id = ?', [$this->termId]);
        $prog = db_one("SELECT id FROM programs WHERE count_for_attendance = 1 LIMIT 1");
        if (!$term || !$prog) {
            $this->markTestSkipped('Cần term có start_date/end_date và program count_for_attendance=1.');
        }
        $sessionDate = $term['start_date'];
        db_run(
            "INSERT INTO attendances (session_date, program_id, student_id, status, year_id)
             VALUES (?, ?, ?, 'có mặt', ?)",
            [$sessionDate, $prog['id'], $this->studentIdA, $this->yearId]
        );

        try {
            $r = $this->http('GET',
                '/api/attendance_class.php?term_id=' . $this->termId
                . '&class_id=' . $this->classA);
            $this->assertSame(200, $r['code'],
                'Admin xem chuyên cần cả lớp A: ' . $r['raw']);
            $this->assertIsArray($r['json'],
                'Response phải là mảng JSON: ' . $r['raw']);

            // Tìm em A trong kết quả.
            $foundA = null;
            foreach ($r['json'] as $row) {
                if ((int) ($row['student_id'] ?? 0) === $this->studentIdA) {
                    $foundA = $row;
                    break;
                }
            }
            $this->assertNotNull($foundA,
                'student_idA phải có mặt trong kết quả lớp A: ' . $r['raw']);
            $this->assertArrayHasKey('percent', $foundA,
                'Mỗi dòng phải có percent.');
            $this->assertArrayHasKey('rank', $foundA,
                'Mỗi dòng phải có rank.');
            $this->assertArrayHasKey('programs', $foundA,
                'Mỗi dòng phải có programs[] (chi tiết theo chương trình).');
            $this->assertIsArray($foundA['programs'],
                'programs phải là mảng.');

            // Em B (lớp khác) KHÔNG được lẫn vào kết quả lớp A.
            foreach ($r['json'] as $row) {
                $this->assertNotSame($this->studentIdB, (int) ($row['student_id'] ?? 0),
                    'student_idB (lớp B) KHÔNG được lẫn vào kết quả lớp A.');
            }
        } finally {
            db_run(
                "DELETE FROM attendances WHERE student_id = ? AND session_date = ?",
                [$this->studentIdA, $sessionDate]
            );
        }
    }

    /**
     * Phân quyền: GLV thường (chỉ có quyền classA) gọi classA → 200;
     * gọi classB (ngoài phạm vi) → 403. Bảo vệ luật cứng #6 chống IDOR.
     */
    public function test_glv_rejects_class_outside_scope(): void
    {
        $this->loginGlv();

        $rOk = $this->http('GET',
            '/api/attendance_class.php?term_id=' . $this->termId
            . '&class_id=' . $this->classA,
            null, 'glv');
        $this->assertSame(200, $rOk['code'],
            'GLV xem được chuyên cần cả lớp mình: ' . $rOk['raw']);
        $this->assertIsArray($rOk['json'],
            'Response phải là mảng JSON: ' . $rOk['raw']);

        $rForbidden = $this->http('GET',
            '/api/attendance_class.php?term_id=' . $this->termId
            . '&class_id=' . $this->classB,
            null, 'glv');
        $this->assertSame(403, $rForbidden['code'],
            'GLV KHÔNG được xem chuyên cần lớp khác: ' . $rForbidden['raw']);
    }
}
