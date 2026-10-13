<?php
// tests/unit/ServerTimezoneTest.php
//
// Phía máy chủ luôn chạy GIỜ VIỆT NAM, không phụ thuộc cấu hình múi giờ PHP của host.
//   - config/db.php (mọi điểm vào đều nạp) đặt Asia/Ho_Chi_Minh;
//   - mọi điểm vào dùng ngày giờ (public/*.php, config/cron_*.php, config/nhac_*.php) nạp db.php/_bootstrap
//     hoặc tự đặt múi giờ, kẻo lại chạy theo cấu hình host.
// Chọn múi giờ lệch xa Việt Nam (Pago_Pago -11, Kiritimati +14): lúc nào cũng có ít nhất một nơi đang là NGÀY KHÁC.

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class ServerTimezoneTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../';

    /** Chạy một đoạn PHP dòng lệnh với date.timezone của host cho trước. */
    private function chay(string $tzHost, string $code): string
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' -d ' . escapeshellarg('date.timezone=' . $tzHost) . ' -r ' . escapeshellarg($code) . ' 2>&1';
        return trim((string) shell_exec($cmd));
    }

    public function test_db_php_dat_gio_viet_nam_bat_ke_cau_hinh_host(): void
    {
        $db = realpath(self::ROOT . 'config/db.php');
        foreach (['UTC', 'Pacific/Pago_Pago', 'Pacific/Kiritimati', 'America/Los_Angeles'] as $tz) {
            $out = $this->chay($tz, 'require ' . var_export($db, true) . '; echo date_default_timezone_get();');
            $this->assertSame('Asia/Ho_Chi_Minh', $out, "Host đặt $tz: sau khi nạp db.php múi giờ PHP phải là Asia/Ho_Chi_Minh.");
        }
    }

    public function test_ngay_hom_nay_theo_gio_viet_nam_o_ca_hai_dau_trai_dat(): void
    {
        $db = realpath(self::ROOT . 'config/db.php');
        $vn = gmdate('Y-m-d', time() + 7 * 3600);
        foreach (['Pacific/Pago_Pago', 'Pacific/Kiritimati'] as $tz) {
            $out = $this->chay($tz, 'require ' . var_export($db, true) . "; echo date('Y-m-d');");
            // Hai lần đọc có thể cách nhau đúng nửa đêm VN: chấp nhận ngày hôm nay hoặc ngày kế.
            $this->assertContains($out, [$vn, gmdate('Y-m-d', time() + 7 * 3600 + 5)], "Host $tz: date() phải ra ngày giờ Việt Nam $vn, nhận được $out.");
        }
    }

    /** Điểm vào có dùng ngày giờ mà không nạp db.php/_bootstrap và không tự đặt múi giờ sẽ chạy theo host. */
    public function test_moi_diem_vao_dung_ngay_gio_deu_co_gio_viet_nam(): void
    {
        $ds = array_merge(glob(self::ROOT . 'public/*.php') ?: [], glob(self::ROOT . 'config/cron_*.php') ?: [], glob(self::ROOT . 'config/nhac_*.php') ?: []);
        $this->assertNotSame([], $ds);
        $vp = [];
        foreach ($ds as $f) {
            $code = file_get_contents($f);
            // Chỉ các hàm PHỤ THUỘC múi giờ; time()/filemtime() là mốc tuyệt đối nên không tính.
            $dung = preg_match('/\b(date|strtotime|mktime|date_create)\s*\(|new\s+DateTime\b/', $code);
            if (!$dung) continue;
            $co = preg_match('/date_default_timezone_set\s*\(/', $code)
               || preg_match('/(require|include)(_once)?[^;]*(db\.php|_bootstrap\.php|_bootstrap_page\.php|_common\.php)/', $code);
            if (!$co) $vp[] = str_replace(self::ROOT, '', $f);
        }
        $this->assertSame([], $vp, "Điểm vào dùng ngày giờ nhưng không nạp db.php/_bootstrap và không đặt múi giờ:\n" . implode("\n", $vp));
    }
}
