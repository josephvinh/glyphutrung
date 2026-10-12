<?php
// tests/unit/NotificationsApiTest.php
//
// KIỂM THỬ API notifications.php
//
// Bao gồm: list, mark_read, unread_count
// Cần DB test đã chạy install.php + ci_seed.php.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class NotificationsApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';

    private int $memberId = 0;
    private string $phone = '';
    private string $cookieJar = '';
    private string $csrf = '';

    public static function setUpBeforeClass(): void
    {
        if (!function_exists('curl_init')) {
            self::markTestSkipped('Cần ext-curl để gọi HTTP.');
        }
        $sock = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if (!$sock) self::markTestSkipped('Không mở được cổng cục bộ.');
        $port = (int) parse_url('tcp://' . stream_socket_get_name($sock, false), PHP_URL_PORT);
        fclose($sock);

        $root = dirname(__DIR__, 2) . '/public';
        $env = array_merge(getenv(), $_ENV);
        $null = PHP_OS === 'WINNT' ? 'NUL' : '/dev/null';
        self::$proc = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes, $root, $env
        );
        self::$base = "http://127.0.0.1:$port";

        for ($i = 0; $i < 50; $i++) {
            $c = @fsockopen('127.0.0.1', $port, $e, $s, 0.1);
            if ($c) { fclose($c); return; }
            usleep(100000);
        }
        self::markTestSkipped('Máy chủ PHP tích hợp không khởi động được.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$proc)) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
    }

    protected function setUp(): void
    {
        $admin = db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1");
        if (!$admin) {
            $this->markTestSkipped('Cần admin để tạo member test.');
        }

        $this->phone = '09' . random_int(10000000, 99999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'Notification Test User', ?, ?, 'glv', 0)",
            ['NTF' . random_int(100000, 999999), $this->phone, password_hash('Test-Pw-1', PASSWORD_DEFAULT)]
        );
        db_run("INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
                VALUES (?, 'glv', 1, CURDATE(), ?)",
               [$this->memberId, (int) $admin['id']]);

        $this->cookieJar = tempnam(sys_get_temp_dir(), 'ntfapi');
        $this->login();
    }

    protected function tearDown(): void
    {
        if ($this->memberId) {
            db_run("DELETE FROM push_notifications WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM activity_logs WHERE actor_id = ?", [$this->memberId]);
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
        if ($this->cookieJar && file_exists($this->cookieJar)) @unlink($this->cookieJar);
    }

    // ---------------- HTTP ----------------

    private function http(string $method, string $path, ?array $json = null, bool $csrf = true, bool $cookies = true): array
    {
        $ch = curl_init(self::$base . $path);
        $headers = ['Content-Type: application/json'];
        if ($csrf && $this->csrf) $headers[] = 'X-CSRF-TOKEN: ' . $this->csrf;
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($cookies) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieJar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieJar);
        }
        if ($json !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'raw' => (string) $body, 'json' => json_decode((string) $body, true)];
    }

    private function login(): void
    {
        $r = $this->http('POST', '/api/auth.php?action=login',
            ['phone' => $this->phone, 'password' => 'Test-Pw-1'], false);
        $this->assertSame(200, $r['code'], 'Đăng nhập member test phải thành công: ' . $r['raw']);

        $page = $this->http('GET', '/index.php', null, false);
        $this->assertSame(1, preg_match('/"csrfToken":"([a-f0-9]+)"/', $page['raw'], $m),
            'Không lấy được csrfToken từ trang chủ');
        $this->csrf = $m[1];
    }

    // ---------------- DDL helpers ----------------

    private function insertNotification(array $fields = []): int
    {
        $defaults = [
            'member_id' => $this->memberId,
            'tag' => 'test-tag',
            'title' => 'Test Thông Báo',
            'body' => 'Nội dung thông báo test',
            'url' => '/',
            'is_read' => 0,
        ];
        $data = array_merge($defaults, $fields);
        $cols = implode(',', array_keys($data));
        $ph = implode(',', array_fill(0, count($data), '?'));
        return (int) db_insert("INSERT INTO push_notifications ($cols) VALUES ($ph)", array_values($data));
    }

    // =================================================================
    //  list
    // =================================================================

    public function test_list_returns_empty_array_for_new_user(): void
    {
        $r = $this->http('GET', '/api/notifications.php?action=list');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(true, $r['json']['ok'] ?? null);
        $this->assertIsArray($r['json']['items'] ?? null);
        $this->assertEmpty($r['json']['items']);
        $this->assertSame(0, $r['json']['total']);
    }

    public function test_list_returns_notifications_for_member(): void
    {
        $id1 = $this->insertNotification(['title' => 'Thông báo 1', 'is_read' => 0]);
        $id2 = $this->insertNotification(['title' => 'Thông báo 2', 'is_read' => 1]);
        $id3 = $this->insertNotification(['title' => 'Thông báo 3', 'is_read' => 0]);

        $r = $this->http('GET', '/api/notifications.php?action=list');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertCount(3, $r['json']['items']);
        $this->assertSame(3, $r['json']['total']);

        // Mới nhất trước
        $this->assertSame('Thông báo 3', $r['json']['items'][0]['title']);
    }

    public function test_list_filter_unread(): void
    {
        $this->insertNotification(['title' => 'Chưa đọc', 'is_read' => 0]);
        $this->insertNotification(['title' => 'Đã đọc', 'is_read' => 1]);
        $this->insertNotification(['title' => 'Chưa đọc 2', 'is_read' => 0]);

        $r = $this->http('GET', '/api/notifications.php?action=list&read=0');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertCount(2, $r['json']['items']);
        $this->assertSame(2, $r['json']['total']);
        foreach ($r['json']['items'] as $item) {
            $this->assertSame(0, $item['is_read']);
        }
    }

    public function test_list_filter_read(): void
    {
        $this->insertNotification(['title' => 'Chưa đọc', 'is_read' => 0]);
        $this->insertNotification(['title' => 'Đã đọc', 'is_read' => 1]);

        $r = $this->http('GET', '/api/notifications.php?action=list&read=1');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertCount(1, $r['json']['items']);
        $this->assertSame('Đã đọc', $r['json']['items'][0]['title']);
    }

    public function test_list_pagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->insertNotification(['title' => "Notif $i"]);
        }

        // Trang 1, limit 2
        $r = $this->http('GET', '/api/notifications.php?action=list&page=1&limit=2');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertCount(2, $r['json']['items']);
        $this->assertSame(5, $r['json']['total']);
        $this->assertSame(1, $r['json']['page']);
        $this->assertSame(2, $r['json']['limit']);

        // Trang 2
        $r2 = $this->http('GET', '/api/notifications.php?action=list&page=2&limit=2');
        $this->assertSame(200, $r2['code'], $r2['raw']);
        $this->assertCount(2, $r2['json']['items']);

        // Trang 3
        $r3 = $this->http('GET', '/api/notifications.php?action=list&page=3&limit=2');
        $this->assertCount(1, $r3['json']['items']);
    }

    public function test_list_does_not_leak_other_member_notifications(): void
    {
        $otherMemberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'Other User', ?, ?, 'glv', 0)",
            ['OTU' . random_int(100000, 999999), '09' . random_int(10000000, 99999999), password_hash('Other-Pw-1', PASSWORD_DEFAULT)]
        );
        $this->insertNotification(['title' => 'Của tôi']);
        db_run("INSERT INTO push_notifications (member_id, tag, title, body, url, is_read)
                VALUES (?, 'other', 'Của người khác', 'Body', '/', 0)", [$otherMemberId]);

        $r = $this->http('GET', '/api/notifications.php?action=list');
        $this->assertSame(200, $r['code'], $r['raw']);
        $titles = array_column($r['json']['items'], 'title');
        $this->assertContains('Của tôi', $titles);
        $this->assertNotContains('Của người khác', $titles);

        db_run("DELETE FROM members WHERE id = ?", [$otherMemberId]);
    }

    // =================================================================
    //  mark_read
    // =================================================================

    public function test_mark_read_single(): void
    {
        $id = $this->insertNotification(['is_read' => 0]);

        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['ids' => [$id]]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(true, $r['json']['ok']);
        $this->assertSame(1, $r['json']['count']);

        $n = db_one('SELECT is_read FROM push_notifications WHERE id = ?', [$id]);
        $this->assertSame(1, (int) $n['is_read']);
    }

    public function test_mark_read_multiple(): void
    {
        $id1 = $this->insertNotification(['is_read' => 0]);
        $id2 = $this->insertNotification(['is_read' => 0]);

        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['ids' => [$id1, $id2]]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(2, $r['json']['count']);

        $n1 = db_one('SELECT is_read FROM push_notifications WHERE id = ?', [$id1]);
        $n2 = db_one('SELECT is_read FROM push_notifications WHERE id = ?', [$id2]);
        $this->assertSame(1, (int) $n1['is_read']);
        $this->assertSame(1, (int) $n2['is_read']);
    }

    public function test_mark_read_all(): void
    {
        $this->insertNotification(['is_read' => 0]);
        $this->insertNotification(['is_read' => 0]);
        $this->insertNotification(['is_read' => 1]);

        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['all' => true]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(true, $r['json']['ok']);
        $this->assertSame(2, $r['json']['count']);
    }

    public function test_mark_read_requires_post(): void
    {
        $r = $this->http('GET', '/api/notifications.php?action=mark_read');
        $this->assertContains($r['code'], [400, 403, 405]);
    }

    public function test_mark_read_requires_csrf(): void
    {
        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['ids' => [1]], false);
        $this->assertSame(403, $r['code']);
    }

    public function test_mark_read_empty_ids_returns_400(): void
    {
        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['ids' => []]);
        $this->assertSame(400, $r['code']);
    }

    public function test_mark_read_only_affects_own_notifications(): void
    {
        $otherMemberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
             VALUES (?, 'Other User', ?, ?, 'glv', 0)",
            ['OTU2' . random_int(100000, 999999), '09' . random_int(10000000, 99999999), password_hash('Other-Pw-2', PASSWORD_DEFAULT)]
        );
        $myId = $this->insertNotification(['is_read' => 0, 'title' => 'Của tôi']);
        $otherId = (int) db_insert(
            "INSERT INTO push_notifications (member_id, tag, title, body, url, is_read)
             VALUES (?, 'other', 'Của người khác', 'Body', '/', 0)",
            [$otherMemberId]
        );

        // Cố đánh dấu notification của người khác
        $r = $this->http('POST', '/api/notifications.php?action=mark_read',
            ['ids' => [$otherId]]);
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(0, $r['json']['count']); // Không ảnh hưởng

        $n = db_one('SELECT is_read FROM push_notifications WHERE id = ?', [$otherId]);
        $this->assertSame(0, (int) $n['is_read']); // Vẫn chưa đọc

        db_run("DELETE FROM members WHERE id = ?", [$otherMemberId]);
    }

    // =================================================================
    //  unread_count
    // =================================================================

    public function test_unread_count_returns_zero_for_new_user(): void
    {
        $r = $this->http('GET', '/api/notifications.php?action=unread_count');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(true, $r['json']['ok']);
        $this->assertSame(0, $r['json']['count']);
    }

    public function test_unread_count_returns_correct_number(): void
    {
        $this->insertNotification(['is_read' => 0]);
        $this->insertNotification(['is_read' => 0]);
        $this->insertNotification(['is_read' => 1]);

        $r = $this->http('GET', '/api/notifications.php?action=unread_count');
        $this->assertSame(200, $r['code'], $r['raw']);
        $this->assertSame(2, $r['json']['count']);
    }

    public function test_unread_count_updates_after_mark_read(): void
    {
        $this->insertNotification(['is_read' => 0]);
        $this->insertNotification(['is_read' => 0]);

        $before = $this->http('GET', '/api/notifications.php?action=unread_count');
        $this->assertSame(2, $before['json']['count']);

        $items = $this->http('GET', '/api/notifications.php?action=list')['json']['items'];
        $id = $items[0]['id'];

        $this->http('POST', '/api/notifications.php?action=mark_read', ['ids' => [$id]]);

        $after = $this->http('GET', '/api/notifications.php?action=unread_count');
        $this->assertSame(1, $after['json']['count']);
    }

    // =================================================================
    //  invalid action
    // =================================================================

    public function test_invalid_action_returns_404(): void
    {
        $r = $this->http('GET', '/api/notifications.php?action=invalid');
        $this->assertSame(404, $r['code']);
    }

    // =================================================================
    //  unauthenticated access
    // =================================================================

    public function test_requires_authentication(): void
    {
        $r = $this->http('GET', '/api/notifications.php?action=list', null, false, false);
        $this->assertContains($r['code'], [401, 403]);
    }
}
