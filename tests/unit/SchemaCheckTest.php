<?php
// tests/unit/SchemaCheckTest.php
//
// scripts/kiem_schema.php (kiểm DB đã đủ migration, chỉ đọc) và khối SQL tương
// đương trong docs/process/DEPLOY.md (cho người chỉ có phpMyAdmin).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class SchemaCheckTest extends TestCase
{
    private const SCRIPT = __DIR__ . '/../../scripts/kiem_schema.php';

    /** @return array{0:int,1:string} */
    private function chay(string $doi = ''): array
    {
        $out = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(self::SCRIPT) . ' ' . $doi . ' 2>&1', $out, $ma);
        return [$ma, implode("\n", $out)];
    }

    private function dungDb(): bool
    {
        return (db_one('SELECT DATABASE() AS d')['d'] ?? '') === 'tntt_test';
    }

    public function test_moi_file_migration_trong_danh_sach_deu_ton_tai(): void
    {
        [, $sql] = $this->chay('--sql');
        preg_match_all("/SELECT '([^']+\\.sql)'|UNION ALL SELECT '([^']+\\.sql)'/", $sql, $m);
        $files = array_unique(array_filter(array_merge($m[1], $m[2])));
        $this->assertGreaterThan(3, count($files));
        foreach ($files as $f) {
            $this->assertTrue(is_file(__DIR__ . '/../../config/migrations/' . $f), "Danh sách kiểm nhắc tới $f nhưng file không có.");
        }
    }

    public function test_khoi_sql_trong_tai_lieu_khop_voi_script(): void
    {
        $doc = file_get_contents(__DIR__ . '/../../docs/process/DEPLOY.md');
        [, $sql] = $this->chay('--sql');
        // Mỗi cặp (bảng, cột) mà script kiểm đều phải có mặt trong khối SQL của tài liệu.
        preg_match_all("/'([^']+\\.sql)'(?: AS migration)?, '([^']+)'(?: AS bang)?, (NULL|'[^']+')/", $sql, $m, PREG_SET_ORDER);
        $this->assertGreaterThan(8, count($m), 'Không đọc được danh sách từ --sql.');
        foreach ($m as [, $file, $bang, $cot]) {
            $mau = "'$file'" . '(?: AS migration)?, ' . "'$bang'" . '(?: AS bang)?, ' . preg_quote($cot, '/');
            $this->assertSame(1, preg_match("/$mau/", $doc),
                "docs/process/DEPLOY.md thiếu/lệch dòng kiểm cho $file / $bang / $cot — cập nhật khối SQL cho khớp scripts/kiem_schema.php.");
        }
    }

    public function test_script_bao_dung_khi_du_va_khi_thieu(): void
    {
        if (!$this->dungDb()) $this->markTestSkipped('Cần DB tntt_test.');
        $co = fn() => (int) db_one("SELECT COUNT(*) c FROM information_schema.tables
                WHERE table_schema = DATABASE() AND table_name = 'push_notifications'")['c'];
        if (!$co()) $this->markTestSkipped('DB test chưa có push_notifications.');

        [, $out] = $this->chay();
        $this->assertStringContainsString('✅ 030_push_notifications.sql — bảng push_notifications', $out);

        db_run('RENAME TABLE push_notifications TO push_notifications_tam');
        try {
            [$ma, $out] = $this->chay();
        } finally {
            db_run('RENAME TABLE push_notifications_tam TO push_notifications');
        }
        $this->assertSame(1, $ma, 'Thiếu bảng thì phải thoát mã 1.');
        $this->assertStringContainsString('❌ 030_push_notifications.sql — THIẾU bảng push_notifications', $out);
        $this->assertStringContainsString('   - 030_push_notifications.sql', $out, 'Phải liệt kê file cần chạy.');
    }
}
