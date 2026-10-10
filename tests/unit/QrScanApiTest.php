<?php
// tests/unit/QrScanApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CÁC CHỐT AN TOÀN CỦA MÁY QUÉT QR
//
// attendance.php đọc php://input, gọi require_login()/exit ngay khi nạp nên
// không nạp trực tiếp được (xem GiftsApiTest). QrScanTest.php kiểm phạm vi ở
// mức hàm + SQL; file NÀY khởi động máy chủ PHP tích hợp (`php -S`) và gọi
// ĐÚNG endpoint api/attendance.php bằng phiên đăng nhập + CSRF thật, để các
// chốt trong nhánh `scan` / `lookup` được chạy bằng mã thật, không phải bản
// sao SQL. Nếu máy chủ không khởi động được (không có DB / cổng) thì skip.
//
// Các chốt được kiểm:
//   scan  : ngoài khối bị bỏ qua · em không còn sinh hoạt bị bỏ qua · lớp
//           ngoài chương trình bị bỏ qua · allow_qr=0 · quá giờ "vắng" ·
//           trần 200 mã · trạng thái do MÁY CHỦ tính (client gửi status vô
//           hiệu) · chưa đăng nhập · thiếu CSRF.
//   lookup: theo khối + lớp của chương trình + chỉ em đang sinh hoạt.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class QrScanApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';

    private int $yearId = 0;
    private int $adminId = 0;
    private int $memberId = 0;
    private string $phone = '';
    private array $classA;      // lớp khối A (GLV được phân vào)
    private array $classA2;     // lớp KHÁC cùng khối A
    private array $classB;      // lớp khối B
    private array $createdStudentIds = [];
    private array $programIds = [];
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

        for ($i = 0; $i < 50; $i++) {                       // chờ tối đa ~5 giây
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
        $a2 = $a ? db_one("SELECT id, block_id FROM classes WHERE block_id = ? AND id <> ? ORDER BY id LIMIT 1",
                          [$a['block_id'], $a['id']]) : null;
        $b = $a ? db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? ORDER BY id LIMIT 1",
                         [$a['block_id']]) : null;
        if (!$year || !$admin || !$a || !$a2 || !$b) {
            $this->markTestSkipped('Cần niên khoá hiện tại, admin, và 2 khối (khối đầu có ≥ 2 lớp).');
        }
        $this->yearId  = (int) $year['id'];
        $this->adminId = (int) $admin['id'];
        [$this->classA, $this->classA2, $this->classB] = [$a, $a2, $b];

        // GLV thật (must_change_pw = 0 để vào được app), phân vào MỘT lớp khối A.
        $this->phone = '09' . random_int(10000000, 99999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'QR Api Test', ?, ?, 'glv', 0)",
            ['QRAPI' . random_int(100000, 999999), $this->phone, password_hash('Mk-Test-1', PASSWORD_DEFAULT)]
        );
        db_run("INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
                VALUES (?, 'glv', NULL, ?, 1, CURDATE(), ?)",
               [$this->memberId, $this->classA['id'], $this->adminId]);
        // glv có 'edit' attendance trong seed; xác nhận để test không ngầm phụ thuộc.
        $this->assertSame('edit', permission_of_role('glv', 'attendance'));

        $this->cookieJar = tempnam(sys_get_temp_dir(), 'qrapi');
        $this->login();
    }

    protected function tearDown(): void
    {
        foreach ($this->programIds as $pid) {
            db_run("DELETE FROM attendances WHERE program_id = ?", [$pid]);
            db_run("DELETE FROM program_classes WHERE program_id = ?", [$pid]);
            db_run("DELETE FROM programs WHERE id = ?", [$pid]);
        }
        foreach ($this->createdStudentIds as $sid) {
            db_run("DELETE FROM attendances WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM students WHERE id = ?", [$sid]);
        }
        if ($this->memberId) {
            db_run("DELETE FROM activity_logs WHERE actor_id = ?", [$this->memberId]);
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
        if ($this->cookieJar && file_exists($this->cookieJar)) @unlink($this->cookieJar);
        $this->createdStudentIds = $this->programIds = [];
    }

    // ---------------- HTTP ----------------

    private function http(string $method, string $path, ?array $json = null, bool $csrf = true, bool $cookies = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && $this->csrf) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrf;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        }
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    private function login(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $this->phone, 'password' => 'Mk-Test-1'], false);
        $this->assertSame(200, $r['code'], 'Đăng nhập GLV test phải thành công: ' . $r['raw']);

        // CSRF token nằm trong TNTT_BOOT của trang chủ (đúng cách frontend lấy).
        $page = $this->http('GET', '/index.php', null, false);
        $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m),
            'Không lấy được csrfToken từ trang chủ');
        $this->csrf = $m[1];
    }

    private function scan(int $programId, string $date, array $codes, array $extra = []): array
    {
        return $this->http('POST', '/api/attendance.php?action=scan',
            array_merge(['programId' => $programId, 'date' => $date, 'codes' => $codes], $extra));
    }

    private function lookup(int $programId, string $date): array
    {
        return $this->http('POST', '/api/attendance.php?action=lookup',
            ['programId' => $programId, 'date' => $date]);
    }

    // ---------------- Dữ liệu ----------------

    private function makeStudent(int $classId, string $status = 'đang sinh hoạt'): array
    {
        $code = 'QRA' . random_int(100000, 999999);
        $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, 'QR Api Em', 1)", [$code]);
        $this->createdStudentIds[] = $sid;
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$this->yearId, $sid, $classId, $status]);
        return ['id' => $sid, 'code' => $code];
    }

    /** Chương trình lặp hằng tuần vào thứ của hôm nay, 23:59, tuỳ chỉnh qua $extra (cột => giá trị). */
    private function makeProgram(array $extra = []): int
    {
        $cols = array_merge([
            'year_id' => $this->yearId, 'name' => 'QR Api Buổi', 'type' => 'bắt buộc',
            'status' => 'kích hoạt', 'day_of_week' => (int) date('w'), 'start_time' => '23:59:00',
        ], $extra);
        $pid = db_insert(
            'INSERT INTO programs (' . implode(',', array_keys($cols)) . ') VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')',
            array_values($cols));
        $this->programIds[] = $pid;
        return $pid;
    }

    /**
     * Buổi HÔM NAY (chưa quá giờ chốt) và buổi CÙNG THỨ tuần trước (đã quá giờ chốt).
     * Không dùng ngày tương lai: attendance.php từ chối điểm danh buổi chưa diễn ra (#85).
     * Chương trình mặc định diễn ra vào thứ của hôm nay, bắt đầu 23:59 nên chỉ quá giờ
     * chốt khi chạy test sát nửa đêm.
     */
    private function todaySession(): string { return date('Y-m-d'); }
    private function pastSession(): string  { return date('Y-m-d', strtotime('-7 days')); }

    private function attRow(int $programId, string $date, int $studentId): ?array
    {
        return db_one("SELECT status, method FROM attendances
                        WHERE program_id=? AND session_date=? AND student_id=?", [$programId, $date, $studentId]);
    }

    private function skippedCodes(array $r): array
    {
        return array_map(fn($x) => $x[0], $r['json']['skipped'] ?? []);
    }

    // =================================================================
    //  Camera: Permissions-Policy
    // =================================================================

    /**
     * HỒI QUY: máy chủ từng gửi `Permissions-Policy: camera=()` — cấm camera
     * trên toàn trang, khiến getUserMedia() luôn bị từ chối và máy quét QR
     * không mở được trên mọi trình duyệt. Trang phải cho phép camera cho
     * chính nó (`camera=(self)`); micro/vị trí vẫn phải bị chặn.
     */
    public function test_pages_allow_camera_for_same_origin(): void
    {
        foreach (['/index.php', '/api/auth.php?action=me'] as $path) {
            $ch = curl_init(self::$base . $path);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20,
                                    CURLOPT_COOKIEFILE => $this->cookieJar]);
            $resp = (string) curl_exec($ch);
            $hdrLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $headers = substr($resp, 0, $hdrLen);

            $this->assertSame(1, preg_match('/^Permissions-Policy:\s*(.+)$/mi', $headers, $m),
                "$path phải gửi Permissions-Policy");
            $policy = $m[1];
            $this->assertStringContainsString('camera=(self)', $policy, "$path: camera phải cho phép same-origin");
            $this->assertStringNotContainsString('camera=()', $policy, "$path: không được cấm hẳn camera");
            $this->assertStringContainsString('microphone=()', $policy, "$path: micro vẫn phải bị chặn");
            $this->assertStringContainsString('geolocation=()', $policy, "$path: vị trí vẫn phải bị chặn");
        }
    }

    // =================================================================
    //  scan
    // =================================================================

    public function test_scan_adds_in_scope_class_student(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classA['id']);   // lớp ĐƯỢC PHÂN của GLV

        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(1, $r['json']['added']);
        $this->assertSame('qr', $this->attRow($pid, $d, $em['id'])['method']);
    }

    public function test_scan_skips_student_outside_class(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classB['id']);   // lớp KHÁC (khối khác)

        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(0, $r['json']['added']);
        $this->assertContains($em['code'], $this->skippedCodes($r));
        $this->assertNull($this->attRow($pid, $d, $em['id']), 'Em lớp khác không được ghi');
    }

    public function test_scan_skips_inactive_enrollment(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classA['id'], 'dừng sinh hoạt');

        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(0, $r['json']['added']);
        $this->assertContains($em['code'], $this->skippedCodes($r));
        $this->assertNull($this->attRow($pid, $d, $em['id']));
    }

    public function test_scan_skips_unknown_code(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $r = $this->scan($pid, $d, ['KHONG-CO-MA-NAY']);
        $this->assertSame(0, $r['json']['added']);
        $this->assertContains('KHONG-CO-MA-NAY', $this->skippedCodes($r));
    }

    public function test_scan_skips_class_outside_program(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        // Chương trình chỉ áp dụng cho classA; em ở classA2 (cùng khối, trong phạm vi quét) phải bị bỏ.
        db_run("INSERT INTO program_classes (program_id, class_id) VALUES (?,?)", [$pid, $this->classA['id']]);
        $inProg  = $this->makeStudent((int) $this->classA['id']);
        $outProg = $this->makeStudent((int) $this->classA2['id']);

        $r = $this->scan($pid, $d, [$inProg['code'], $outProg['code']]);
        $this->assertSame(1, $r['json']['added']);
        $this->assertNotNull($this->attRow($pid, $d, $inProg['id']));
        $this->assertNull($this->attRow($pid, $d, $outProg['id']));
        $this->assertContains($outProg['code'], $this->skippedCodes($r));
    }

    public function test_scan_rejected_when_program_disallows_qr(): void
    {
        $pid = $this->makeProgram(['allow_qr' => 0]); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classA['id']);

        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(false, $r['json']['ok'] ?? null);
        $this->assertNull($this->attRow($pid, $d, $em['id']));
    }

    public function test_scan_rejected_after_absent_time(): void
    {
        // Buổi đã qua + mốc "vắng" 00:01 => đã quá giờ tính vắng.
        $pid = $this->makeProgram(['absent_time' => '00:01:00']); $d = $this->pastSession();
        $em = $this->makeStudent((int) $this->classA['id']);

        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(false, $r['json']['ok'] ?? null);
        $this->assertNull($this->attRow($pid, $d, $em['id']));
    }

    public function test_scan_caps_batch_at_200_codes(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $codes = array_map(fn($i) => "X$i", range(1, 201));
        $r = $this->scan($pid, $d, $codes);
        $this->assertSame(false, $r['json']['ok'] ?? null, 'Lô 201 mã phải bị từ chối');

        $ok = $this->scan($pid, $d, array_slice($codes, 0, 200));
        $this->assertSame(true, $ok['json']['ok'] ?? null, 'Lô 200 mã là hợp lệ');
    }

    public function test_status_is_server_computed_and_client_status_ignored(): void
    {
        $em1 = $this->makeStudent((int) $this->classA['id']);
        $em2 = $this->makeStudent((int) $this->classA['id']);

        // Buổi CHƯA tới giờ chốt -> 'có mặt'; client gửi status='đi trễ' bị bỏ qua.
        $pidFuture = $this->makeProgram(); $dF = $this->todaySession();
        $r = $this->scan($pidFuture, $dF, [$em1['code']], ['status' => 'đi trễ']);
        $this->assertSame('có mặt', $r['json']['status']);
        $this->assertSame('có mặt', $this->attRow($pidFuture, $dF, $em1['id'])['status']);

        // Buổi ĐÃ quá giờ chốt -> 'đi trễ'; client gửi status='có mặt' cũng vô hiệu.
        $pidPast = $this->makeProgram(); $dP = $this->pastSession();
        $r = $this->scan($pidPast, $dP, [$em2['code']], ['status' => 'có mặt']);
        $this->assertSame('đi trễ', $r['json']['status']);
        $this->assertSame('đi trễ', $this->attRow($pidPast, $dP, $em2['id'])['status']);
    }

    public function test_scan_rejects_unauthenticated_request(): void
    {
        // Không phiên, không CSRF: require_write() chặn (403) trước cả require_login() (401).
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classA['id']);
        $r = $this->http('POST', '/api/attendance.php?action=scan',
            ['programId' => $pid, 'date' => $d, 'codes' => [$em['code']]], false, false);
        $this->assertContains($r['code'], [401, 403]);
        $this->assertNull($this->attRow($pid, $d, $em['id']), 'Chưa đăng nhập thì không được ghi');
    }

    public function test_scan_rejects_future_date(): void
    {
        // #85: không điểm danh trước cho buổi chưa diễn ra (cùng thứ, +7 ngày).
        $pid = $this->makeProgram(); $d = date('Y-m-d', strtotime('+7 days'));
        $em = $this->makeStudent((int) $this->classA['id']);
        $r = $this->scan($pid, $d, [$em['code']]);
        $this->assertSame(400, $r['code'], $r['raw']);
        $this->assertNull($this->attRow($pid, $d, $em['id']), 'Buổi tương lai thì không được ghi');
    }

    public function test_scan_requires_csrf_token(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $em = $this->makeStudent((int) $this->classA['id']);
        $r = $this->http('POST', '/api/attendance.php?action=scan',
            ['programId' => $pid, 'date' => $d, 'codes' => [$em['code']]], false);
        $this->assertSame(403, $r['code']);
        $this->assertNull($this->attRow($pid, $d, $em['id']), 'Thiếu CSRF thì không được ghi');
    }

    // =================================================================
    //  lookup
    // =================================================================

    public function test_lookup_scoped_to_block_and_active_only(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        $inBlock  = $this->makeStudent((int) $this->classA2['id']);
        $outBlock = $this->makeStudent((int) $this->classB['id']);
        $inactive = $this->makeStudent((int) $this->classA['id'], 'dừng sinh hoạt');

        $r = $this->lookup($pid, $d);
        $this->assertSame(200, $r['code'], $r['raw']);
        $codes = array_column($r['json']['items'], 0);
        $this->assertContains($inBlock['code'], $codes);
        $this->assertNotContains($outBlock['code'], $codes, 'Em khối khác không được lọt vào bảng tra');
        $this->assertNotContains($inactive['code'], $codes, 'Em không còn sinh hoạt không được lọt vào');

        // Bảng tra chỉ có 4 trường gọn [mã, id, tên, lớp] — không lộ ngày sinh/địa chỉ/SĐT.
        $row = array_values(array_filter($r['json']['items'], fn($x) => $x[0] === $inBlock['code']))[0];
        $this->assertCount(4, $row);
    }

    public function test_lookup_respects_program_classes(): void
    {
        $pid = $this->makeProgram(); $d = $this->todaySession();
        db_run("INSERT INTO program_classes (program_id, class_id) VALUES (?,?)", [$pid, $this->classA['id']]);
        $inProg  = $this->makeStudent((int) $this->classA['id']);
        $outProg = $this->makeStudent((int) $this->classA2['id']);   // trong khối nhưng ngoài chương trình

        $codes = array_column($this->lookup($pid, $d)['json']['items'], 0);
        $this->assertContains($inProg['code'], $codes);
        $this->assertNotContains($outProg['code'], $codes,
            'Lớp không tham gia chương trình không được có trong bảng tra');
    }
}
