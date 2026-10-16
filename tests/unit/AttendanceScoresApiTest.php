<?php
// tests/unit/AttendanceScoresApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA attendance_scores.php:
//   GET ?term_id=...&student_id=... → điểm chuyên cần của một em trong học kỳ
//
// Bảo vệ LUẬT CỨNG #6 (chống IDOR — endpoint đụng dữ liệu lớp):
//   - GLV thường gọi cho em lớp mình phụ trách → 200
//   - GLV thường gọi cho em lớp khác (ngoài phạm vi) → 403
//   - Chưa đăng nhập → 401
//
// Cookie/session/CSRF: dùng header thẳng (xem ScoreRulesApiTest để biết lý do —
// CURLOPT_COOKIEJAR không ghi HttpOnly cookies trên môi trường PHP/curl này).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class AttendanceScoresApiTest extends TestCase
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
        $this->classA    = (int) $a['id'];
        $this->classB    = (int) $b['id'];
        $this->termId    = (int) $term['id'];

        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        // Tạo GLV chỉ có quyền classA
        $this->phoneGlv = '09' . random_int(10000000, 99999999);
        $this->memberIdGlv = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'AttScores GLV', ?, ?, 'glv', 0)",
            ['ASG' . random_int(100000, 999999), $this->phoneGlv, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', NULL, ?, 1, CURDATE(), ?)",
            [$this->memberIdGlv, $this->classA, $this->adminId]
        );

        // Hai học sinh: một ở classA (trong phạm vi), một ở classB (ngoài phạm vi)
        $codeA = 'ASA' . random_int(100000, 999999);
        $this->studentIdA = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'AttScores A', 1)",
            [$codeA]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$year['id'], $this->studentIdA, $this->classA, 'đang sinh hoạt']);

        $codeB = 'ASB' . random_int(100000, 999999);
        $this->studentIdB = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'AttScores B', 1)",
            [$codeB]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$year['id'], $this->studentIdB, $this->classB, 'đang sinh hoạt']);

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
            '/api/attendance_scores.php?term_id=' . $this->termId
            . '&student_id=' . $this->studentIdA,
            null, '__noauth__', false);
        $this->assertNotEquals(200, $r['code'],
            'Yêu cầu attendance_scores khi chưa đăng nhập phải bị chặn: ' . $r['raw']);
    }

    public function test_admin_can_view_any_student(): void
    {
        $r = $this->http('GET',
            '/api/attendance_scores.php?term_id=' . $this->termId
            . '&student_id=' . $this->studentIdA);
        $this->assertSame(200, $r['code'],
            'Admin xem được chuyên cần của em lớp A: ' . $r['raw']);
        $this->assertSame($this->studentIdA, $r['json']['student_id'] ?? null);
    }

    /**
     * Phân quyền: GLV thường (chỉ có quyền classA) gọi cho em ở classA → 200,
     * cho em ở classB (ngoài phạm vi) → 403. Bảo vệ luật cứng #6 chống IDOR.
     */
    public function test_glv_rejects_class_outside_scope(): void
    {
        $this->loginGlv();

        $rOk = $this->http('GET',
            '/api/attendance_scores.php?term_id=' . $this->termId
            . '&student_id=' . $this->studentIdA,
            null, 'glv');
        $this->assertSame(200, $rOk['code'],
            'GLV xem được chuyên cần em lớp mình: ' . $rOk['raw']);

        $rForbidden = $this->http('GET',
            '/api/attendance_scores.php?term_id=' . $this->termId
            . '&student_id=' . $this->studentIdB,
            null, 'glv');
        $this->assertSame(403, $rForbidden['code'],
            'GLV KHÔNG được xem chuyên cần em lớp khác: ' . $rForbidden['raw']);
    }

    /**
     * Review mục 8: 'JOIN terms t ON t.year_id = a.year_id' trước đây đếm buổi
     * của CẢ niên khoá, không lọc theo term.start_date/end_date. Bài test:
     *   1. Tạo program FRESH (chưa có buổi nào trong kỳ), gắn vào term_programs.
     *   2. Chèn 1 dòng điểm danh cho studentA với session_date NGOÀI khoảng
     *      [start_date, end_date] của term.
     *   3. Gọi endpoint.
     *   4. Với program fresh này, expected phải = 0 (vì buổi ngoài kỳ KHÔNG
     *      được tính). Nếu code lỗi cũ JOIN theo year, expected = 1.
     *
     * Bài test này xác minh: mọi buổi ngoài khoảng [start_date, end_date] đều
     * bị bỏ — kể cả buổi trong CÙNG niên khoá. Response detail[] per-program
     * chỉ chứa tổng đếm (không kèm session_date), nên không assert được per-row;
     * cách gián tiếp ở trên (program fresh) là cách chính xác nhất có thể.
     */
    public function test_expected_sessions_filter_by_term_date_range(): void
    {
        $year = current_year();
        $term = db_one('SELECT id, start_date, end_date FROM terms WHERE id = ?', [$this->termId]);
        if (!$year || !$term) {
            $this->markTestSkipped('Cần niên khoá hiện tại + 1 học kỳ.');
        }
        if (strtotime($term['start_date']) === false || strtotime($term['end_date']) === false) {
            $this->markTestSkipped('term thiếu start_date/end_date hợp lệ.');
        }

        // Ngày NGOÀI khoảng học kỳ: lùi 1 năm so với start_date. Cùng year_id
        // với cả niên khoá nên sẽ bị ĐẾM NHẦM nếu JOIN chỉ theo year_id.
        $outside = date('Y-m-d', strtotime($term['start_date'] . ' -1 year'));

        // 1. Tạo program fresh cho năm hiện tại, count_for_attendance=1.
        $progName = 'TAS Outside ' . random_int(100000, 999999);
        $progId = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance,
                                   start_time, day_of_week, sort_order)
             VALUES (?, ?, 'bắt buộc', 'kích hoạt', 1, '08:00:00', 0, 99)",
            [$year['id'], $progName]
        );
        // Gắn program vào term_programs để endpoint liệt kê nó trong detail[].
        db_run(
            "INSERT INTO term_programs (term_id, program_id, is_active) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE is_active = 1",
            [$this->termId, $progId]
        );

        // 2. Chèn 1 dòng điểm danh NGOÀI khoảng học kỳ.
        db_run(
            "INSERT INTO attendances (session_date, program_id, student_id, status, year_id)
             VALUES (?, ?, ?, 'có mặt', ?)",
            [$outside, $progId, $this->studentIdA, $year['id']]
        );

        try {
            $r = $this->http('GET',
                '/api/attendance_scores.php?term_id=' . $this->termId
                . '&student_id=' . $this->studentIdA);
            $this->assertSame(200, $r['code'],
                'Endpoint phải trả 200 khi có buổi ngoài khoảng ngày: ' . $r['raw']);
            $this->assertArrayHasKey('detail', $r['json'] ?? [],
                'Response phải có key "detail".');

            // 3. Tìm program fresh trong detail[] và assert expected == 0.
            $foundProg = null;
            foreach (($r['json']['detail'] ?? []) as $d) {
                if ((int) ($d['program_id'] ?? 0) === $progId) {
                    $foundProg = $d;
                    break;
                }
            }
            $this->assertNotNull($foundProg,
                'Program fresh phải xuất hiện trong detail[] (đã gắn term_programs). '
                . 'Response: ' . $r['raw']);
            $this->assertSame(0, (int) ($foundProg['expected'] ?? -1),
                'Buổi NGOÀI khoảng [start_date, end_date] không được tính vào expected. '
                . 'Nếu = 1 thì endpoint vẫn đang đếm cả niên khoá (lỗi JOIN theo year_id). '
                . 'Response: ' . $r['raw']);
        } finally {
            // 4. Dọn dẹp: xoá attendance, term_programs, program theo đúng thứ tự FK.
            db_run('DELETE FROM attendances WHERE student_id = ? AND session_date = ?',
                [$this->studentIdA, $outside]);
            db_run('DELETE FROM term_programs WHERE program_id = ?', [$progId]);
            db_run('DELETE FROM programs WHERE id = ?', [$progId]);
        }
    }
}
