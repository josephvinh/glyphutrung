<?php
/**
 * Test cho "móc" giữa attendance.php và Engine Sổ Mộc.
 *
 * attendance.php là endpoint request-scoped (đọc php://input, cần auth) nên
 * KHÔNG gọi HTTP trực tiếp. Thay vào đó test HỢP ĐỒNG TÍCH HỢP ở tầng CSDL:
 *   - program_earns_stamps(): luật "buổi này có tính Mộc không" (đơn vị).
 *   - Mô phỏng đúng những gì attendance.php làm (INSERT/DELETE vào
 *     `attendances` rồi gọi recalc_stamps) và assert ví đổi/hoàn đúng.
 *
 * Quy ước theo tests/unit/StampEngineTest.php / ScopeTest.php:
 *   - require_once bootstrap (tránh fatal app_config() redeclare)
 *   - dựng program/attendances tạm trong từng test, dọn ở tearDown.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';

use PHPUnit\Framework\TestCase;

class StampHookTest extends TestCase
{
    private int $yearId = 1;
    private int $sid    = 1;   // HS001
    private int $adminId = 1;
    /** @var int[] chương trình tạo trong test, để dọn */
    private array $progIds = [];

    protected function setUp(): void
    {
        $this->adminId = (int) (db_one("SELECT id FROM members WHERE role_code='admin' LIMIT 1")['id'] ?? 1);
        $this->cleanStudentData();
    }

    protected function tearDown(): void
    {
        $this->cleanStudentData();
        if ($this->progIds) {
            $ph = implode(',', array_fill(0, count($this->progIds), '?'));
            db_run("DELETE FROM attendances WHERE program_id IN ($ph)", $this->progIds);
            db_run("DELETE FROM programs WHERE id IN ($ph)", $this->progIds);
        }
        $this->progIds = [];
    }

    private function cleanStudentData(): void
    {
        db_run("DELETE FROM stamp_transactions WHERE student_id IN (1,2,3) AND year_id = ?", [$this->yearId]);
        db_run("DELETE FROM student_stamps    WHERE student_id IN (1,2,3) AND year_id = ?", [$this->yearId]);
        if ($this->progIds) {
            $ph = implode(',', array_fill(0, count($this->progIds), '?'));
            db_run("DELETE FROM attendances WHERE program_id IN ($ph)", $this->progIds);
        }
    }

    /** Tạo một chương trình EMULATION, lặp mọi ngày trong tuần. */
    private function makeEmulationProgram(string $effFrom, ?string $effTo = null): int
    {
        $id = db_insert(
            "INSERT INTO programs
                (year_id, name, type, status, count_for_attendance, count_for_emulation,
                 start_time, day_of_week, days_of_week, effective_from, effective_to)
             VALUES (?, 'Đi lễ hằng ngày (hook test)', 'bắt buộc', 'kích hoạt', 1, 1,
                     '05:00:00', NULL, '0,1,2,3,4,5,6', ?, ?)",
            [$this->yearId, $effFrom, $effTo]
        );
        $this->progIds[] = $id;
        return $id;
    }

    /** Ví hiện tại của một em (ép kiểu int cho assertSame). */
    private function walletOf(?int $studentId = null): array
    {
        $studentId ??= $this->sid;
        $w = db_one("SELECT * FROM student_stamps WHERE student_id=? AND year_id=?",
                    [$studentId, $this->yearId]) ?? [];
        foreach (['current_balance', 'held_balance', 'total_earned', 'current_streak', 'longest_streak'] as $k) {
            $w[$k] = (int) ($w[$k] ?? 0);
        }
        return $w;
    }

    // =====================================================================
    //  program_earns_stamps(): luật bật/tắt của "móc"
    // =====================================================================

    public function test_program_earns_stamps_true_when_count_for_emulation_1(): void
    {
        $this->assertTrue(program_earns_stamps(['count_for_emulation' => 1]));
        $this->assertTrue(program_earns_stamps(['count_for_emulation' => '1']));
    }

    public function test_program_earns_stamps_false_when_0_or_absent(): void
    {
        $this->assertFalse(program_earns_stamps(['count_for_emulation' => 0]));
        $this->assertFalse(program_earns_stamps(['count_for_emulation' => null]));
        $this->assertFalse(program_earns_stamps([]));
    }

    // =====================================================================
    //  Hợp đồng tích hợp: ghi điểm danh -> recalc -> ví đổi; gỡ -> ví hoàn
    // =====================================================================

    /**
     * Mô phỏng đúng luồng của attendance.php: chương trình emulation ->
     * INSERT vào attendances -> gọi recalc_stamps() (như "móc" sẽ làm sau
     * mỗi thao tác ghi). Sau đó DELETE (untoggle) -> gọi lại recalc_stamps()
     * -> ví phải hoàn về 0.
     */
    public function test_insert_then_recalc_credits_wallet_delete_then_recalc_refunds(): void
    {
        $date = '2026-01-05'; // Thứ Hai, ngày thường -> +1
        $p = $this->makeEmulationProgram($date, $date);

        $this->assertTrue(program_earns_stamps($this->findProgram($p)), 'chương trình test phải là emulation');

        // Ghi điểm danh giống nhánh INSERT tay của attendance.php
        $attId = db_insert(
            "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
             VALUES (?,?,?,?, 'có mặt', 'tay', ?)",
            [$this->yearId, $p, $date, $this->sid, $this->adminId]
        );

        recalc_stamps($this->sid, $this->yearId);

        $w1 = $this->walletOf();
        $this->assertSame(1, $w1['total_earned'], 'ghi điểm danh buổi emulation -> +1 Mộc');
        $this->assertSame(1, $w1['current_balance']);

        // Gỡ điểm danh giống nhánh DELETE (untoggle) của attendance.php
        db_run("DELETE FROM attendances WHERE id=?", [$attId]);
        recalc_stamps($this->sid, $this->yearId);

        $w2 = $this->walletOf();
        $this->assertSame(0, $w2['total_earned'], 'gỡ điểm danh -> hoàn Mộc về 0');
        $this->assertSame(0, $w2['current_balance']);
    }

    /** Chương trình KHÔNG emulation -> "móc" phải bỏ qua, ví không đổi. */
    public function test_non_emulation_program_hook_is_skipped(): void
    {
        $date = '2026-01-06'; // Thứ Ba
        $id = db_insert(
            "INSERT INTO programs
                (year_id, name, type, status, count_for_attendance, count_for_emulation,
                 start_time, day_of_week, days_of_week, effective_from, effective_to)
             VALUES (?, 'Sinh hoạt thường (không Mộc)', 'bắt buộc', 'kích hoạt', 1, 0,
                     '05:00:00', NULL, '0,1,2,3,4,5,6', ?, ?)",
            [$this->yearId, $date, $date]
        );
        $this->progIds[] = $id;
        $prog = $this->findProgram($id);

        $this->assertFalse(program_earns_stamps($prog), 'chương trình này không tính Mộc');

        db_insert(
            "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
             VALUES (?,?,?,?, 'có mặt', 'tay', ?)",
            [$this->yearId, $id, $date, $this->sid, $this->adminId]
        );

        // "Móc" thật sự chỉ gọi recalc_stamps khi program_earns_stamps() true;
        // ở đây ta khẳng định điều kiện đó false nên không gọi -> ví vẫn trống.
        $w = $this->walletOf();
        $this->assertSame(0, $w['total_earned'], 'chương trình không emulation -> không có ví/Mộc nào được tạo');
    }

    private function findProgram(int $id): array
    {
        return db_one('SELECT * FROM programs WHERE id = ?', [$id]);
    }
}
