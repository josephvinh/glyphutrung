<?php
/**
 * Test XẾP CHỒNG POPUP — chặn lỗi "nút ở đáy popup bị thanh điều hướng đè".
 *
 * Gốc lỗi: .app-content mà có z-index thì tạo ngữ cảnh xếp chồng riêng, mọi
 * popup (fixed, z-[200]) nằm trong đó bị nhốt dưới thanh nav (.app-bottomnav,
 * z-index 150, nằm ngoài) -> trên điện thoại nút Lưu / Đổi mật khẩu bị đè.
 *
 * Đây là test tĩnh trên mã nguồn (không cần DB, không cần trình duyệt).
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ModalStackingTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_app_content_khong_co_z_index(): void
    {
        $css = file_get_contents(self::root() . '/public/assets/css/app.css');
        $this->assertNotFalse($css);
        // Bỏ chú thích để lời giải thích trong CSS không bị tính là khai báo.
        $css = preg_replace('!/\*.*?\*/!s', '', $css);

        preg_match_all('/\.app-content\s*\{([^}]*)\}/', $css, $m);
        $this->assertNotEmpty($m[1], 'Không tìm thấy luật .app-content trong app.css.');
        foreach ($m[1] as $body) {
            $this->assertStringNotContainsString(
                'z-index',
                $body,
                '.app-content không được có z-index: nó nhốt popup dưới thanh nav (xem chú thích đầu file test).'
            );
        }
    }

    public function test_moi_popup_dang_bottom_sheet_co_modal_sheet(): void
    {
        // modal-sheet là class chừa safe-area (tai thỏ / thanh Home iPhone) ở chân popup.
        $files = array_merge(
            glob(self::root() . '/views/*.php') ?: [],
            [self::root() . '/public/index.php']
        );
        $thieu = [];
        foreach ($files as $f) {
            $src = file_get_contents($f);
            if ($src === false) continue;
            if (!preg_match_all('/<div\b[^>]*\brounded-t-sheet\b[^>]*>/s', $src, $m)) continue;
            foreach ($m[0] as $tag) {
                if (!preg_match('/\bmodal-sheet\b/', $tag)) {
                    $thieu[] = basename($f) . ': ' . substr(preg_replace('/\s+/', ' ', $tag), 0, 90);
                }
            }
        }
        $this->assertSame([], $thieu, "Popup bottom-sheet thiếu class modal-sheet:\n" . implode("\n", $thieu));
    }
}
