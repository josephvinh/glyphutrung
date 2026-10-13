<?php
// tests/unit/ScoresExamNumApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA scores.php với exam_num:
// - POST action=set → lưu điểm với exam_num (verify bằng DB)
// - POST action=delete_exam → xoá bài và renumber (verify bằng DB)
//
// Bảo vệ LUẬT CỨNG #6:
//   - require_write + kiểm phạm vi class
//
// Lưu ý: scores.php KHÔNG có action=list (UI đọc từ data.php). Vì vậy
// verify điểm sau khi set bằng cách truy vấn thẳng DB — chính xác hơn
// và phụ thuộc ít vào cấu trúc phản hồi của data.php.
//
// Cookie/session: dùng header Cookie thẳng, không qua cookie jar (xem
// ScoreRulesApiTest để biết lý do).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoresExamNumApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private array $sessions = [];
    private array $csrfs = [];
    private int $adminId = 0;
    private int $memberIdGlv = 0;
    private string $phoneGlv = '';
    private int $studentId = 0;
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

        // Bỏ cờ buộc đổi mật khẩu của admin mặc định.
        db_run('UPDATE members SET must_change_pw = 0 WHERE id = ?', [$this->adminId]);

        // Tạo GLV chỉ có quyền classA (dùng test "lớp khác → 403")
        $this->phoneGlv = '09' . random_int(10000000, 99999999);
        $this->memberIdGlv = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'ScoresExam GLV', ?, ?, 'glv', 0)",
            ['SCEG' . random_int(100000, 999999), $this->phoneGlv, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, 'glv', NULL, ?, 1, CURDATE(), ?)",
            [$this->memberIdGlv, $this->classA, $this->adminId]
        );

        // Tạo học sinh trong classA
        $code = 'SCOR' . random_int(100000, 999999);
        $this->studentId = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'Score Exam Em', 1)",
            [$code]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$year['id'], $this->studentId, $this->classA, 'đang sinh hoạt']);

        $this->loginAdmin();
    }

    protected function tearDown(): void
    {
        if ($this->studentId) {
            db_run("DELETE FROM scores WHERE student_id = ?", [$this->studentId]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$this->studentId]);
            db_run("DELETE FROM students WHERE id = ?", [$this->studentId]);
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

    private function scoreExists(int $studentId, int $termId, string $type, int $examNum): ?float
    {
        $row = db_one(
            'SELECT value FROM scores
             WHERE student_id=? AND term_id=? AND type_code=? AND exam_num=?',
            [$studentId, $termId, $type, $examNum]
        );
        return $row === null ? null : (float) $row['value'];
    }

    // ==================== Tests ====================

    public function test_requires_login(): void
    {
        $r = $this->http('POST', '/api/scores.php',
            ['action' => 'set', 'studentId' => 1, 'termId' => 1,
             'type' => 'mieng', 'exam_num' => 1, 'value' => '5'],
            '__noauth__', false);
        $this->assertNotEquals(200, $r['code'],
            'Yêu cầu scores.php khi chưa đăng nhập phải bị chặn: ' . $r['raw']);
    }

    public function test_set_score_with_exam_num(): void
    {
        $r = $this->http('POST', '/api/scores.php', [
            'action'   => 'set',
            'studentId' => $this->studentId,
            'termId'    => $this->termId,
            'type'      => 'mieng',
            'exam_num'  => 1,
            'value'     => '8.5',
        ]);
        $this->assertSame(200, $r['code'], 'set score phải thành công: ' . $r['raw']);

        $v = $this->scoreExists($this->studentId, $this->termId, 'mieng', 1);
        $this->assertNotNull($v, 'Điểm bài 1 phải được lưu');
        $this->assertEquals(8.5, $v);
    }

    public function test_set_score_multiple_exams_same_type(): void
    {
        $this->http('POST', '/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p15',
            'exam_num' => 1, 'value' => '7.0',
        ]);
        $this->http('POST', '/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p15',
            'exam_num' => 2, 'value' => '8.0',
        ]);

        $v1 = $this->scoreExists($this->studentId, $this->termId, 'p15', 1);
        $v2 = $this->scoreExists($this->studentId, $this->termId, 'p15', 2);
        $this->assertEquals(7.0, $v1, 'Bài 1 phải có điểm 7.0');
        $this->assertEquals(8.0, $v2, 'Bài 2 phải có điểm 8.0');
    }

    public function test_delete_exam_removes_score_and_renumbers(): void
    {
        // Set 3 bài
        foreach ([1 => 6.0, 2 => 7.5, 3 => 9.0] as $num => $val) {
            $this->http('POST', '/api/scores.php', [
                'action' => 'set', 'studentId' => $this->studentId,
                'termId' => $this->termId, 'type' => 'p45',
                'exam_num' => $num, 'value' => (string) $val,
            ]);
        }

        // Xoá bài 1 — phải truyền action trong query string (xem scores.php:13)
        $r = $this->http('POST', '/api/scores.php?action=delete_exam', [
            'studentId' => $this->studentId,
            'termId'    => $this->termId,
            'type'      => 'p45',
            'examNum'   => 1,
        ]);
        $this->assertSame(200, $r['code'], 'delete_exam phải thành công: ' . $r['raw']);

        // Bài exam_num=1 không còn; exam_num=2,3 đã renumber thành 1,2
        $this->assertNull($this->scoreExists($this->studentId, $this->termId, 'p45', 3),
            'Bài 3 cũ phải được renumber xuống 2 (không còn exam_num=3)');
        $this->assertEquals(7.5, $this->scoreExists($this->studentId, $this->termId, 'p45', 1),
            'Bài cũ exam_num=2 phải renumber thành 1 với giá trị 7.5');
        $this->assertEquals(9.0, $this->scoreExists($this->studentId, $this->termId, 'p45', 2),
            'Bài cũ exam_num=3 phải renumber thành 2 với giá trị 9.0');
    }

    public function test_score_value_must_be_0_to_10(): void
    {
        $r1 = $this->http('POST', '/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'mieng',
            'exam_num' => 1, 'value' => '-1',
        ]);
        $this->assertSame(400, $r1['code'], 'Điểm âm phải bị 400: ' . $r1['raw']);

        $r2 = $this->http('POST', '/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'mieng',
            'exam_num' => 1, 'value' => '11',
        ]);
        $this->assertSame(400, $r2['code'], 'Điểm > 10 phải bị 400: ' . $r2['raw']);
    }

    /**
     * Phân quyền: GLV thường (chỉ có quyền classA) gọi set điểm cho học sinh
     * ở classA → 200; cho học sinh ở classB (ngoài phạm vi) → 403.
     */
    public function test_glv_score_set_rejects_class_outside_scope(): void
    {
        // Tạo học sinh ở classB (ngoài phạm vi của GLV)
        $year = current_year();
        $codeB = 'SCOB' . random_int(100000, 999999);
        $studentBId = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'Other Class Em', 1)",
            [$codeB]
        );
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$year['id'], $studentBId, $this->classB, 'đang sinh hoạt']);

        try {
            $this->loginGlv();

            // Trong phạm vi (classA) → 200
            $r1 = $this->http('POST', '/api/scores.php', [
                'action' => 'set', 'studentId' => $this->studentId,
                'termId' => $this->termId, 'type' => 'mieng',
                'exam_num' => 1, 'value' => '7.0',
            ], 'glv');
            $this->assertSame(200, $r1['code'],
                'GLV được set điểm lớp mình: ' . $r1['raw']);

            // Ngoài phạm vi (classB) → 403
            $r2 = $this->http('POST', '/api/scores.php', [
                'action' => 'set', 'studentId' => $studentBId,
                'termId' => $this->termId, 'type' => 'mieng',
                'exam_num' => 1, 'value' => '7.0',
            ], 'glv');
            $this->assertSame(403, $r2['code'],
                'GLV KHÔNG được set điểm lớp khác: ' . $r2['raw']);
        } finally {
            db_run("DELETE FROM scores WHERE student_id = ?", [$studentBId]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$studentBId]);
            db_run("DELETE FROM students WHERE id = ?", [$studentBId]);
        }
    }
}
