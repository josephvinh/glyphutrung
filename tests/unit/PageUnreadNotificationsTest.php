<?php
// tests/unit/PageUnreadNotificationsTest.php
//
// Số thông báo chưa đọc lúc mở trang (page_bootstrap) KHÔNG được làm sập trang
// khi host chưa chạy migration 030 (bảng push_notifications chưa có): trước đây
// mọi trang sau đăng nhập trả HTTP 500 (trắng), riêng trang khách vẫn lên.

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_bootstrap_page.php';

use PHPUnit\Framework\TestCase;

class PageUnreadNotificationsTest extends TestCase
{
    private int $memberId = 0;
    private array $notifIds = [];

    protected function setUp(): void
    {
        $m = db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1");
        if (!$m) $this->markTestSkipped('Cần DB test đã seed (không có admin).');
        $this->memberId = (int) $m['id'];
        $co = db_one("SELECT COUNT(*) c FROM information_schema.tables
                       WHERE table_schema = DATABASE() AND table_name = 'push_notifications'");
        if ((int) ($co['c'] ?? 0) === 0) $this->markTestSkipped('DB test chưa có push_notifications (migration 030).');
    }

    protected function tearDown(): void
    {
        foreach ($this->notifIds as $id) db_run('DELETE FROM push_notifications WHERE id = ?', [$id]);
        $this->notifIds = [];
    }

    private function addNotification(int $isRead): void
    {
        $this->notifIds[] = db_insert(
            "INSERT INTO push_notifications (member_id, tag, title, body, is_read) VALUES (?, 'test', 't', 'b', ?)",
            [$this->memberId, $isRead]
        );
    }

    public function test_dem_dung_so_chua_doc(): void
    {
        $truoc = page_unread_notifications($this->memberId);
        $this->addNotification(0);
        $this->addNotification(0);
        $this->addNotification(1);   // đã đọc: không tính
        $this->assertSame($truoc + 2, page_unread_notifications($this->memberId));
    }

    public function test_thieu_bang_thi_tra_0_chu_khong_nem_loi(): void
    {
        db_run('RENAME TABLE push_notifications TO push_notifications_tam');
        try {
            $this->assertSame(0, page_unread_notifications($this->memberId));
        } finally {
            db_run('RENAME TABLE push_notifications_tam TO push_notifications');
        }
        $this->assertSame(
            1,
            (int) db_one("SELECT COUNT(*) c FROM information_schema.tables
                           WHERE table_schema = DATABASE() AND table_name = 'push_notifications'")['c'],
            'Bảng push_notifications phải được trả lại sau test.'
        );
    }

    public function test_loi_khac_van_duoc_nem_len(): void
    {
        // Thiếu CỘT (khác thiếu BẢNG) là lỗi thật cần thấy, không được nuốt.
        db_run('ALTER TABLE push_notifications CHANGE is_read is_read_tam TINYINT(1) NOT NULL DEFAULT 0');
        try {
            $nem = false;
            try { page_unread_notifications($this->memberId); }
            catch (PDOException $e) { $nem = true; }
            $this->assertTrue($nem, 'Lỗi không phải "thiếu bảng" phải được ném lên.');
        } finally {
            db_run('ALTER TABLE push_notifications CHANGE is_read_tam is_read TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
}
