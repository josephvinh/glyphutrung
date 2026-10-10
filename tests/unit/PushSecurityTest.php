<?php
// tests/unit/PushSecurityTest.php
//
// KIỂM THỬ AN TOÀN WEB PUSH (P5: #99 SSRF, #100 gửi sau phản hồi, #107 token/chiếm endpoint)
//
// PushEndpointTest.php chỉ kiểm hàm thuần. File NÀY chạy mã thật:
//   - HTTP thật (`php -S`, như QrScanApiTest) cho api/push.php: allowlist host ở API,
//     409 endpoint_owned, token/endpoint ở `pending` theo trạng thái thành viên, ép kiểu mảng.
//   - Tiến trình `php` con (CLI) cho push_bao + giao dịch: rollback, và "chưa commit thì chưa gửi".
//   - Hàm thuần push_giai_ip / push_ip_cong_khai (chặn IP riêng, loopback, link-local, IPv4-mapped IPv6).
//
// KHÔNG skip: thiếu môi trường là lỗi (CI chặn test bị skip, #82).
//
// Cấu hình VAPID: máy CI không có config/config.local.php nên push_bao() thoát sớm vì thiếu khoá.
// setUpBeforeClass() ghi tạm một config.local.php (đè lên file sẵn có nếu máy dev có — file cũ được
// giữ làm .p5bak và nạp lại bên dưới) rồi trả về nguyên trạng ở tearDownAfterClass(). KHÔNG bật
// test_hosts và KHÔNG đặt production => false: mọi chốt SSRF chạy đúng như trên máy chủ thật.
// Không có test nào mở kết nối ra ngoài: endpoint dùng để "xả hàng" là endpoint độc hại (sẽ bị xoá
// lười, không kết nối), nhờ đó vừa thấy được xả hàng có chạy hay không, vừa không gửi gì ra Internet.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/push.php';

use PHPUnit\Framework\TestCase;

class PushSecurityTest extends TestCase
{
    private const MAT_KHAU = 'Mk-Test-1';
    /** Endpoint "độc hại cũ": hợp lệ về dạng nhưng host không được phép → xả hàng sẽ xoá dòng, không kết nối */
    private const EP_BAN = 'https://127.0.0.1:9/p5sec-ban/';

    private static $proc = null;
    private static string $base = '';
    private static string $logFile = '';
    private static string $cfgFile = '';
    private static string $cfgBak = '';
    private static bool $cfgDaGhi = false;
    private static bool $coCfgCu = false;
    private static array $khoa = [];
    /** Một thành viên + phiên đăng nhập dùng chung cho các ca không cần tách người (đăng nhập bcrypt khá chậm) */
    private static ?array $tvChung = null;
    private static ?array $khachChung = null;

    /** @var int[] */
    private array $memberIds = [];
    /** @var string[] */
    private array $cookieJars = [];
    private int $seq = 0;

    // ---------------------------------------------------------------
    //  Môi trường
    // ---------------------------------------------------------------

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init')) self::fail('Cần ext-curl để gọi HTTP.');

        // 1) config.local.php tạm: khoá VAPID thử + hạn chuyển tiếp còn dài (không phụ thuộc ngày chạy test)
        self::$cfgFile = dirname(__DIR__, 2) . '/config/config.local.php';
        self::$cfgBak  = self::$cfgFile . '.p5bak';
        self::$khoa = push_tao_khoa();
        $coCu = is_file(self::$cfgFile);
        if ($coCu && !rename(self::$cfgFile, self::$cfgBak)) self::fail('Không giữ được config.local.php sẵn có.');
        self::$coCfgCu = $coCu;
        self::ghiCauHinh(date('Y-m-d', strtotime('+3 days')));
        self::$cfgDaGhi = true;
        register_shutdown_function([self::class, 'traCauHinh']);   // phòng khi tiến trình chết giữa chừng

