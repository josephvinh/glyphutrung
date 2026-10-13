<?php
// tests/unit/DateDisplayFormatTest.php
//
// Ngày hiển thị cho người dùng luôn là dd/mm/yyyy, ngày giờ là dd/mm/yyyy HH:mm (giờ Việt Nam).
//   1. Hàm PHP dùng chung fmt_ngay()/fmt_ngay_gio() (_common.php) — cắt chuỗi thuần, không dính múi giờ.
//   2. Các mẫu giao diện không in thẳng trường ngày giờ thô ('yyyy-mm-dd HH:mm') ra màn hình.
//   3. Phiếu liên lạc PDF (export.php) in ngày sinh / kỳ học theo dd/mm/yyyy.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class DateDisplayFormatTest extends TestCase
{
    public function test_fmt_ngay(): void
    {
        $this->assertSame('13/10/2016', fmt_ngay('2016-10-13'));
        $this->assertSame('13/10/2016', fmt_ngay('2016-10-13 06:30:45'), 'Có giờ thì chỉ lấy ngày.');
        $this->assertSame('13/10/2016', fmt_ngay('2016-10-13T06:30'));
        $this->assertSame('', fmt_ngay(null));
        $this->assertSame('', fmt_ngay(''));
        $this->assertSame('13/10/2016', fmt_ngay('13/10/2016'), 'Đã đúng dạng dd/mm/yyyy thì giữ nguyên.');
        $this->assertSame('abc', fmt_ngay('abc'), 'Không đúng dạng thì trả nguyên văn, không bịa ngày.');
    }

    public function test_fmt_ngay_gio(): void
    {
        $this->assertSame('13/10/2026 06:30', fmt_ngay_gio('2026-10-13 06:30'));
        $this->assertSame('13/10/2026 06:30', fmt_ngay_gio('2026-10-13 06:30:59'));
        $this->assertSame('13/10/2026', fmt_ngay_gio('2026-10-13'));
        $this->assertSame('', fmt_ngay_gio(null));
        $this->assertSame('x', fmt_ngay_gio('x'));
    }

    /** Trường ngày giờ của dữ liệu trả về ('yyyy-mm-dd HH:mm') không được in thô trong mẫu giao diện. */
    public function test_mau_giao_dien_khong_in_ngay_gio_tho(): void
    {
        $root = dirname(__DIR__, 2);
        $truong = 'publishedAt|meetingAt|createdAt|approvedAt|remindAt';
        $vp = [];
        foreach (glob($root . '/views/*.php') as $f) {
            foreach (explode("\n", file_get_contents($f)) as $i => $dong) {
                if (!preg_match_all('/x-text="([^"]*)"/', $dong, $ms)) continue;
                foreach ($ms[1] as $bt) {
                    if (!preg_match('/\b(' . $truong . ')\b/', $bt)) continue;
                    if (preg_match('/vnFmtDateTime|vnFmtDate|formatDate|formatRelativeTime|cfFormat/', $bt)) continue;
                    $vp[] = basename($f) . ':' . ($i + 1) . '  x-text="' . $bt . '"';
                }
            }
        }
        $this->assertSame([], $vp, "In thẳng ngày giờ thô (cần vnFmtDateTime(...)):\n" . implode("\n", $vp));
    }

    public function test_phieu_lien_lac_in_ngay_dd_mm_yyyy(): void
    {
        $src = file_get_contents(dirname(__DIR__, 2) . '/public/api/export.php');
        foreach (["birth_date", "['from']", "['to']"] as $k) {
            $this->assertMatchesRegularExpression('/fmt_ngay\(\$[a-z]+\[\'?' . preg_quote(trim($k, "[]'"), '/') . '\'?\]/', $src,
                "export.php: $k phải qua fmt_ngay() khi dựng phiếu liên lạc.");
        }
    }
}
