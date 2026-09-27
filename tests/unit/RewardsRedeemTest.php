<?php
/**
 * Test cho lõi ĐỔI QUÀ TẠI QUẦY (rewards_redeem) — transaction có khoá dòng.
 *
 * Quy ước theo tests/unit/StampEngineTest.php:
 *   - require_once bootstrap + StampService + _rewards
 *   - dựng gift + ví tạm trong từng test, dọn ở tearDown.
 *
 * Trọng tâm: tính đúng của GIAO DỊCH đổi quà —
 *   trừ Mộc + trừ tồn + ghi audit 'spend' NGUYÊN TỬ, số dư không âm,
 *   và khi từ chối thì KHÔNG đổi bất cứ thứ gì (all-or-nothing).
 *
 * Dữ liệu seed dùng chung: năm học id=1 (đang mở), học sinh HS001 (id 1),
 * admin member id=1.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';
require_once __DIR__ . '/../../public/api/_rewards.php';

use PHPUnit\Framework\TestCase;

class RewardsRedeemTest extends TestCase
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

    /** Dọn ví + giao dịch + đơn đổi + quà test của HS001. */
    private function cleanUp(): void
    {
        // giao dịch spend
        db_run("DELETE FROM stamp_transactions WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
        // item + đơn (item CASCADE theo đơn, nhưng xoá tường minh cho chắc)
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
            // dọn mọi item còn tham chiếu quà test (an toàn nếu test tự tạo đơn khác)
            db_run("DELETE FROM gift_order_items WHERE gift_id IN ($ph)", $this->giftIds);
            db_run("DELETE FROM gifts WHERE id IN ($ph)", $this->giftIds);
        }
        $this->giftIds = [];
    }

    // ---- Helpers dựng dữ liệu -------------------------------------------

    private function makeGift(int $cost, int $stock, string $status = 'còn bán', string $name = 'Quà test'): int
    {
        $id = db_insert(
            "INSERT INTO gifts (name, stamp_cost, stock, status, sort_order) VALUES (?,?,?,?,1)",
            [$name, $cost, $stock, $status]
        );
        $this->giftIds[] = $id;
        return $id;
    }

    /** Đặt ví HS001: current_balance, held_balance. */
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

    private function stockOf(int $giftId): int
    {
        return (int) db_val("SELECT stock FROM gifts WHERE id=?", [$giftId]);
    }

    private function spendTx(): array
    {
        return db_all("SELECT * FROM stamp_transactions WHERE student_id=? AND year_id=? AND type='spend' ORDER BY id",
                      [$this->sid, $this->yearId]);
    }

    private function orders(): array
    {
        return db_all("SELECT * FROM gift_orders WHERE student_id=? AND year_id=?", [$this->sid, $this->yearId]);
    }

    // =====================================================================
    //  CÁC CA KIỂM THỬ
    // =====================================================================

    /** Đổi thành công: số dư -tổng, tồn -qty, có txn spend gắn ref_order_id + đơn+item. */
    public function test_redeem_deducts_balance_stock_and_logs(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(30);

        $r = rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], $this->adminId);

        $this->assertSame(20, $r['total'], 'tổng = 10 x 2');
        $this->assertSame(10, $r['available'], 'khả dụng còn 30-20=10');

        $w = $this->wallet();
        $this->assertSame(10, $w['current'], 'số dư trừ đúng tổng');
        $this->assertSame(3, $this->stockOf($g), 'tồn 5-2=3');

        $tx = $this->spendTx();
        $this->assertCount(1, $tx, 'đúng 1 giao dịch spend');
        $this->assertSame(-20, (int) $tx[0]['amount'], 'spend ghi số âm = -tổng');
        $this->assertSame((int) $r['orderId'], (int) $tx[0]['ref_order_id'], 'spend gắn ref_order_id');
        $this->assertSame($this->adminId, (int) $tx[0]['actor_id'], 'spend gắn actor_id');

        $orders = $this->orders();
        $this->assertCount(1, $orders, 'tạo đúng 1 đơn');
        $this->assertSame('đã giao', $orders[0]['status'], 'đơn ở trạng thái đã giao');
        $this->assertSame($this->adminId, (int) $orders[0]['delivered_by']);
        $items = db_all("SELECT * FROM gift_order_items WHERE order_id=?", [(int) $orders[0]['id']]);
        $this->assertCount(1, $items, 'đúng 1 dòng quà');
        $this->assertSame(2, (int) $items[0]['qty']);
        $this->assertSame(20, (int) $items[0]['line_cost']);
    }

    /** Không đủ số dư khả dụng → ném lỗi, KHÔNG đổi gì. */
    public function test_redeem_rejects_when_insufficient_balance(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(15);   // 15 < 20

        $threw = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], $this->adminId);
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'thiếu Mộc phải ném RewardsError');

        $this->assertSame(15, $this->wallet()['current'], 'số dư không đổi');
        $this->assertSame(5, $this->stockOf($g), 'tồn không đổi');
        $this->assertCount(0, $this->spendTx(), 'không ghi giao dịch');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** Held làm giảm khả dụng: current đủ nhưng available thiếu → từ chối. */
    public function test_redeem_respects_held_balance(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(25, 10);   // available = 25-10 = 15 < 20

        $threw = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], $this->adminId);
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'khả dụng (trừ held) thiếu phải từ chối');
        $this->assertSame(25, $this->wallet()['current'], 'số dư không đổi');
        $this->assertSame(5, $this->stockOf($g), 'tồn không đổi');
    }

    /** Vượt tồn kho (qty > stock) → từ chối, KHÔNG đổi gì. */
    public function test_redeem_rejects_when_out_of_stock(): void
    {
        $g = $this->makeGift(10, 1);
        $this->setWallet(100);

        $threw = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], $this->adminId);
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'thiếu tồn phải ném RewardsError');
        $this->assertSame(100, $this->wallet()['current'], 'số dư không đổi');
        $this->assertSame(1, $this->stockOf($g), 'tồn không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** Quà ẩn hoặc không tồn tại → từ chối. */
    public function test_redeem_rejects_hidden_or_missing_gift(): void
    {
        $this->setWallet(100);
        $hidden = $this->makeGift(10, 5, 'ẩn');

        $threwHidden = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => $hidden, 'qty' => 1]], $this->adminId);
        } catch (RewardsError $e) { $threwHidden = true; }
        $this->assertTrue($threwHidden, 'quà ẩn phải từ chối');

        $threwMissing = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => 99999999, 'qty' => 1]], $this->adminId);
        } catch (RewardsError $e) { $threwMissing = true; }
        $this->assertTrue($threwMissing, 'quà không tồn tại phải từ chối');

        $this->assertSame(100, $this->wallet()['current'], 'số dư không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** qty<=0 hoặc giỏ rỗng → từ chối. */
    public function test_redeem_rejects_bad_quantity_and_empty_cart(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(100);

        $threwQty = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 0]], $this->adminId);
        } catch (RewardsError $e) { $threwQty = true; }
        $this->assertTrue($threwQty, 'qty<=0 phải từ chối');

        $threwEmpty = false;
        try {
            rewards_redeem($this->sid, $this->yearId, [], $this->adminId);
        } catch (RewardsError $e) { $threwEmpty = true; }
        $this->assertTrue($threwEmpty, 'giỏ rỗng phải từ chối');

        $this->assertSame(100, $this->wallet()['current'], 'số dư không đổi');
        $this->assertSame(5, $this->stockOf($g), 'tồn không đổi');
    }

    /** Giỏ nhiều quà: tổng đúng, mọi tồn giảm, MỘT giao dịch spend. */
    public function test_redeem_multi_gift_cart(): void
    {
        $g1 = $this->makeGift(10, 5, 'còn bán', 'Bút');
        $g2 = $this->makeGift(7, 4, 'còn bán', 'Sổ');
        $this->setWallet(100);

        // 10*2 + 7*3 = 20 + 21 = 41
        $r = rewards_redeem($this->sid, $this->yearId,
            [['giftId' => $g1, 'qty' => 2], ['giftId' => $g2, 'qty' => 3]], $this->adminId);

        $this->assertSame(41, $r['total'], 'tổng nhiều dòng đúng');
        $this->assertSame(59, $this->wallet()['current'], 'số dư 100-41');
        $this->assertSame(3, $this->stockOf($g1), 'tồn g1 5-2');
        $this->assertSame(1, $this->stockOf($g2), 'tồn g2 4-3');

        $tx = $this->spendTx();
        $this->assertCount(1, $tx, 'chỉ MỘT giao dịch spend cho cả đơn');
        $this->assertSame(-41, (int) $tx[0]['amount']);

        $orders = $this->orders();
        $this->assertCount(1, $orders);
        $items = db_all("SELECT * FROM gift_order_items WHERE order_id=? ORDER BY id", [(int) $orders[0]['id']]);
        $this->assertCount(2, $items, 'hai dòng quà');
        $this->assertSame(41, (int) $orders[0]['total_cost']);
    }

    /** Sau đổi, current_balance không bao giờ âm (đổi hết sạch số dư). */
    public function test_balance_never_negative(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(20);   // đổi đúng 20 → về 0

        rewards_redeem($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], $this->adminId);

        $this->assertSame(0, $this->wallet()['current'], 'đổi hết → số dư 0, không âm');
        $this->assertGreaterThanOrEqual(0, $this->wallet()['current']);
    }

    /** Phân quyền module rewards: admin & thu_thu = edit; glv = none. */
    public function test_permission_seed_rewards(): void
    {
        // Chú ý chữ ký thật: permission_of_role(roleCode, moduleKey)
        $this->assertSame('edit', permission_of_role('thu_thu', 'rewards'), 'Thủ thư = edit');
        $this->assertSame('edit', permission_of_role('admin', 'rewards'), 'Quản trị = edit');
        $this->assertSame('none', permission_of_role('glv', 'rewards'), 'GLV = none (không đứng quầy)');
        $this->assertSame('none', permission_of_role('bdh', 'rewards'), 'BĐH = none (không đứng quầy)');
    }
}
