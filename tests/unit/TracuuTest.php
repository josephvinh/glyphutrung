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
            db_run("DELETE FROM programs    WHERE id IN ($ph)", $this->progIds);
        }
        $this->progIds = [];
    }

    private function cleanStudentData(): void
    {
        db_run("DELETE FROM stamp_transactions WHERE student_id = ? AND year_id = ?", [$this->sid, $this->yearId]);
        db_run("DELETE FROM student_stamps    WHERE student_id = ? AND year_id = ?", [$this->sid, $this->yearId]);
        if ($this->progIds) {
            $ph = implode(',', array_fill(0, count($this->progIds), '?'));
            db_run("DELETE FROM attendances WHERE program_id IN ($ph)", $this->progIds);
        }
    }

    /** Chương trình count_for_emulation tối thiểu (để có program_id cho attendances). */
    private function makeProgram(): int
    {
        $id = db_insert(
            "INSERT INTO programs
                (year_id, name, type, status, count_for_attendance, count_for_emulation,
                 start_time, day_of_week, days_of_week, effective_from, effective_to)
             VALUES (?, 'Đi lễ (test tra cứu)', 'bắt buộc', 'kích hoạt', 1, 1,
                     '05:00:00', NULL, '0,1,2,3,4,5,6', '2026-01-01', NULL)",
            [$this->yearId]
        );
        $this->progIds[] = $id;
        return $id;
    }

    /** Ghi một buổi điểm danh, trả về attendance_id (để gắn ref_attendance_id). */
    private function mark(int $progId, string $date, string $status = 'có mặt'): int
    {
        return db_insert(
            "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
             VALUES (?,?,?,?,?, 'tay', ?)",
            [$this->yearId, $progId, $date, $this->sid, $status, $this->adminId]
        );
    }

    /** Giao dịch Mộc CÓ gắn ref_attendance_id (earn/bonus lấy ngày từ attendance). */
    private function insertTxRef(int $amount, string $type, int $attId, string $desc = 'test'): void
    {
        db_run(
            "INSERT INTO stamp_transactions (year_id, student_id, amount, type, ref_attendance_id, description, actor_id)
             VALUES (?,?,?,?,?,?, NULL)",
            [$this->yearId, $this->sid, $amount, $type, $attId, $desc]
        );
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

    // ================= tracuu_moc_by_day() (LỊCH ĐÓNG MỘC) ================

    public function test_moc_by_day_groups_by_session_date(): void
    {
        $prog = $this->makeProgram();
        // Ngày thường (T5): +1 ; Chúa Nhật: +2 (một earn) + thưởng chuỗi +1
        $a1 = $this->mark($prog, '2026-09-24');            // thứ Năm
        $a2 = $this->mark($prog, '2026-09-27');            // Chúa Nhật
        $this->insertTxRef(1, 'attendance',   $a1, 'Đi lễ 2026-09-24 (+1)');
        $this->insertTxRef(2, 'attendance',   $a2, 'Đi lễ 2026-09-27 (+2)');
        $this->insertTxRef(1, 'streak_bonus', $a2, 'Thưởng chuỗi (+1)');

        $moc = tracuu_moc_by_day($this->sid, $this->yearId);

        // Gộp THEO NGÀY: earn+bonus cùng ngày cộng lại.
        $this->assertSame(1, $moc['2026-09-24'] ?? null, 'ngày thường +1');
        $this->assertSame(3, $moc['2026-09-27'] ?? null, 'Chúa Nhật +2 và thưởng chuỗi +1 = 3');
        $this->assertCount(2, $moc);
    }

    public function test_moc_by_day_excludes_spend_and_manual_adjust(): void
    {
        $prog = $this->makeProgram();
        $a1 = $this->mark($prog, '2026-09-24');
        $this->insertTxRef(1,  'attendance',    $a1, 'Đi lễ (+1)');
        // spend/manual_adjust dù có gắn ref vẫn KHÔNG được lên lịch (lọc theo type).
        $this->insertTxRef(-5, 'spend',         $a1, 'Đổi quà (-5)');
        $this->insertTxRef(3,  'manual_adjust', $a1, 'Thưởng tay (+3)');

        $moc = tracuu_moc_by_day($this->sid, $this->yearId);

        $this->assertSame(1, $moc['2026-09-24'] ?? null, 'chỉ earn/bonus lên lịch');
        $this->assertCount(1, $moc, 'spend & manual_adjust không tạo ngày trên lịch');
    }

    public function test_moc_by_day_skips_non_positive_days(): void
    {
        $prog = $this->makeProgram();
        $a1 = $this->mark($prog, '2026-09-24');
        // Ngày có earn +1 rồi bị điều chỉnh earn -1 (net 0) → không hiện ô vàng.
        $this->insertTxRef(1,  'attendance', $a1, '+1');
        $this->insertTxRef(-1, 'attendance', $a1, 'điều chỉnh -1');

        $moc = tracuu_moc_by_day($this->sid, $this->yearId);
        $this->assertArrayNotHasKey('2026-09-24', $moc, 'ngày tổng ≤ 0 không lên lịch');
    }

    // ============== tracuu_moc_thuong_khac() (manual_adjust) =============

    public function test_moc_thuong_khac_sums_only_manual_adjust(): void
    {
        $this->insertTx(5,  'manual_adjust', 'Thưởng phục vụ (+5)');
        $this->insertTx(3,  'manual_adjust', 'Thưởng lễ phép (+3)');
        $this->insertTx(2,  'attendance',    'Đi lễ (+2)');   // KHÔNG tính
        $this->insertTx(-4, 'spend',         'Đổi quà (-4)'); // KHÔNG tính

        $this->assertSame(8, tracuu_moc_thuong_khac($this->sid, $this->yearId));
    }

    public function test_moc_thuong_khac_zero_when_none(): void
    {
        $this->insertTx(2, 'attendance', 'Đi lễ (+2)');
        $this->assertSame(0, tracuu_moc_thuong_khac($this->sid, $this->yearId));
    }

    public function test_moc_thuong_khac_nets_negative_adjustments(): void
    {
        $this->insertTx(5,  'manual_adjust', 'Thưởng (+5)');
        $this->insertTx(-2, 'manual_adjust', 'Trừ nhầm (-2)');
        $this->assertSame(3, tracuu_moc_thuong_khac($this->sid, $this->yearId));
    }

    // ==================== tracuu_loi_la_thu() (lời thư) ===================

    public function test_loi_la_thu_has_all_keys(): void
    {
        $loi = tracuu_loi_la_thu(['full_name' => 'Nguyễn Văn An', 'current_streak' => 0, 'longest_streak' => 0, 'current_balance' => 0]);
        $this->assertSame(['khen', 'themVi', 'nhac', 'cham'], array_keys($loi));
        $this->assertNotSame('', $loi['khen']);
        $this->assertNotSame('', $loi['nhac']);
        $this->assertNotSame('', $loi['cham']);
    }

    public function test_loi_la_thu_khen_branch_by_streak(): void
    {
        $mk = fn(int $streak, int $longest = 0) => tracuu_loi_la_thu([
            'full_name' => 'Trần Bình Minh', 'current_streak' => $streak,
            'longest_streak' => $longest, 'current_balance' => 0,
        ])['khen'];

        $this->assertStringContainsString('8 tuần', $mk(8), 'chuỗi ≥8 khen mạnh, gọi bằng tên');
        $this->assertStringContainsString('Minh', $mk(8), 'gọi bằng tên (từ cuối họ tên)');
        $this->assertStringContainsString('4 tuần', $mk(4));
        $this->assertStringContainsString('2 tuần', $mk(2));
        // streak=0 nhưng từng giữ ≥3 tuần → nhánh AN ỦI nhắc kỷ lục cũ.
        $this->assertStringContainsString('5 tuần', $mk(0, 5));
        // streak=0, chưa từng có chuỗi → nhánh mời đi lễ Chúa Nhật.
        $this->assertStringContainsString('Chúa Nhật', $mk(0, 0));
    }

    public function test_loi_la_thu_them_vi_only_when_rich(): void
    {
        $ngheo = tracuu_loi_la_thu(['full_name' => 'A B', 'current_streak' => 1, 'longest_streak' => 1, 'current_balance' => 50]);
        $this->assertSame('', $ngheo['themVi'], 'ví < 100 không gợi ý đổi quà');

        $giau = tracuu_loi_la_thu(['full_name' => 'A B', 'current_streak' => 1, 'longest_streak' => 1, 'current_balance' => 120]);
        $this->assertStringContainsString('120 Mộc', $giau['themVi'], 'ví ≥ 100 gợi ý đổi quà');
    }
}
