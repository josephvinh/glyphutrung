<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/push.php';

use PHPUnit\Framework\TestCase;

/**
 * Kiểm thử allowlist endpoint Web Push (#99, chống SSRF).
 *
 * Toàn bộ là hàm thuần: không cần CSDL, không cần mạng, không phân giải DNS.
 */
class PushEndpointTest extends TestCase
{
    /** @return array<string,array{0:string}> */
    public static function endpointHopLe(): array
    {
        return [
            'fcm'            => ['https://fcm.googleapis.com/fcm/send/abc:APA91b_x-y'],
            'fcm :443'       => ['https://fcm.googleapis.com:443/fcm/send/abc'],
            'mozilla'        => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAAA'],
            'apple'          => ['https://web.push.apple.com/QGxx'],
            'apple khác'     => ['https://api.development.push.apple.com/3/device/abc'],
            'wns'            => ['https://wns2-par02p.notify.windows.com/w/?token=BQYAAAB%2f'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpointHopLe')]
    public function test_endpoint_hop_le(string $ep): void
    {
        $r = push_kiem_endpoint($ep);
        $this->assertTrue($r['ok'], $ep);
        $this->assertSame(443, $r['port']);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function endpointBiTuChoi(): array
    {
        return [
            // sai cổng
            'IP nội bộ + cổng'        => ["https://127.0.0.1:9444/internal-admin", 'cong'],
            'FCM cổng lạ'             => ["https://fcm.googleapis.com:8443/x", 'cong'],
            'localhost cổng lạ'       => ["https://localhost:9443/push/ok", 'cong'],
            'cổng 0443'               => ["https://fcm.googleapis.com:0443/x", 'cong'],
            // host ngoài danh sách
            'IP thập phân'            => ["https://2130706433/x", 'host'],
            'IP hex'                  => ["https://0x7f.1/x", 'host'],
            'metadata đám mây'        => ["https://169.254.169.254/latest", 'host'],
            'FCM + đuôi lạ'           => ["https://fcm.googleapis.com.evil.com/x", 'host'],
            'tiền tố lạ'              => ["https://evilfcm.googleapis.com/x", 'host'],
            'googleapis khác'         => ["https://android.googleapis.com/gcm/send/x", 'host'],
            'apple giả'               => ["https://evilpush.apple.com/x", 'host'],
            'apple đuôi lạ'           => ["https://web.push.apple.com.attacker.net/x", 'host'],
            'WNS giả'                 => ["https://evilnotify.windows.com/x", 'host'],
            'localhost'               => ["https://localhost/x", 'host'],
            // regex
            'IPv6'                    => ["https://[::1]/x", 'regex'],
            'IPv6 mapped'             => ["https://[::ffff:127.0.0.1]/x", 'regex'],
            'userinfo'                => ["https://fcm.googleapis.com@127.0.0.1/x", 'regex'],
            'userinfo + backslash'    => ["https://fcm.googleapis.com\\@127.0.0.1/x", 'regex'],
            'host chữ hoa'            => ["https://FCM.googleapis.com/x", 'regex'],
            'dấu chấm cuối host'      => ["https://fcm.googleapis.com./x", 'regex'],
            'fragment'                => ["https://fcm.googleapis.com/x#a", 'regex'],
            'CRLF'                    => ["https://fcm.googleapis.com/x\r\nHost: evil", 'regex'],
            'CRLF cuối'               => ["https://fcm.googleapis.com/x\r\n", 'regex'],
            'khoảng trắng'            => ["https://fcm.googleapis.com/x y", 'regex'],
            'http'                    => ["http://fcm.googleapis.com/x", 'regex'],
            'HTTPS chữ hoa'           => ["HTTPS://fcm.googleapis.com/x", 'regex'],
            'thiếu path'              => ["https://fcm.googleapis.com", 'regex'],
            'rỗng'                    => ["", 'regex'],
            // độ dài
            'quá 500 ký tự'           => ["https://fcm.googleapis.com/" . str_repeat('a', 600), 'dai'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('endpointBiTuChoi')]
    public function test_endpoint_bi_tu_choi(string $ep, string $loi): void
    {
        $r = push_kiem_endpoint($ep);
        $this->assertFalse($r['ok'], $ep);
        $this->assertSame($loi, $r['loi'], $ep);
    }

    public function test_host_thu_chi_co_tac_dung_khi_duoc_truyen_vao(): void
    {
        $ep = 'https://localhost:9443/push/ok';
        $this->assertFalse(push_kiem_endpoint($ep)['ok']);
        $this->assertFalse(push_kiem_endpoint($ep, ['localhost:9444'])['ok']);
        $this->assertTrue(push_kiem_endpoint($ep, ['localhost:9443'])['ok']);
        // test_hosts không mở thêm host/cổng khác
        $this->assertFalse(push_kiem_endpoint('https://localhost:9444/x', ['localhost:9443'])['ok']);
        $this->assertFalse(push_kiem_endpoint('https://127.0.0.1:9443/x', ['localhost:9443'])['ok']);
    }

    /** @return array<string,array{0:string,1:bool}> */
    public static function ipCongKhai(): array
    {
        return [
            '127.0.0.1'           => ['127.0.0.1', false],
            '10.x'                => ['10.1.2.3', false],
            '172.16.x'            => ['172.16.5.5', false],
            '192.168.x'           => ['192.168.1.1', false],
            'metadata'            => ['169.254.169.254', false],
            'CGNAT'               => ['100.64.1.1', false],
            '0.0.0.0'             => ['0.0.0.0', false],
            '::1'                 => ['::1', false],
            '::ffff:127.0.0.1'    => ['::ffff:127.0.0.1', false],
            'fc00::1'             => ['fc00::1', false],
            'không phải IP'       => ['localhost', false],
            '8.8.8.8'             => ['8.8.8.8', true],
            'Google FCM'          => ['216.239.36.55', true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ipCongKhai')]
    public function test_ip_cong_khai(string $ip, bool $mong): void
    {
        $this->assertSame($mong, push_ip_cong_khai($ip), $ip);
    }

    public function test_push_host_thu_rong_khi_production(): void
    {
        // Cấu hình mặc định của repo là production => true: dù ai có khai
        // test_hosts thì cũng bị bỏ qua.
        $this->assertNotFalse(app_config('production'));
        $this->assertSame([], push_host_thu());
    }
}
