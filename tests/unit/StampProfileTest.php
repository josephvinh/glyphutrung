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
    private int $sid2   = 2;   // HS002 — dùng cho các test hàng loạt

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
        db_run("DELETE FROM stamp_transactions WHERE student_id IN (?,?) AND year_id = ?", [$this->sid, $this->sid2, $this->yearId]);
        db_run("DELETE FROM student_stamps    WHERE student_id IN (?,?) AND year_id = ?", [$this->sid, $this->sid2, $this->yearId]);
    }

    private function insertTx(int $amount, string $type, string $desc, string $createdAt, ?int $studentId = null): void
    {
        db_run(
            "INSERT INTO stamp_transactions (year_id, student_id, amount, type, description, actor_id, created_at)
             VALUES (?,?,?,?,?, NULL, ?)",
            [$this->yearId, $studentId ?? $this->sid, $amount, $type, $desc, $createdAt]
        );
    }

    private function insertWallet(int $current, int $held, int $totalEarned, ?int $studentId = null): void
    {
        db_run(
            "INSERT INTO student_stamps (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak)
             VALUES (?,?,?,?,?,0,0)",
            [$this->yearId, $studentId ?? $this->sid, $current, $held, $totalEarned]
        );
    }

    /**
     * Số dư có thể âm trong tình huống hiếm (admin xoá buổi điểm danh đã nuôi
     * mộc mà em đã đổi quà). Hiển thị KHÔNG được cho ra số âm — kẹp về 0.
     * Giá trị thật vẫn ở CSDL để chặn tiêu tiếp (kiểm ở rewards_redeem, không
     * qua các hàm hiển thị này).
     */
    public function test_negative_balance_clamped_to_zero_in_display(): void
    {
        // total_earned = 2 nhưng đã tiêu 10 (spend -10) → current_balance = -8
        $this->insertWallet(-8, 0, 2);

        $single = stamp_summary($this->sid, $this->yearId);
        $this->assertSame(0, $single['current_balance'], 'stamp_summary phải kẹp số âm về 0');
        $this->assertSame(2, $single['total_earned']);

        $bulk = stamp_summaries_bulk([$this->sid], $this->yearId);
        $this->assertSame(0, $bulk[$this->sid]['current_balance'], 'stamp_summaries_bulk phải kẹp số âm về 0');

        // CSDL vẫn giữ giá trị âm thật (không bị ghi đè bởi hiển thị)
        $raw = db_one("SELECT current_balance FROM student_stamps WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
        $this->assertSame(-8, (int) $raw['current_balance'], 'CSDL vẫn lưu số dư thật (âm) để chặn tiêu tiếp');
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

        // Chèn 25 giao dịch — nhiều hơn giới hạn hiển thị (STAMP_RECENT_LIMIT).
        for ($i = 1; $i <= 25; $i++) {
            $this->insertTx(1, 'attendance', "Đi lễ #$i", sprintf('2026-09-%02d 05:00:00', ($i % 28) + 1));
        }

        $out = stamp_summary($this->sid, $this->yearId);

        $this->assertCount(STAMP_RECENT_LIMIT, $out['recent_transactions']);
    }

    // ---- stamp_summaries_bulk() — bản hàng loạt dùng ở api/data.php -------

    public function test_bulk_matches_single_student_summary_and_zeros_missing_wallet(): void
    {
        db_run(
            "INSERT INTO student_stamps
                (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak)
             VALUES (?,?,?,?,?,?,?)",
            [$this->yearId, $this->sid, 42, 5, 60, 3, 7]
        );
        $this->insertTx(2, 'attendance', 'Đi lễ ngày 2026-09-20 (+2)', '2026-09-20 05:10:00', $this->sid);
        $this->insertTx(-10, 'spend', 'Đổi quà: Bút bi', '2026-09-21 09:00:00', $this->sid);
        // sid2 KHÔNG có dòng student_stamps/giao dịch nào — phải ra toàn số 0.

        $bulk = stamp_summaries_bulk([$this->sid, $this->sid2], $this->yearId);

        $this->assertArrayHasKey($this->sid, $bulk);
        $this->assertArrayHasKey($this->sid2, $bulk);

        // Em có ví: khớp CHÍNH XÁC với stamp_summary() cho cùng em.
        $this->assertSame(stamp_summary($this->sid, $this->yearId), $bulk[$this->sid]);

        // Em không có ví: toàn số 0 / rỗng, không lỗi, không bị thiếu khỏi kết quả.
        $this->assertSame(0, $bulk[$this->sid2]['current_balance']);
        $this->assertSame(0, $bulk[$this->sid2]['held_balance']);
        $this->assertSame(0, $bulk[$this->sid2]['total_earned']);
        $this->assertSame(0, $bulk[$this->sid2]['current_streak']);
        $this->assertSame(0, $bulk[$this->sid2]['longest_streak']);
        $this->assertSame([], $bulk[$this->sid2]['recent_transactions']);
    }

    public function test_bulk_caps_recent_transactions_per_student_independently(): void
    {
        db_run(
            "INSERT INTO student_stamps (year_id, student_id, current_balance, held_balance, total_earned, current_streak, longest_streak)
             VALUES (?,?,25,0,25,1,1), (?,?,3,0,3,0,0)",
            [$this->yearId, $this->sid, $this->yearId, $this->sid2]
        );

        // sid: 25 giao dịch (vượt STAMP_RECENT_LIMIT); sid2: 3 giao dịch (dưới giới hạn).
        for ($i = 1; $i <= 25; $i++) {
            $this->insertTx(1, 'attendance', "Đi lễ #$i", sprintf('2026-09-%02d 05:00:00', ($i % 28) + 1), $this->sid);
        }
        for ($i = 1; $i <= 3; $i++) {
            $this->insertTx(1, 'attendance', "Đi lễ #$i", sprintf('2026-09-%02d 06:00:00', $i), $this->sid2);
        }

        $bulk = stamp_summaries_bulk([$this->sid, $this->sid2], $this->yearId);

        // Giao dịch của một em không được rò rỉ / lẫn vào giới hạn của em kia:
        // sid bị cắt đúng STAMP_RECENT_LIMIT dòng, sid2 vẫn đủ 3 dòng của
        // riêng nó (không bị "ăn hụt" chỗ vì sid dùng hết ngân sách LIMIT SQL).
        $this->assertCount(STAMP_RECENT_LIMIT, $bulk[$this->sid]['recent_transactions']);
        $this->assertCount(3, $bulk[$this->sid2]['recent_transactions']);
    }
}
