<?php
// tests/unit/GiftsApiTest.php
// Task 5 — Sổ Mộc Điện Tử Phase 1: module danh mục quà (gifts).
//
// gifts.php là endpoint request-scoped (đọc php://input, gọi require_login()
// ngay khi được require) nên KHÔNG gọi HTTP trực tiếp file đó ở đây. Thay vào
// đó test ở mức DB/logic, theo đúng cách ScopeTest.php đang làm:
//   1. Xác nhận permission đã được seed đúng (kể cả chống leo thang cho glv).
//   2. Unit-test hàm thuần gift_validate() (tách ra từ gifts.php để test được).
//   3. Round-trip DB: insert/update/xoá một quà bằng đúng câu SQL mà handler dùng.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_gifts.php';

use PHPUnit\Framework\TestCase;

class GiftsApiTest extends TestCase
{
    private array $createdIds = [];

    protected function tearDown(): void
    {
        foreach ($this->createdIds as $id) {
            db_run('DELETE FROM gifts WHERE id=?', [$id]);
        }
        $this->createdIds = [];
    }

    // -----------------------------------------------------------------
    // 1. PHÂN QUYỀN — khớp với seed migration 003 + chống leo thang cho glv
    // -----------------------------------------------------------------
    public function test_permission_seed_grants_edit_to_admin_bdh_thu_thu(): void
    {
        $this->assertSame('edit', permission_of_role('admin', 'gifts'));
        $this->assertSame('edit', permission_of_role('bdh', 'gifts'));
        $this->assertSame('edit', permission_of_role('thu_thu', 'gifts'));
    }

    public function test_permission_seed_denies_glv_by_default(): void
    {
        // Chống leo thang: GLV thường KHÔNG được cấp quyền gì trên gifts.
        $this->assertSame('none', permission_of_role('glv', 'gifts'));
    }

    // -----------------------------------------------------------------
    // 2. gift_validate() — logic thuần, tách khỏi handler để test được
    // -----------------------------------------------------------------
    public function test_gift_validate_accepts_valid_input(): void
    {
        $err = gift_validate(['name' => 'Bút bi', 'stampCost' => 10, 'stock' => 5]);
        $this->assertNull($err);
    }

    public function test_gift_validate_rejects_empty_name(): void
    {
        $err = gift_validate(['name' => '  ', 'stampCost' => 10, 'stock' => 5]);
        $this->assertNotNull($err);
    }

    public function test_gift_validate_rejects_non_positive_cost(): void
    {
        $this->assertNotNull(gift_validate(['name' => 'Bút bi', 'stampCost' => 0, 'stock' => 5]));
        $this->assertNotNull(gift_validate(['name' => 'Bút bi', 'stampCost' => -3, 'stock' => 5]));
    }

    public function test_gift_validate_rejects_negative_stock(): void
    {
        $err = gift_validate(['name' => 'Bút bi', 'stampCost' => 10, 'stock' => -1]);
        $this->assertNotNull($err);
    }

    // -----------------------------------------------------------------
    // 3. Round-trip DB — dùng đúng câu SQL mà gifts.php dùng để save/delete
    // -----------------------------------------------------------------
    public function test_insert_read_update_delete_gift(): void
    {
        // Tạo — đúng cột mà case 'save' của gifts.php ghi.
        $id = db_insert(
            'INSERT INTO gifts (name, stamp_cost, stock, image_url, status, sort_order)
             VALUES (?,?,?,?,?,?)',
            ['Bút bi GIFT_TEST', 15, 20, null, 'còn bán', 1]
        );
        $this->createdIds[] = $id;
        $this->assertGreaterThan(0, $id);

        $row = db_one('SELECT * FROM gifts WHERE id=?', [$id]);
        $this->assertNotNull($row);
        $this->assertSame('Bút bi GIFT_TEST', $row['name']);
        $this->assertSame(15, (int) $row['stamp_cost']);
        $this->assertSame(20, (int) $row['stock']);
        $this->assertSame('còn bán', $row['status']);

        $out = gift_row_out($row);
        $this->assertSame($id, $out['id']);
        $this->assertSame(15, $out['stampCost']);

        // Cập nhật tồn kho (đổi quà làm giảm stock).
        db_run('UPDATE gifts SET stock=? WHERE id=?', [18, $id]);
        $row2 = db_one('SELECT stock FROM gifts WHERE id=?', [$id]);
        $this->assertSame(18, (int) $row2['stock']);

        // Ẩn thay vì xoá (mô phỏng nhánh "đã có đơn đổi" của case 'delete').
        db_run("UPDATE gifts SET status='ẩn' WHERE id=?", [$id]);
        $row3 = db_one('SELECT status FROM gifts WHERE id=?', [$id]);
        $this->assertSame('ẩn', $row3['status']);

        // Xoá hẳn khi không bị tham chiếu bởi gift_order_items.
        $this->assertNull(db_one('SELECT id FROM gift_order_items WHERE gift_id=? LIMIT 1', [$id]));
        db_run('DELETE FROM gifts WHERE id=?', [$id]);
        $this->assertNull(db_one('SELECT id FROM gifts WHERE id=?', [$id]));
        $this->createdIds = array_values(array_diff($this->createdIds, [$id]));
    }

