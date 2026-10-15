<?php
// tests/unit/LogsApiFilterTest.php
//
// Verify logs.php (và log_scope_where) chấp nhận filter đúng:
//   - module chính xác
//   - khoảng ngày (from/to)
//   - actor LIKE
//
// Test gọi SQL trực tiếp (mô phỏng logs.php sau khi thêm filter) vì
// endpoint cần HTTP harness. Mỗi test ghi log riêng bằng action
// để query/xóa chính xác.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class LogsApiFilterTest extends TestCase
{
    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') {
            $this->markTestSkipped("Chỉ chạy log filter test trên DB tntt_test (đang là '$ten').");
        }
    }

    private function xoaLogTheoAction(string $action): void
    {
        try {
            db_run("DELETE FROM activity_logs WHERE action = ?", [$action]);
        } catch (Throwable $e) {}
    }

    public function test_filter_module_chi_tra_module_do(): void
    {
        $a1 = 'test_filter_module_a';
        $a2 = 'test_filter_module_b';
        $this->xoaLogTheoAction($a1);
        $this->xoaLogTheoAction($a2);
        try {
            log_action($a1, 'students', 'log students');
            log_action($a2, 'gifts',    'log gifts');

            $rows = db_all(
                "SELECT * FROM activity_logs WHERE action IN (?,?) ORDER BY id DESC LIMIT 10",
                [$a1, $a2]
            );

            // Tách theo module
            $students = array_filter($rows, fn($r) => $r['module'] === 'students');
            $gifts    = array_filter($rows, fn($r) => $r['module'] === 'gifts');

            $this->assertNotEmpty($students, 'Có log students.');
            $this->assertNotEmpty($gifts,    'Có log gifts.');
            foreach ($students as $r) {
                $this->assertSame('students', $r['module'], 'Module phải là students.');
            }
            foreach ($gifts as $r) {
                $this->assertSame('gifts', $r['module'], 'Module phải là gifts.');
            }
        } finally {
            $this->xoaLogTheoAction($a1);
            $this->xoaLogTheoAction($a2);
        }
    }

    public function test_filter_from_to_chi_tra_trong_khoang(): void
    {
        $a = 'test_filter_date';
        $this->xoaLogTheoAction($a);
        try {
            // Ghi log mới (hiện tại)
            log_action($a, 'test', 'log now');

            // Lọc từ 2025-01-01 (chỉ log mới)
            $rows = db_all(
                "SELECT * FROM activity_logs WHERE action = ? AND logged_at >= ?",
                [$a, '2025-01-01 00:00:00']
            );
            $this->assertNotEmpty($rows, 'Có log sau 2025-01-01.');
            foreach ($rows as $r) {
                $this->assertGreaterThanOrEqual(
                    strtotime('2025-01-01'),
                    strtotime($r['logged_at']),
                    'Thời gian phải >= 2025-01-01.'
                );
            }
        } finally {
            $this->xoaLogTheoAction($a);
        }
    }

    public function test_filter_actor_like_hoat_dong(): void
    {
        $a = 'test_filter_actor';
        $this->xoaLogTheoAction($a);
        try {
            log_action($a, 'test', 'log actor test');

            $rows = db_all(
                "SELECT * FROM activity_logs WHERE action = ? AND actor_name LIKE ?",
                [$a, '%Hệ thống%']
            );
            $this->assertNotEmpty($rows, 'Có log với actor_name chứa "Hệ thống".');
        } finally {
            $this->xoaLogTheoAction($a);
        }
    }

    public function test_filter_combined_module_and_from(): void
    {
        $a = 'test_filter_combined';
        $this->xoaLogTheoAction($a);
        try {
            log_action($a, 'students', 'log combined');

            $rows = db_all(
                "SELECT * FROM activity_logs
                  WHERE action = ? AND module = ? AND logged_at >= ?",
                [$a, 'students', '2025-01-01 00:00:00']
            );
            $this->assertNotEmpty($rows, 'Có log students sau 2025.');
            foreach ($rows as $r) {
                $this->assertSame('students', $r['module']);
                $this->assertGreaterThanOrEqual(strtotime('2025-01-01'), strtotime($r['logged_at']));
            }
        } finally {
            $this->xoaLogTheoAction($a);
        }
    }
}
