<?php
/**
 * Test cho LỚP API đặt/hủy/xác nhận đơn đổi quà (Task P3-2).
 *
 * Endpoint (`public/api/tracuu_order.php` cho public, `public/api/rewards.php`
 * cho Thủ thư) đọc `php://input` + gọi `require_login()`/`require_write()`
 * ngay khi được nạp, nên KHÔNG test trực tiếp qua HTTP (giống lý do
 * RewardsRedeemTest/RewardsOrderTest test thẳng _rewards.php thay vì
 * rewards.php). Ở đây kiểm 3 thứ API sẽ dựa vào:
 *
 *   1) Hàm THUẦN `rewards_normalize_items()` — API dùng để chuẩn hoá input
 *      JSON thô (`{giftId, qty}` kiểu chuỗi/âm/thiếu) trước khi gọi
 *      rewards_place_order().
 *   2) QUYẾT ĐỊNH GÁC QUYỀN mà mỗi action sẽ áp dụng: 'confirm'/'staff_pending'
 *      cần require_permission('rewards', 'edit'|'view') — thu_thu/admin có,
 *      glv/bdh không.
 *   3) HỢP ĐỒNG gọi lõi P3-1 mà mỗi action thực hiện: place rồi pending trả
 *      đúng đơn vừa đặt; cancel nhả đúng; confirm override bỏ qua mật mã;
 *      confirm mật mã sai bị từ chối. Đây là hình chiếu của những gì action
 *      trong tracuu_order.php / rewards.php sẽ làm, KHÔNG lặp lại toàn bộ
 *      RewardsOrderTest.php (chỉ tái khẳng định phần API dựa vào).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';
require_once __DIR__ . '/../../public/api/_rewards.php';

use PHPUnit\Framework\TestCase;

class RewardsOrderApiTest extends TestCase
{
    private int $yearId  = 1;
    private int $sid     = 1;   // HS001
    private int $adminId = 1;
    /** @var int[] quà tạo trong test, để dọn */
    private array $giftIds = [];

    protected function setUp(): void
    {
        $this->adminId = (int) (db_one("SELECT id FROM members WHERE role_code='admin' LIMIT 1")['id'] ?? 1);
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        db_run("DELETE FROM stamp_transactions WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
        $orders = db_all("SELECT id FROM gift_orders WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
        if ($orders) {
            $ids = array_map(fn($o) => (int) $o['id'], $orders);
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            db_run("DELETE FROM gift_order_items WHERE order_id IN ($ph)", $ids);
            db_run("DELETE FROM gift_orders WHERE id IN ($ph)", $ids);
        }
        db_run("DELETE FROM student_stamps WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
        if ($this->giftIds) {
            $ph = implode(',', array_fill(0, count($this->giftIds), '?'));
            db_run("DELETE FROM gift_order_items WHERE gift_id IN ($ph)", $this->giftIds);
            db_run("DELETE FROM gifts WHERE id IN ($ph)", $this->giftIds);
        }
        $this->giftIds = [];
    }

    private function makeGift(int $cost, int $stock, string $status = 'còn bán', string $name = 'Quà test'): int
    {
        $id = db_insert(
            "INSERT INTO gifts (name, stamp_cost, stock, status, sort_order) VALUES (?,?,?,?,1)",
            [$name, $cost, $stock, $status]
        );
        $this->giftIds[] = $id;
        return $id;
    }

    private function setWallet(int $current, int $held = 0): void
    {
        db_run(
            "INSERT INTO student_stamps (year_id, student_id, current_balance, held_balance, total_earned)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE current_balance=VALUES(current_balance), held_balance=VALUES(held_balance)",
            [$this->yearId, $this->sid, $current, $held, $current]
        );
    }

    private function wallet(): array
    {
        $w = db_one("SELECT current_balance, held_balance FROM student_stamps WHERE student_id=? AND year_id=?",
                    [$this->sid, $this->yearId]) ?? [];
        return [
            'current' => (int) ($w['current_balance'] ?? 0),
            'held'    => (int) ($w['held_balance'] ?? 0),
        ];
    }

    // =====================================================================
    //  1) rewards_normalize_items() — hàm thuần, chuẩn hoá input public
    // =====================================================================

    public function test_normalize_items_keeps_valid_entries_and_casts_types(): void
    {
        $out = rewards_normalize_items([
            ['giftId' => '5', 'qty' => '2'],   // chuỗi số -> ép kiểu int
            ['giftId' => 7, 'qty' => 1],
        ]);
        $this->assertSame([
            ['giftId' => 5, 'qty' => 2],
            ['giftId' => 7, 'qty' => 1],
        ], $out);
    }

    public function test_normalize_items_drops_nonpositive_and_malformed_entries(): void
    {
        $out = rewards_normalize_items([
            ['giftId' => 0, 'qty' => 2],     // giftId<=0 -> loại
            ['giftId' => 5, 'qty' => 0],     // qty<=0 -> loại
            ['giftId' => -1, 'qty' => -3],   // cả hai âm -> loại
            'khong-phai-mang',               // phần tử không phải mảng -> loại
            ['qty' => 2],                    // thiếu giftId -> giftId=0 -> loại
            ['giftId' => 9, 'qty' => 3],     // hợp lệ -> giữ
        ]);
        $this->assertSame([
            ['giftId' => 9, 'qty' => 3],
        ], $out);
    }

    public function test_normalize_items_non_array_input_returns_empty(): void
    {
        $this->assertSame([], rewards_normalize_items(null));
        $this->assertSame([], rewards_normalize_items('chuoi-bat-ky'));
        $this->assertSame([], rewards_normalize_items(42));
    }

    // =====================================================================
    //  2) Quyết định gác quyền module rewards (đoàn-wide, §6bis)
    // =====================================================================

    /** confirm/cancel_staff cần 'edit'; staff_pending cần 'view' (edit cũng đủ). */
    public function test_permission_gating_confirm_needs_edit(): void
    {
        $this->assertSame('edit', permission_of_role('thu_thu', 'rewards'), 'Thủ thư gác được confirm (edit)');
        $this->assertSame('edit', permission_of_role('admin', 'rewards'), 'Quản trị gác được confirm (edit)');
        $this->assertSame('none', permission_of_role('glv', 'rewards'), 'GLV không gác được confirm/staff_pending');
        $this->assertSame('none', permission_of_role('bdh', 'rewards'), 'BĐH không gác được confirm/staff_pending');
    }

    // =====================================================================
    //  3) Hợp đồng API: place -> pending -> cancel/confirm gọi đúng lõi P3-1
    // =====================================================================

    /** action=place rồi action=pending (public) phải thấy ĐÚNG đơn vừa đặt. */
    public function test_place_then_pending_contract(): void
    {
        $g = $this->makeGift(10, 5, 'còn bán', 'Bút chì');
        $this->setWallet(50);

        // Mô phỏng API: chuẩn hoá items thô từ JSON rồi gọi thẳng lõi P3-1,
        // đúng như action=place trong tracuu_order.php sẽ làm.
        $items = rewards_normalize_items([['giftId' => (string) $g, 'qty' => '2']]);
        $placed = rewards_place_order($this->sid, $this->yearId, $items, 'matma123');

        $this->assertGreaterThan(0, $placed['orderId']);
        $this->assertSame(20, $placed['total']);

        // action=pending: đọc lại bằng rewards_pending_order như API sẽ trả.
        $pending = rewards_pending_order($this->sid, $this->yearId);
        $this->assertNotNull($pending);
        $this->assertSame($placed['orderId'], $pending['orderId']);
        $this->assertSame(20, $pending['total']);
        $this->assertCount(1, $pending['items']);
    }

    /** action=cancel (public, mật mã đúng) nhả held+tồn; sau đó pending -> null. */
    public function test_cancel_contract_releases_and_clears_pending(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $res = rewards_cancel_order($this->sid, $this->yearId, 'matma123');
        $this->assertGreaterThan(0, $res['orderId']);
        $this->assertSame(0, $this->wallet()['held']);
        $this->assertNull(rewards_pending_order($this->sid, $this->yearId), 'sau hủy không còn đơn chờ lấy');
    }

    /** action=confirm với override=true (Thủ thư quên mật mã) bỏ qua mật mã. */
    public function test_confirm_override_contract_skips_password(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        // API: khi override=true thì gọi rewards_confirm_order(orderId, null, actorId, true)
        // (đã gác require_permission('rewards','edit') trước khi tới đây).
        $r = rewards_confirm_order((int) $p['orderId'], null, $this->adminId, true);

        $this->assertSame((int) $p['orderId'], $r['orderId']);
        $this->assertSame('đã giao', db_one("SELECT status FROM gift_orders WHERE id=?", [$p['orderId']])['status']);
        $this->assertSame(30, $this->wallet()['current'], 'current -= tổng sau khi giao');
    }

    /** action=confirm KHÔNG override + mật mã sai -> RewardsError, không đổi gì. */
    public function test_confirm_wrong_password_contract_rejected(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $threw = false;
        try {
            // API: override=false -> truyền thẳng mật mã người dùng nhập vào rewards_confirm_order.
            rewards_confirm_order((int) $p['orderId'], 'saibet', $this->adminId, false);
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'mật mã sai (không override) phải bị từ chối');
        $this->assertSame('chờ lấy', db_one("SELECT status FROM gift_orders WHERE id=?", [$p['orderId']])['status']);
        $this->assertSame(50, $this->wallet()['current'], 'current không đổi khi từ chối');
    }

    /** Thủ thư hủy hộ (action=cancel_staff): bỏ qua mật mã, nhả held+tồn. */
    public function test_cancel_staff_contract_skips_password(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $r = rewards_cancel_order_staff((int) $p['orderId'], $this->adminId);

        $this->assertSame((int) $p['orderId'], $r['orderId']);
        $this->assertSame(0, $this->wallet()['held']);
        $this->assertSame('đã hủy', db_one("SELECT status FROM gift_orders WHERE id=?", [$p['orderId']])['status']);
    }
}
