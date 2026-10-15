<?php
// tests/unit/VnTimeUsageTest.php
//
// "Hôm nay / bây giờ" trong giao diện PHẢI lấy từ public/assets/js/modules/vntime.js (giờ Việt
// Nam), không từ đồng hồ/múi giờ của máy. Test tĩnh này chặn các kiểu viết đã gây lệch ngày/giờ:
//   - toDateInput(new Date())        : "hôm nay" theo múi giờ máy -> dùng this.vnToday()
//   - new Date().getHours()/getDate()/getDay()/getMonth()/getFullYear()/toLocale*()/toDateString()
//   - toISOString().slice/split      : ngày UTC, lệch 1 ngày lúc 00:00-07:00 giờ VN
// Mốc thời gian tuyệt đối (Date.now(), new Date().toISOString() nguyên chuỗi) KHÔNG bị chặn: không phụ
// thuộc múi giờ. xlsx_io.js (nhập/xuất Excel) được xử lý ở bước riêng của kế hoạch giờ VN.

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class VnTimeUsageTest extends TestCase
{
    /** Tệp được phép dùng đồng hồ máy. */
    private const BO_QUA = ['vntime.js', 'xlsx_io.js'];

    private const CAM = [
        '/toDateInput\(\s*new Date\(\s*\)\s*\)/'
            => 'dùng this.vnToday() (giờ VN) thay cho toDateInput(new Date())',
        '/new Date\(\s*\)\s*\.\s*(getHours|getMinutes|getSeconds|getDate|getDay|getMonth|getFullYear|toLocale\w*|toDateString)\s*\(/'
            => 'dùng window.TNTT.vn.parts()/today()/time() thay cho new Date().get*/toLocale*',
        '/\.toISOString\(\)\s*\.\s*(slice|split|substring|substr)\s*\(/'
            => 'toISOString() là ngày UTC; dùng this.vnToday() hoặc vnAddDays()',
        '/\.toDateString\s*\(/'
            => 'toDateString() theo múi giờ máy; dùng window.TNTT.vn.today()',
    ];

    private function nguon(): array
    {
        $root = dirname(__DIR__, 2);
        $ds = array_merge(glob($root . '/public/assets/js/modules/*.js') ?: [], glob($root . '/views/*.php') ?: []);
        $out = [];
        foreach ($ds as $f) {
            if (in_array(basename($f), self::BO_QUA, true)) continue;
            $out[$f] = file_get_contents($f);
        }
        return $out;
    }

    public function test_khong_dung_dong_ho_may_cho_hom_nay_va_gio(): void
    {
        $vp = [];
        foreach ($this->nguon() as $f => $code) {
            // bỏ chú thích để không bắt nhầm lời giải thích
            $sach = preg_replace('#/\*.*?\*/#s', '', $code);
            foreach (explode("\n", $sach) as $i => $dong) {
                $dong = preg_replace('#(?<![:\'"])//.*$#', '', $dong);
                foreach (self::CAM as $re => $huong) {
                    if (preg_match($re, $dong)) {
                        $vp[] = basename($f) . ':' . ($i + 1) . ' — ' . $huong . '  |  ' . trim($dong);
                    }
                }
            }
        }
        $this->assertSame([], $vp, "Còn chỗ dùng đồng hồ/múi giờ của máy:\n" . implode("\n", $vp));
    }

    /**
     * window.TNTT.vn.now() / this.vnNow() trả CHUỖI 'yyyy-mm-dd HH:mm', KHÔNG phải đối tượng.
     * `vn.now().year` ra undefined -> NaN/'NaN-NaN' trên giao diện mà ESLint và PHPUnit đều không thấy
     * (lỗi này đã nằm trong personal.js). Muốn năm/tháng/ngày: window.TNTT.vn.parts() -> {y, m, d, ...}.
     */
    public function test_vn_now_la_chuoi_khong_doc_thuoc_tinh_nhu_doi_tuong(): void
    {
        $re = '/\b(?:vn\.now|vnNow)\(\s*[^()]*\)\s*\.\s*(year|month|day|hour|minute|second|y|m|d|hh|mm|ss)\b/';
        $vp = [];
        foreach ($this->nguon() as $f => $code) {
            $sach = preg_replace('#/\*.*?\*/#s', '', $code);
            foreach (explode("\n", $sach) as $i => $dong) {
                $dong = preg_replace('#(?<![:\'"])//.*$#', '', $dong);
                if (preg_match($re, $dong)) {
                    $vp[] = basename($f) . ':' . ($i + 1) . '  |  ' . trim($dong);
                }
            }
        }
        $this->assertSame([], $vp, "vn.now() trả chuỗi — dùng window.TNTT.vn.parts() để lấy năm/tháng/ngày:\n" . implode("\n", $vp));
    }

    public function test_vntime_la_module_dau_tien_sau_router(): void
    {
        $m = require dirname(__DIR__, 2) . '/public/assets/asset_manifest.php';
        $ds = $m['js_modules'];
        $this->assertContains('vntime', $ds);
        $this->assertLessThan(array_search('core', $ds, true), array_search('vntime', $ds, true),
            'vntime phải nạp trước core và mọi mảnh dùng window.TNTT.vn.');
    }
}
