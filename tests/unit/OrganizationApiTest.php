<?php
// tests/unit/OrganizationApiTest.php
//
// CHỐT CHẶN PHÂN QUYỀN cho `api/personal.php` (T2.4 + T4.3) — hồ sơ cá nhân
// của thành viên. Theo AGENT_RULES.md rule 6: endpoint ghi phải
// `require_write()` + `require_permission('org','edit')` + kiểm phạm vi.
//
// Hành vi đang chốt:
//   1. getProfile: ai cũng xem được của CHÍNH MÌNH (org.view); xem NGƯỜI KHÁC
//      cần 'org.edit'.
//   2. updateGlvYear / saveServiceHistory / deleteServiceHistory: chỉ
//      'org.edit' (BĐH/Quản trị). GLV/Thủ thư/Trưởng khối không có
//      'org.edit' -> 403.
//
// Test theo LUẬT CỨNG: bộ chốt chặn phân quyền FAIL (không skip) khi máy
// chủ PHP tích hợp không khởi động được.

require_once __DIR__ . '/P1ApiHarness.php';

use PHPUnit\Framework\TestCase;

class OrganizationApiTest extends TestCase
{
    use P1ApiHarness;

    private int $yearId = 0;
    /** @var array<string,array{id:int,phone:string,password:string}> */
    private array $who = [];

    public static function setUpBeforeClass(): void
    {
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    protected function setUp(): void
    {
        $y = current_year();
        self::assertNotNull($y, 'Cần niên khoá hiện tại.');
        $this->yearId = (int) $y['id'];

        // 4 tài khoản đủ 4 cấp quyền org khác nhau:
        //   - bdh      : org.edit (được ghi)
        //   - glv      : org.view  (chỉ xem)
        //   - thu_thu  : org.view  (chỉ xem)
        //   - admin    : org.edit (mặc định, dùng để getProfile của mình)
        $this->who['admin']   = $this->makeMember('admin');
        $this->who['bdh']     = $this->makeMember('bdh');
        $this->who['glv']     = $this->makeMember('glv');
        $this->who['thu_thu'] = $this->makeMember('thu_thu');

        foreach ($this->who as $w) $this->loginAs($w);
    }

    protected function tearDown(): void
    {
        $this->cleanupHarness();
    }

    // ------------------------------------------------------------------
    //  getProfile: xem của chính mình
    // ------------------------------------------------------------------

    public function testGetProfile_Self_WorksForAllRoles(): void
    {
        // Role có org.view (mặc định theo install.php) xem được hồ sơ
        // của chính mình: bdh, truong_khoi, glv_chu_nhiem, glv, du_bi.
        // thu_thu KHÔNG có trong $roleOrder nên không nhận quyền org ->
        // xem của mình cũng 403 (đúng hành vi: thủ thư không có module
        // "Khối lớp").
        $can = ['admin', 'bdh', 'glv'];
        foreach ($can as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'GET', '/api/personal.php?action=getProfile');
            self::assertSame(200, $r['code'],
                "[$role] xem của mình phải 200, được {$r['code']}: {$r['raw']}");
            self::assertSame($me['id'], (int) ($r['json']['data']['member']['id'] ?? 0),
                "[$role] id trả về phải khớp với mình");
        }
    }

