<?php
/**
 * Test cho scripts/backfill_program_times.php — điền giờ còn trống.
 *   - chỉ điền khi có --cutoff-after=N / --absent-after=N (= start + N phút);
 *     không cờ thì chỉ báo cáo, không ghi.
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

    private function runScript(array $args): int
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

    public function test_khong_co_co_thi_khong_doi_gi(): void
    {
        // Không truyền --cutoff-after/--absent-after -> chỉ báo cáo, không ghi.
        $this->assertSame(0, $this->runScript(['--program=' . $this->pid, '--apply']));
        $p = $this->prog();
        $this->assertNull($p['cutoff_time'], 'không có cờ -> không điền gì');
        $this->assertNull($p['absent_time']);
    }

    public function test_dien_gio_di_tre_theo_co(): void
    {
        $this->assertSame(0, $this->runScript(['--program=' . $this->pid, '--cutoff-after=15', '--apply']));
        $p = $this->prog();
        $this->assertSame('06:15:00', $p['cutoff_time'], 'giờ đi trễ = start 06:00 + 15 phút');
        $this->assertNull($p['absent_time'], 'không có --absent-after thì KHÔNG đụng khoá sổ');
    }

    public function test_dien_khoa_so_theo_co(): void
    {
        $this->assertSame(0, $this->runScript(['--program=' . $this->pid, '--absent-after=120', '--apply']));
        $p = $this->prog();
        $this->assertSame('08:00:00', $p['absent_time'], 'khoá sổ = start 06:00 + 120 phút = 08:00');
        $this->assertNull($p['cutoff_time'], 'không có --cutoff-after thì KHÔNG đụng giờ đi trễ');
    }
}
