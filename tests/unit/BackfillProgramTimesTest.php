<?php
/**
 * Test cho scripts/backfill_program_times.php — điền giờ còn trống.
 *   - cutoff_time NULL -> start + cutoff_minutes (an toàn, không đổi hành vi).
 *   - absent_time chỉ điền khi có --absent-after=N; mặc định KHÔNG đụng.
 * Dùng --program để chỉ chạm chương trình dù-một-lần của test.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class BackfillProgramTimesTest extends TestCase
{
    private int $pid = 0;

    protected function setUp(): void
    {
        if (!db_one("SELECT id FROM school_years WHERE id = 1 AND is_current = 1")) {
            $this->markTestSkipped('Cần năm học id=1 đang mở.');
        }
        // Chương trình CŨ: start 06:00, chốt + khoá sổ còn TRỐNG.
        $this->pid = db_insert(
            "INSERT INTO programs (year_id, name, type, status, count_for_attendance, start_time, day_of_week)
             VALUES (1, 'BACKFILL TEST', 'bắt buộc', 'kích hoạt', 1, '06:00:00', 0)");
    }

    protected function tearDown(): void
    {
        if ($this->pid) db_run("DELETE FROM programs WHERE id = ?", [$this->pid]);
    }

    private function prog(): array
    {
        return db_one("SELECT cutoff_time, absent_time FROM programs WHERE id = ?", [$this->pid]);
    }

    private function run(array $args): int
    {
        $script = dirname(__DIR__, 2) . '/scripts/backfill_program_times.php';
        $env = array_merge(getenv(), $_ENV);
        $p = proc_open(array_merge([PHP_BINARY, $script], $args),
                       [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        $this->assertIsResource($p);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        stream_get_contents($pipes[2]); fclose($pipes[2]);
        return proc_close($p);
    }

    public function test_dry_run_khong_doi_gi(): void
    {
        $this->assertSame(0, $this->run(['--program=' . $this->pid]));
        $p = $this->prog();
        $this->assertNull($p['cutoff_time'], 'dry-run không được điền');
        $this->assertNull($p['absent_time']);
    }

    public function test_dien_gio_di_tre_khong_dung_khoa_so(): void
    {
        $this->assertSame(0, $this->run(['--program=' . $this->pid, '--apply']));
        $p = $this->prog();
        $min = (int) app_config('cutoff_minutes');
        $expect = date('H:i:s', strtotime('1970-01-01 06:00:00') + $min * 60);
        $this->assertSame($expect, $p['cutoff_time'], 'cutoff = start + cutoff_minutes');
        $this->assertNull($p['absent_time'], 'không có --absent-after thì KHÔNG đụng khoá sổ');
    }

    public function test_dien_khoa_so_khi_co_co(): void
    {
        $this->assertSame(0, $this->run(['--program=' . $this->pid, '--absent-after=120', '--apply']));
        $p = $this->prog();
        $this->assertSame('08:00:00', $p['absent_time'], 'khoá sổ = start 06:00 + 120 phút = 08:00');
    }
}
