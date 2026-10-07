<?php
/**
 * Test ĐỘ PHỦ CLASS CSS — chặn lỗi "class dùng trong giao diện nhưng không có kiểu".
 *
 * Vì sao: public/assets/css/tailwind.css là bản BUILD SẴN (Tailwind v3.2.7). Class nào
 * không có mặt lúc build thì không tồn tại, và markup dùng nó mất kiểu mà KHÔNG báo
 * lỗi gì (từng gặp: `max-h-[...]`, `bg-white/15`, `line-clamp-2`, `w-4.5`, `sr-only`,
 * và `oversc-contain` gõ sai tên). Test này liệt kê class kiểu Tailwind/tuỳ biến dùng
 * trong view/trang công khai/chuỗi HTML của JS mà không có định nghĩa ở đâu.
 *
 * PHẠM VI (cố ý hẹp để không báo nhầm):
 *   - Chỉ xét class TĨNH ghi thẳng trong class="..." và các chuỗi trong :class="...".
 *     Class ghép động (vd. 'bg-' + mau + '-100') không kiểm được.
 *   - Chỉ xét tên giống class CSS (ASCII, có dấu gạch hoặc dấu hai chấm) để bỏ qua
 *     từ tiếng Việt và tên màn hình nằm trong điều kiện :class.
 *   - "Có định nghĩa" = có selector .ten trong các file CSS của app, trong khối <style>
 *     của chính view/trang, hoặc trong chuỗi CSS của JS (dạng `.ten {`).
 *
 * Test tĩnh trên mã nguồn: không cần DB, không cần trình duyệt.
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class CssClassCoverageTest extends TestCase
{
    /**
     * Class CỐ Ý không có kiểu riêng (khung/dấu hiệu cấu trúc). Mỗi mục PHẢI có lý do.
     * Thêm vào đây chỉ khi chắc chắn class đó không cần CSS; còn lại hãy định nghĩa nó
     * (app.css) hoặc sửa tên cho đúng.
     */
    private const KHONG_CAN_CSS = [
        'scroll-x'      => 'module_scores.php: dấu hiệu thừa, bảng đã có overflow-x-auto',
        'sidebar-brand' => 'layout_sidebar.php: dấu hiệu cấu trúc, không có CSS/JS nào dùng',
        'header-content' => 'print.php: khung bọc cấu trúc (CSS nhắm vào .header .org, .header h1…)',
        'main-column'   => 'print.php: khung bọc cấu trúc của phiếu liên lạc',
        'side-column'   => 'print.php: khung bọc cấu trúc của phiếu liên lạc',
        'sig-block'     => 'print.php: khung bọc cấu trúc phần chữ ký',
        'sig-space'     => 'print.php: khung bọc cấu trúc phần chữ ký',
    ];

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function read(string $path): string
    {
        $s = file_get_contents($path);
        self::assertNotFalse($s, "Không đọc được $path");
        return $s;
    }

    /** Mọi tên class có selector trong một đoạn CSS (đã bỏ dấu gạch chéo ngược của escape). */
    private static function cssSelectors(string $css): array
    {
        preg_match_all('~\.((?:[A-Za-z0-9_-]|\\\\.)+)~', $css, $m);
        $out = [];
        foreach ($m[1] as $name) {
            $out[preg_replace('~\\\\(.)~', '$1', $name)] = true;
        }
        return $out;
    }

    private static function looksLikeClass(string $t): bool
    {
        if ($t === '' || preg_match('~[{}()\'"$?=<>;,`]|^\d~', $t)) return false;
        if (!preg_match('~^[a-z0-9:/\[\].%_-]+$~', $t)) return false;
        return strpos($t, '-') !== false || strpos($t, ':') !== false;
    }

    /** @return array{defined: array<string,true>, used: array<string,string[]>} */
    private static function scan(): array
    {
        $root = self::root();
        $defined = [];
        foreach (['tailwind', 'font', 'app', 'skeleton', 'analytics', 'toast', 'brand'] as $f) {
            $defined += self::cssSelectors(self::read("$root/public/assets/css/$f.css"));
        }

        $used = [];
        $add = function (array $tokens, string $file) use (&$used) {
            foreach ($tokens as $t) {
                $t = trim($t);
                if (self::looksLikeClass($t)) $used[$t][$file] = true;
            }
        };

        $pages = array_merge(glob("$root/views/*.php") ?: [], glob("$root/public/*.php") ?: []);
        foreach ($pages as $f) {
            $raw = self::read($f);
            // Khối <style> của chính trang cũng là nơi định nghĩa class.
            if (preg_match_all('~<style[^>]*>(.*?)</style>~s', $raw, $sm)) {
                foreach ($sm[1] as $css) $defined += self::cssSelectors($css);
            }
            $s = preg_replace('~<\?(?:php|=).*?\?>~s', '', $raw);
            if (preg_match_all('~(?<![:@\w-])class="([^"]*)"~', $s, $cm)) {
                foreach ($cm[1] as $v) $add(preg_split('~\s+~', $v), basename($f));
            }
            if (preg_match_all('~:class="([^"]*)"~', $s, $am)) {
                foreach ($am[1] as $expr) {
                    if (preg_match_all("~'([^']*)'~", $expr, $qm)) {
                        foreach ($qm[1] as $v) $add(preg_split('~\s+~', $v), basename($f));
                    }
                }
            }
        }

        // JS: class="..." nằm trong chuỗi HTML; CSS in thẻ/phiếu viết ngay trong chuỗi JS.
        foreach (glob("$root/public/assets/js/modules/*.js") ?: [] as $f) {
            $raw = self::read($f);
            // Selector trong chuỗi CSS của JS, kể cả dạng con (`.card-body p {`). Chỉ xét tên có dấu
            // gạch ngang (xem looksLikeClass) nên không thể trùng với tên thuộc tính/biến JS.
            if (preg_match_all('~\.([A-Za-z_][\w-]*)(?=[^{};]*\{)~', $raw, $dm)) {
                foreach ($dm[1] as $n) $defined[$n] = true;
            }
            if (preg_match_all('~class=\\\\?["\']([^"\'\\\\]*)\\\\?["\']~', $raw, $cm)) {
                foreach ($cm[1] as $v) $add(preg_split('~\s+~', $v), basename($f));
            }
        }

        return ['defined' => $defined, 'used' => array_map('array_keys', $used)];
    }

    public function test_moi_class_dung_trong_giao_dien_deu_co_dinh_nghia(): void
    {
        $r = self::scan();
        $thieu = [];
        foreach ($r['used'] as $cls => $files) {
            if (isset($r['defined'][$cls]) || isset(self::KHONG_CAN_CSS[$cls])) continue;
            $thieu[] = sprintf('%s  (dùng ở: %s)', $cls, implode(', ', $files));
        }
        sort($thieu);
        $this->assertSame([], $thieu,
            "Class dùng trong giao diện nhưng KHÔNG có định nghĩa CSS (markup sẽ mất kiểu mà không báo lỗi).\n"
            . "Định nghĩa nó trong public/assets/css/app.css, sửa lại tên cho đúng, hoặc (chỉ khi là dấu hiệu cấu trúc)\n"
            . "thêm vào KHONG_CAN_CSS kèm lý do:\n  " . implode("\n  ", $thieu));
    }

    public function test_danh_sach_ngoai_le_khong_thua(): void
    {
        // Mỗi ngoại lệ phải còn được dùng VÀ vẫn chưa có CSS; nếu không thì xoá khỏi danh sách.
        $r = self::scan();
        $thua = [];
        foreach (self::KHONG_CAN_CSS as $cls => $lyDo) {
            if (!isset($r['used'][$cls])) $thua[] = "$cls: không còn được dùng trong giao diện";
            elseif (isset($r['defined'][$cls])) $thua[] = "$cls: đã có định nghĩa CSS, không cần ngoại lệ";
            $this->assertNotSame('', trim($lyDo), "Ngoại lệ $cls phải có lý do.");
        }
        $this->assertSame([], $thua, "Danh sách KHONG_CAN_CSS có mục thừa:\n  " . implode("\n  ", $thua));
    }
}
