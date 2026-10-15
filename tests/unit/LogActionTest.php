<?php
// tests/unit/LogActionTest.php
//
// log_action() ở public/api/_bootstrap.php dòng 205-257 đã được đổi chữ ký:
//   (string $action, string $module, string $what, string $detail = '', array $opts = [])
// Tham số thứ 4 + 5 optional. Khi truyền $opts sẽ ghi thêm IP, user_agent, target_*
// và diff_json. Khi KHÔNG truyền $opts (3-4 tham số — chữ ký cũ), 6 cột mới tự NULL.
//
// Verify:
//   1) Chữ ký cũ 3 tham số vẫn chạy (backward compat), 6 cột mới NULL
//   2) Chữ ký cũ 4 tham số vẫn chạy
//   3) Chữ ký mới ghi diff_json (before + after) + target_*
//   4) IP và user_agent được lấy từ $_SERVER (save/restore để không ảnh hưởng test khác)
//   5) Chỉ truyền 'after' (không 'before') vẫn ghi diff
//   6) Truyền $opts rỗng vẫn không tạo diff (diff_json NULL)
//
// Chỉ chạy trên DB tntt_test (CLAUDE.md luật 8).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class LogActionTest extends TestCase
{
    /** Mỗi test ghi log với action riêng để query/xóa chính xác. */
    private const ACTOR_HE_THONG = 'Hệ thống';

    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') {
            $this->markTestSkipped("Chỉ chạy log_action test trên DB tntt_test (đang là '$ten').");
        }
    }

    /** Lưu giá trị $_SERVER cũ của REMOTE_ADDR + HTTP_USER_AGENT để tearDown khôi phục. */
    private function datThietLapServer(string $ip, ?string $ua): array
    {
        $cu = [
            'REMOTE_ADDR'     => $_SERVER['REMOTE_ADDR']     ?? null,
            'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
        $_SERVER['REMOTE_ADDR']     = $ip;
        if ($ua === null) {
            unset($_SERVER['HTTP_USER_AGENT']);
        } else {
            $_SERVER['HTTP_USER_AGENT'] = $ua;
        }
        return $cu;
    }

    private function khoiPhucServer(array $cu): void
    {
        foreach ($cu as $k => $v) {
            if ($v === null) unset($_SERVER[$k]);
            else              $_SERVER[$k] = $v;
        }
    }

    private function docLogTheoAction(string $action): ?array
    {
        return db_one(
            "SELECT * FROM activity_logs
              WHERE actor_name = ? AND action = ?
              ORDER BY id DESC LIMIT 1",
            [self::ACTOR_HE_THONG, $action]
        );
    }

    private function xoaLogTheoAction(string $action): void
    {
        db_run(
            "DELETE FROM activity_logs WHERE actor_name = ? AND action = ?",
            [self::ACTOR_HE_THONG, $action]
        );
    }

    public function test_chu_ky_cu_3_tham_so_van_chay(): void
    {
        $action = 'test_chu_ky_cu_3';
        $this->xoaLogTheoAction($action);
        try {
            // Không truyền detail, không truyền $opts — chữ ký cũ gọn nhất.
            log_action($action, 'test_module', 'test_what');

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertNull($row['actor_id'], 'actor_id phải NULL khi current_member() trả null.');
            $this->assertSame('test_module', $row['module']);
            $this->assertSame('test_what',   $row['what']);
            $this->assertSame('',            $row['detail'], 'detail phải rỗng khi không truyền.');

            // 6 cột mới từ migration 010 phải NULL khi gọi chữ ký cũ.
            foreach (['ip', 'user_agent', 'target_type', 'target_id', 'target_label', 'diff_json'] as $c) {
                $this->assertNull($row[$c] ?? 'KHONG_PHU_HOP',
                    "Cột '$c' phải NULL với chữ ký cũ, lấy: " . var_export($row[$c] ?? null, true));
            }
        } finally {
            $this->xoaLogTheoAction($action);
        }
    }

    public function test_chu_ky_cu_4_tham_so_van_chay(): void
    {
        $action = 'test_chu_ky_cu_4';
        $this->xoaLogTheoAction($action);
        try {
            log_action($action, 'test_module', 'test_what', 'chi tiết ABC');

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertSame('test_module',   $row['module']);
            $this->assertSame('test_what',     $row['what']);
            $this->assertSame('chi tiết ABC',  $row['detail']);

            foreach (['ip', 'user_agent', 'target_type', 'target_id', 'target_label', 'diff_json'] as $c) {
                $this->assertNull($row[$c] ?? 'KHONG_PHU_HOP',
                    "Cột '$c' phải NULL với chữ ký cũ 4 tham số, lấy: " . var_export($row[$c] ?? null, true));
            }
        } finally {
            $this->xoaLogTheoAction($action);
        }
    }

    public function test_chu_ky_moi_ghi_diff_json_va_target(): void
    {
        $action = 'test_chu_ky_moi_diff';
        $this->xoaLogTheoAction($action);
        try {
            log_action($action, 'students', 'Sửa thiếu nhi #42', 'Đổi tên', [
                'before'       => ['name' => 'Nguyễn Văn A'],
                'after'        => ['name' => 'Nguyễn Văn B'],
                'target_type'  => 'student',
                'target_id'    => 42,
                'target_label' => 'Nguyễn Văn A · Chiên 2',
            ]);

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertSame('student',                  $row['target_type']);
            $this->assertSame('42',                       (string) $row['target_id']);
            $this->assertSame('Nguyễn Văn A · Chiên 2',   $row['target_label']);

            // So sánh diff_json bằng json_decode — tránh phụ thuộc thứ tự key
            $this->assertNotNull($row['diff_json'], 'diff_json phải có khi truyền before+after.');
            $diff = json_decode((string) $row['diff_json'], true);
            $this->assertIsArray($diff, 'diff_json phải là JSON object hợp lệ.');
            $this->assertSame(['name' => 'Nguyễn Văn A'], $diff['before'] ?? null,
                'diff.before phải khớp.');
            $this->assertSame(['name' => 'Nguyễn Văn B'], $diff['after'] ?? null,
                'diff.after phải khớp.');
        } finally {
            $this->xoaLogTheoAction($action);
        }
    }

    public function test_ip_va_user_agent_duoc_ghi_tu_server(): void
    {
        $action = 'test_ip_ua';
        $this->xoaLogTheoAction($action);

        $ipMoi = '203.0.113.42';
        $uaMoi = 'PHPUnit-Test/1.0';
        $cu = $this->datThietLapServer($ipMoi, $uaMoi);

        try {
            log_action($action, 'test_module', 'test_what');

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertSame($ipMoi, $row['ip'],
                'ip phải lấy từ $_SERVER[REMOTE_ADDR].');
            $this->assertSame($uaMoi, $row['user_agent'],
                'user_agent phải lấy từ $_SERVER[HTTP_USER_AGENT].');
        } finally {
            $this->khoiPhucServer($cu);
            $this->xoaLogTheoAction($action);
        }
    }

    public function test_chi_co_after_khong_co_before_van_ghi_diff(): void
    {
        $action = 'test_chi_after';
        $this->xoaLogTheoAction($action);
        try {
            log_action($action, 'students', 'Thêm thiếu nhi', '', [
                'after' => ['name' => 'Thiếu nhi mới'],
            ]);

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertNotNull($row['diff_json'],
                'diff_json phải có khi có after, dù không có before.');

            $diff = json_decode((string) $row['diff_json'], true);
            $this->assertIsArray($diff, 'diff_json phải là JSON object hợp lệ.');
            $this->assertArrayHasKey('before', $diff, 'diff phải có key before (null).');
            $this->assertNull($diff['before'],
                'diff.before phải null khi không truyền before.');
            $this->assertSame(['name' => 'Thiếu nhi mới'], $diff['after'] ?? null,
                'diff.after phải khớp.');
        } finally {
            $this->xoaLogTheoAction($action);
        }
    }

    public function test_khong_co_opts_khong_tao_diff(): void
    {
        $action = 'test_khong_opts';
        $this->xoaLogTheoAction($action);
        try {
            // Truyền $opts = [] rỗng — không có before/after → diff phải NULL.
            log_action($action, 'test_module', 'test_what', '', []);

            $row = $this->docLogTheoAction($action);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa ghi.');
            $this->assertNull($row['diff_json'],
                'diff_json phải NULL khi $opts rỗng (không có before/after).');
        } finally {
            $this->xoaLogTheoAction($action);
        }
    }
}