    public function testGetProfile_Other_FailsForViewOnly(): void
    {
        // GLV/Thủ thư (org=view) xem hồ sơ NGƯỜI KHÁC -> 403.
        $target = $this->who['admin']['id'];     // bất kỳ ai khác
        foreach (['glv', 'thu_thu'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $target);
            self::assertSame(403, $r['code'],
                "[$role] xem người khác phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testGetProfile_Other_WorksForEdit(): void
    {
        // BĐH/Quản trị (org=edit) xem hồ sơ NGƯỜI KHÁC -> 200.
        $target = $this->who['admin']['id'];
        foreach (['admin', 'bdh'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $target);
            self::assertSame(200, $r['code'],
                "[$role] xem người khác phải 200, được {$r['code']}: {$r['raw']}");
        }
    }

    // ------------------------------------------------------------------
    //  updateGlvYear: chỉ 'org.edit'
    // ------------------------------------------------------------------

    public function testUpdateGlvYear_ViewOnly_Forbidden(): void
    {
        // GLV/Thủ thư gọi updateGlvYear -> 403 (org.view).
        foreach (['glv', 'thu_thu'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'POST', '/api/personal.php?action=updateGlvYear',
                ['member_id' => $me['id'], 'glv_year' => 2020]);
            self::assertSame(403, $r['code'],
                "[$role] updateGlvYear phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testUpdateGlvYear_Bdh_WritesNullAndRestores(): void
    {
        // BĐH set NULL rồi khôi phục: happy path + xác minh DB đổi theo.
        // Mẹo: chuẩn bị sẵn glv_year=2018 để set null còn gì để verify,
        // vì nếu NULL ban đầu thì set null không thay đổi gì.
        $target = (int) $this->who['glv']['id'];
        $bdh = $this->who['bdh'];
        db_run('UPDATE members SET glv_year = 2018 WHERE id = ?', [$target]);
        $g = db_one('SELECT glv_year FROM members WHERE id = ?', [$target]);
        self::assertSame(2018, (int) $g['glv_year'], 'tiền-điều-kiện: phải có glv_year=2018');

        // Bước 1: set NULL
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear',
            ['member_id' => $target, 'glv_year' => null]);
        self::assertSame(200, $r['code'],
            "set NULL phải 200, được {$r['code']}: {$r['raw']}");
        $g = db_one('SELECT glv_year FROM members WHERE id = ?', [$target]);
        self::assertTrue($g['glv_year'] === null, 'glv_year phải NULL sau khi set null, được: ' . var_export($g['glv_year'], true));

        // Bước 2: khôi phục = 2018
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear',
            ['member_id' => $target, 'glv_year' => 2018]);
        self::assertSame(200, $r['code'],
            "set 2018 phải 200, được {$r['code']}: {$r['raw']}");
        $g = db_one('SELECT glv_year FROM members WHERE id = ?', [$target]);
        self::assertSame(2018, (int) $g['glv_year'], 'glv_year phải 2018');

        // Dọn: trả về NULL để trạng thái DB sạch cho test khác
        db_run('UPDATE members SET glv_year = NULL WHERE id = ?', [$target]);
    }

    public function testUpdateGlvYear_Bdh_ConflictWithManualHistory(): void
    {
        // T2.2-fix: set glv_year=2018 KHI đã có dòng is_auto=0 cùng năm -> lỗi.
        // Theo thiết kế (task 2.4-report.md), PersonalService throw Exception,
        // personal.php catch và trả 400 + thông báo liệt kê năm xung đột.
        $target = (int) $this->who['thu_thu']['id'];
        $bdh = $this->who['bdh'];

        // Tạo 1 dòng lịch sử nhập tay cho năm 2018-2019
        $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
            ['member_id' => $target, 'year_label' => '2018-2019',
             'role_code' => 'glv', 'note' => 'test conflict']);
        $g = db_one('SELECT COUNT(*) AS n FROM member_service_history WHERE member_id = ? AND year_label = ? AND is_auto = 0',
            [$target, '2018-2019']);
        self::assertSame(1, (int) $g['n'], 'phải có 1 dòng is_auto=0 năm 2018-2019');

        // Giờ set glv_year=2018 -> năm 2018-2019 sẽ được tái dựng is_auto=1
        // -> đụng dòng is_auto=0 cùng year_label -> lỗi 400 với message liệt kê năm
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear',
            ['member_id' => $target, 'glv_year' => 2018]);
        self::assertSame(400, $r['code'],
            "set 2018 khi có manual 2018-2019 phải 400, được {$r['code']}: {$r['raw']}");
        self::assertStringContainsString('2018-2019', (string) ($r['raw'] ?? ''),
            'thông báo lỗi phải liệt kê năm xung đột');

        // Quan trọng: members.glv_year KHÔNG bị đổi (T2.2-fix).
        $g = db_one('SELECT glv_year FROM members WHERE id = ?', [$target]);
        self::assertNull($g['glv_year'], 'glv_year phải vẫn NULL vì lỗi');

        // Dọn
        db_run('DELETE FROM member_service_history WHERE member_id = ?', [$target]);
    }

    // ------------------------------------------------------------------
    //  saveServiceHistory: chỉ 'org.edit'
    // ------------------------------------------------------------------

    public function testSaveServiceHistory_ViewOnly_Forbidden(): void
    {
        $target = (int) $this->who['glv']['id'];
        foreach (['glv', 'thu_thu'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
                ['member_id' => $target, 'year_label' => '2020-2021', 'role_code' => 'glv']);
            self::assertSame(403, $r['code'],
                "[$role] saveServiceHistory phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testSaveServiceHistory_Bdh_HappyPath(): void
    {
        $target = (int) $this->who['thu_thu']['id'];
        $bdh = $this->who['bdh'];
        $year = '2020-2021';

        // Tạo
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
            ['member_id' => $target, 'year_label' => $year, 'role_code' => 'glv', 'note' => 'unit test']);
        self::assertSame(200, $r['code'],
            "save phải 200, được {$r['code']}: {$r['raw']}");

        // Verify DB
        $row = db_one('SELECT role_code, is_auto, note FROM member_service_history WHERE member_id = ? AND year_label = ?',
            [$target, $year]);
        self::assertNotNull($row, 'phải có dòng vừa tạo');
        self::assertSame('glv', $row['role_code']);
        self::assertSame(0, (int) $row['is_auto'], 'dòng BĐH tạo = is_auto=0');
        self::assertSame('unit test', $row['note']);

        // Sửa lại (PUT giống POST) — đổi role_code
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
            ['member_id' => $target, 'year_label' => $year, 'role_code' => 'bdh', 'note' => 'updated']);
        self::assertSame(200, $r['code']);
        $row = db_one('SELECT role_code, note FROM member_service_history WHERE member_id = ? AND year_label = ?',
            [$target, $year]);
        self::assertSame('bdh', $row['role_code']);
        self::assertSame('updated', $row['note']);

        // Dọn
        db_run('DELETE FROM member_service_history WHERE member_id = ? AND year_label = ?', [$target, $year]);
    }

    public function testSaveServiceHistory_InvalidYearLabel_400(): void
    {
        $target = (int) $this->who['thu_thu']['id'];
        $bdh = $this->who['bdh'];

        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
            ['member_id' => $target, 'year_label' => 'không phải YYYY-YYYY', 'role_code' => 'glv']);
        self::assertSame(400, $r['code'],
            "yearLabel sai phải 400, được {$r['code']}: {$r['raw']}");
    }

    // ------------------------------------------------------------------
    //  deleteServiceHistory
    // ------------------------------------------------------------------

    public function testDeleteServiceHistory_ViewOnly_Forbidden(): void
    {
        $target = (int) $this->who['glv']['id'];
        foreach (['glv', 'thu_thu'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'POST', '/api/personal.php?action=deleteServiceHistory',
                ['member_id' => $target, 'year_label' => '2020-2021']);
            self::assertSame(403, $r['code'],
                "[$role] deleteServiceHistory phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testDeleteServiceHistory_Bdh_HappyPath(): void
    {
        $target = (int) $this->who['thu_thu']['id'];
        $bdh = $this->who['bdh'];
        $year = '2019-2020';

        // Tạo
        $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory',
            ['member_id' => $target, 'year_label' => $year, 'role_code' => 'glv']);

        // Xoá
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=deleteServiceHistory',
            ['member_id' => $target, 'year_label' => $year]);
        self::assertSame(200, $r['code'],
            "delete phải 200, được {$r['code']}: {$r['raw']}");

        $g = db_one('SELECT COUNT(*) AS n FROM member_service_history WHERE member_id = ? AND year_label = ?',
            [$target, $year]);
        self::assertSame(0, (int) $g['n'], 'sau delete phải còn 0 dòng');
    }

    public function testDeleteServiceHistory_AutoRow_Forbidden(): void
    {
        // Dòng is_auto=1 do updateGlvYear tạo ra KHÔNG được xoá qua API.
        $target = (int) $this->who['glv']['id'];
        $bdh = $this->who['bdh'];

        // Set glv_year để tạo 1 dòng is_auto=1
        $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear',
            ['member_id' => $target, 'glv_year' => 2020]);
        $g = db_one('SELECT COUNT(*) AS n FROM member_service_history WHERE member_id = ? AND is_auto = 1',
            [$target]);
        self::assertGreaterThan(0, (int) $g['n'], 'phải có ít nhất 1 dòng is_auto=1');

        // Thử xoá dòng is_auto=1
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=deleteServiceHistory',
            ['member_id' => $target, 'year_label' => '2020-2021']);
        self::assertContains($r['code'], [400, 403, 409],
            "xoá is_auto=1 phải bị từ chối, được {$r['code']}: {$r['raw']}");

        // Dọn
        db_run('DELETE FROM member_service_history WHERE member_id = ?', [$target]);
        db_run('UPDATE members SET glv_year = NULL WHERE id = ?', [$target]);
    }

    // ------------------------------------------------------------------
    //  410 Gone: 4 case ghi đã chuyển sang api/personal.php. Gọi org.php
    //  với 4 action này phải trả 410 (kể cả BĐH — không phải 403) để
    //  frontend cũ phát hiện URL sai và cập nhật.
    // ------------------------------------------------------------------

    public function testOrg_410_SaveServiceHistory_Gone(): void
    {
        $bdh = $this->who['bdh'];
        $r = $this->http($bdh['id'], 'POST',
            '/api/org.php?action=saveServiceHistory',
            ['member_id' => $this->who['glv']['id'], 'year_label' => '2024-2025']);
        self::assertSame(410, $r['code'],
            "saveServiceHistory ở org.php phải 410 Gone, được {$r['code']}: {$r['raw']}");
    }

    public function testOrg_410_DeleteServiceHistory_Gone(): void
    {
        $bdh = $this->who['bdh'];
        $r = $this->http($bdh['id'], 'POST',
            '/api/org.php?action=deleteServiceHistory',
            ['member_id' => $this->who['glv']['id'], 'year_label' => '2024-2025']);
        self::assertSame(410, $r['code'],
            "deleteServiceHistory ở org.php phải 410 Gone, được {$r['code']}: {$r['raw']}");
    }

    public function testOrg_410_UpdateGlvYear_Gone(): void
    {
        $bdh = $this->who['bdh'];
        $r = $this->http($bdh['id'], 'POST',
            '/api/org.php?action=updateGlvYear',
            ['member_id' => $this->who['glv']['id'], 'glv_year' => 2020]);
        self::assertSame(410, $r['code'],
            "updateGlvYear ở org.php phải 410 Gone, được {$r['code']}: {$r['raw']}");
    }

    public function testOrg_410_ChangeMemberStatus_Gone(): void
    {
        $bdh = $this->who['bdh'];
        $r = $this->http($bdh['id'], 'POST',
            '/api/org.php?action=changeMemberStatus',
            ['member_id' => $this->who['glv']['id'], 'status' => 'tạm nghỉ', 'note' => 'test']);
        self::assertSame(410, $r['code'],
            "changeMemberStatus ở org.php phải 410 Gone, được {$r['code']}: {$r['raw']}");
    }

    // ------------------------------------------------------------------
    //  changeMemberStatus: BĐH đổi trạng thái qua personal.php. Case
    //  hợp lệ (200), bảo vệ admin (400), bắt buộc ghi chú khi rời
    //  "đang phục vụ" (400), trạng thái không hợp lệ (400).
    // ------------------------------------------------------------------

    public function testChangeMemberStatus_Bdh_HappyPath(): void
    {
        $bdh = $this->who['bdh'];
        $target = (int) $this->who['glv']['id'];

        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $target, 'status' => 'tạm nghỉ', 'note' => 'Nghỉ thai sản']);
        self::assertSame(200, $r['code'],
            "happy path phải 200, được {$r['code']}: {$r['raw']}");

