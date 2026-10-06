<?php
/**
 * Test cho SCRIPT sửa dữ liệu status điểm danh (scripts/fix_attendance_status.php).
 *
 *  1) Unit: attendance_expected_status() tính đúng theo giờ chốt thật.
 *  2) Tích hợp: CHẠY script thật (proc_open) trên DB test, kiểm bản ghi
 *     được sửa đúng CẢ HAI chiều, và dry-run KHÔNG đổi gì.
 *
 * Dùng học sinh + chương trình DÙNG-MỘT-LẦN để không đụng dữ liệu test khác
 * (recalc Sổ Mộc chỉ chạm học sinh này; tearDown dọn sạch cả stamp).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class AttendanceStatusFixTest extends TestCase
{
    // ---------- 1) UNIT: logic tính trạng thái đúng ----------
    public function test_truoc_gio_chot_la_co_mat(): void
    {
        $prog = ['start_time' => '06:00:00', 'cutoff_time' => '08:00:00'];
        $this->assertSame('có mặt', attendance_expected_status('2026-10-05 06:36:00', $prog, '2026-10-05'));
    }

    public function test_sau_gio_chot_la_di_tre(): void
    {
        $prog = ['start_time' => '06:00:00', 'cutoff_time' => '08:00:00'];
        $this->assertSame('đi trễ', attendance_expected_status('2026-10-05 08:06:00', $prog, '2026-10-05'));
    }

    public function test_cutoff_trong_dung_mac_dinh(): void
    {
        $min  = (int) app_config('cutoff_minutes');
        $prog = ['start_time' => '07:30:00', 'cutoff_time' => null];
        $sau  = date('Y-m-d H:i:s', strtotime('2026-10-05 07:30:00') + $min * 60 + 1);
        $this->assertSame('đi trễ', attendance_expected_status($sau, $prog, '2026-10-05'));
        $this->assertSame('có mặt', attendance_expected_status('2026-10-05 07:30:00', $prog, '2026-10-05'));
    }

    // ---------- 2) TÍCH HỢP: chạy script thật ----------
    private int $pid = 0;
    private int $sid = 0;

    protected function setUp(): void
    {
        if (!db_one("SELECT id FROM school_years WHERE id = 1 AND is_current = 1")) {
            $this->markTestSkipped('Cần năm học id=1 đang mở.');
        }
        $this->pid = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, cutoff_time, day_of_week)
             VALUES (1, 'FIXTEST', 'bắt buộc', 'kích hoạt', 1, '06:00:00', '08:00:00', 0)");
        $this->sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, 'Fix Test Em', 1)",
                               ['FIX' . random_int(100000, 999999)]);
        db_run("INSERT INTO enrollments (year_id, student_id, class_id, status)
                VALUES (1, ?, 1, 'đang sinh hoạt')", [$this->sid]);
    }

    protected function tearDown(): void
    {
        if ($this->pid) {
            db_run("DELETE FROM attendances WHERE program_id = ?", [$this->pid]);
            db_run("DELETE FROM programs WHERE id = ?", [$this->pid]);
        }
        if ($this->sid) {
            db_run("DELETE FROM student_stamps WHERE student_id = ?", [$this->sid]);
            db_run("DELETE FROM stamp_transactions WHERE student_id = ?", [$this->sid]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$this->sid]);
            db_run("DELETE FROM students WHERE id = ?", [$this->sid]);
        }
    }

    private function addAtt(string $date, string $markedAt, string $status): int
    {
        return db_insert(
            "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by, marked_at)
             VALUES (1, ?, ?, ?, ?, 'tay', 1, ?)",
            [$this->pid, $date, $this->sid, $status, $markedAt]);
    }

    private function statusOf(int $id): string
    {
        return (string) db_one("SELECT status FROM attendances WHERE id = ?", [$id])['status'];
    }

    /** Chạy script, trả [exitCode, stdout, stderr]. */
    private function runScript(array $args): array
    {
        $script = dirname(__DIR__, 2) . '/scripts/fix_attendance_status.php';
        $env = array_merge(getenv(), $_ENV);
        $p = proc_open(array_merge([PHP_BINARY, $script], $args),
                       [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $this->assertIsResource($p, 'Không chạy được script.');
        $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
        $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
        return [proc_close($p), $out, $err];
    }

    public function test_script_sua_dung_hai_chieu(): void
    {
        $a = $this->addAtt('2026-09-06', '2026-09-06 06:36:00', 'đi trễ');  // sai -> có mặt
        $b = $this->addAtt('2026-09-13', '2026-09-13 08:06:00', 'đi trễ');  // đúng, giữ
        $c = $this->addAtt('2026-09-20', '2026-09-20 06:20:00', 'có mặt');  // đúng, giữ
        $d = $this->addAtt('2026-09-27', '2026-09-27 09:00:00', 'có mặt');  // sai ngược -> đi trễ

        [$code, $out, $err] = $this->runScript(['--apply', '--program=' . $this->pid]);
        $this->assertSame(0, $code, "Script lỗi: $err\n$out");

        $this->assertSame('có mặt', $this->statusOf($a), 'đi trễ@06:36 -> có mặt');
        $this->assertSame('đi trễ', $this->statusOf($b), 'đi trễ@08:06 giữ nguyên');
        $this->assertSame('có mặt', $this->statusOf($c), 'có mặt@06:20 giữ nguyên');
        $this->assertSame('đi trễ', $this->statusOf($d), 'có mặt@09:00 -> đi trễ');
    }

    public function test_dry_run_khong_doi_gi(): void
    {
        $a = $this->addAtt('2026-09-06', '2026-09-06 06:36:00', 'đi trễ');
        [$code, , ] = $this->runScript(['--program=' . $this->pid]);   // không --apply
        $this->assertSame(0, $code);
        $this->assertSame('đi trễ', $this->statusOf($a), 'dry-run KHÔNG được đổi dữ liệu');
    }
}
