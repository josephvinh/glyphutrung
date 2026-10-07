<?php
// tests/unit/AttendanceSetStatusApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA `attendance.php?action=set_status`:
// đổi "có mặt" <-> "đi trễ" của một bản ghi điểm danh ĐÃ CÓ.
//
// Giống QrScanApiTest: khởi động `php -S`, đăng nhập bằng phiên + CSRF thật,
// gọi đúng endpoint. Không có DB / cổng thì skip.
//
// Bảo vệ LUẬT CỨNG #6 (không nới phân quyền) cho thao tác mới:
//   - chỉ admin/bdh/truong_khoi/glv_chu_nhiem (nhóm có "cửa sửa");
//   - CHỈ trong phạm vi lớp/khối mình phụ trách (lớp/khối khác -> 403);
//   - GLV thường -> 403 dù đúng lớp của mình.
// Ngoài ra: không tạo bản ghi mới (404), trạng thái sai (400), có nhật ký,
// và Sổ Mộc được tính lại.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class AttendanceSetStatusApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';

    private int $yearId = 0;
    private int $adminId = 0;
    private array $classA;      // lớp khối A
    private array $classA2;     // lớp KHÁC cùng khối A
    private array $classB;      // lớp khối B
    private string $cookieJar = '';
    private string $csrf = '';

    /** @var array<string,array{id:int,phone:string,jar:string,csrf:string}> */
    private array $who = [];
    private array $createdStudentIds = [];
    private array $programIds = [];

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

        // Ba người: GLV thường (lớp A) · GLV chủ nhiệm (lớp A) · Trưởng khối (khối A).
        $this->makeMember('glv', 'glv', ['class_id' => (int) $a['id']]);
        $this->makeMember('cn', 'glv_chu_nhiem', ['class_id' => (int) $a['id']]);
        $this->makeMember('tk', 'truong_khoi', ['block_id' => (int) $a['block_id']]);
        foreach (['glv', 'cn', 'tk'] as $k) {
            $role = $k === 'glv' ? 'glv' : ($k === 'cn' ? 'glv_chu_nhiem' : 'truong_khoi');
            $this->assertSame('edit', permission_of_role($role, 'attendance'),
                "Vai $role phải có 'edit' điểm danh để test không ngầm phụ thuộc.");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->programIds as $pid) {
            db_run("DELETE FROM attendances WHERE program_id = ?", [$pid]);
            db_run("DELETE FROM program_classes WHERE program_id = ?", [$pid]);
            db_run("DELETE FROM programs WHERE id = ?", [$pid]);
        }
        foreach ($this->createdStudentIds as $sid) {
            db_run("DELETE FROM stamp_transactions WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM student_stamps WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM attendances WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM students WHERE id = ?", [$sid]);
        }
        foreach ($this->who as $w) {
            db_run("DELETE FROM activity_logs WHERE actor_id = ?", [$w['id']]);
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$w['id']]);
            db_run("DELETE FROM members WHERE id = ?", [$w['id']]);
            if ($w['jar'] && file_exists($w['jar'])) @unlink($w['jar']);
        }
        $this->who = $this->createdStudentIds = $this->programIds = [];
    }

    // ---------------- Người dùng + HTTP ----------------

    /** Tạo một thành viên thật (must_change_pw = 0) kèm MỘT phân công đúng vai. */
    private function makeMember(string $key, string $role, array $scope): void
    {
        $phone = '09' . random_int(10000000, 99999999);
        $id = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, ?, ?, ?, ?, 0)",
            ['SST' . random_int(100000, 999999), "SetStatus $key", $phone,
             password_hash('Mk-Test-1', PASSWORD_DEFAULT), $role]
        );
        db_run("INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
                VALUES (?, ?, ?, ?, 1, CURDATE(), ?)",
               [$id, $role, $scope['block_id'] ?? null, $scope['class_id'] ?? null, $this->adminId]);
        $this->who[$key] = ['id' => $id, 'phone' => $phone, 'jar' => tempnam(sys_get_temp_dir(), 'sst'), 'csrf' => ''];
    }

    /** Chuyển sang đăng nhập bằng người $key (đăng nhập lần đầu khi cần). */
    private function loginAs(string $key): void
    {
        $w = &$this->who[$key];
        $this->cookieJar = $w['jar'];
        if ($w['csrf'] === '') {
            $r = $this->http('POST', '/api/auth.php?action=login',
                ['phone' => $w['phone'], 'password' => 'Mk-Test-1'], false);
            $this->assertSame(200, $r['code'], "Đăng nhập $key phải thành công: " . $r['raw']);
            $page = $this->http('GET', '/index.php', null, false);
            $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m),
                'Không lấy được csrfToken từ trang chủ');
            $w['csrf'] = $m[1];
        }
        $this->csrf = $w['csrf'];
    }

    private function http(string $method, string $path, ?array $json = null, bool $csrf = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && $this->csrf) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrf;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_COOKIEJAR      => $this->cookieJar,
            CURLOPT_COOKIEFILE     => $this->cookieJar,
        ]);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    private function setStatus(int $programId, string $date, int $studentId, $status): array
    {
        return $this->http('POST', '/api/attendance.php?action=set_status',
            ['programId' => $programId, 'date' => $date, 'studentId' => $studentId, 'status' => $status]);
    }

    // ---------------- Dữ liệu ----------------

    private function makeStudent(int $classId): int
    {
        $code = 'SST' . random_int(100000, 999999);
        $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, 'SetStatus Em', 1)", [$code]);
        $this->createdStudentIds[] = $sid;
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?, 'đang sinh hoạt')",
               [$this->yearId, $sid, $classId]);
        return $sid;
    }

    /** Chương trình lặp hằng tuần vào thứ của HÔM NAY (nên buổi hôm nay hợp lệ, không phải tương lai). */
    private function makeProgram(bool $earnsStamps = false): int
    {
        $pid = db_insert(
            "INSERT INTO programs (year_id, name, type, status, day_of_week, start_time, count_for_emulation)
             VALUES (?, 'SetStatus Buổi', 'bắt buộc', 'kích hoạt', ?, '06:00:00', ?)",
            [$this->yearId, (int) date('w'), $earnsStamps ? 1 : 0]);
        $this->programIds[] = $pid;
        return $pid;
    }

    private function today(): string { return date('Y-m-d'); }

    /** Ghi thẳng một bản ghi điểm danh (không qua API) với trạng thái cho trước. */
    private function seedAttendance(int $programId, int $studentId, string $status): void
    {
        db_run("INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
                VALUES (?,?,?,?,?, 'tay', ?)",
               [$this->yearId, $programId, $this->today(), $studentId, $status, $this->adminId]);
    }

    private function statusOf(int $programId, int $studentId): ?string
    {
        $r = db_one("SELECT status FROM attendances WHERE program_id=? AND session_date=? AND student_id=?",
                    [$programId, $this->today(), $studentId]);
        return $r ? $r['status'] : null;
    }

    private function logCount(string $key): int
    {
        return (int) db_val("SELECT COUNT(*) FROM activity_logs
                              WHERE actor_id = ? AND module = 'attendance' AND what LIKE 'Sửa trạng thái%'",
                            [$this->who[$key]['id']]);
    }

    // =================================================================
    //  Thành công
    // =================================================================

    public function test_chu_nhiem_doi_di_tre_thanh_co_mat_va_ghi_nhat_ky(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('cn');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertTrue($r['json']['ok']);
        $this->assertSame('có mặt', $r['json']['status']);
        $this->assertTrue($r['json']['changed']);
        $this->assertSame('có mặt', $this->statusOf($pid, $sid));
        $this->assertSame(1, $this->logCount('cn'), 'Phải ghi nhật ký ai sửa.');
    }

    public function test_doi_nguoc_lai_co_mat_thanh_di_tre(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'có mặt');

        $this->loginAs('cn');
        $r = $this->setStatus($pid, $this->today(), $sid, 'đi trễ');

        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid));
    }

    public function test_cung_trang_thai_thi_khong_doi_va_khong_ghi_nhat_ky(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'có mặt');

        $this->loginAs('cn');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertFalse($r['json']['changed']);
        $this->assertSame(0, $this->logCount('cn'));
    }

    public function test_truong_khoi_sua_duoc_lop_khac_trong_cung_khoi(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA2['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('tk');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame('có mặt', $this->statusOf($pid, $sid));
    }

    public function test_buoi_tinh_moc_thi_so_moc_duoc_tinh_lai(): void
    {
        $pid = $this->makeProgram(true); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');   // ghi thẳng: chưa có giao dịch Mộc nào
        $this->assertSame(0, (int) db_val("SELECT COUNT(*) FROM stamp_transactions WHERE student_id = ?", [$sid]));

        $this->loginAs('cn');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertGreaterThanOrEqual(1,
            (int) db_val("SELECT COUNT(*) FROM stamp_transactions WHERE student_id = ? AND type = 'attendance'", [$sid]),
            'Đổi trạng thái buổi tính Mộc phải kích hoạt tính lại Sổ Mộc.');
    }

    // =================================================================
    //  Phân quyền (luật cứng #6)
    // =================================================================

    public function test_glv_thuong_bi_chan_du_dung_lop_cua_minh(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('glv');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(403, $r['code'], $r['raw']);
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid), 'GLV thường không được đổi trạng thái.');
        $this->assertSame(0, $this->logCount('glv'));
    }

    public function test_chu_nhiem_khong_sua_duoc_lop_khac_cung_khoi(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA2['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('cn');   // chủ nhiệm lớp A, em thuộc lớp A2
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(403, $r['code'], $r['raw']);
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid));
    }

    public function test_truong_khoi_khong_sua_duoc_khoi_khac(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classB['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('tk');   // trưởng khối A, em thuộc khối B
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(403, $r['code'], $r['raw']);
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid));
    }

    public function test_thieu_csrf_thi_khong_doi(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('cn');
        $r = $this->http('POST', '/api/attendance.php?action=set_status',
            ['programId' => $pid, 'date' => $this->today(), 'studentId' => $sid, 'status' => 'có mặt'], false);

        $this->assertGreaterThanOrEqual(400, $r['code'], 'Thiếu CSRF phải bị từ chối: ' . $r['raw']);
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid));
    }

    // =================================================================
    //  Đầu vào sai
    // =================================================================

    public function test_em_chua_ghi_thi_404_va_khong_tao_ban_ghi(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);

        $this->loginAs('cn');
        $r = $this->setStatus($pid, $this->today(), $sid, 'có mặt');

        $this->assertSame(404, $r['code'], $r['raw']);
        $this->assertNull($this->statusOf($pid, $sid), 'Không được tạo bản ghi mới (vắng là không có bản ghi).');
    }

    public function test_trang_thai_khong_hop_le_thi_400(): void
    {
        $pid = $this->makeProgram(); $sid = $this->makeStudent((int) $this->classA['id']);
        $this->seedAttendance($pid, $sid, 'đi trễ');

        $this->loginAs('cn');
        foreach (['vắng', '', 'CÓ MẶT', ['có mặt'], null] as $bad) {
            $r = $this->setStatus($pid, $this->today(), $sid, $bad);
            $this->assertSame(400, $r['code'], 'Trạng thái ' . json_encode($bad, JSON_UNESCAPED_UNICODE) . ': ' . $r['raw']);
        }
        $this->assertSame('đi trễ', $this->statusOf($pid, $sid));
    }
}