        // 2) máy chủ PHP tích hợp (đọc cấu hình ở trên khi khởi động)
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$sock) self::fail('Không mở được cổng cục bộ.');
        $port = (int) parse_url('tcp://' . stream_socket_get_name($sock, false), PHP_URL_PORT);
        fclose($sock);

        $root = dirname(__DIR__, 2) . '/public';
        self::$logFile = tempnam(sys_get_temp_dir(), 'p5sec_log');
        $env = array_merge(getenv(), $_ENV);
        self::$proc = proc_open(
            // opcache tắt: ghiCauHinh() đổi config.local.php giữa các request phải có hiệu lực ngay
            [PHP_BINARY, '-d', 'opcache.enable=0', '-S', "127.0.0.1:$port", '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', self::$logFile, 'a']],
            $pipes, $root, $env
        );
        self::$base = "http://127.0.0.1:$port";
        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen('127.0.0.1', $port, $e, $s, 0.1);
            if ($c) { fclose($c); return; }
            usleep(100000);
        }
        self::traCauHinh();
        self::fail('Máy chủ PHP tích hợp không khởi động được.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tvChung) {
            db_run('DELETE FROM push_outbox WHERE member_id = ?', [self::$tvChung['id']]);
            db_run('DELETE FROM push_subscriptions WHERE member_id = ?', [self::$tvChung['id']]);
            db_run('DELETE FROM activity_logs WHERE actor_id = ?', [self::$tvChung['id']]);
            db_run('DELETE FROM members WHERE id = ?', [self::$tvChung['id']]);
        }
        self::$tvChung = self::$khachChung = null;
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
        self::$proc = null;
        if (self::$logFile !== '' && is_file(self::$logFile)) @unlink(self::$logFile);
        self::traCauHinh();
    }

    /**
     * Ghi config.local.php tạm: khoá VAPID thử + hạn chuyển tiếp. `php -S` nạp lại cấu hình ở MỖI request
     * nên đổi hạn giữa chừng có hiệu lực ngay (dùng để thử hết hạn chuyển tiếp).
     */
    private static function ghiCauHinh(string $legacyUntil): void
    {
        $them = var_export([
            'push' => ['public' => self::$khoa['public'], 'private' => self::$khoa['private'],
                       'subject' => 'mailto:test@example.invalid', 'legacy_until' => $legacyUntil],
        ], true);
        $noiDung = "<?php\n"
            . (self::$coCfgCu ? "\$goc = require " . var_export(self::$cfgBak, true) . ";\n" : "\$goc = [];\n")
            . "return array_replace_recursive(is_array(\$goc) ? \$goc : [], $them);\n";
        if (file_put_contents(self::$cfgFile, $noiDung) === false) self::fail('Không ghi được config.local.php tạm.');
        if (function_exists('opcache_invalidate')) @opcache_invalidate(self::$cfgFile, true);
    }

    /** Trả config.local.php về nguyên trạng (gọi được nhiều lần) */
    public static function traCauHinh(): void
    {
        if (!self::$cfgDaGhi) return;
        self::$cfgDaGhi = false;
        @unlink(self::$cfgFile);
        if (is_file(self::$cfgBak)) @rename(self::$cfgBak, self::$cfgFile);
    }

    protected function tearDown(): void
    {
        if (self::$tvChung) {   // phiên dùng chung: dọn dòng của ca vừa chạy để ca đỏ không làm đỏ lây ca sau
            db_run('DELETE FROM push_outbox WHERE member_id = ?', [self::$tvChung['id']]);
            db_run('DELETE FROM push_subscriptions WHERE member_id = ?', [self::$tvChung['id']]);
        }
        foreach ($this->memberIds as $id) {
            db_run('DELETE FROM push_outbox WHERE member_id = ?', [$id]);
            db_run('DELETE FROM push_subscriptions WHERE member_id = ?', [$id]);
            db_run('DELETE FROM activity_logs WHERE actor_id = ?', [$id]);
            db_run('DELETE FROM members WHERE id = ?', [$id]);
        }
        db_run("DELETE FROM push_subscriptions WHERE endpoint LIKE '%p5sec-%'");
        foreach ($this->cookieJars as $j) if (is_file($j)) @unlink($j);
        $this->memberIds = $this->cookieJars = [];
    }

    // ---------------------------------------------------------------
    //  Dữ liệu + HTTP
    // ---------------------------------------------------------------

    /** @return array{id:int, phone:string} */
    private function taoThanhVien(string $status = 'đang phục vụ'): array
    {
        $phone = '09' . random_int(10000000, 99999999);
        $id = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw, status)
             VALUES (?, 'Push Sec Test', ?, ?, 'glv', 0, ?)",
            ['PSEC' . random_int(100000, 999999), $phone, password_hash(self::MAT_KHAU, PASSWORD_DEFAULT), $status]
        );
        $this->memberIds[] = $id;
        return ['id' => $id, 'phone' => $phone];
    }

    private function epMoi(string $host = 'fcm.googleapis.com'): string
    {
        return "https://$host/fcm/send/p5sec-" . bin2hex(random_bytes(8)) . ':' . (++$this->seq);
    }

    /** Một "trình duyệt": cookie jar riêng + CSRF của phiên đó */
    private function khach(bool $donDep = true): array
    {
        $jar = tempnam(sys_get_temp_dir(), 'p5sec_jar');
        if ($donDep) $this->cookieJars[] = $jar;
        else register_shutdown_function(static fn() => @unlink($jar));
        return ['jar' => $jar, 'csrf' => ''];
    }

    private function http(array &$k, string $method, string $path, $json = null, bool $csrf = true): array
    {
        $ch = curl_init(self::$base . $path);
        $h  = ['Content-Type: application/json'];
        if ($csrf && $k['csrf'] !== '') $h[] = 'X-CSRF-TOKEN: ' . $k['csrf'];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $h,
            CURLOPT_TIMEOUT => 20, CURLOPT_COOKIEJAR => $k['jar'], CURLOPT_COOKIEFILE => $k['jar'],
        ]);
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($json) ? $json : json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    /** @return array{0:array,1:array} [thành viên, khách] dùng chung */
    private function phienChung(): array
    {
        if (self::$tvChung === null) {
            $phone = '09' . random_int(10000000, 99999999);
            $id = db_insert(
                "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
                 VALUES (?, 'Push Sec Test', ?, ?, 'glv', 0)",
                ['PSEC' . random_int(100000, 999999), $phone, password_hash(self::MAT_KHAU, PASSWORD_DEFAULT)]);
            self::$tvChung = ['id' => $id, 'phone' => $phone];
            self::$khachChung = $this->dangNhap(self::$tvChung, false);
        }
        return [self::$tvChung, self::$khachChung];
    }

    private function dangNhap(array $tv, bool $donDep = true): array
    {
        $k = $this->khach($donDep);
        $r = $this->http($k, 'POST', '/api/auth.php?action=login',
            ['phone' => $tv['phone'], 'password' => self::MAT_KHAU], false);
        $this->assertSame(200, $r['code'], 'Đăng nhập phải thành công: ' . $r['raw']);
        $page = $this->http($k, 'GET', '/index.php', null, false);
        $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m), 'Không lấy được csrfToken');
        $k['csrf'] = $m[1];
        return $k;
    }

    /** Gọi api/push.php không phiên, không CSRF — đúng như service worker */
    private function an(string $action, $json): array
    {
        $k = $this->khach();
        return $this->http($k, 'POST', '/api/push.php?action=' . $action, $json, false);
    }

    private function dongDangKy(string $ep): ?array
    {
        return db_one('SELECT * FROM push_subscriptions WHERE endpoint = ?', [$ep]);
    }

    /** Chèn thẳng một máy đã có token; trả token thô */
    private function chenMayCoToken(int $memberId, ?string $ep = null): array
    {
        $ep    = $ep ?? $this->epMoi();
        $token = push_sinh_token();
        db_run('INSERT INTO push_subscriptions (member_id, endpoint, ua, created_at, token_hash) VALUES (?,?,?,NOW(),?)',
               [$memberId, $ep, 'phpunit', push_bam_token($token)]);
        return ['ep' => $ep, 'token' => $token];
    }

    /** Chèn thẳng một máy kiểu cũ (chưa có token) */
    private function chenMayCu(int $memberId): string
    {
        $ep = $this->epMoi();
        db_run('INSERT INTO push_subscriptions (member_id, endpoint, ua, created_at, token_hash) VALUES (?,?,?,NOW(),NULL)',
               [$memberId, $ep, 'phpunit']);
        return $ep;
    }

    private function chenNoiDung(int $memberId, string $title = 'Tiêu đề thử'): int
    {
        return db_insert("INSERT INTO push_outbox (member_id, title, body, url, tag, created_at)
                          VALUES (?, ?, 'Nội dung thử', '/', 'p5sec', NOW())", [$memberId, $title]);
    }

    private function conChuaLay(int $memberId): int
    {
        return (int) db_one('SELECT COUNT(*) c FROM push_outbox WHERE member_id = ? AND taken_at IS NULL', [$memberId])['c'];
    }

    // ---------------------------------------------------------------
    //  (a) push_giai_ip / push_ip_cong_khai: chặn IP nội bộ
    // ---------------------------------------------------------------

    public function test_giai_ip_localhost_la_null_khi_khong_phai_host_thu(): void
    {
        $cache = [];
        $this->assertNull(push_giai_ip('localhost', false, $cache));
        $this->assertArrayHasKey('localhost', $cache);   // kết quả (null) được cache
    }

    /** @return array<string,array{0:string}> */
    public static function ipNoiBo(): array
    {
        return [
            'loopback'              => ['127.0.0.1'],
            'loopback khác'         => ['127.1.2.3'],
            '10/8'                  => ['10.0.0.1'],
            '172.16/12'             => ['172.16.5.5'],
            '172.31'                => ['172.31.255.254'],
            '192.168/16'            => ['192.168.1.1'],
            'link-local'            => ['169.254.1.1'],
            'metadata đám mây'      => ['169.254.169.254'],
            '0.0.0.0'               => ['0.0.0.0'],
            'CGNAT'                 => ['100.64.0.1'],
            'CGNAT cuối'            => ['100.127.255.255'],
            'broadcast'             => ['255.255.255.255'],
            'IPv6 loopback'         => ['::1'],
            'IPv6 chưa xác định'    => ['::'],
            'IPv6 ULA'              => ['fd00::1'],
            'IPv6 link-local'       => ['fe80::1'],
            'IPv4-mapped loopback'  => ['::ffff:127.0.0.1'],
            'IPv4-mapped metadata'  => ['::ffff:169.254.169.254'],
            'IPv4-mapped riêng'     => ['::ffff:10.0.0.1'],
            'không phải IP'         => ['not-an-ip'],
            'rỗng'                  => [''],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ipNoiBo')]
    public function test_ip_noi_bo_khong_phai_cong_khai(string $ip): void
    {
        $this->assertFalse(push_ip_cong_khai($ip), $ip);
        // Cả đường phân giải: IP nội bộ dạng chữ số tự nó là "kết quả phân giải" và phải bị loại
        $cache = [];
        $this->assertNull(push_giai_ip($ip, false, $cache), $ip);
    }

    public function test_ip_cong_khai_duoc_chap_nhan(): void
    {
        foreach (['8.8.8.8', '1.1.1.1', '216.239.38.55', '2001:4860:4860::8888'] as $ip) {
            $this->assertTrue(push_ip_cong_khai($ip), $ip);
        }
        $cache = [];
        $this->assertSame('8.8.8.8', push_giai_ip('8.8.8.8', false, $cache));   // IP chữ số: không cần DNS
    }

    public function test_giai_ip_host_thu_khong_loc(): void
    {
        // Chỉ máy thử (test_hosts) mới được bỏ lọc: mock chạy ở 127.0.0.1
        $cache = [];
        $this->assertSame('127.0.0.1', push_giai_ip('127.0.0.1', true, $cache));
    }

    public function test_giai_ip_cache_theo_host(): void
    {
        $cache = ['x.example' => '8.8.4.4'];
        $this->assertSame('8.8.4.4', push_giai_ip('x.example', false, $cache));   // lấy từ cache, không phân giải
    }

    // ---------------------------------------------------------------
    //  (d) allowlist host — qua API subscribe (hàm thuần đã có ở PushEndpointTest)
    // ---------------------------------------------------------------

    /** @return array<string,array{0:string}> */
    public static function epTanCong(): array
    {
        return [
            'FCM + đuôi lạ'        => ['https://fcm.googleapis.com.evil.com/x'],
            'userinfo @127.0.0.1'  => ['https://fcm.googleapis.com@127.0.0.1/x'],
            'userinfo + cổng'      => ['https://fcm.googleapis.com:443@127.0.0.1:9444/x'],
            'FCM cổng 8443'        => ['https://fcm.googleapis.com:8443/x'],
            'FCM cổng 9444'        => ['https://fcm.googleapis.com:9444/x'],
            'loopback'             => ['https://127.0.0.1/x'],
            'loopback + cổng'      => ['https://127.0.0.1:9444/x'],
            'localhost'            => ['https://localhost/x'],
            'localhost cổng thử'   => ['https://localhost:9443/x'],
            'IPv6 loopback'        => ['https://[::1]/x'],
            'IPv6 mapped'          => ['https://[::ffff:127.0.0.1]/x'],
            'IP thập phân'         => ['https://2130706433/x'],
            'IP hex'               => ['https://0x7f.1/x'],
            'metadata'             => ['https://169.254.169.254/latest'],
            'tiền tố lạ'           => ['https://evilfcm.googleapis.com/x'],
            'apple giả'            => ['https://evilpush.apple.com/x'],
            'apple đuôi lạ'        => ['https://web.push.apple.com.evil.com/x'],
            'WNS giả'              => ['https://xnotify.windows.com/x'],
            'http thường'          => ['http://fcm.googleapis.com/x'],
            'host chữ hoa'         => ['https://FCM.GOOGLEAPIS.COM/x'],
            'CRLF'                 => ["https://fcm.googleapis.com/x\r\nX: y"],
            'khoảng trắng đầu'     => [' https://fcm.googleapis.com/x'],
            'khoảng trắng cuối'    => ['https://fcm.googleapis.com/x '],
            'fragment'             => ['https://evil.com/#fcm.googleapis.com'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('epTanCong')]
    public function test_subscribe_tu_choi_endpoint_tan_cong(string $ep): void
    {
        [$tv, $k] = $this->phienChung();
        $r  = $this->http($k, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $this->assertSame(422, $r['code'], $r['raw']);
        $this->assertFalse($r['json']['ok']);
        $this->assertArrayNotHasKey('token', $r['json']);
        $this->assertSame(0, (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?', [$tv['id']])['c'],
            'Endpoint bị từ chối không được để lại dòng nào');
    }

    /** @return array<string,array{0:string}> */
    public static function epHopLe(): array
    {
        return [
            'FCM'      => ['https://fcm.googleapis.com/fcm/send/p5sec-a:APA91b'],
            'FCM :443' => ['https://fcm.googleapis.com:443/fcm/send/p5sec-b'],
            'Mozilla'  => ['https://updates.push.services.mozilla.com/wpush/v2/p5sec-gAAA'],
            'Apple'    => ['https://web.push.apple.com/p5sec-QGxx'],
            'WNS'      => ['https://wns2-par02p.notify.windows.com/w/?token=p5sec-BQYAAAB%2f'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('epHopLe')]
    public function test_subscribe_nhan_endpoint_dich_vu_that(string $ep): void
    {
        [$tv, $k] = $this->phienChung();
        $r  = $this->http($k, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertTrue($r['json']['ok']);
        $this->assertTrue(push_token_dung_dinh_dang($r['json']['token']));
        $dong = $this->dongDangKy($ep);
        $this->assertNotNull($dong);
        $this->assertSame($tv['id'], (int) $dong['member_id']);
        $this->assertSame(push_bam_token($r['json']['token']), $dong['token_hash'], 'DB chỉ lưu băm của token');
    }

    public function test_subscribe_endpoint_khong_phai_chuoi_bi_tu_choi_khong_canh_bao(): void
    {
        $tv = $this->taoThanhVien();
        $k  = $this->dangNhap($tv);
        $dau = (int) @filesize(self::$logFile);
        foreach ([['x'], ['a' => 'b'], 123, true] as $kieu) {
            $r = $this->http($k, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $kieu]);
            $this->assertSame(422, $r['code'], json_encode($kieu) . ' ' . $r['raw']);
        }
        $r = $this->http($k, 'POST', '/api/push.php?action=subscribe', ['endpoint' => null]);
        $this->assertSame(400, $r['code']);
        $r = $this->http($k, 'POST', '/api/push.php?action=unsubscribe', ['endpoint' => ['x']]);
        $this->assertSame(200, $r['code']);
        $r = $this->http($k, 'GET', '/api/push.php?action=status&endpoint[]=x');
        $this->assertSame(200, $r['code']);
        $this->assertSame(0, (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?', [$tv['id']])['c']);
        $this->assertStringNotContainsString('Array to string', $this->logMoi($dau));
    }

    // ---------------------------------------------------------------
    //  (c) 409 endpoint_owned (#107)
    // ---------------------------------------------------------------

    public function test_subscribe_endpoint_cua_nguoi_khac_la_409_va_khong_doi_chu(): void
    {
        $a  = $this->taoThanhVien();
        $b  = $this->taoThanhVien();
        $ka = $this->dangNhap($a);
        $kb = $this->dangNhap($b);
        $ep = $this->epMoi();

        $ra = $this->http($ka, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $this->assertSame(200, $ra['code'], $ra['raw']);
        $truoc = $this->dongDangKy($ep);
        $this->assertSame($a['id'], (int) $truoc['member_id']);

        $rb = $this->http($kb, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $this->assertSame(409, $rb['code'], $rb['raw']);
        $this->assertFalse($rb['json']['ok']);
        $this->assertSame('endpoint_owned', $rb['json']['code']);
        $this->assertArrayNotHasKey('token', $rb['json'], 'Không được lộ/cấp token cho người chiếm');

        $sau = $this->dongDangKy($ep);
        $this->assertSame($a['id'], (int) $sau['member_id'], 'Chủ máy không được đổi');
        $this->assertSame($truoc['token_hash'], $sau['token_hash'], 'token_hash không được đổi');
        $this->assertSame(1, (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE endpoint = ?', [$ep])['c']);
        $this->assertSame(0, (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?', [$b['id']])['c']);

        // B cũng không huỷ được máy của A
        $this->http($kb, 'POST', '/api/push.php?action=unsubscribe', ['endpoint' => $ep]);
        $this->assertNotNull($this->dongDangKy($ep));

        // B không đọc được nội dung của A bằng token của A thì cũng không có: token chỉ A biết
        $this->chenNoiDung($a['id'], 'riêng của A');
        $cua = $this->an('pending', ['token' => $rb['json']['token'] ?? str_repeat('a', 43)]);
        $this->assertSame(401, $cua['code']);
        $this->assertSame(1, $this->conChuaLay($a['id']));
    }

    public function test_cung_nguoi_dang_ky_lai_xoay_token(): void
    {
        $a  = $this->taoThanhVien();
        $ka = $this->dangNhap($a);
        $ep = $this->epMoi();

        $r1 = $this->http($ka, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $r2 = $this->http($ka, 'POST', '/api/push.php?action=subscribe', ['endpoint' => $ep]);
        $this->assertSame(200, $r1['code']);
        $this->assertSame(200, $r2['code']);
        $this->assertNotSame($r1['json']['token'], $r2['json']['token']);
        $this->assertSame(1, (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE endpoint = ?', [$ep])['c']);

        $this->chenNoiDung($a['id']);
        $cu = $this->an('pending', ['token' => $r1['json']['token']]);
        $this->assertSame(401, $cu['code'], 'Token đã xoay phải hết hiệu lực');
        $this->assertSame(1, $this->conChuaLay($a['id']));
        $moi = $this->an('pending', ['token' => $r2['json']['token']]);
        $this->assertSame(200, $moi['code']);
        $this->assertSame('Tiêu đề thử', $moi['json']['item']['title']);
    }

    // ---------------------------------------------------------------
    //  (e) pending: lọc theo trạng thái thành viên + token + hạn chuyển tiếp
    // ---------------------------------------------------------------

    /** @return array<string,array{0:string,1:bool}> trạng thái => có nhận nội dung không */
    public static function trangThai(): array
    {
        return [
            'đang phục vụ' => ['đang phục vụ', true],
            'tạm nghỉ'     => ['tạm nghỉ', true],
            'chờ duyệt'    => ['chờ duyệt', false],
            'đã nghỉ'      => ['đã nghỉ', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('trangThai')]
    public function test_pending_bang_token_theo_trang_thai(string $status, bool $nhan): void
    {
        $tv  = $this->taoThanhVien($status);
        $may = $this->chenMayCoToken($tv['id']);
        $this->chenNoiDung($tv['id']);

        $r = $this->an('pending', ['token' => $may['token']]);
        if ($nhan) {
            $this->assertSame(200, $r['code'], $status . ' ' . $r['raw']);
            $this->assertSame('Tiêu đề thử', $r['json']['item']['title']);
            $this->assertSame(0, $this->conChuaLay($tv['id']));
        } else {
            $this->assertSame(401, $r['code'], $status . ' ' . $r['raw']);
            $this->assertArrayNotHasKey('item', $r['json'] ?? []);
            $this->assertStringNotContainsString('Tiêu đề thử', $r['raw']);
            $this->assertSame(1, $this->conChuaLay($tv['id']), 'Không được đánh dấu đã lấy');
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('trangThai')]
    public function test_pending_bang_endpoint_cu_theo_trang_thai(string $status, bool $nhan): void
    {
        $tv = $this->taoThanhVien($status);
        $ep = $this->chenMayCu($tv['id']);
        $this->chenNoiDung($tv['id']);

        $r = $this->an('pending', ['endpoint' => $ep]);
        if ($nhan) {
            $this->assertSame(200, $r['code'], $status . ' ' . $r['raw']);
            $this->assertSame('Tiêu đề thử', $r['json']['item']['title']);
        } else {
            $this->assertSame(401, $r['code'], $status . ' ' . $r['raw']);
            $this->assertStringNotContainsString('Tiêu đề thử', $r['raw']);
            $this->assertSame(1, $this->conChuaLay($tv['id']));
        }
    }

    public function test_pending_chua_duyet_va_da_nghi_khong_phan_biet_duoc(): void
    {
        $cd = $this->taoThanhVien('chờ duyệt');
        $dn = $this->taoThanhVien('đã nghỉ');
        $t1 = $this->chenMayCoToken($cd['id']);
        $t2 = $this->chenMayCoToken($dn['id']);
        $this->chenNoiDung($cd['id']);
        $this->chenNoiDung($dn['id']);
        $r1 = $this->an('pending', ['token' => $t1['token']]);
        $r2 = $this->an('pending', ['token' => $t2['token']]);
        $r3 = $this->an('pending', ['token' => push_sinh_token()]);     // token không tồn tại
        $this->assertSame([401, 401, 401], [$r1['code'], $r2['code'], $r3['code']]);
        // Security: compare ok + error (ignore meta with random requestId/timestamp)
        $this->assertSame($r3['json']['ok'], $r1['json']['ok']);
        $this->assertSame($r3['json']['error'], $r1['json']['error'], 'Không lộ khác biệt giữa chờ duyệt và token sai');
        $this->assertSame($r3['json']['error'], $r2['json']['error']);
    }

    public function test_pending_token_sai_dinh_dang_va_dong_da_co_token_khong_nhan_endpoint(): void
    {
        $tv  = $this->taoThanhVien();
        $may = $this->chenMayCoToken($tv['id']);
        $this->chenNoiDung($tv['id']);
        foreach (['', 'abc', strtoupper($may['token']), "' OR 1=1 -- aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"] as $t) {
            $r = $this->an('pending', ['token' => $t]);
            $this->assertSame(401, $r['code'], $t);
        }
        // Dòng đã có token: biết endpoint không còn là chìa khoá (#107)
        $r = $this->an('pending', ['endpoint' => $may['ep']]);
        $this->assertSame(401, $r['code']);
        $this->assertSame(1, $this->conChuaLay($tv['id']));
    }

    public function test_pending_token_hoac_endpoint_la_mang_khong_canh_bao_va_van_401(): void
    {
        $tv  = $this->taoThanhVien();
        $may = $this->chenMayCoToken($tv['id']);
        $this->chenNoiDung($tv['id']);
        $dau = (int) @filesize(self::$logFile);
        foreach ([['token' => [$may['token']]], ['token' => ['a' => 1]], ['endpoint' => [$may['ep']]],
                  ['token' => [], 'endpoint' => []], ['token' => 5, 'endpoint' => true]] as $in) {
            $r = $this->an('pending', $in);
            $this->assertSame(401, $r['code'], json_encode($in) . ' ' . $r['raw']);
        }
        $this->assertSame(1, $this->conChuaLay($tv['id']));
        $this->assertStringNotContainsString('Array to string', $this->logMoi($dau));
    }

    public function test_pending_endpoint_cu_chi_nhan_trong_han_chuyen_tiep(): void
    {
        $tv = $this->taoThanhVien();
        $ep = $this->chenMayCu($tv['id']);
        $this->chenNoiDung($tv['id']);
        try {
            // Hết hạn từ hôm qua: endpoint không còn là chìa khoá, dù dòng còn token_hash NULL
            self::ghiCauHinh(date('Y-m-d', strtotime('-1 day')));
            $r = $this->an('pending', ['endpoint' => $ep]);
            $this->assertSame(401, $r['code'], $r['raw']);
            $this->assertSame(1, $this->conChuaLay($tv['id']));

            // Hôm nay vẫn là ngày cuối còn nhận (hạn tính cả ngày)
            self::ghiCauHinh(date('Y-m-d'));
            $r = $this->an('pending', ['endpoint' => $ep]);
            $this->assertSame(200, $r['code'], $r['raw']);
        } finally {
            self::ghiCauHinh(date('Y-m-d', strtotime('+3 days')));
        }
    }

    // ---------------------------------------------------------------
    //  (b) push_bao + giao dịch (PUSH-38) — tiến trình CLI riêng
    // ---------------------------------------------------------------

    /**
     * Chạy mã PHP trong một tiến trình con có nạp nền của ứng dụng. Mã in một dòng JSON.
     * @return array<string,mixed>
     */
    private function chayCli(string $code, array $args): array
    {
        $root = dirname(__DIR__, 2);
        $pre  = '$ROOT = ' . var_export($root, true) . ';'
              . 'require $ROOT . "/public/api/_bootstrap.php"; require $ROOT . "/config/push.php";'
              . '$ARGS = json_decode(getenv("P5_ARGS"), true);'
              . 'function p5_out(array $a): void { fwrite(STDOUT, "\nP5JSON" . json_encode($a) . "\n"); }';
        $env = array_merge(getenv(), $_ENV, ['P5_ARGS' => json_encode($args)]);
        $p = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $pre . $code],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $env);
        $this->assertIsResource($p, 'Không chạy được tiến trình php con');
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $ma = proc_close($p);
        $this->assertSame(1, preg_match('/P5JSON(\{.*\})\s*\z/s', $out, $m), "Tiến trình con không trả JSON (mã $ma): $out $err");
        return json_decode($m[1], true);
    }

    /** Thành viên + một máy ĐỘC HẠI cũ (xả hàng chạm tới sẽ xoá nó, không kết nối) */
    private function dungChoPushBao(): array
    {
        $tv = $this->taoThanhVien();
        db_run('INSERT INTO push_subscriptions (member_id, endpoint, ua, created_at, token_hash) VALUES (?,?,?,NOW(),?)',
               [$tv['id'], self::EP_BAN . bin2hex(random_bytes(4)) . 'p5sec-', 'phpunit', push_bam_token(push_sinh_token())]);
        return $tv;
    }

    private const CLI_TRANG_THAI = <<<'PHP'
        function p5_tt(int $mid): array {
            return [
                'outbox' => (int) db_one('SELECT COUNT(*) c FROM push_outbox WHERE member_id = ?', [$mid])['c'],
                'ring'   => (int) db_one('SELECT COALESCE(SUM(ring_seq),0) s FROM push_subscriptions WHERE member_id = ?', [$mid])['s'],
                'may'    => (int) db_one('SELECT COUNT(*) c FROM push_subscriptions WHERE member_id = ?', [$mid])['c'],
            ];
        }
        PHP;

    public function test_push_bao_rollback_khong_de_lai_chuong_hay_outbox(): void
    {
        $tv = $this->dungChoPushBao();
        $r = $this->chayCli(self::CLI_TRANG_THAI . <<<'PHP'
            $mid = $ARGS['mid']; $trong = null; $ketQua = 'khong-nem';
            try {
                trong_giao_dich(function () use ($mid, &$trong) {
                    $n = push_bao([$mid], 'Tiêu đề', 'Nội dung', '/', 'p5sec');
                    $trong = p5_tt($mid) + ['n' => $n];
                    throw new RuntimeException('hỏng giữa chừng');
                });
            } catch (RuntimeException $e) { $ketQua = 'da-nem'; }
            p5_out(['trong' => $trong, 'sau' => p5_tt($mid), 'ketQua' => $ketQua]);
            PHP, ['mid' => $tv['id']]);

        $this->assertSame('da-nem', $r['ketQua']);
        // Bên trong giao dịch: có 1 outbox + chuông 1 máy; và CHƯA xả hàng (máy vẫn còn, chưa bị xoá lười)
        $this->assertSame(['outbox' => 1, 'ring' => 1, 'may' => 1, 'n' => 1], $r['trong'],
            'Đang trong giao dịch chưa commit thì không được xả hàng đợi');
        // Sau rollback: 0 chuông, 0 outbox
        $this->assertSame(['outbox' => 0, 'ring' => 0, 'may' => 1], $r['sau']);
        // Và đúng trong DB thật
        $this->assertSame(0, (int) db_one('SELECT COUNT(*) c FROM push_outbox WHERE member_id = ?', [$tv['id']])['c']);
        $this->assertSame(0, (int) db_one('SELECT ring_seq s FROM push_subscriptions WHERE member_id = ?', [$tv['id']])['s']);
    }

    public function test_push_bao_commit_co_outbox_va_chuong_nhung_chua_xa_trong_giao_dich(): void
    {
        $tv = $this->dungChoPushBao();
        $r = $this->chayCli(self::CLI_TRANG_THAI . <<<'PHP'
            $mid = $ARGS['mid'];
            $trong = null;
            trong_giao_dich(function () use ($mid, &$trong) {
                push_bao([$mid], 'Tiêu đề', 'Nội dung', '/', 'p5sec');
                $trong = p5_tt($mid);
            });
            p5_out(['trong' => $trong, 'sau' => p5_tt($mid)]);
            PHP, ['mid' => $tv['id']]);

        $this->assertSame(['outbox' => 1, 'ring' => 1, 'may' => 1], $r['trong'],
            'Trong giao dịch chưa commit: chưa xả hàng đợi (máy độc hại chưa bị chạm tới)');
        $this->assertSame(['outbox' => 1, 'ring' => 1, 'may' => 1], $r['sau']);
        $this->assertSame(1, (int) db_one('SELECT COUNT(*) c FROM push_outbox WHERE member_id = ?', [$tv['id']])['c']);
        $this->assertSame(1, (int) db_one('SELECT ring_seq s FROM push_subscriptions WHERE member_id = ?', [$tv['id']])['s']);
    }

    public function test_push_bao_ngoai_giao_dich_xa_hang_ngay(): void
    {
        // Đối chứng: ngoài giao dịch thì xả hàng thật sự chạy (máy độc hại bị xoá lười, outbox giữ nguyên).
        // Có ca này thì hai ca trên mới chứng minh được "chưa commit nên chưa xả", chứ không phải "xả hàng hỏng".
        $tv = $this->dungChoPushBao();
        $r = $this->chayCli(self::CLI_TRANG_THAI . <<<'PHP'
            $mid = $ARGS['mid'];
            $n = push_bao([$mid], 'Tiêu đề', 'Nội dung', '/', 'p5sec');
            p5_out(['n' => $n, 'sau' => p5_tt($mid)]);
            PHP, ['mid' => $tv['id']]);
        $this->assertSame(1, $r['n']);
        $this->assertSame(1, $r['sau']['outbox']);
        $this->assertSame(0, $r['sau']['may'], 'Xả hàng phải chạm tới máy và xoá endpoint độc hại');
    }

    public function test_hen_sau_phan_hoi_khong_xa_khi_giao_dich_con_do(): void
    {
        // Đường web: push_hen_sau_phan_hoi() đăng ký hàm shutdown. Nếu request kết thúc khi giao dịch
        // chưa commit thì shutdown KHÔNG được xả hàng. Hàm dò dưới đăng ký SAU nên chạy sau hàm xả.
        $tv = $this->dungChoPushBao();
        db_run('UPDATE push_subscriptions SET ring_seq = ring_seq + 1 WHERE member_id = ?', [$tv['id']]);   // có hàng chờ
        $r = $this->chayCli(self::CLI_TRANG_THAI . <<<'PHP'
            $mid = $ARGS['mid'];
            db()->beginTransaction();
            push_hen_sau_phan_hoi();
            register_shutdown_function(function () use ($mid) {
                $tt = p5_tt($mid) + ['trongGd' => db()->inTransaction()];
                db()->rollBack();
                p5_out($tt);
            });
            exit(0);
            PHP, ['mid' => $tv['id']]);
        $this->assertTrue($r['trongGd']);
        $this->assertSame(1, $r['may'], 'Giao dịch còn dở: shutdown không được xả hàng (máy độc hại chưa bị chạm tới)');
    }

    public function test_hen_sau_phan_hoi_xa_khi_khong_con_giao_dich(): void
    {
        // Đối chứng của ca trên: cùng kịch bản nhưng không mở giao dịch → shutdown xả hàng (xoá máy độc hại)
        $tv = $this->dungChoPushBao();
        db_run('UPDATE push_subscriptions SET ring_seq = ring_seq + 1 WHERE member_id = ?', [$tv['id']]);
        $r = $this->chayCli(self::CLI_TRANG_THAI . <<<'PHP'
            $mid = $ARGS['mid'];
            push_hen_sau_phan_hoi();
            register_shutdown_function(function () use ($mid) { p5_out(p5_tt($mid)); });
            exit(0);
            PHP, ['mid' => $tv['id']]);
        $this->assertSame(0, $r['may'], 'Ngoài giao dịch, shutdown phải xả hàng');
    }

    // ---------------------------------------------------------------

    private function logMoi(int $tuByte): string
    {
        clearstatcache(true, self::$logFile);
        $s = (string) @file_get_contents(self::$logFile, false, null, $tuByte);
        return $s;
    }
}
