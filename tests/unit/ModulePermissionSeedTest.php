<?php
require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

/**
 * Mọi module trong bảng modules phải có dòng quyền cho đủ các vai gốc sau
 * install.php. Thiếu dòng → BOOT.permissions[module] undefined → màn Cài đặt
 * quyền vỡ (lỗi JS "reading 'glv'") và module bị ẩn với mọi vai trừ Quản trị.
 * Đã xảy ra với thu_vien: schema.sql seed trước khi có roles, INSERT IGNORE
 * nuốt lỗi khóa ngoại.
 */
class ModulePermissionSeedTest extends TestCase {
    private const BASE_ROLES = ['admin', 'bdh', 'truong_khoi', 'glv_chu_nhiem', 'glv', 'du_bi'];

    public function testEveryModuleHasPermissionRowForEveryBaseRole(): void {
        $missing = [];
        foreach (db_all('SELECT module_key FROM modules') as $m) {
            $have = array_column(
                db_all('SELECT role_code FROM permissions WHERE module_key = ?', [$m['module_key']]),
                'role_code'
            );
            foreach (array_diff(self::BASE_ROLES, $have) as $role) {
                $missing[] = $m['module_key'] . '/' . $role;
            }
        }
        $this->assertSame([], $missing, 'Thiếu dòng quyền (module/vai) sau install.php');
    }

    public function testThuVienDefaultLevels(): void {
        $rows = db_all("SELECT role_code, level FROM permissions WHERE module_key = 'thu_vien'");
        $levels = array_column($rows, 'level', 'role_code');
        foreach (['admin' => 'edit', 'bdh' => 'edit', 'truong_khoi' => 'view',
                  'glv_chu_nhiem' => 'view', 'glv' => 'view', 'du_bi' => 'view'] as $role => $lv) {
            $this->assertSame($lv, $levels[$role] ?? null, "thu_vien/$role");
        }
    }

    public function testMigration008IsIdempotentAndKeepsAdminChoice(): void {
        $sql  = file_get_contents(__DIR__ . '/../../config/migrations/008_thu_vien_permissions.sql');
        $stmt = trim(preg_replace('/^\s*--.*$/m', '', $sql));   // bỏ comment
        $this->assertStringNotContainsStringIgnoringCase('INSERT IGNORE', $stmt);

        $before = db_one("SELECT level FROM permissions WHERE module_key = 'thu_vien' AND role_code = 'glv'");
        db_run("UPDATE permissions SET level = 'none' WHERE module_key = 'thu_vien' AND role_code = 'glv'");
        try {
            db_run(rtrim($stmt, ';'));   // chạy lại lần 2
            $after = db_one("SELECT level FROM permissions WHERE module_key = 'thu_vien' AND role_code = 'glv'");
            $this->assertSame('none', $after['level'], 'Chạy lại migration không được ghi đè mức Quản trị đã chỉnh');
        } finally {
            db_run("UPDATE permissions SET level = ? WHERE module_key = 'thu_vien' AND role_code = 'glv'",
                   [$before['level'] ?? 'view']);
        }
    }
}
