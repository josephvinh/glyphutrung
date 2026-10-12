<?php
// tests/unit/InstallRerunTest.php
//
// config/install.php phải CHẠY LẠI được trên DB đã dựng (idempotent). Trước đây lần
// chạy thứ hai dừng ở migration 030 với lỗi 2014 "Cannot execute queries while
// other unbuffered queries are active": khi bảng/cột/index đã có, migration chạy
// 'SELECT 1' qua PREPARE/EXECUTE và exec() không đóng tập kết quả đó.
//
// Chỉ chạy trên DB tntt_test (install.php gieo dữ liệu khởi tạo — CLAUDE.md luật 8).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class InstallRerunTest extends TestCase
{
    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') $this->markTestSkipped("Chỉ chạy install.php trên DB tntt_test (đang là '$ten').");
    }

    /** @return array{0:int,1:string} [mã thoát, đầu ra] */
    private function chayInstall(): array
    {
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 2) . '/config/install.php') . ' 2>&1', $out, $ma);
        return [$ma, implode("\n", $out)];
    }

    public function test_chay_install_hai_lan_lien_tiep_deu_thanh_cong(): void
    {
        foreach ([1, 2] as $lan) {
            [$ma, $out] = $this->chayInstall();
            $this->assertSame(0, $ma, "Lần $lan: install.php thoát mã $ma.\n$out");
            $this->assertStringNotContainsString('LỖI', $out, "Lần $lan có dòng LỖI:\n$out");
            $this->assertStringContainsString('HOÀN TẤT', $out, "Lần $lan không in HOÀN TẤT:\n$out");
        }
    }

    public function test_sau_install_co_du_doi_tuong_cua_migration_009_va_030(): void
    {
        $this->chayInstall();
        $dem = fn(string $sql) => (int) (db_one($sql)['c'] ?? 0);
        $this->assertSame(2, $dem("SELECT COUNT(*) c FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'attendances'
              AND column_name IN ('scanned_at','offline_marked')"), 'Thiếu cột của migration 009.');
        $this->assertSame(1, $dem("SELECT COUNT(*) c FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'push_notifications'"), 'Thiếu bảng của migration 030.');
        $this->assertSame(1, $dem("SELECT COUNT(*) c FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'push_outbox' AND column_name = 'notification_id'"));
    }
}
