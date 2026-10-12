<?php
// tests/unit/AttendanceSyncMarkerTest.php
//
// MỐC ĐỒNG BỘ RIÊNG CỦA ĐIỂM DANH
//
// Trước đây mọi lần ghi điểm danh gọi Cache::flush() -> đổi mốc sync.txt ->
// MỌI máy đang mở app nạp lại toàn bộ dữ liệu. Giờ điểm danh có mốc riêng
// (sync_att.txt): máy khác chỉ nạp lại phần điểm danh/điểm. Kiểm ba tầng:
//   1. Cache::flush / flushAttendance: mốc nào đổi, mốc nào đứng yên, cache
//      dữ liệu có bị xoá không.
//   2. sync.php: bản mới (?att=1) trả "chung:điểm danh", bản cũ vẫn là MỘT số
//      và vẫn đổi khi điểm danh đổi (trang mở từ trước khi cập nhật).
//   3. Đầu-cuối qua HTTP thật: toggle/mark/scan/set_status chỉ đổi mốc điểm
//      danh; buổi tính Mộc đổi cả mốc chung (ví/chuỗi nằm ở phần core).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/cache.php';
require_once __DIR__ . '/P1ApiHarness.php';

use PHPUnit\Framework\TestCase;

class AttendanceSyncMarkerTest extends TestCase
{
    use P1ApiHarness;

    private int $yearId = 0;
    private array $classA;
    private array $createdStudentIds = [];
    private array $programIds = [];
    /** @var array<string,?string> nội dung gốc của các tệp mốc, để trả lại sau test */
    private array $savedMarkers = [];

