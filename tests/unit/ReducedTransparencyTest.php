<?php
/**
 * Test GIẢM ĐỘ TRONG SUỐT — chặn lỗi "nút glass chữ trắng biến mất".
 *
 * Gốc lỗi: trong @media (prefers-reduced-transparency: reduce), .btn-glass nằm chung
 * nhóm với .glass-card/.nav-inner... và nhận nền SÁNG var(--glass-bg-elevated)
 * trong khi chữ nút là TRẮNG (color:#fff) -> chữ trắng trên nền trắng. Trên máy
 * bật "Giảm độ trong suốt" (vd. Samsung) nút Đăng nhập và nút Điểm danh tay
 * chỉ còn ô trắng trống.
 *
 * Đây là test tĩnh trên mã nguồn CSS (không cần DB, không cần trình duyệt).
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ReducedTransparencyTest extends TestCase
{
    /** Các luật (selector => thân) bên trong khối @media (prefers-reduced-transparency: reduce). */
    private static function rulesInReducedTransparency(): array
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/public/assets/css/app.css');
        self::assertNotFalse($css);
        $css = preg_replace('!/\*.*?\*/!s', '', $css);   // bỏ chú thích để lời giải thích không bị tính

        $at = strpos($css, '@media (prefers-reduced-transparency: reduce)');
        self::assertNotFalse($at, 'Không thấy khối @media (prefers-reduced-transparency: reduce) trong app.css.');
        $open = strpos($css, '{', $at);
        // Cắt đúng khối @media bằng cách đếm ngoặc (bên trong có các luật lồng).
        $depth = 0; $end = null;
        for ($i = $open, $n = strlen($css); $i < $n; $i++) {
            if ($css[$i] === '{') $depth++;
            elseif ($css[$i] === '}' && --$depth === 0) { $end = $i; break; }
        }
        self::assertNotNull($end, 'Khối @media không đóng ngoặc.');
        $inner = substr($css, $open + 1, $end - $open - 1);

        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $inner, $m, PREG_SET_ORDER);
        $rules = [];
        foreach ($m as $r) $rules[trim(preg_replace('/\s+/', ' ', $r[1]))] = $r[2];
        return $rules;
    }

    public function test_btn_glass_khong_duoc_nhan_nen_sang_khi_giam_trong_suot(): void
    {
        $thay = false;
        foreach (self::rulesInReducedTransparency() as $selectors => $body) {
            $list = array_map('trim', explode(',', $selectors));
            if (!in_array('.btn-glass', $list, true)) continue;
            $thay = true;
            $this->assertStringNotContainsString(
                'glass-bg-elevated',
                $body,
                '.btn-glass có chữ trắng nên không được dùng nền sáng --glass-bg-elevated khi giảm độ trong suốt.'
            );
            $this->assertStringContainsString(
                'accent-color',
                $body,
                '.btn-glass khi giảm độ trong suốt phải có nền ĐẶC màu thương hiệu (--accent-color).'
            );
        }
        $this->assertTrue($thay, 'Phải có luật riêng cho .btn-glass trong khối giảm độ trong suốt.');
    }

    public function test_cac_the_kinh_van_dung_nen_sang_dac(): void
    {
        // Các thành phần chữ TỐI vẫn dùng nền sáng đặc (hành vi đúng, không đổi).
        $dung = false;
        foreach (self::rulesInReducedTransparency() as $selectors => $body) {
            $list = array_map('trim', explode(',', $selectors));
            if (in_array('.glass-card', $list, true) && in_array('.nav-inner', $list, true)) {
                $dung = true;
                $this->assertStringContainsString('glass-bg-elevated', $body);
                $this->assertStringContainsString('backdrop-filter: none', $body);
            }
        }
        $this->assertTrue($dung);
    }
}