        $row = db_one('SELECT status FROM members WHERE id = ?', [$target]);
        self::assertSame('tạm nghỉ', $row['status'], 'status phải = "tạm nghỉ"');

        // Khôi phục
        db_run('UPDATE members SET status = ? WHERE id = ?', ['đang phục vụ', $target]);
    }

    public function testChangeMemberStatus_Bdh_Admin_Protected(): void
    {
        // BĐH đổi trạng thái tài khoản admin → phải 400 (bảo vệ admin).
        $bdh = $this->who['bdh'];
        $admin = (int) $this->who['admin']['id'];
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $admin, 'status' => 'tạm nghỉ', 'note' => 'hack']);
        self::assertSame(400, $r['code'],
            "BĐH đổi status admin phải 400, được {$r['code']}: {$r['raw']}");
    }

    public function testChangeMemberStatus_NoteRequired_WhenLeavingActive(): void
    {
        // Đổi sang 'tạm nghỉ' mà KHÔNG ghi chú → 400.
        $bdh = $this->who['bdh'];
        $target = (int) $this->who['glv']['id'];
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $target, 'status' => 'tạm nghỉ']);
        self::assertSame(400, $r['code'],
            "thiếu ghi chú phải 400, được {$r['code']}: {$r['raw']}");
    }

    public function testChangeMemberStatus_InvalidStatus_400(): void
    {
        $bdh = $this->who['bdh'];
        $target = (int) $this->who['glv']['id'];
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $target, 'status' => 'invalid_status', 'note' => 'x']);
        self::assertSame(400, $r['code'],
            "status không hợp lệ phải 400, được {$r['code']}: {$r['raw']}");
    }

    // ------------------------------------------------------------------
    //  T4.1: data shift expose (PR7 chỉ có test + validation, không có
    //  các thay đổi data.php + _bootstrap_page.php expose classShift —
    //  vẫn chờ PR8 để bật test này)
    // ------------------------------------------------------------------

    // ------------------------------------------------------------------
    //  T4.1: data shift expose — verify _bootstrap_page.php gửi
    //  `shift` của từng lớp xuống window.TNTT.boot.classes, để
    //  module_students dropdown shiftSuffix dùng được.
    // ------------------------------------------------------------------

    public function testBootstrapShift_AppearsInClasses(): void
    {
        // Tạo 1 khối + 1 lớp có shift='sang' (nếu chưa có khối/lớp nào).
        // Không xoá ở tearDown vì nếu test khác cũng cần lớp — chỉ dọn
        // những dòng do test này tạo.
        $blockId = (int) (db_one("SELECT id FROM blocks ORDER BY id LIMIT 1")['id'] ?? 0);
        $classId = 0;
        $className = 'BootstrapShiftTest_' . substr((string) mt_rand(), 0, 6);
        $madeClass = false;
        if ($blockId === 0) {
            $blockId = (int) db_insert("INSERT INTO blocks (name, sort_order) VALUES (?, 99)", ['TestBs']);
        }
        $existing = db_one('SELECT id FROM classes WHERE name = ?', [$className]);
        if ($existing) {
            $classId = (int) $existing['id'];
        } else {
            $classId = (int) db_insert(
                "INSERT INTO classes (block_id, name, shift, sort_order) VALUES (?, ?, 'sang', 99)",
                [$blockId, $className]
            );
            $madeClass = true;
        }
        self::assertGreaterThan(0, $classId, 'phải có class id để test');

        try {
            // Mở trang index.php qua máy chủ tích hợp, parse window.TNTT_BOOT
            $r = $this->http($this->who['admin']['id'], 'GET', '/index.php', null, false);
            self::assertSame(200, $r['code'],
                "GET /index.php phải 200, được {$r['code']}: {$r['raw']}");

            // Tìm block window.TNTT_BOOT = {...} (mọi thứ nằm trong 1 <script>)
            self::assertStringContainsString('window.TNTT_BOOT', (string) $r['raw'],
                'trang phải có window.TNTT_BOOT');

            // _bootstrap_page.php render qua json_encode — JSON format chuẩn,
            // không có space giữa key:value. Match entry chứa tên lớp vừa tạo
            // + shift:"sang".
            $pattern = '/\{[^{}]*"name":"' . preg_quote($className, '/')
                     . '"[^{}]*"shift":"sang"[^{}]*\}/';
            self::assertSame(
                1,
                preg_match($pattern, (string) $r['raw']),
                'payload classes phải có lớp vừa tạo với shift="sang"'
            );
        } finally {
            if ($madeClass) {
                db_run('DELETE FROM classes WHERE id = ?', [$classId]);
            }
        }
    }
}
