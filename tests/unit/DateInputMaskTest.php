<?php
// tests/unit/DateInputMaskTest.php
//
// Ô nhập ngày/giờ luôn theo khuôn dd/mm/yyyy · hh:mm (views/_ngay_input.php + modules/ngay_nhap.js), không dùng
// <input type="date|time|datetime-local|month"> gốc (hiển thị theo vùng của trình duyệt: mm/dd/yyyy, AM/PM).
// Test tĩnh trên mã nguồn: không cần DB hay trình duyệt.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../views/_ngay_input.php';

use PHPUnit\Framework\TestCase;

class DateInputMaskTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    public function test_view_khong_dung_o_ngay_gio_goc(): void
    {
        $vp = [];
        $ds = array_merge(glob(self::ROOT . 'views/*.php') ?: [], [self::ROOT . 'public/index.php']);
        foreach ($ds as $f) {
            if (basename($f) === '_ngay_input.php') continue;     // chỉ nơi này được tạo ô gốc (nút lịch ẩn)
            foreach (explode("\n", file_get_contents($f)) as $i => $dong) {
                if (preg_match('/<input\b[^>]*\btype\s*=\s*"(date|time|datetime-local|month|week)"/i', $dong)) {
                    $vp[] = basename($f) . ':' . ($i + 1) . ' — dùng ngay_input() thay cho ô gốc';
                }
            }
        }
        $this->assertSame([], $vp, "Còn ô ngày/giờ gốc:\n" . implode("\n", $vp));
    }

    public function test_ngay_input_dung_chi_thi_va_giu_thuoc_tinh(): void
    {
        ob_start();
        ngay_input('form.birthDate', ['class' => 'w-full', 'attrs' => 'required min="1900-01-01" max="2026-10-13"']);
        $h = ob_get_clean();
        $this->assertStringContainsString('x-ngay="form.birthDate"', $h);
        $this->assertStringContainsString('type="text"', $h, 'Ô chính phải là ô chữ.');
        $this->assertStringContainsString('required min="1900-01-01" max="2026-10-13"', $h);
        $this->assertStringContainsString('class="ngay-in w-full"', $h);
        $this->assertStringContainsString('class="ngay-native"', $h, 'Phải có ô gốc ẩn cho nút lịch.');

        ob_start(); ngay_input('f.gio', ['kind' => 'time']); $g = ob_get_clean();
        $this->assertStringContainsString('x-ngay.time="f.gio"', $g);
        ob_start(); ngay_input('f.nh', ['kind' => 'datetime', 'wrap' => 'flex-1']); $n = ob_get_clean();
        $this->assertStringContainsString('x-ngay.datetime="f.nh"', $n);
        $this->assertStringContainsString('ngay-wrap flex-1', $n);
    }

    public function test_ngay_input_thoat_chuoi_model_va_lop(): void
    {
        ob_start();
        ngay_input('a" x-init="alert(1)', ['class' => 'c" onfocus="x']);
        $h = ob_get_clean();
        $this->assertStringNotContainsString('x-init="alert(1)"', $h, 'Model phải được thoát dấu nháy.');
        $this->assertStringNotContainsString('onfocus="x"', $h, 'Lớp CSS phải được thoát dấu nháy.');
    }

    public function test_module_va_trang_dang_nhap_nap_chi_thi(): void
    {
        $m = require self::ROOT . 'public/assets/asset_manifest.php';
        $this->assertContains('ngay_nhap', $m['js_modules'], 'Trang chính nạp chỉ thị qua asset_manifest.php.');
        $dn = file_get_contents(self::ROOT . 'views/layout_login.php');
        $this->assertStringContainsString('assets/js/modules/ngay_nhap.js', $dn, 'Trang đăng nhập (form đăng ký có ngày sinh) phải nạp chỉ thị.');
        $idx = file_get_contents(self::ROOT . 'public/index.php');
        $this->assertStringContainsString('_ngay_input.php', $idx, 'index.php phải nạp hàm ngay_input() trước các view.');
        $css = file_get_contents(self::ROOT . 'public/assets/css/app.css');
        foreach (['.ngay-wrap', '.ngay-btn', '.ngay-native', '.ngay-loi'] as $c) {
            $this->assertStringContainsString($c, $css, "app.css thiếu kiểu $c.");
        }
    }
}
