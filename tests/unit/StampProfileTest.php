<?php
/**
 * Test cho stamp_summary() — tổng hợp Ví Mộc + Lửa chuỗi + lịch sử giao dịch
 * gần nhất, dùng cho card hồ sơ thiếu nhi (SPEC-MOC-DIEN-TU §6.2).
 *
 * Quy ước theo tests/unit/StampEngineTest.php / ScopeTest.php:
 *   - require_once bootstrap (tránh fatal app_config() redeclare)
 *   - dựng dữ liệu tạm trong từng test, dọn ở tearDown.
 *
 * Dữ liệu seed dùng chung: năm học id=1 (đang mở), học sinh HS001 (id 1).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';

use PHPUnit\Framework\TestCase;

class StampProfileTest extends TestCase
{
    private int $yearId = 1;
    private int $sid    = 1;   // HS001

    protected function setUp(): void
    {
        $this->cleanStudentData();
    }

    protected function tearDown(): void
    {
        $this->cleanStudentData();
    }

    private function cleanStudentData(): void
    {
        db_run("DELETE FROM stamp_transactions WHERE student_id = ? AND year_id = ?", [$this->sid, $this->yearId]);
        db_run("DELETE FROM student_stamps    WHERE student_id = ? AND year_id = ?", [$this->sid, $this->yearId]);
    }

    private function insertTx(int $amount, string $type, string $desc, string $createdAt): void
    {
        db_run(
            "INSERT INTO stamp_transactions (year_id, student_id, amount, type, description, actor_id, created_at)
             VALUES (?,?,?,?,?, NULL, ?)",
            [$this->yearId, $this->sid, $amount, $type, $desc, $createdAt]
        );
    }

    public function test_no_wallet_row_returns_zeros_without_error(): void
    {
        $out = stamp_summary($this->sid, $this->yearId);

        $this->assertSame(0, $out['current_balance']);
        $this->assertSame(0, $out['held_balance']);
        $this->assertSame(0, $out['total_earned']);
        $this->assertSame(0, $out['current_streak']);
        $this->assertSame(0, $out['longest_streak']);
        $this->assertSame([], $out['recent_transactions']);
    }

    public function test_summary_reflects_wallet_and_recent_transactions_newest_first_limited(): void
    {
        db_run(
            "INSERT INTO student_stamps
                (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak, last_attendance_date)
             VALUES (?,?,?,?,?,?,?,?)",
            [$this->yearId, $this->sid, 42, 5, 60, 3, 7, '2026-09-20']
        );

        // 3 giao dịch, chèn KHÔNG theo thứ tự thời gian để chắc chắn ORDER BY
        // của hàm mới là thứ quyết định thứ tự trả về, không phải id insert.
        $this->insertTx(1, 'attendance', 'Đi lễ ngày 2026-09-13 (+1)', '2026-09-13 05:10:00');
        $this->insertTx(2, 'attendance', 'Đi lễ ngày 2026-09-20 (+2)', '2026-09-20 05:10:00');
        $this->insertTx(-10, 'spend', 'Đổi quà: Bút bi', '2026-09-21 09:00:00');

        $out = stamp_summary($this->sid, $this->yearId);

        $this->assertSame(42, $out['current_balance']);
        $this->assertSame(5, $out['held_balance']);
        $this->assertSame(60, $out['total_earned']);
        $this->assertSame(3, $out['current_streak']);
        $this->assertSame(7, $out['longest_streak']);

        $this->assertCount(3, $out['recent_transactions']);
        // Mới nhất trước: spend (09-21) > attendance +2 (09-20) > attendance +1 (09-13)
        $this->assertSame(-10, $out['recent_transactions'][0]['amount']);
        $this->assertSame('spend', $out['recent_transactions'][0]['type']);
        $this->assertSame('Đổi quà: Bút bi', $out['recent_transactions'][0]['description']);
        $this->assertArrayHasKey('created_at', $out['recent_transactions'][0]);

        $this->assertSame(2, $out['recent_transactions'][1]['amount']);
        $this->assertSame(1, $out['recent_transactions'][2]['amount']);
    }

    public function test_recent_transactions_is_limited_to_cap(): void
    {
        db_run(
            "INSERT INTO student_stamps
                (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak)
             VALUES (?,?,?,?,?,?,?)",
            [$this->yearId, $this->sid, 25, 0, 25, 1, 1]
        );

        // Chèn 25 giao dịch — nhiều hơn giới hạn hiển thị (tối đa 20).
        for ($i = 1; $i <= 25; $i++) {
            $this->insertTx(1, 'attendance', "Đi lễ #$i", sprintf('2026-09-%02d 05:00:00', ($i % 28) + 1));
        }

        $out = stamp_summary($this->sid, $this->yearId);

        $this->assertLessThanOrEqual(20, count($out['recent_transactions']));
        $this->assertNotEmpty($out['recent_transactions']);
    }
}
