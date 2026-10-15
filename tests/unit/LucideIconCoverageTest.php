<?php
/**
 * Test ĐỘ PHỦ ICON LUCIDE — chặn lỗi "icon dùng trong giao diện nhưng không có trong bộ icon".
 *
 * Vì sao: public/assets/js/vendor/lucide-icons.js là bộ icon RÚT GỌN (không phải cả thư viện
 * lucide). `<i data-lucide="ten">` mà `ten` không có trong bộ thì lucide.createIcons() bỏ qua
 * và KHÔNG báo lỗi gì — giao diện chỉ hiện một ô trống (từng gặp: icon "award" ở thẻ Thâm niên
 * phục vụ). Test này liệt kê icon dùng trong view/trang/chuỗi HTML của JS mà bộ icon không có.
 *
 * Thêm icon mới: ghi vào bộ icon (xem chú thích đầu lucide-icons.js), không nới test này.
 *
 * PHẠM VI: chỉ xét tên TĨNH ghi thẳng data-lucide="...". Tên ghép động (:data-lucide, ternary)
 * không kiểm được. Test tĩnh trên mã nguồn: không cần DB, không cần trình duyệt.
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class LucideIconCoverageTest extends TestCase
{
    /** @return list<string> tên icon có trong bộ rút gọn */
    private function boIcon(): array
    {
        $js = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/vendor/lucide-icons.js');
        preg_match_all('/"([a-z0-9][a-z0-9-]*)"\s*:\s*\[/', $js, $m);
        return array_values(array_unique($m[1]));
    }

    public function test_bo_icon_doc_duoc_va_co_icon_nen_tang(): void
    {
        $co = $this->boIcon();
        $this->assertGreaterThan(80, count($co), 'Không đọc được danh sách icon từ lucide-icons.js.');
        foreach (['x', 'check', 'lock', 'trophy'] as $ten) {
            $this->assertContains($ten, $co, "Bộ icon phải có '$ten' (kiểm tra regex đọc tên, kể cả tên một ký tự).");
        }
    }

    public function test_moi_icon_dung_trong_giao_dien_deu_co_trong_bo_icon(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(
            glob($root . '/views/*.php') ?: [],
            glob($root . '/public/*.php') ?: [],
            glob($root . '/public/assets/js/modules/*.js') ?: []
        );
        $this->assertGreaterThan(20, count($files));

        $co = array_flip($this->boIcon());
        $thieu = [];
        foreach ($files as $f) {
            if (preg_match_all('/data-lucide=["\']([a-z0-9-]+)["\']/', file_get_contents($f), $m)) {
                foreach (array_unique($m[1]) as $ten) {
                    if (!isset($co[$ten])) $thieu[$ten][] = basename($f);
                }
            }
        }
        $dong = [];
        foreach ($thieu as $ten => $dsFile) $dong[] = "$ten  (" . implode(', ', $dsFile) . ')';
        $this->assertSame([], $dong, "Icon không có trong lucide-icons.js (giao diện sẽ hiện ô trống):\n  " . implode("\n  ", $dong));
    }
}
