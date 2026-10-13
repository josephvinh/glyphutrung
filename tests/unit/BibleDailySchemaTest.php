<?php
// tests/unit/BibleDailySchemaTest.php
//
// api/bible.php (thẻ "Lời Chúa" ở trang chủ) giới hạn 1 câu/IP/giờ bằng bảng
// bible_daily. Bảng này trước đây chỉ do migration 006 tạo, còn config/schema.sql
// (nguồn của install.php) THIẾU -> CSDL dựng bằng install.php (CI, máy dev) làm
// bible.php?action=random trả HTTP 500. Kiểm: schema.sql có bảng, CSDL có đủ cột
// mã đang dùng, và endpoint chạy thật (câu thứ hai trong giờ lấy từ bản đã lưu).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/P1ApiHarness.php';

use PHPUnit\Framework\TestCase;

class BibleDailySchemaTest extends TestCase
{
    use P1ApiHarness;

    private const COLS = ['id', 'ip_address', 'verse_text', 'verse_ref', 'verse_translation', 'fetched_at'];

    public static function setUpBeforeClass(): void
    {
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    private function dbTest(): bool
    {
        return (db_one('SELECT DATABASE() AS d')['d'] ?? '') === 'tntt_test';
    }

    public function test_schema_sql_co_bang_bible_daily_du_cot(): void
    {
        $sql = file_get_contents(__DIR__ . '/../../config/schema.sql');
        $this->assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS bible_daily\s*\((.*?)\)\s*ENGINE=/s', $sql, $m),
            'config/schema.sql phải tạo bảng bible_daily (CREATE TABLE IF NOT EXISTS).');
        foreach (self::COLS as $c) {
            $this->assertMatchesRegularExpression('/\b' . $c . '\b/', $m[1], "schema.sql: bible_daily thiếu cột $c.");
        }
    }

    public function test_csdl_co_bang_bible_daily_du_cot(): void
    {
        $cot = array_column(db_all(
            "SELECT column_name AS c FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = 'bible_daily'"), 'c');
        $this->assertNotSame([], $cot, 'CSDL chưa có bảng bible_daily (đã chạy php config/install.php chưa?).');
        foreach (self::COLS as $c) $this->assertContains($c, $cot, "bible_daily thiếu cột $c.");
    }

    public function test_endpoint_random_tra_cau_va_cau_thu_hai_lay_tu_ban_da_luu(): void
    {
        if (!$this->dbTest()) $this->markTestSkipped('Chỉ chạy trên DB tntt_test (endpoint ghi vào bible_daily).');
        db_run("DELETE FROM bible_daily WHERE ip_address = '127.0.0.1'");
        try {
            $get = function (): array {
                $ch = curl_init(self::$base . '/api/bible.php?action=random');
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
                $body = curl_exec($ch);
                $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
                curl_close($ch);
                return [$code, json_decode((string) $body, true)];
            };
            [$c1, $j1] = $get();
            $this->assertSame(200, $c1, 'bible.php?action=random phải trả 200.');
            $this->assertTrue($j1['success'] ?? false);
            $this->assertFalse($j1['cached'] ?? true, 'Lần đầu không được lấy từ bản đã lưu.');
            $this->assertNotSame('', (string) ($j1['verse'] ?? ''));

            [$c2, $j2] = $get();
            $this->assertSame(200, $c2);
            $this->assertTrue($j2['cached'] ?? false, 'Lần thứ hai trong giờ phải lấy từ bản đã lưu (giới hạn theo IP).');
            $this->assertSame($j1['verse'], $j2['verse']);
        } finally {
            db_run("DELETE FROM bible_daily WHERE ip_address = '127.0.0.1'");
        }
    }
}
