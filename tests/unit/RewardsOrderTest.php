<?php
/**
 * Test cho VÒNG ĐỜI ĐƠN ĐẶT QUÀ (đặt trước online) — transaction có khoá dòng.
 *
 * Quy ước theo tests/unit/RewardsRedeemTest.php:
 *   - require_once bootstrap + StampService + _rewards
 *   - dựng gift + ví tạm trong từng test, dọn ở tearDown.
 *
 * Trọng tâm: LÕI tiền/tồn của vòng đời đơn —
 *   ĐẶT giữ Mộc (held) + giữ tồn; HỦY/QUÁ HẠN nhả lại đúng; GIAO chuyển
 *   held→spend (không nhả tồn). Held không bao giờ âm, không "rò". Mọi từ chối
 *   là all-or-nothing: rollback, không đổi bất cứ thứ gì.
 *
 * Dữ liệu seed dùng chung: năm học id=1 (đang mở), học sinh HS001 (id 1),
 * admin/Thủ thư member id=1.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/StampService.php';
require_once __DIR__ . '/../../public/api/_rewards.php';

use PHPUnit\Framework\TestCase;

class RewardsOrderTest extends TestCase
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
        return db_all("SELECT * FROM gift_orders WHERE student_id=? AND year_id=? ORDER BY id", [$this->sid, $this->yearId]);
    }

    private function orderById(int $id): ?array
    {
        return db_one("SELECT * FROM gift_orders WHERE id=?", [$id]);
    }

    // =====================================================================
    //  CÁC CA KIỂM THỬ
    // =====================================================================

    /** ĐẶT: held += tổng, tồn -= qty, tạo đơn 'chờ lấy' + items; current_balance KHÔNG đổi. */
    public function test_place_holds_stamps_and_stock(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(30);

        $r = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $this->assertSame(20, $r['total'], 'tổng = 10 x 2');
        $this->assertGreaterThan(0, (int) $r['orderId']);
        $this->assertNotEmpty($r['expiresAt']);

        $w = $this->wallet();
        $this->assertSame(30, $w['current'], 'current_balance KHÔNG đổi khi đặt');
        $this->assertSame(20, $w['held'], 'held += tổng');
        $this->assertSame(3, $this->stockOf($g), 'tồn 5-2=3 (giữ tồn)');

        $this->assertCount(0, $this->spendTx(), 'đặt KHÔNG ghi giao dịch spend');

        $orders = $this->orders();
        $this->assertCount(1, $orders, 'tạo đúng 1 đơn');
        $this->assertSame('chờ lấy', $orders[0]['status']);
        $this->assertSame(20, (int) $orders[0]['total_cost']);
        $this->assertNotSame('', (string) $orders[0]['redeem_code_hash'], 'lưu hash mật mã');
        $this->assertTrue(password_verify('matma123', $orders[0]['redeem_code_hash']), 'hash khớp mật mã');

        $items = db_all("SELECT * FROM gift_order_items WHERE order_id=?", [(int) $orders[0]['id']]);
        $this->assertCount(1, $items, 'đúng 1 dòng quà');
        $this->assertSame(2, (int) $items[0]['qty']);
        $this->assertSame(10, (int) $items[0]['unit_cost']);
        $this->assertSame(20, (int) $items[0]['line_cost']);
    }

    /** Mỗi em chỉ 1 đơn 'chờ lấy': đặt đơn thứ hai bị từ chối, không đổi gì. */
    public function test_place_rejects_second_pending_order(): void
    {
        $g = $this->makeGift(10, 10);
        $this->setWallet(100);

        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 1]], 'matma123');
        $heldAfter1 = $this->wallet()['held'];
        $stockAfter1 = $this->stockOf($g);

        $threw = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 1]], 'khac456');
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'đơn thứ hai khi đang có đơn chờ lấy phải từ chối');
        $this->assertSame($heldAfter1, $this->wallet()['held'], 'held không đổi sau lần từ chối');
        $this->assertSame($stockAfter1, $this->stockOf($g), 'tồn không đổi sau lần từ chối');
        $this->assertCount(1, $this->orders(), 'vẫn chỉ 1 đơn');
    }

    /** available (current-held) < tổng → từ chối, không đổi gì. */
    public function test_place_rejects_insufficient_available(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(25, 10);   // available = 25-10 = 15 < 20

        $threw = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'khả dụng thiếu phải từ chối');
        $this->assertSame(10, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(5, $this->stockOf($g), 'tồn không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** stock < qty → từ chối, không đổi gì. */
    public function test_place_rejects_out_of_stock(): void
    {
        $g = $this->makeGift(10, 1);
        $this->setWallet(100);

        $threw = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        } catch (RewardsError $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'thiếu tồn phải từ chối');
        $this->assertSame(0, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(1, $this->stockOf($g), 'tồn không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** Quà ẩn hoặc không tồn tại → từ chối, không đổi gì. */
    public function test_place_rejects_hidden_or_missing_gift(): void
    {
        $this->setWallet(100);
        $hidden = $this->makeGift(10, 5, 'ẩn');

        $threwHidden = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => $hidden, 'qty' => 1]], 'matma123');
        } catch (RewardsError $e) { $threwHidden = true; }
        $this->assertTrue($threwHidden, 'quà ẩn phải từ chối');

        $threwMissing = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => 99999999, 'qty' => 1]], 'matma123');
        } catch (RewardsError $e) { $threwMissing = true; }
        $this->assertTrue($threwMissing, 'quà không tồn tại phải từ chối');

        $this->assertSame(0, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(5, $this->stockOf($hidden), 'tồn quà ẩn không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** qty<=0 hoặc giỏ rỗng → từ chối, không đổi gì. */
    public function test_place_rejects_empty_or_nonpositive_qty(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(100);

        $threwQty = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 0]], 'matma123');
        } catch (RewardsError $e) { $threwQty = true; }
        $this->assertTrue($threwQty, 'qty<=0 phải từ chối');

        $threwEmpty = false;
        try {
            rewards_place_order($this->sid, $this->yearId, [], 'matma123');
        } catch (RewardsError $e) { $threwEmpty = true; }
        $this->assertTrue($threwEmpty, 'giỏ rỗng phải từ chối');

        $this->assertSame(0, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(5, $this->stockOf($g), 'tồn không đổi');
        $this->assertCount(0, $this->orders(), 'không tạo đơn');
    }

    /** HỦY (mật mã đúng): nhả held + tồn, đơn 'đã hủy'. */
    public function test_cancel_releases_hold_and_stock(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $this->assertSame(20, $this->wallet()['held'], 'giữ 20 sau khi đặt');
        $this->assertSame(3, $this->stockOf($g), 'giữ tồn còn 3');

        $r = rewards_cancel_order($this->sid, $this->yearId, 'matma123');

        $this->assertSame(0, $this->wallet()['held'], 'nhả held về 0');
        $this->assertSame(50, $this->wallet()['current'], 'current_balance không đổi');
        $this->assertSame(5, $this->stockOf($g), 'hoàn tồn về 5');
        $order = $this->orders()[0];
        $this->assertSame('đã hủy', $order['status']);
        $this->assertCount(0, $this->spendTx(), 'hủy không ghi spend');
    }

    /** HỦY với mật mã SAI → từ chối, không đổi gì (đơn còn 'chờ lấy'). */
    public function test_cancel_rejects_wrong_password(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $threw = false;
        try {
            rewards_cancel_order($this->sid, $this->yearId, 'saibet');
        } catch (RewardsError $e) { $threw = true; }
        $this->assertTrue($threw, 'mật mã sai phải từ chối');

        $this->assertSame(20, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(3, $this->stockOf($g), 'tồn không đổi');
        $this->assertSame('chờ lấy', $this->orders()[0]['status'], 'đơn vẫn chờ lấy');
    }

    /** Thủ thư hủy hộ (bỏ qua mật mã): nhả held + tồn, đơn 'đã hủy'. */
    public function test_cancel_staff_skips_password(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        rewards_cancel_order_staff((int) $p['orderId'], $this->adminId);

        $this->assertSame(0, $this->wallet()['held'], 'nhả held');
        $this->assertSame(5, $this->stockOf($g), 'hoàn tồn');
        $this->assertSame('đã hủy', $this->orderById((int) $p['orderId'])['status']);
    }

    /** GIAO (mật mã đúng): held->spend, current -= tổng, 1 tx spend gắn ref+actor, tồn giữ nguyên. */
    public function test_confirm_moves_held_to_spend(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        $orderId = (int) $p['orderId'];

        $stockBefore = $this->stockOf($g);   // đã là 3 (trừ lúc đặt)

        rewards_confirm_order($orderId, 'matma123', $this->adminId);

        $w = $this->wallet();
        $this->assertSame(0, $w['held'], 'held nhả về 0');
        $this->assertSame(30, $w['current'], 'current -= tổng (50-20)');
        $this->assertSame($stockBefore, $this->stockOf($g), 'tồn KHÔNG đổi khi giao (đã trừ lúc đặt)');

        $tx = $this->spendTx();
        $this->assertCount(1, $tx, 'đúng 1 giao dịch spend');
        $this->assertSame(-20, (int) $tx[0]['amount'], 'spend = -tổng');
        $this->assertSame($orderId, (int) $tx[0]['ref_order_id'], 'spend gắn ref_order_id');
        $this->assertSame($this->adminId, (int) $tx[0]['actor_id'], 'spend gắn actor_id');

        $order = $this->orderById($orderId);
        $this->assertSame('đã giao', $order['status']);
        $this->assertSame($this->adminId, (int) $order['delivered_by']);
        $this->assertNotEmpty($order['delivered_at']);
    }

    /** GIAO override: bỏ qua mật mã (Thủ thư gác quyền). */
    public function test_confirm_override_skips_password(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        $orderId = (int) $p['orderId'];

        // override=true, plainCode null → vẫn giao được
        rewards_confirm_order($orderId, null, $this->adminId, true);

        $this->assertSame(0, $this->wallet()['held']);
        $this->assertSame(30, $this->wallet()['current']);
        $this->assertSame('đã giao', $this->orderById($orderId)['status']);
        $this->assertCount(1, $this->spendTx());
    }

    /** GIAO mật mã SAI (không override) → từ chối, không đổi gì. */
    public function test_confirm_wrong_password_rejected(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        $orderId = (int) $p['orderId'];

        $threw = false;
        try {
            rewards_confirm_order($orderId, 'saibet', $this->adminId);
        } catch (RewardsError $e) { $threw = true; }
        $this->assertTrue($threw, 'mật mã sai phải từ chối');

        $this->assertSame(20, $this->wallet()['held'], 'held không đổi');
        $this->assertSame(50, $this->wallet()['current'], 'current không đổi');
        $this->assertSame('chờ lấy', $this->orderById($orderId)['status'], 'đơn vẫn chờ lấy');
        $this->assertCount(0, $this->spendTx(), 'không ghi spend');
    }

    /** QUÁ HẠN: đơn quá expires_at → 'quá hạn', nhả held + tồn. */
    public function test_expire_releases_hold_and_stock(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        $orderId = (int) $p['orderId'];

        $this->assertSame(20, $this->wallet()['held']);
        $this->assertSame(3, $this->stockOf($g));

        // ép đơn thành đã quá hạn
        db_run("UPDATE gift_orders SET expires_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE id=?", [$orderId]);

        $n = rewards_expire_due($this->yearId);
        $this->assertGreaterThanOrEqual(1, $n, 'ít nhất 1 đơn quá hạn được xử lý');

        $this->assertSame(0, $this->wallet()['held'], 'nhả held');
        $this->assertSame(50, $this->wallet()['current'], 'current không đổi');
        $this->assertSame(5, $this->stockOf($g), 'hoàn tồn');
        $this->assertSame('quá hạn', $this->orderById($orderId)['status']);
        $this->assertCount(0, $this->spendTx(), 'quá hạn không ghi spend');
    }

    /** Đơn CHƯA quá hạn không bị đụng. */
    public function test_expire_leaves_fresh_order(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        rewards_expire_due($this->yearId);

        $this->assertSame('chờ lấy', $this->orderById((int) $p['orderId'])['status'], 'đơn còn hạn giữ nguyên');
        $this->assertSame(20, $this->wallet()['held'], 'held giữ nguyên');
    }

    /** Đặt → hủy → đặt lại: held + tồn về ĐÚNG, không "rò". */
    public function test_hold_never_leaks(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(50);

        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');
        rewards_cancel_order($this->sid, $this->yearId, 'matma123');

        // sau hủy: held 0, tồn 5 lại
        $this->assertSame(0, $this->wallet()['held']);
        $this->assertSame(5, $this->stockOf($g));

        // đặt lại y hệt
        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'lai789');
        $this->assertSame(20, $this->wallet()['held'], 'held đúng 20, không cộng dồn rò');
        $this->assertSame(3, $this->stockOf($g), 'tồn đúng 3, không rò');
        $this->assertSame(50, $this->wallet()['current'], 'current suốt quá trình không đổi');
    }

    /** Sau đặt, available (current-held) không bao giờ âm (đặt hết sạch khả dụng). */
    public function test_available_never_negative_after_place(): void
    {
        $g = $this->makeGift(10, 5);
        $this->setWallet(20);   // đặt đúng 20 → available về 0

        rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $w = $this->wallet();
        $available = $w['current'] - $w['held'];
        $this->assertSame(0, $available, 'khả dụng về đúng 0');
        $this->assertGreaterThanOrEqual(0, $available, 'khả dụng không âm');
        $this->assertGreaterThanOrEqual(0, $w['held'], 'held không âm');
    }

    /** rewards_pending_order: trả đơn 'chờ lấy' kèm items + expiresAt; null khi không có. */
    public function test_pending_order_returns_current(): void
    {
        $this->assertNull(rewards_pending_order($this->sid, $this->yearId), 'chưa có đơn → null');

        $g = $this->makeGift(10, 5, 'còn bán', 'Bút');
        $this->setWallet(50);
        $p = rewards_place_order($this->sid, $this->yearId, [['giftId' => $g, 'qty' => 2]], 'matma123');

        $pend = rewards_pending_order($this->sid, $this->yearId);
        $this->assertNotNull($pend, 'có đơn chờ lấy → trả về');
        $this->assertSame((int) $p['orderId'], (int) $pend['orderId']);
        $this->assertSame(20, (int) $pend['total']);
        $this->assertNotEmpty($pend['expiresAt']);
        $this->assertCount(1, $pend['items'], 'kèm 1 dòng quà');
        $this->assertSame('Bút', $pend['items'][0]['name']);
        $this->assertSame(2, (int) $pend['items'][0]['qty']);

        // sau khi hủy → null
        rewards_cancel_order($this->sid, $this->yearId, 'matma123');
        $this->assertNull(rewards_pending_order($this->sid, $this->yearId), 'sau hủy → null');
    }
}
