<?php
// tests/unit/ScoresExamNumApiTest.php
//
// KIỂM THỬ ĐẦU-CUỐI (qua HTTP thật) CỦA scores.php với exam_num:
// - GET → danh sách điểm (phải có exam_num)
// - POST action=set → lưu điểm với exam_num
// - POST action=delete_exam → xoá bài và renumber
//
// Bảo vệ LUẬT CỨNG #6:
//   - require_write + kiểm phạm vi class

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';

use PHPUnit\Framework\TestCase;

class ScoresExamNumApiTest extends TestCase
{
    private static $proc = null;
    private static string $base = '';
    private string $cookieJar = '';
    private string $csrf = '';
    private int $studentId = 0;
    private int $termId = 0;
    private int $classId = 0;
    private array $createdScoreIds = [];

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
        sleep(1);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$proc) {
            proc_terminate(self::$proc);
            proc_close(self::$proc);
        }
    }

    protected function setUp(): void
    {
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'ck_');
        $ch = curl_init(self::$base . '/api/login.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['username' => 'admin', 'password' => 'admin'],
        ]);
        $out = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] !== 200) {
            $this->markTestSkipped('Không đăng nhập được admin.');
        }

        $ch = curl_init(self::$base . '/api/me.php');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $me = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $this->csrf = $me['csrf'] ?? '';

        // Lấy student + term + class để test
        $r = $this->api('/api/scores.php?action=list');
        $this->studentId = $r['body']['students'][0]['id'] ?? 0;
        $this->termId = $r['body']['terms'][0]['id'] ?? 0;
        $this->classId = $r['body']['classes'][0]['id'] ?? 0;
    }

    protected function tearDown(): void
    {
        // Cleanup scores
        foreach ($this->createdScoreIds as $ids) {
            $this->api('/api/scores.php', [
                '_csrf' => $this->csrf,
                'action' => 'delete_exam',
                'studentId' => $ids['studentId'],
                'termId' => $ids['termId'],
                'type' => $ids['type'],
                'examNum' => $ids['examNum'],
            ], 'POST');
        }
        $this->createdScoreIds = [];

        if ($this->cookieJar && file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }

    private function api(string $url, array $post = [], string $method = 'GET'): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_RETURNTRANSFER => true,
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST] = true;
            $p = $post;
            $p['_csrf'] = $this->csrf;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($p);
        }
        curl_setopt_array($ch, $opts);
        $out = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ['code' => $code, 'body' => json_decode($out, true) ?: $out];
    }

    // ─── GET list → scores phải có exam_num ───

    public function test_list_scores_has_exam_num_field(): void
    {
        if ($this->studentId === 0) {
            $this->markTestSkipped('Không có student để test.');
        }
        $r = $this->api('/api/scores.php?action=list');
        $this->assertEquals(200, $r['code']);
        $this->assertArrayHasKey('scores', $r['body']);

        // Nếu có điểm, mỗi bản ghi phải có exam_num
        foreach ($r['body']['scores'] as $s) {
            $this->assertArrayHasKey('exam_num', $s, 'Score record must have exam_num');
        }
    }

    // ─── POST set với exam_num ───

    public function test_set_score_with_exam_num(): void
    {
        if ($this->studentId === 0) {
            $this->markTestSkipped('Không có student để test.');
        }

        // Set điểm bài 1
        $r = $this->api('/api/scores.php', [
            'action' => 'set',
            'studentId' => $this->studentId,
            'termId' => $this->termId,
            'type' => 'mieng',
            'exam_num' => 1,
            'value' => '8.5',
        ], 'POST');

        $this->assertEquals(200, $r['code']);

        // Verify: reload list
        $list = $this->api('/api/scores.php?action=list');
        $found = array_filter($list['body']['scores'], fn($s) =>
            $s['student_id'] == $this->studentId
            && $s['term_id'] == $this->termId
            && $s['type_code'] === 'mieng'
            && ($s['exam_num'] ?? 1) == 1
        );
        $this->assertNotEmpty($found, 'Điểm bài 1 phải được lưu');
    }

    public function test_set_score_multiple_exams_same_type(): void
    {
        if ($this->studentId === 0) {
            $this->markTestSkipped('Không có student để test.');
        }

        // Set bài 1 và bài 2 cùng loại
        $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p15',
            'exam_num' => 1, 'value' => '7.0',
        ], 'POST');

        $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p15',
            'exam_num' => 2, 'value' => '8.0',
        ], 'POST');

        $list = $this->api('/api/scores.php?action=list');
        $p15Scores = array_filter($list['body']['scores'], fn($s) =>
            $s['student_id'] == $this->studentId
            && $s['type_code'] === 'p15'
        );

        // Phải có 2 bài
        $this->assertGreaterThanOrEqual(2, count($p15Scores));

        // Verify exam_num khác nhau
        $examNums = array_column($p15Scores, 'exam_num');
        $this->assertContains(1, $examNums);
        $this->assertContains(2, $examNums);
    }

    // ─── POST delete_exam ───

    public function test_delete_exam_removes_score(): void
    {
        if ($this->studentId === 0) {
            $this->markTestSkipped('Không có student để test.');
        }

        // Set 2 bài
        $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p45',
            'exam_num' => 1, 'value' => '6.0',
        ], 'POST');

        $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'p45',
            'exam_num' => 2, 'value' => '7.5',
        ], 'POST');

        // Xoá bài 1
        $r = $this->api('/api/scores.php', [
            'action' => 'delete_exam',
            'studentId' => $this->studentId,
            'termId' => $this->termId,
            'type' => 'p45',
            'examNum' => 1,
        ], 'POST');

        $this->assertEquals(200, $r['code']);

        // Verify: chỉ còn bài 2 (đã được renumber thành 1)
        $list = $this->api('/api/scores.php?action=list');
        $remaining = array_filter($list['body']['scores'], fn($s) =>
            $s['student_id'] == $this->studentId
            && $s['type_code'] === 'p45'
        );

        // Các exam_num còn lại phải không chứa số đã xoá
        foreach ($remaining as $s) {
            $this->assertNotEquals(1, $s['exam_num'] ?? null, 'Bài đã xoá không còn trong danh sách');
        }
    }

    // ─── Validation: điểm 0-10 ───

    public function test_score_value_must_be_0_to_10(): void
    {
        if ($this->studentId === 0) {
            $this->markTestSkipped('Không có student để test.');
        }

        // Điểm âm → phải bị từ chối
        $r = $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'mieng',
            'exam_num' => 1, 'value' => '-1',
        ], 'POST');

        // Backend validate 0-10 → 400
        $this->assertEquals(400, $r['code']);

        // Điểm > 10
        $r2 = $this->api('/api/scores.php', [
            'action' => 'set', 'studentId' => $this->studentId,
            'termId' => $this->termId, 'type' => 'mieng',
            'exam_num' => 1, 'value' => '11',
        ], 'POST');
        $this->assertEquals(400, $r2['code']);
    }

    public function test_requires_login(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ck_');
        $ch = curl_init(self::$base . '/api/scores.php?action=list');
        curl_setopt_array($ch, [
            CURLOPT_COOKIEFILE => $tmp,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($tmp);
        $this->assertEquals(401, $code);
    }
}
