<?php
/**
 * Verify rằng các endpoint đã được "gắn diff" qua PR7 thực sự ghi
 * diff_json hợp lệ vào activity_logs.
 *
 * Không chạy HTTP (không gọi API) — chỉ INSERT/UPDATE/DELETE trực tiếp
 * vào DB test, gọi log_action(), rồi SELECT lại dòng log vừa ghi.
 *
 * Lý do: test phải độc lập với HTTP server + session + CSRF. log_action()
 * là pure DB write (INSERT activity_logs), nên test được thẳng từ đó.
 *
 * Phạm vi:
 *   - log_action() với 4-đối-số (cũ): vẫn hoạt động, diff_json = NULL
 *   - log_action() với 5-đối-số (mới): diff_json có before/after
 *   - Các khóa phone trong before/after KHÔNG bị mask ở đây
 *     (mask chỉ áp dụng khi xuất logs_export, không phải khi lưu)
 *   - target_type/target_id/target_label được ghi đúng
 *   - IP + user_agent được lưu
 *   - log_action() không throw khi before/after là null
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';

use PHPUnit\Framework\TestCase;

class LogActionDiffTest extends TestCase
{
    private int $adminId = 0;
    private string $adminName = '';
    private ?string $oldIp = null;
    private ?string $oldUa = null;

    protected function setUp(): void
    {
        $row = db_one("SELECT id, full_name FROM members WHERE role_code='admin' LIMIT 1");
        $this->adminId = (int) $row['id'];
        $this->adminName = $row['full_name'];

        // Khởi tạo session để current_member() hoạt động trong log_action().
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION = ['member_id' => $this->adminId];

        // Lưu IP/UA gốc để restore sau khi test_ip_and_ua_persisted chỉnh.
        $this->oldIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->oldUa = $_SERVER['HTTP_USER_AGENT'] ?? null;

        // Wipe rows created by this test (chỉ dòng target_id LIKE 'TEST_%')
        db_run("DELETE FROM activity_logs WHERE target_label LIKE 'TEST_diff_%'");
    }

    protected function tearDown(): void
    {
        db_run("DELETE FROM activity_logs WHERE target_label LIKE 'TEST_diff_%'");

        // Khôi phục IP/UA (test_ip_and_ua_persisted đã ghi đè) để không
        // ảnh hưởng test khác cùng chạy trong cùng process (vd LogActionTest
        // cũng giả định NULL khi chữ ký 4-đối-số).
        if ($this->oldIp === null) unset($_SERVER['REMOTE_ADDR']);
        else $_SERVER['REMOTE_ADDR'] = $this->oldIp;
        if ($this->oldUa === null) unset($_SERVER['HTTP_USER_AGENT']);
        else $_SERVER['HTTP_USER_AGENT'] = $this->oldUa;

        $_SESSION = [];
    }

    /** Hàm tiện: lấy row log mới nhất theo target_label, hoặc theo `what` nếu label NULL */
    private function lastLog(string $label): ?array
    {
        // Trong test, log_action() cùng nhãn 'TEST_diff_old_sig' được ghi cả
        // dưới 4-đối-số (target_label = NULL) lẫn 5-đối-số (target_label = $label).
        // Tìm cả hai trường hợp.
        $r = db_one(
            "SELECT * FROM activity_logs
              WHERE target_label = ? OR what = ?
              ORDER BY id DESC LIMIT 1",
            [$label, $label]
        );
        return $r ?: null;
    }

    public function test_old_signature_4args_still_works(): void
    {
        log_action('sua', 'test', 'TEST_diff_old_sig', 'detail-text');
        $row = $this->lastLog('TEST_diff_old_sig');
        $this->assertNotNull($row, 'Old 4-arg signature must still write a log row');
        $this->assertSame('sua', $row['action']);
        $this->assertSame('test', $row['module']);
        // target_type/id/label là null vì không truyền 5-đối-số
        $this->assertNull($row['target_type']);
        $this->assertNull($row['target_id']);
        $this->assertNull($row['diff_json'],
            'Old 4-arg call should not write diff_json (saves space)');
    }

    public function test_new_signature_5args_writes_diff_json(): void
    {
        log_action('sua', 'test', 'TEST_diff_new_sig', 'detail',
            [
                'before' => ['name' => 'A', 'value' => 1],
                'after'  => ['name' => 'B', 'value' => 2],
                'target_type'  => 'thing',
                'target_id'    => 42,
                'target_label' => 'TEST_diff_new_sig',
            ]);

        $row = $this->lastLog('TEST_diff_new_sig');
        $this->assertNotNull($row);
        $this->assertNotNull($row['diff_json'], '5-arg call must write diff_json');
        $diff = json_decode($row['diff_json'], true);
        $this->assertSame('A', $diff['before']['name']);
        $this->assertSame('B', $diff['after']['name']);
        $this->assertSame(1, (int) $diff['before']['value']);
        $this->assertSame(2, (int) $diff['after']['value']);
    }

    public function test_target_columns_persisted(): void
    {
        log_action('tao', 'test', 'TEST_diff_target_cols', '',
            [
                'before' => null,
                'after'  => ['name' => 'X'],
                'target_type'  => 'student',
                'target_id'    => 999,
                'target_label' => 'TEST_diff_target_cols',
            ]);
        $row = $this->lastLog('TEST_diff_target_cols');
        $this->assertSame('student', $row['target_type']);
        $this->assertSame(999, (int) $row['target_id']);
        $this->assertSame('TEST_diff_target_cols', $row['target_label']);
    }

    public function test_actor_id_and_name_persisted(): void
    {
        log_action('sua', 'test', 'TEST_diff_actor', '',
            [
                'before' => null,
                'after'  => ['v' => 1],
                'target_type' => 'x',
                'target_id' => 1,
                'target_label' => 'TEST_diff_actor',
            ]);
        $row = $this->lastLog('TEST_diff_actor');
        $this->assertSame($this->adminId, (int) $row['actor_id']);
        $this->assertSame($this->adminName, $row['actor_name']);
    }

    public function test_ip_and_ua_persisted(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0 (Diff test)';
        log_action('sua', 'test', 'TEST_diff_ip_ua', '',
            [
                'before' => null,
                'after'  => ['v' => 1],
                'target_type' => 'x',
                'target_id' => 1,
                'target_label' => 'TEST_diff_ip_ua',
            ]);
        $row = $this->lastLog('TEST_diff_ip_ua');
        $this->assertSame('203.0.113.5', $row['ip']);
        $this->assertStringContainsString('TestBrowser', $row['user_agent']);
    }

    public function test_null_before_only_writes_after(): void
    {
        // Create: before null, after full
        log_action('tao', 'test', 'TEST_diff_create', '',
            [
                'before' => null,
                'after'  => ['name' => 'New', 'value' => 1],
                'target_type' => 'thing',
                'target_id' => 1,
                'target_label' => 'TEST_diff_create',
            ]);
        $row = $this->lastLog('TEST_diff_create');
        $diff = json_decode($row['diff_json'], true);
        $this->assertNull($diff['before']);
        $this->assertSame('New', $diff['after']['name']);
    }

    public function test_null_after_only_writes_before(): void
    {
        // Delete: before full, after null
        log_action('xoa', 'test', 'TEST_diff_delete', '',
            [
                'before' => ['name' => 'Gone', 'value' => 1],
                'after'  => null,
                'target_type' => 'thing',
                'target_id' => 1,
                'target_label' => 'TEST_diff_delete',
            ]);
        $row = $this->lastLog('TEST_diff_delete');
        $diff = json_decode($row['diff_json'], true);
        $this->assertSame('Gone', $diff['before']['name']);
        $this->assertNull($diff['after']);
    }

    public function test_phone_not_masked_at_write_time(): void
    {
        // Mask chỉ áp dụng khi xuất (logs_export.php), không phải lúc lưu.
        // Audit trail phải LƯU NGUYÊN dữ liệu để phục vụ truy vết; mask
        // là trách nhiệm của lớp xuất.
        log_action('sua', 'test', 'TEST_diff_phone_raw', '',
            [
                'before' => ['phone' => '0901234567'],
                'after'  => ['phone' => '0987654321'],
                'target_type' => 'member',
                'target_id' => 1,
                'target_label' => 'TEST_diff_phone_raw',
            ]);
        $row = $this->lastLog('TEST_diff_phone_raw');
        $diff = json_decode($row['diff_json'], true);
        $this->assertSame('0901234567', $diff['before']['phone'],
            'SĐT phải lưu NGUYÊN ở activity_logs (mask chỉ ở logs_export)');
        $this->assertSame('0987654321', $diff['after']['phone']);
    }

    public function test_unicode_preserved_in_diff(): void
    {
        log_action('sua', 'test', 'TEST_diff_unicode', '',
            [
                'before' => ['name' => 'Nguyễn Văn A — Chiên 2'],
                'after'  => ['name' => 'Trần Thị B — Chiên 3'],
                'target_type' => 'student',
                'target_id' => 7,
                'target_label' => 'TEST_diff_unicode',
            ]);
        $row = $this->lastLog('TEST_diff_unicode');
        $diff = json_decode($row['diff_json'], true);
        $this->assertSame('Nguyễn Văn A — Chiên 2', $diff['before']['name']);
        $this->assertSame('Trần Thị B — Chiên 3', $diff['after']['name']);
    }

    public function test_no_session_uses_he_thong(): void
    {
        // Trường hợp somoc_order (cổng công khai): không có session.
        $_SESSION = [];
        log_action('doi_qua', 'rewards', 'TEST_diff_no_session', '',
            [
                'before' => null,
                'after'  => ['order_id' => 1, 'status' => 'chờ lấy'],
                'target_type' => 'gift_order',
                'target_id' => 1,
                'target_label' => 'TEST_diff_no_session',
            ]);
        $row = $this->lastLog('TEST_diff_no_session');
        $this->assertNull($row['actor_id'],
            'No session -> actor_id phải là NULL (cổng công khai)');
        $this->assertSame('Hệ thống', $row['actor_name'],
            'No session -> actor_name mặc định "Hệ thống"');
    }
}
