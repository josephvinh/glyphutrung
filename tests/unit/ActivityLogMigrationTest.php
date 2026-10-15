<?php
// tests/unit/ActivityLogMigrationTest.php
//
// Migration 010 thêm 6 cột (ip, user_agent, target_type, target_id, target_label,
// diff_json) + 2 index (idx_log_module_time, idx_log_action_time) vào activity_logs.
// Kiểm:
//   1) install.php chạy xong phải có đủ 6 cột mới trong information_schema.columns
//   2) install.php chạy xong phải có đủ 2 index mới trong information_schema.statistics,
//      đúng thứ tự cột
//   3) install.php chạy LẦN HAI vẫn thành công (idempotent: ADD COLUMN IF NOT EXISTS +
//      CREATE INDEX IF NOT EXISTS) — cột + index vẫn còn
//   4) Log cũ (chỉ có 7 cột) vẫn insert/đọc được, 6 cột mới mặc định NULL
//   5) Bản thân file 010_activity_log_diff.sql dùng IF NOT EXISTS, KHÔNG DELIMITER,
//      có đủ tên 2 index
//
// Chỉ chạy trên DB tntt_test (install.php gieo dữ liệu khởi tạo — CLAUDE.md luật 8).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class ActivityLogMigrationTest extends TestCase
{
    private const COT_MOI = ['ip', 'user_agent', 'target_type', 'target_id', 'target_label', 'diff_json'];
    private const INDEX_MOI = [
        'idx_log_module_time' => ['module', 'logged_at'],
        'idx_log_action_time' => ['action', 'logged_at'],
    ];
    private const ACTOR_TEST = 'test-migration-010';

    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') {
            $this->markTestSkipped("Chỉ chạy migration test trên DB tntt_test (đang là '$ten').");
        }
    }

    protected function tearDown(): void
    {
        // Test 4 chèn log thử; xóa sạch dù test pass/fail để không ảnh hưởng test khác.
        try {
            db_run("DELETE FROM activity_logs WHERE actor_name = ?", [self::ACTOR_TEST]);
        } catch (Throwable $e) {
            // Bảng chưa tồn tại cũng không sao (install chưa chạy) — bỏ qua.
        }
    }

    /** @return array{0:int,1:string} [mã thoát, đầu ra] */
    private function chayInstall(): array
    {
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/config/install.php') . ' 2>&1', $out, $ma);
        return [$ma, implode("\n", $out)];
    }

    /** @return string[] */
    private function dsCotMoi(): array
    {
        $ds = db_all("SELECT column_name AS c FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'activity_logs'
              AND column_name IN ('ip','user_agent','target_type','target_id','target_label','diff_json')");
        return array_column($ds, 'c');
    }

    /**
     * Lấy danh sách tên cột của một index, theo đúng thứ tự seq_in_index ASC.
     * @return string[]
     */
    private function dsCotIndex(string $tenIndex): array
    {
        $ds = db_all("SELECT column_name AS c FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'activity_logs'
              AND index_name = ?
            ORDER BY seq_in_index ASC", [$tenIndex]);
        return array_column($ds, 'c');
    }

    private function coBangActivityLogs(): bool
    {
        return (int) (db_one("SELECT COUNT(*) c FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'activity_logs'")['c'] ?? 0) > 0;
    }

    public function test_install_tao_du_6_cot_moi_trong_activity_logs(): void
    {
        [$ma, $out] = $this->chayInstall();
        $this->assertSame(0, $ma, "install.php thoát mã $ma.\n$out");
        $this->assertStringContainsString('HOÀN TẤT', $out, "install.php không in HOÀN TẤT:\n$out");
        $this->assertStringNotContainsString('LỖI', $out, "install.php có dòng LỖI:\n$out");
        $this->assertTrue($this->coBangActivityLogs(), 'CSDL chưa có bảng activity_logs.');

        $cot = $this->dsCotMoi();
        sort($cot);
        $mong = self::COT_MOI;
        sort($mong);
        $this->assertSame($mong, $cot,
            'Thiếu cột mới của migration 010. Tìm: ' . implode(',', $cot));
        foreach (self::COT_MOI as $c) {
            $dem = (int) (db_one("SELECT COUNT(*) c FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'activity_logs' AND column_name = ?", [$c])['c'] ?? 0);
            $this->assertSame(1, $dem, "Cột mới '$c' phải xuất hiện đúng 1 lần, đang có $dem.");
        }
    }

    public function test_install_tao_du_2_index_moi(): void
    {
        [$ma, $out] = $this->chayInstall();
        $this->assertSame(0, $ma, "install.php thoát mã $ma.\n$out");
        $this->assertStringContainsString('HOÀN TẤT', $out);
        $this->assertTrue($this->coBangActivityLogs(), 'CSDL chưa có bảng activity_logs.');

        $tenIndex = array_column(db_all("SELECT DISTINCT index_name AS i FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'activity_logs'
              AND index_name IN ('idx_log_module_time','idx_log_action_time')"), 'i');
        foreach (array_keys(self::INDEX_MOI) as $idx) {
            $this->assertContains($idx, $tenIndex, "Thiếu index '$idx' trên activity_logs.");
        }

        // Đúng thứ tự cột cho từng index
        foreach (self::INDEX_MOI as $idx => $cotMong) {
            $cotThuc = $this->dsCotIndex($idx);
            $this->assertSame($cotMong, $cotThuc,
                "Index '$idx' có thứ tự cột sai. Mong " . implode(',', $cotMong)
                . '; thấy ' . implode(',', $cotThuc));
        }
    }

    public function test_install_chay_lan_thu_hai_van_thanh_cong_idempotent(): void
    {
        // Lần 1
        [$ma1, $out1] = $this->chayInstall();
        $this->assertSame(0, $ma1, "Lần 1: install.php thoát mã $ma1.\n$out1");

        // Lần 2
        [$ma2, $out2] = $this->chayInstall();
        $this->assertSame(0, $ma2, "Lần 2: install.php thoát mã $ma2 (không idempotent).\n$out2");
        $this->assertStringContainsString('HOÀN TẤT', $out2, "Lần 2 không in HOÀN TẤT:\n$out2");
        $this->assertStringNotContainsString('LỖI', $out2, "Lần 2 có dòng LỖI:\n$out2");

        // Cột vẫn còn
        $cot = $this->dsCotMoi();
        sort($cot);
        $mong = self::COT_MOI;
        sort($mong);
        $this->assertSame($mong, $cot, 'Sau lần 2, cột mới đã mất hoặc lệch.');

        // Index vẫn còn + đúng thứ tự cột
        $tenIndex = array_column(db_all("SELECT DISTINCT index_name AS i FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'activity_logs'
              AND index_name IN ('idx_log_module_time','idx_log_action_time')"), 'i');
        foreach (array_keys(self::INDEX_MOI) as $idx) {
            $this->assertContains($idx, $tenIndex, "Sau lần 2, index '$idx' đã mất.");
            $this->assertSame(self::INDEX_MOI[$idx], $this->dsCotIndex($idx),
                "Sau lần 2, index '$idx' có thứ tự cột sai.");
        }
    }

    public function test_insert_log_cu_van_chay_voi_7_cot_cu(): void
    {
        // Đảm bảo bảng/cột mới đã có sẵn
        [$ma, $out] = $this->chayInstall();
        $this->assertSame(0, $ma, "install.php thoát mã $ma.\n$out");
        $this->assertTrue($this->coBangActivityLogs(), 'CSDL chưa có bảng activity_logs.');

        // Dọn trước khi insert để chạy lại nhiều lần đều pass
        db_run("DELETE FROM activity_logs WHERE actor_name = ?", [self::ACTOR_TEST]);

        try {
            $id = db_insert(
                "INSERT INTO activity_logs (actor_id, actor_name, action, module, what, detail)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [0, self::ACTOR_TEST, 'test', 'test_module', 'log cũ chỉ có 7 cột', 'chi tiết']
            );
            $this->assertGreaterThan(0, $id, 'INSERT log cũ phải trả về id > 0.');

            $row = db_one("SELECT ip, user_agent, target_type, target_id, target_label, diff_json
                FROM activity_logs WHERE id = ?", [$id]);
            $this->assertNotNull($row, 'Không đọc lại được dòng log vừa insert.');
            foreach (['ip', 'user_agent', 'target_type', 'target_id', 'target_label', 'diff_json'] as $c) {
                $this->assertNull($row[$c] ?? 'KHONG_PHU_HOP', "Cột mới '$c' phải NULL với log cũ, lấy: "
                    . var_export($row[$c] ?? null, true));
            }
        } finally {
            db_run("DELETE FROM activity_logs WHERE actor_name = ?", [self::ACTOR_TEST]);
        }
    }

    public function test_migration_file_010_co_noi_dung_idempotent(): void
    {
        $file = dirname(__DIR__, 2) . '/config/migrations/010_activity_log_diff.sql';
        $this->assertFileExists($file, 'Thiếu file migration 010_activity_log_diff.sql.');

        $noiDung = (string) file_get_contents($file);
        $this->assertNotSame('', $noiDung, 'File migration 010 rỗng.');

        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS', $noiDung,
            'Migration 010 phải dùng ADD COLUMN IF NOT EXISTS để idempotent.');
        $this->assertStringNotContainsString('DELIMITER', $noiDung,
            'Migration 010 KHÔNG được dùng DELIMITER (PDO không hỗ trợ).');

        foreach (array_keys(self::INDEX_MOI) as $idx) {
            $this->assertStringContainsString($idx, $noiDung, "Migration 010 phải khai báo index '$idx'.");
            $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS', $noiDung,
                'Migration 010 phải dùng CREATE INDEX IF NOT EXISTS để idempotent.');
        }
    }
}
