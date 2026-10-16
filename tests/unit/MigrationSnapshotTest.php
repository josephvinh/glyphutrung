<?php
// tests/unit/MigrationSnapshotTest.php
// Task 1: kiểm tra schema member_service_history có 3 cột snapshot
// denormalize + FK ON DELETE SET NULL + COLLATE utf8mb4_unicode_ci.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class MigrationSnapshotTest extends TestCase
{
    public function test_snapshot_columns_exist(): void
    {
        $rows = db_all("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE()
                          AND TABLE_NAME = 'member_service_history'");
        $names = array_column($rows, 'COLUMN_NAME');
        $this->assertContains('member_holy_name', $names, 'thiếu cột member_holy_name');
        $this->assertContains('member_full_name', $names, 'thiếu cột member_full_name');
        $this->assertContains('role_label', $names, 'thiếu cột role_label');
    }

    public function test_fk_member_id_is_set_null(): void
    {
        $row = db_one("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS
                       WHERE CONSTRAINT_SCHEMA = DATABASE()
                         AND TABLE_NAME = 'member_service_history'
                         AND CONSTRAINT_NAME = 'fk_msh_member'");
        $this->assertNotNull($row, 'thiếu FK fk_msh_member');
        $this->assertSame('SET NULL', $row['DELETE_RULE']);
    }

    public function test_table_collation_is_unicode_ci(): void
    {
        $row = db_one("SELECT TABLE_COLLATION FROM information_schema.TABLES
                       WHERE TABLE_SCHEMA = DATABASE()
                         AND TABLE_NAME = 'member_service_history'");
        $this->assertNotNull($row);
        $this->assertSame('utf8mb4_unicode_ci', $row['TABLE_COLLATION']);
    }
}