    public function test_gift_is_referenced_false_when_no_order_items(): void
    {
        $giftId = db_insert(
            'INSERT INTO gifts (name, stamp_cost, stock, status, sort_order) VALUES (?,?,?,?,?)',
            ['Quà GIFT_TEST chưa đổi', 5, 3, 'còn bán', 1]
        );
        $this->createdIds[] = $giftId;

        $this->assertFalse(gift_is_referenced($giftId));
    }

    public function test_gift_is_referenced_true_when_has_order_items(): void
    {
        // Quà đã được đổi (có dòng trong gift_order_items) -> gift_is_referenced()
        // phải trả true. gifts.php case 'delete' dùng đúng giá trị này để
        // QUYẾT ĐỊNH từ chối xoá (json_fail), mirror hệt cách programs.php
        // từ chối xoá chương trình đã có điểm danh (programs.php:138-140) —
        // KHÔNG tự đổi dữ liệu, không tự chuyển status='ẩn' như bản đầu từng làm.
        [$giftId, $orderId] = $this->makeReferencedGift();

        $this->assertTrue(gift_is_referenced($giftId));

        // Chỉ tạo tham chiếu, chưa gọi bất kỳ UPDATE/DELETE nào trên gifts ->
        // dòng phải còn nguyên vẹn, đúng với hành vi "refuse, leave untouched".
        $row = db_one('SELECT * FROM gifts WHERE id=?', [$giftId]);
        $this->assertNotNull($row, 'Quà bị từ chối xoá không được biến mất khỏi bảng');
        $this->assertSame('còn bán', $row['status'], 'Quà bị từ chối xoá phải GIỮ NGUYÊN trạng thái, không tự ẩn');

        // Dọn dẹp thứ tự khoá ngoại: item -> order -> gift.
        db_run('DELETE FROM gift_order_items WHERE order_id=?', [$orderId]);
        db_run('DELETE FROM gift_orders WHERE id=?', [$orderId]);
    }

    /** Tạo một quà + một đơn đổi tham chiếu tới nó. Trả về [giftId, orderId]. */
    private function makeReferencedGift(): array
    {
        $giftId = db_insert(
            'INSERT INTO gifts (name, stamp_cost, stock, status, sort_order) VALUES (?,?,?,?,?)',
            ['Quà GIFT_TEST đã đổi', 5, 3, 'còn bán', 1]
        );
        $this->createdIds[] = $giftId;

        $yearId = (int) db_one('SELECT id FROM school_years WHERE is_current = 1 LIMIT 1')['id'];
        $studentId = (int) db_one('SELECT id FROM students LIMIT 1')['id'];

        $orderId = db_insert(
            'INSERT INTO gift_orders (year_id, student_id, total_cost, redeem_code_hash, created_at, expires_at)
             VALUES (?,?,?,?,NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY))',
            [$yearId, $studentId, 5, password_hash('GIFT_TEST', PASSWORD_DEFAULT)]
        );
        db_run(
            'INSERT INTO gift_order_items (order_id, gift_id, qty, unit_cost, line_cost) VALUES (?,?,?,?,?)',
            [$orderId, $giftId, 1, 5, 5]
        );

        return [$giftId, $orderId];
    }
}
