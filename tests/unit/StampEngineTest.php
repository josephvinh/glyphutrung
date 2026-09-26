<?php
/**
 * Test cho Engine tính Mộc & chuỗi (recalc_stamps / stamp_earn_days).
 *
 * Quy ước theo tests/unit/ScopeTest.php:
 *   - require_once bootstrap (tránh fatal app_config() redeclare)
 *   - dựng program/attendances tạm trong từng test, dọn ở tearDown.
 *
 * Dữ liệu seed dùng chung: năm học id=1 (đang mở), học sinh HS001..003 (id 1..3),
 * admin member id=1. Hôm nay = 2026-09-26 → mọi ngày test đặt trong quá khứ.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';

use PHPUnit\Framework\TestCase;

class StampEngineTest extends TestCase
{
    private int $yearId = 1;
    private int $sid    = 1;   // HS001
    private int $adminId = 1;
    /** @var int[] chương trình tạo trong test, để dọn */
    private array $progIds = [];
    /** @var string[] ngày đã chèn leave_requests, để dọn */
    private array $leaveDates = [];

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
            // attendances gắn theo program bị xoá theo; xoá tường minh cho chắc
            db_run("DELETE FROM attendances WHERE program_id IN ($ph)", $this->progIds);
            db_run("DELETE FROM programs WHERE id IN ($ph)", $this->progIds);
        }
        $this->progIds = [];
    }

    /** Dọn ví + giao dịch + điểm danh của các học sinh test (1,2,3) */
    private function cleanStudentData(): void
    {
        db_run("DELETE FROM stamp_transactions WHERE student_id IN (1,2,3) AND year_id = ?", [$this->yearId]);
        db_run("DELETE FROM student_stamps    WHERE student_id IN (1,2,3) AND year_id = ?", [$this->yearId]);
        if ($this->progIds) {
            $ph = implode(',', array_fill(0, count($this->progIds), '?'));
            db_run("DELETE FROM attendances WHERE program_id IN ($ph)", $this->progIds);
        }
        if (db_has_table('leave_requests')) {
            db_run("DELETE FROM leave_requests WHERE student_id IN (1,2,3)");
        }
    }

    // ---- Helpers dựng dữ liệu -------------------------------------------

    /** Tạo một chương trình count_for_emulation, lặp các thứ $days (CSV 0-6). */
    private function makeProgram(string $effFrom, ?string $effTo = null, string $days = '0,1,2,3,4,5,6'): int
    {
        $id = db_insert(
            "INSERT INTO programs
                (year_id, name, type, status, count_for_attendance, count_for_emulation,
                 start_time, day_of_week, days_of_week, effective_from, effective_to)
             VALUES (?, 'Đi lễ hằng ngày (test)', 'bắt buộc', 'kích hoạt', 1, 1,
                     '05:00:00', NULL, ?, ?, ?)",
            [$this->yearId, $days, $effFrom, $effTo]
        );
        $this->progIds[] = $id;
        return $id;
    }

    /** Ghi một lượt điểm danh trực tiếp vào attendances. */
    private function mark(int $progId, string $date, string $status = 'có mặt', ?int $studentId = null): int
    {
        $studentId ??= $this->sid;
        return db_insert(
            "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
             VALUES (?,?,?,?,?, 'tay', ?)",
            [$this->yearId, $progId, $date, $studentId, $status, $this->adminId]
        );
    }

    /** Ví hiện tại của một em (ép kiểu int cho assertSame). */
    private function walletOf(?int $studentId = null): array
    {
        $studentId ??= $this->sid;
        $w = db_one("SELECT * FROM student_stamps WHERE student_id=? AND year_id=?",
                    [$studentId, $this->yearId]) ?? [];
        foreach (['current_balance','held_balance','total_earned','current_streak','longest_streak'] as $k) {
            $w[$k] = (int) ($w[$k] ?? 0);
        }
        return $w;
    }

    private function txByType(string $type, ?int $studentId = null): array
    {
        $studentId ??= $this->sid;
        return db_all("SELECT * FROM stamp_transactions WHERE student_id=? AND year_id=? AND type=? ORDER BY id",
                      [$studentId, $this->yearId, $type]);
    }

    private function plusDays(string $date, int $n): string
    {
        $d = new DateTime($date);
        $d->modify(($n >= 0 ? '+' : '') . $n . ' days');
        return $d->format('Y-m-d');
    }

    // =====================================================================
    //  CÁC CA KIỂM THỬ
    // =====================================================================

    /** Ngày thường +1, Chúa Nhật +2. */
    public function test_weekday_earns_1_sunday_earns_2(): void
    {
        // 2026-01-04 là Chúa Nhật, 2026-01-05 là Thứ Hai.
        $this->assertSame('0', date('w', strtotime('2026-01-04')), 'kiểm định lịch: 04/01/2026 là CN');
        $this->assertSame('1', date('w', strtotime('2026-01-05')), 'kiểm định lịch: 05/01/2026 là T2');

        $p = $this->makeProgram('2026-01-04', '2026-01-05');
        $this->mark($p, '2026-01-04', 'có mặt'); // CN → +2
        $this->mark($p, '2026-01-05', 'có mặt'); // T2 → +1

        $r = recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(3, $w['total_earned'], 'CN(+2) + ngày thường(+1) = 3');
        $this->assertSame(3, $w['current_balance']);
        $this->assertSame(2, $w['current_streak']);
        $this->assertSame(3, (int) $r['total_earned']);
    }

    /** Nhiều buổi cùng ngày chỉ tính 1 lần; đúng giờ ưu tiên hơn trễ. */
    public function test_multiple_sessions_same_day_counts_once(): void
    {
        // Hai chương trình emulation khác nhau, cùng một ngày thường.
        $p1 = $this->makeProgram('2026-02-02', '2026-02-02');
        $p2 = $this->makeProgram('2026-02-02', '2026-02-02');
        $this->mark($p1, '2026-02-02', 'đi trễ');
        $this->mark($p2, '2026-02-02', 'có mặt'); // có mặt phải thắng

        $days = stamp_earn_days($this->sid, $this->yearId);
        $this->assertSame('có mặt', $days['2026-02-02'] ?? null, 'có mặt ưu tiên hơn đi trễ');

        recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(1, $w['total_earned'], 'hai buổi cùng ngày chỉ +1');
        $this->assertCount(1, $this->txByType('attendance'), 'chỉ 1 giao dịch earn cho 1 ngày');
    }

    /** Chuỗi tăng theo ngày liên tiếp. */
    public function test_streak_increments_consecutive_days(): void
    {
        // 2026-03-02 (T2), 2026-03-03 (T3): hai ngày liên tiếp, dưới mốc 3.
        $p = $this->makeProgram('2026-03-02', '2026-03-03');
        $this->mark($p, '2026-03-02', 'có mặt');
        $this->mark($p, '2026-03-03', 'có mặt');

        recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(2, $w['current_streak']);
        $this->assertSame(2, $w['longest_streak']);
        $this->assertSame(2, $w['total_earned']);
        $this->assertCount(0, $this->txByType('streak_bonus'), 'chưa chạm mốc → không thưởng');
    }

    /** Vắng một ngày có lịch → chuỗi reset 0 (kể cả có phép). */
    public function test_missing_scheduled_day_resets_streak(): void
    {
        // Lịch T2..T4; đi T2,T3; vắng T4 (ngày cuối cửa sổ) → chuỗi về 0.
        $p = $this->makeProgram('2026-03-02', '2026-03-04'); // T2..T4
        $this->mark($p, '2026-03-02', 'có mặt');
        $this->mark($p, '2026-03-03', 'có mặt');
        // KHÔNG điểm danh 2026-03-04 (T4)

        // Dù có đơn nghỉ phép đã duyệt vẫn phải reset (engine không đọc leave_requests).
        if (db_has_table('leave_requests')) {
            $cols = db_all("SHOW COLUMNS FROM leave_requests");
            $names = array_map(fn($c) => $c['Field'], $cols);
            if (in_array('student_id', $names, true) && in_array('status', $names, true)) {
                // chèn tối thiểu nếu schema cho phép; nếu không, bỏ qua an toàn.
                try {
                    db_run("INSERT INTO leave_requests (student_id, status) VALUES (?, 'đã duyệt')", [$this->sid]);
                    $this->leaveDates[] = '2026-03-04';
                } catch (Throwable $e) { /* schema khác — không sao, engine vẫn không đọc */ }
            }
        }

        recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(0, $w['current_streak'], 'vắng ngày có lịch cuối cửa sổ → chuỗi 0');
        $this->assertSame(2, $w['longest_streak'], 'đỉnh chuỗi trước khi vắng = 2');
        $this->assertSame(2, $w['total_earned']);
    }

    /** Đi trễ vẫn nối chuỗi nhưng nếu chạm mốc vào hôm trễ thì mất thưởng. */
    public function test_late_keeps_streak_but_forfeits_milestone(): void
    {
        // T2,T3 có mặt; T4 đi trễ → streak chạm 3 đúng hôm trễ → mất mốc +1.
        $p = $this->makeProgram('2026-04-06', '2026-04-08'); // T2..T4
        $this->mark($p, '2026-04-06', 'có mặt');
        $this->mark($p, '2026-04-07', 'có mặt');
        $this->mark($p, '2026-04-08', 'đi trễ');

        recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(3, $w['current_streak'], 'đi trễ vẫn nối chuỗi');
        $this->assertCount(0, $this->txByType('streak_bonus'), 'chạm mốc hôm trễ → mất thưởng');
        $this->assertSame(3, $w['total_earned'], 'đi trễ vẫn +1 mỗi ngày');
        $this->assertSame(3, $w['current_balance']);
    }

    /** Thưởng mốc 3 (+1), 7 (+3), 30 (+15) khi chạm mốc vào hôm có mặt. */
    public function test_milestone_bonus_3_7_30(): void
    {
        $start = '2026-05-04';
        $end   = $this->plusDays($start, 29); // 30 ngày liên tiếp
        $p = $this->makeProgram($start, $end);

        $dayEarn = 0;
        for ($i = 0; $i < 30; $i++) {
            $d = $this->plusDays($start, $i);
            $this->mark($p, $d, 'có mặt');
            $dayEarn += (date('w', strtotime($d)) === '0') ? 2 : 1;
        }

        recalc_stamps($this->sid, $this->yearId);

        $bonuses = array_map(fn($t) => (int) $t['amount'], $this->txByType('streak_bonus'));
        sort($bonuses);
        $this->assertSame([1, 3, 15], $bonuses, 'ba mốc 3/7/30 → +1/+3/+15');

        $w = $this->walletOf();
        $this->assertSame(30, $w['current_streak']);
        $this->assertSame(30, $w['longest_streak']);
        $this->assertSame($dayEarn + 19, $w['total_earned'], 'earn ngày + tổng thưởng 19');
        $this->assertSame($dayEarn + 19, $w['current_balance']);
    }

    /** Điểm danh trước effective_from không được tính. */
    public function test_attendance_before_effective_from_not_counted(): void
    {
        $p = $this->makeProgram('2026-07-06', '2026-07-06'); // hiệu lực từ T2 06/07
        $before = $this->mark($p, '2026-07-05', 'có mặt');   // CN trước hạn → bỏ
        $this->mark($p, '2026-07-06', 'có mặt');             // trong hạn → +1

        $days = stamp_earn_days($this->sid, $this->yearId);
        $this->assertArrayNotHasKey('2026-07-05', $days, 'ngày trước effective_from bị loại');

        recalc_stamps($this->sid, $this->yearId);

        $w = $this->walletOf();
        $this->assertSame(1, $w['total_earned'], 'chỉ tính ngày trong hạn');
        $this->assertSame(1, $w['current_streak']);

        // Không giao dịch nào tham chiếu buổi trước hạn
        $ref = db_one("SELECT COUNT(*) c FROM stamp_transactions WHERE ref_attendance_id=?", [$before]);
        $this->assertSame(0, (int) $ref['c']);
    }

    /** Gọi recalc hai lần: tổng không đổi, không nhân đôi giao dịch; giữ nguyên spend/manual_adjust. */
    public function test_recalc_idempotent(): void
    {
        $p = $this->makeProgram('2026-08-10', '2026-08-11'); // T2,T3
        $this->mark($p, '2026-08-10', 'có mặt');
        $this->mark($p, '2026-08-11', 'có mặt');

        // Một giao dịch điều chỉnh tay phải được recalc GIỮ NGUYÊN.
        db_run("INSERT INTO stamp_transactions (year_id, student_id, amount, type, description, actor_id)
                VALUES (?,?,?, 'manual_adjust', 'thưởng tay', ?)",
               [$this->yearId, $this->sid, 5, $this->adminId]);

        recalc_stamps($this->sid, $this->yearId);
        $w1  = $this->walletOf();
        $n1  = count($this->txByType('attendance'));

        recalc_stamps($this->sid, $this->yearId);
        $w2  = $this->walletOf();
        $n2  = count($this->txByType('attendance'));

        $this->assertSame($w1['total_earned'], $w2['total_earned'], 'tổng earn không đổi');
        $this->assertSame($w1['current_balance'], $w2['current_balance'], 'số dư không đổi');
        $this->assertSame($n1, $n2, 'không nhân đôi giao dịch earn');
        $this->assertSame(2, $w2['total_earned']);
        $this->assertSame(7, $w2['current_balance'], 'earn 2 + manual_adjust 5');
        $this->assertCount(1, $this->txByType('manual_adjust'), 'manual_adjust được giữ nguyên');
    }

    /** Gỡ điểm danh rồi recalc → hoàn Mộc, về đúng trạng thái trước. */
    public function test_untoggle_refunds(): void
    {
        // Chỉ ngày D nằm trong lịch (effective_to = D); D+1 vẫn earn theo ngày
        // nhưng không thuộc chuỗi → gỡ D+1 không tạo "vắng có lịch".
        $D  = '2026-08-03';        // T2
        $D1 = $this->plusDays($D, 1); // T3
        $p = $this->makeProgram($D, $D); // lịch chỉ có D
        $this->mark($p, $D,  'có mặt');
        $attD1 = $this->mark($p, $D1, 'có mặt');

        recalc_stamps($this->sid, $this->yearId);
        $w1 = $this->walletOf();
        $this->assertSame(2, $w1['total_earned']);
        $this->assertSame(2, $w1['current_balance']);
        $this->assertSame(1, $w1['current_streak']);
        $this->assertCount(2, $this->txByType('attendance'));

        // Gỡ điểm danh ngày D+1
        db_run("DELETE FROM attendances WHERE id=?", [$attD1]);
        recalc_stamps($this->sid, $this->yearId);

        $w2 = $this->walletOf();
        $this->assertSame(1, $w2['total_earned'], 'hoàn về đúng trạng thái trước khi thêm D+1');
        $this->assertSame(1, $w2['current_balance']);
        $this->assertSame(1, $w2['current_streak']);
        $this->assertCount(1, $this->txByType('attendance'), 'giao dịch earn của D+1 đã bị gỡ');
    }
}
