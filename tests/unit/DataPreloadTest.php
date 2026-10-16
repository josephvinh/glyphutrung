<?php
// tests/unit/DataPreloadTest.php
// Task 2: kiểm tra preload_class_student_counts() + preload_effective_assignments()
// ở public/api/_common.php — thay thế N+1 query trong data.php.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class DataPreloadTest extends TestCase
{
    private int $yearId = 0;
    private int $memberId = 0;

    protected function setUp(): void
    {
        $year = current_year();
        if (!$year) {
            $this->markTestSkipped('chưa có school_years');
        }
        $this->yearId = (int) $year['id'];

        $this->memberId = (int) db_val("SELECT id FROM members LIMIT 1");
        if (!$this->memberId) {
            $this->markTestSkipped('chưa có member nào');
        }
    }

    public function test_preload_class_student_counts_returns_array_keyed_by_class_id(): void
    {
        $counts = preload_class_student_counts($this->yearId);
        $this->assertIsArray($counts);
        // Mỗi khoá là class_id (int), giá trị là count (int)
        foreach ($counts as $cid => $cnt) {
            $this->assertIsInt($cid);
            $this->assertIsInt($cnt);
            $this->assertGreaterThanOrEqual(0, $cnt);
        }
    }

    public function test_preload_effective_assignments_returns_array_of_assignments(): void
    {
        $rows = preload_effective_assignments([$this->memberId]);
        $this->assertIsArray($rows);
    }

    public function test_preload_effective_assignments_skips_expired(): void
    {
        // Tạo assignment đã hết hạn — thử thêm vào nếu bảng có school_year_id
        $row = db_one("SHOW COLUMNS FROM member_assignments LIKE 'school_year_id'");
        if ($row) {
            db_run(
                "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, to_date, assigned_by, school_year_id, note)
                 VALUES (?, 'glv', NULL, 0, '2020-01-01', '2020-12-31', ?, ?, 'expired test')",
                [$this->memberId, $this->memberId, $this->yearId]
            );
        } else {
            db_run(
                "INSERT INTO member_assignments (member_id, role_code, block_id, is_primary, from_date, to_date, assigned_by, note)
                 VALUES (?, 'glv', NULL, 0, '2020-01-01', '2020-12-31', ?, 'expired test')",
                [$this->memberId, $this->memberId]
            );
        }
        $rows = preload_effective_assignments([$this->memberId]);
        foreach ($rows as $r) {
            $this->assertNotSame('expired test', $r['note'] ?? '', 'assignment hết hạn không được trả');
        }
    }
}
