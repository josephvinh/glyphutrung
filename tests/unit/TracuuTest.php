<?php
/**
 * Test cho tracuu_public_summary() — logic thuần của cổng tra cứu công khai
 * `public/tracuu.php` (Sổ Mộc theo mã thiếu nhi, SPEC-MOC-DIEN-TU §6.3).
 *
 * Quy ước theo tests/unit/StampProfileTest.php / RewardsRedeemTest.php:
 *   - require_once bootstrap (tránh fatal app_config() redeclare) + StampService + _tracuu
 *   - dựng ví/giao dịch tạm cho HS001 trong từng test, dọn ở setUp/tearDown.
 *
 * Trọng tâm bảo mật: hàm CHỈ được lộ đúng 8 khoá cho phép (không rò SĐT,
 * địa chỉ, tên cha mẹ... của students) và số dư hiển thị phải kẹp về 0
 * giống stamp_summary().
 *
 * KHÔNG test tracuu_throttle() vượt ngưỡng ở đây: hàm đó gọi json_fail()
 * (exit ngay), sẽ giết luôn tiến trình PHPUnit nếu gọi tới nhánh vượt ngưỡng.
 *
 * Dữ liệu seed dùng chung: năm học id=1 (đang mở), học sinh HS001 (id 1),
 * lớp id=1 (enrollment của HS001 trong seed).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';
require_once __DIR__ . '/../../public/api/_tracuu.php';

use PHPUnit\Framework\TestCase;

class TracuuTest extends TestCase
{
    private int $yearId = 1;
    private int $sid    = 1; // HS001
    private string $code = 'HS001';

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

    private function insertWallet(int $current, int $totalEarned, int $streak, int $longest): void
    {
        db_run(
            "INSERT INTO student_stamps (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak)
             VALUES (?,?,?,0,?,?,?)",
            [$this->yearId, $this->sid, $current, $totalEarned, $streak, $longest]
        );
    }

    private function insertTx(int $amount, string $type, string $desc): void
    {
        db_run(
            "INSERT INTO stamp_transactions (year_id, student_id, amount, type, description, actor_id)
             VALUES (?,?,?,?,?, NULL)",
            [$this->yearId, $this->sid, $amount, $type, $desc]
        );
    }

    public function test_returns_correct_numbers_name_and_class(): void
    {
        $this->insertWallet(15, 20, 3, 5);
        $this->insertTx(1, 'attendance', 'Đi lễ ngày 2026-09-20 (+1)');

        $out = tracuu_public_summary($this->code, $this->yearId);

        $this->assertNotNull($out);
        $this->assertSame('HS001', $out['code']);
        $this->assertNotSame('', $out['full_name']);
        $this->assertArrayHasKey('class_name', $out);
        $this->assertSame(15, $out['current_balance']);
        $this->assertSame(20, $out['total_earned']);
        $this->assertSame(3, $out['current_streak']);
        $this->assertSame(5, $out['longest_streak']);
        $this->assertCount(1, $out['recent_transactions']);
        $this->assertSame(1, $out['recent_transactions'][0]['amount']);

        // Chỉ lộ ĐÚNG các khoá cho phép — không rò SĐT/địa chỉ/tên cha mẹ...
        $this->assertSame(
            ['code', 'full_name', 'class_name', 'current_balance', 'total_earned', 'current_streak', 'longest_streak', 'recent_transactions'],
            array_keys($out)
        );
    }

    public function test_negative_balance_is_clamped_to_zero(): void
    {
        // total_earned=2 nhưng đã tiêu 10 (như test tương tự ở StampProfileTest) -> current_balance=-8
        $this->insertWallet(-8, 2, 0, 0);

        $out = tracuu_public_summary($this->code, $this->yearId);

        $this->assertNotNull($out);
        $this->assertSame(0, $out['current_balance'], 'current_balance hiển thị phải kẹp về 0 (qua stamp_summary)');
    }

    public function test_unknown_code_returns_null(): void
    {
        $this->assertNull(tracuu_public_summary('KHONG-TON-TAI-XYZ', $this->yearId));
    }
}
