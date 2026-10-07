<?php
/**
 * Test KHUNG TRANG ĐĂNG NHẬP — chặn lỗi "nút Đăng ký bị cắt trên màn hình thấp".
 *
 * Gốc lỗi: trang login dùng chung khung .app-shell (max-height:100dvh + overflow:
 * hidden, hợp với app vì phần nội dung tự cuộn bên trong). Trang login không có
 * vùng cuộn riêng, nên khi màn hình thấp hơn nội dung (điện thoại có thanh công
 * cụ, laptop cửa sổ thấp, cỡ chữ hệ thống lớn) thẻ đăng nhập — overflow:hidden
 * nên được phép co trong khung flex — bị bóp lại và cắt mất nút cuối cùng.
 * Cách sửa: khung login có class .login-shell (cao theo nội dung, không cắt).
 *
 * Test tĩnh trên mã nguồn (không cần DB, không cần trình duyệt).
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class LoginShellTest extends TestCase
{
    private static function read(string $rel): string
    {
        $s = file_get_contents(dirname(__DIR__, 2) . '/' . $rel);
        self::assertNotFalse($s, "Không đọc được $rel");
        return $s;
    }

    public function test_khung_login_co_class_login_shell(): void
    {
        $html = self::read('views/layout_login.php');
        // Thẻ nằm gọn trên MỘT dòng và có đoạn mã PHP chèn giữa (chứa dấu đóng thẻ),
        // nên khớp theo dòng chứ không theo "đến dấu lớn hơn đầu tiên".
        $this->assertSame(1, preg_match('/<div\b[^\n]*x-data="loginScreen"[^\n]*/', $html, $m),
            'Không thấy khung x-data="loginScreen" trong layout_login.php.');
        $this->assertMatchesRegularExpression('/\bapp-shell\b/', $m[0]);
        $this->assertMatchesRegularExpression('/\blogin-shell\b/', $m[0],
            'Khung trang login phải có class login-shell, nếu không màn hình thấp sẽ cắt nút Đăng ký.');
    }

    public function test_login_shell_khong_cat_va_thang_app_shell(): void
    {
        $css = preg_replace('!/\*.*?\*/!s', '', self::read('public/assets/css/app.css'));

        $this->assertSame(1, preg_match('/\.login-shell\s*\{([^}]*)\}/', $css, $m), 'Thiếu luật .login-shell trong app.css.');
        $this->assertMatchesRegularExpression('/max-height:\s*none/', $m[1], '.login-shell phải bỏ max-height của .app-shell.');
        $this->assertMatchesRegularExpression('/overflow:\s*visible/', $m[1], '.login-shell phải bỏ overflow:hidden của .app-shell.');

        // Phải nằm SAU khối @media 640px của .app-shell (cùng độ ưu tiên, luật sau thắng).
        $app = strpos($css, 'min-height: calc(100dvh - 3rem)');
        $this->assertNotFalse($app, 'Không thấy khối .app-shell ở màn hình rộng.');
        $this->assertGreaterThan($app, strpos($css, '.login-shell'),
            '.login-shell phải đặt sau khối @media (min-width: 640px) của .app-shell để thắng nó.');

        // Cấm co các khối con: thẻ overflow:hidden trong khung flex cột sẽ bị bóp lại.
        $this->assertSame(1, preg_match('/\.login-shell\s*>\s*\*\s*\{([^}]*)\}/', $css, $c),
            'Thiếu luật .login-shell > * { flex-shrink: 0 }.');
        $this->assertMatchesRegularExpression('/flex-shrink:\s*0/', $c[1]);
    }
}
