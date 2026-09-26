<?php
// tests/unit/RewardsSchemaTest.php
// Task 1 — Sổ Mộc Điện Tử Phase 1: kiểm tra schema Mộc/quà + vai trò + phân quyền
// sau khi áp dụng migration 003_stamps_rewards.sql.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class RewardsSchemaTest extends TestCase
{
    public function test_tables_exist(): void
    {
        // Lưu ý: dùng information_schema thay vì "SHOW TABLES LIKE ?" — driver
        // PDO của project chạy real prepared statements (ATTR_EMULATE_PREPARES
        // = false trong config/db.php), và MariaDB không cho prepare được câu
        // SHOW ... LIKE ? qua binary protocol (lỗi 1064 near '?' dù bảng có
        // tồn tại hay không). db_has_table() trong db.php né bằng cách nối
        // chuỗi trực tiếp; ở đây dùng information_schema có tham số hoá cho an toàn.
        foreach (['student_stamps', 'stamp_transactions', 'gifts', 'gift_orders', 'gift_order_items'] as $t) {
            $this->assertNotNull(
                db_one("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$t]),
                "thiếu bảng $t"
            );
        }
    }

    public function test_role_and_permissions_seeded(): void
    {
        $this->assertNotNull(db_one("SELECT 1 FROM roles WHERE code='thu_thu'"));
        $this->assertSame('edit', db_one("SELECT level FROM permissions WHERE module_key='rewards' AND role_code='thu_thu'")['level']);
        $this->assertNull(db_one("SELECT 1 FROM permissions WHERE module_key='rewards' AND role_code='glv'"));
        $this->assertSame('edit', db_one("SELECT level FROM permissions WHERE module_key='gifts' AND role_code='bdh'")['level']);
    }

    public function test_permissions_default_to_none_for_other_roles(): void
    {
        // Các role còn lại không được seed hàng nào cho gifts/rewards → mặc định 'none'.
        foreach (['glv_chu_nhiem', 'truong_khoi', 'du_bi'] as $role) {
            $this->assertNull(
                db_one("SELECT 1 FROM permissions WHERE module_key='rewards' AND role_code=?", [$role]),
                "rewards không được seed cho role $role"
            );
            $this->assertNull(
                db_one("SELECT 1 FROM permissions WHERE module_key='gifts' AND role_code=?", [$role]),
                "gifts không được seed cho role $role"
            );
        }
    }

    public function test_modules_seeded(): void
    {
        $gifts = db_one("SELECT * FROM modules WHERE module_key='gifts'");
        $this->assertNotNull($gifts, "thiếu module gifts");
        $this->assertSame('bdh', $gifts['area']);

        $rewards = db_one("SELECT * FROM modules WHERE module_key='rewards'");
        $this->assertNotNull($rewards, "thiếu module rewards");
        $this->assertSame('bdh', $rewards['area'], 'rewards area đã hợp nhất về bdh khớp core.js');
    }

    public function test_stamp_transactions_unique_ref_attendance_and_type(): void
    {
        $rows = db_all(
            "SELECT index_name FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = 'stamp_transactions'
               AND non_unique = 0 AND index_name <> 'PRIMARY'
               AND column_name IN ('ref_attendance_id', 'type')
             GROUP BY index_name
             HAVING COUNT(*) = 2"
        );
        $this->assertNotEmpty($rows, "thiếu UNIQUE(ref_attendance_id, type) trên stamp_transactions");
    }
}