    public static function setUpBeforeClass(): void
    {
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    protected function setUp(): void
    {
        Cache::init();
        $dir = dirname(__DIR__, 2) . '/storage/cache/';
        foreach (['sync.txt', 'sync_att.txt'] as $ten) {
            $v = @file_get_contents($dir . $ten);
            $this->savedMarkers[$ten] = $v === false ? null : $v;
        }
        $year = current_year();
        $this->assertNotNull($year, 'Cần niên khoá hiện tại (install.php).');
        $this->yearId = (int) $year['id'];
        $a = db_one('SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1');
        $this->assertNotNull($a, 'Cần ít nhất một lớp có khối.');
        $this->classA = $a;
    }

    protected function tearDown(): void
    {
        foreach ($this->programIds as $pid) {
            db_run('DELETE FROM attendances WHERE program_id = ?', [$pid]);
            db_run('DELETE FROM program_classes WHERE program_id = ?', [$pid]);
            db_run('DELETE FROM programs WHERE id = ?', [$pid]);
        }
        foreach ($this->createdStudentIds as $sid) {
            db_run('DELETE FROM attendances WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM enrollments WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM students WHERE id = ?', [$sid]);
        }
        $this->createdStudentIds = $this->programIds = [];
        $this->cleanupHarness();

        $dir = dirname(__DIR__, 2) . '/storage/cache/';
        foreach ($this->savedMarkers as $ten => $v) {
            if ($v === null) @unlink($dir . $ten);
            else file_put_contents($dir . $ten, $v);
        }
    }

    // ---------------- Tiện ích ----------------

    private function markerFile(string $ten, ?string $noiDung): void
    {
        $f = dirname(__DIR__, 2) . '/storage/cache/' . $ten;
        if ($noiDung === null) @unlink($f);
        else file_put_contents($f, $noiDung);
    }

    private function makeStudent(): array
    {
        $code = 'SYN' . random_int(100000, 999999);
        $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, 'Sync Marker Em', 1)", [$code]);
        $this->createdStudentIds[] = $sid;
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
               [$this->yearId, $sid, $this->classA['id'], 'đang sinh hoạt']);
        return ['id' => $sid, 'code' => $code];
    }

    /** Chương trình diễn ra hôm nay, 23:59 (chưa quá giờ chốt). $mocMoc = có tính Mộc. */
    private function makeProgram(bool $mocMoc): int
    {
        $pid = db_insert(
            "INSERT INTO programs (year_id, name, type, status, day_of_week, start_time, count_for_emulation)
             VALUES (?, 'Sync Marker Buổi', 'bắt buộc', 'kích hoạt', ?, '23:59:00', ?)",
            [$this->yearId, (int) date('w'), $mocMoc ? 1 : 0]);
        $this->programIds[] = $pid;
        return $pid;
    }

    private function attApi(array $admin, string $action, array $body): array
    {
        return $this->http((int) $admin['id'], 'POST', '/api/attendance.php?action=' . $action, $body);
    }

    private function syncText(bool $moi): string
    {
        $ch = curl_init(self::$base . '/api/sync.php' . ($moi ? '?att=1' : ''));
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $body = (string) curl_exec($ch);
        curl_close($ch);
        return $body;
    }

    // =================================================================
    //  1. Cache
    // =================================================================

    public function test_flush_changes_only_general_marker_and_clears_cache(): void
    {
        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');
        Cache::set('sync_marker_test', ['x' => 1], 60);
        $this->assertSame(['x' => 1], Cache::get('sync_marker_test'));

        Cache::flush();

        $this->assertNotSame('1', Cache::marker('sync.txt'), 'flush() phải đổi mốc chung');
        $this->assertSame('5000', Cache::marker('sync_att.txt'), 'flush() không được đụng mốc điểm danh');
        $this->assertNull(Cache::get('sync_marker_test'), 'flush() phải xoá cache dữ liệu');
    }

    public function test_flush_attendance_changes_only_attendance_marker_and_clears_cache(): void
    {
        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');
        Cache::set('sync_marker_test', ['x' => 1], 60);

        $moi = Cache::flushAttendance();

        $this->assertSame('1', Cache::marker('sync.txt'), 'Mốc chung phải đứng yên');
        $this->assertSame($moi, Cache::marker('sync_att.txt'), 'Trả về đúng mốc điểm danh vừa ghi');
        $this->assertGreaterThan(5000, (int) $moi);
        $this->assertNull(Cache::get('sync_marker_test'), 'Cache dữ liệu vẫn phải bị xoá (data.php đọc điểm danh từ đó)');
    }

    public function test_flush_attendance_marker_strictly_increases_even_within_same_millisecond(): void
    {
        // Mốc hiện tại ở tương lai xa: lần gọi kế tiếp vẫn phải lớn hơn.
        $this->markerFile('sync_att.txt', (string) (PHP_INT_MAX - 10));
        $a = (int) Cache::flushAttendance();
        $b = (int) Cache::flushAttendance();
        $this->assertGreaterThan(PHP_INT_MAX - 10, $a);
        $this->assertGreaterThan($a, $b);
    }

    public function test_flush_attendance_can_also_change_general_marker(): void
    {
        $this->markerFile('sync.txt', '1');
        Cache::flushAttendance(true);
        $this->assertNotSame('1', Cache::marker('sync.txt'), 'Ảnh hưởng core (Sổ Mộc) thì mốc chung phải đổi');
    }

    // =================================================================
    //  2. sync.php
    // =================================================================

    public function test_sync_php_new_format_returns_both_markers(): void
    {
        $this->markerFile('sync.txt', '1700000000');
        $this->markerFile('sync_att.txt', '1700000123456');
        $this->assertSame('1700000000:1700000123456', $this->syncText(true));
    }

    public function test_sync_php_new_format_defaults_to_zero_when_files_missing(): void
    {
        $this->markerFile('sync.txt', null);
        $this->markerFile('sync_att.txt', null);
        $this->assertSame('0:0', $this->syncText(true));
        $this->assertSame('0', $this->syncText(false));
    }

    public function test_sync_php_legacy_format_is_one_number_that_follows_attendance(): void
    {
        $this->markerFile('sync.txt', '1700000000');
        $this->markerFile('sync_att.txt', '1700000500000');   // 500 giây sau
        $this->assertSame('1700000500', $this->syncText(false),
            'Máy cũ chỉ biết một số: điểm danh đổi thì số này phải đổi theo');

        $this->markerFile('sync_att.txt', '1699999000000');    // điểm danh cũ hơn mốc chung
        $this->assertSame('1700000000', $this->syncText(false), 'Lấy mốc lớn hơn');
    }

    // =================================================================
    //  3. Đầu-cuối: ghi điểm danh đổi mốc nào
    // =================================================================

    public function test_toggle_changes_only_attendance_marker_and_returns_it(): void
    {
        $admin = $this->makeMember('admin');
        $this->loginAs($admin);
        $pid = $this->makeProgram(false);
        $em  = $this->makeStudent();
        $d   = date('Y-m-d');
        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');

        $r = $this->attApi($admin, 'toggle', ['programId' => $pid, 'date' => $d, 'studentId' => $em['id']]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertTrue($r['json']['ok']);
        $this->assertSame('1', Cache::marker('sync.txt'), 'Ghi điểm danh không được đổi mốc chung');
        $this->assertSame(Cache::marker('sync_att.txt'), (string) $r['json']['att'],
            'Phản hồi phải trả đúng mốc điểm danh mới để máy ghi nhận ra thay đổi của chính nó');
        $this->assertGreaterThan(5000, (int) $r['json']['att']);

        // Gỡ ra cũng đổi mốc điểm danh, vẫn không đổi mốc chung.
        $truoc = (int) $r['json']['att'];
        $r2 = $this->attApi($admin, 'toggle', ['programId' => $pid, 'date' => $d, 'studentId' => $em['id']]);
        $this->assertTrue($r2['json']['removed']);
        $this->assertGreaterThan($truoc, (int) $r2['json']['att']);
        $this->assertSame('1', Cache::marker('sync.txt'));
    }

    public function test_scan_changes_only_attendance_marker(): void
    {
        $admin = $this->makeMember('admin');
        $this->loginAs($admin);
        $pid = $this->makeProgram(false);
        $em  = $this->makeStudent();
        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');

        $r = $this->attApi($admin, 'scan', ['programId' => $pid, 'date' => date('Y-m-d'), 'codes' => [$em['code']]]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(1, $r['json']['added']);
        $this->assertSame('1', Cache::marker('sync.txt'));
        $this->assertSame(Cache::marker('sync_att.txt'), (string) $r['json']['att']);
    }

    public function test_set_status_changes_attendance_marker_only_when_status_changes(): void
    {
        $admin = $this->makeMember('admin');
        $this->loginAs($admin);
        $pid = $this->makeProgram(false);
        $em  = $this->makeStudent();
        $d   = date('Y-m-d');
        $ghi = $this->attApi($admin, 'toggle', ['programId' => $pid, 'date' => $d, 'studentId' => $em['id']]);
        $dangLa = $ghi['json']['status'];                                  // sát nửa đêm có thể là "đi trễ"
        $doiSang = $dangLa === 'có mặt' ? 'đi trễ' : 'có mặt';

        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');

        // Đặt lại đúng trạng thái đang có: không đổi gì thì không đổi mốc.
        $r0 = $this->attApi($admin, 'set_status',
            ['programId' => $pid, 'date' => $d, 'studentId' => $em['id'], 'status' => $dangLa]);
        $this->assertFalse($r0['json']['changed']);
        $this->assertNull($r0['json']['att']);
        $this->assertSame('5000', Cache::marker('sync_att.txt'));

        $r1 = $this->attApi($admin, 'set_status',
            ['programId' => $pid, 'date' => $d, 'studentId' => $em['id'], 'status' => $doiSang]);
        $this->assertTrue($r1['json']['changed']);
        $this->assertSame('1', Cache::marker('sync.txt'));
        $this->assertSame(Cache::marker('sync_att.txt'), (string) $r1['json']['att']);
    }

    public function test_mark_and_unmark_change_only_attendance_marker(): void
    {
        $admin = $this->makeMember('admin');
        $this->loginAs($admin);
        $pid = $this->makeProgram(false);
        $em  = $this->makeStudent();
        $d   = date('Y-m-d');
        $this->markerFile('sync.txt', '1');
        $this->markerFile('sync_att.txt', '5000');

        $r = $this->attApi($admin, 'mark', ['programId' => $pid, 'date' => $d, 'studentId' => $em['id']]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertTrue($r['json']['added']);
        $this->assertSame('1', Cache::marker('sync.txt'), 'mark không được đổi mốc chung');
        $this->assertSame(Cache::marker('sync_att.txt'), (string) $r['json']['att']);

        $u = $this->attApi($admin, 'unmark', ['programId' => $pid, 'date' => $d, 'studentId' => $em['id']]);
        $this->assertSame(200, $u['code'], $u['raw']);
        $this->assertTrue($u['json']['removed']);
        $this->assertSame('1', Cache::marker('sync.txt'), 'unmark không được đổi mốc chung');
        $this->assertGreaterThan((int) $r['json']['att'], (int) $u['json']['att']);
    }

    public function test_stamp_earning_program_also_changes_general_marker(): void
    {
        $admin = $this->makeMember('admin');
        $this->loginAs($admin);
        $pid = $this->makeProgram(true);          // buổi tính Mộc: ví/chuỗi nằm ở phần core
        $em  = $this->makeStudent();
        $this->markerFile('sync.txt', '1');

        $r = $this->attApi($admin, 'toggle', ['programId' => $pid, 'date' => date('Y-m-d'), 'studentId' => $em['id']]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertNotSame('1', Cache::marker('sync.txt'),
            'Buổi tính Mộc làm đổi stampSummaries (core) nên phải đổi mốc chung để máy khác nạp lại cả gói');
    }
}
