<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_bootstrap.php';

use PHPUnit\Framework\TestCase;

/**
 * Hồi quy F-thu_thu: vai `thu_thu` (scope toàn đoàn, nhưng KHÔNG có quyền
 * gì trên students/attendance) không được phép "mở khoá" scan điểm danh
 * hay hồ sơ toàn đoàn chỉ vì kiêm nhiệm cùng lúc với một vai lớp (glv).
 *
 * Member = glv (assignment lớp A/classA) + thu_thu (assignment toàn đoàn,
 * block/class NULL).
 */
class RewardsScopeTest extends TestCase
{
    private int $memberId = 0;
    private int $adminId  = 0;
    private array $classA;   // lớp thuộc khối A — nơi glv được phân công
    private array $classB;   // lớp thuộc khối B — KHÔNG được phân công
    private array $savedPerms = [];

    protected function setUp(): void
    {
        $this->adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];

        $this->classA = db_one("SELECT id, block_id FROM classes WHERE block_id IS NOT NULL LIMIT 1");
        $this->classB = db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? LIMIT 1",
            [$this->classA['block_id']]
        );
        if (!$this->classA || !$this->classB) {
            $this->markTestSkipped('Cần ít nhất 2 lớp ở 2 khối khác nhau.');
        }

        $phone = '09' . random_int(10000000, 99999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('REWARDS_SCOPE_TEST', 'Rewards Scope Test', ?, ?, 'glv')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );

        $this->addAssignment('glv', null, (int) $this->classA['id']);
        $this->addAssignment('thu_thu', null, null); // toàn đoàn theo seed role scope
    }

    protected function tearDown(): void
    {
        if ($this->memberId) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
        foreach ($this->savedPerms as $p) {
            if ($p['level'] === null) {
                db_run("DELETE FROM permissions WHERE module_key=? AND role_code=?", [$p['mod'], $p['role']]);
            } else {
                db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE level = VALUES(level)", [$p['mod'], $p['role'], $p['level']]);
            }
        }
    }

    private function setPerm(string $mod, string $role, string $level): void
    {
        $old = db_one("SELECT level FROM permissions WHERE module_key=? AND role_code=?", [$mod, $role]);
        $this->savedPerms[] = ['mod' => $mod, 'role' => $role, 'level' => $old['level'] ?? null];
        db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = VALUES(level)", [$mod, $role, $level]);
    }

    private function addAssignment(string $role, ?int $blockId, ?int $classId): void
    {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, ?, ?, ?, 0, CURDATE(), ?)",
            [$this->memberId, $role, $blockId, $classId, $this->adminId]
        );
    }

    public function test_thu_thu_khong_duoc_mo_rong_scan_va_ho_so_toan_doan(): void
    {
        $me = ['id' => $this->memberId, 'role_code' => 'glv', 'role_scope' => 'lớp',
               'block_id' => null, 'class_id' => $this->classA['id']];

        // scan_class_ids: chỉ khối của lớp A, KHÔNG phải null (toàn đoàn)
        $ids = scan_class_ids($me);
        $this->assertNotNull($ids, 'thu_thu không được mở quét toàn đoàn');
        $this->assertNotContains((int) $this->classB['id'], $ids);

        // responsible_class_ids: chỉ lớp A, không null
        $r = responsible_class_ids($me);
        $this->assertNotNull($r);
        $this->assertContains((int) $this->classA['id'], $r);
        $this->assertNotContains((int) $this->classB['id'], $r);

        // nhưng quyền trên rewards vẫn có (edit) — vá lỗi không được xoá quyền hợp lệ
        $this->assertSame('edit', permission_of_role('thu_thu', 'rewards'));
    }
}
