<?php
// tests/unit/OrganizationApiTest.php
//
// CHỐT CHẶN PHÂN QUYỀN cho `api/personal.php` (T2.4 + T4.3) — hồ sơ cá nhân
// của thành viên. Theo AGENT_RULES.md rule 6: endpoint ghi phải
// `require_write()` + quyền module + kiểm phạm vi theo-đối-tượng.
//
// Hành vi đang chốt:
//   1. getProfile: ai có org.view cũng xem được của CHÍNH MÌNH; xem NGƯỜI KHÁC
//      cần 'staff.edit' (Nhân sự) — Trưởng khối có org.edit nhưng KHÔNG đủ.
//   2. updateGlvYear / saveServiceHistory / deleteServiceHistory /
//      changeMemberStatus: cần 'staff.edit' (BĐH/Quản trị) rồi kiểm đối tượng:
//      Quản trị với người khác = 404 (F9), BĐH khác chỉ Quản trị mới sửa (403, #97),
//      không ai tự khoá chính mình, hồ sơ 'chờ duyệt' đi qua bước Duyệt.
//      GLV/Thủ thư/Trưởng khối -> 403.
//   3. Ghi xong phải xoá cache data.php (người ghi thấy ngay, không đợi 60 giây).
//
// Test theo LUẬT CỨNG: bộ chốt chặn phân quyền FAIL (không skip) khi máy
// chủ PHP tích hợp không khởi động được.

require_once __DIR__ . '/P1ApiHarness.php';
require_once __DIR__ . '/../../public/api/PersonalService.php';

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

        // 4 tài khoản đủ các cấp quyền khác nhau (ma trận mặc định của install.php):
        //   - admin    : org=edit, staff=edit (ghi được; bị ẩn với người không phải admin)
        //   - bdh      : org=edit, staff=edit (ghi được, trừ BĐH khác/Quản trị)
        //   - glv      : org=view, staff=view (chỉ xem hồ sơ CỦA MÌNH)
        //   - thu_thu  : không có org (không xem được cả hồ sơ của mình)
        // Trưởng khối (org=edit nhưng staff=view) được tạo riêng trong các test cần nó.
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
        // (Không dùng admin làm mục tiêu: admin bị ẩn nên trả 404 — có test riêng bên dưới.)
        $target = $this->who['bdh']['id'];       // bất kỳ ai khác, không phải admin
        foreach (['glv', 'thu_thu'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $target);
            self::assertSame(403, $r['code'],
                "[$role] xem người khác phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testGetProfile_Other_WorksForEdit(): void
    {
        // BĐH/Quản trị (staff=edit) xem hồ sơ NGƯỜI KHÁC (không phải admin) -> 200.
        $target = $this->who['glv']['id'];
        foreach (['admin', 'bdh'] as $role) {
            $me = $this->who[$role];
            $r = $this->http($me['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $target);
            self::assertSame(200, $r['code'],
                "[$role] xem người khác phải 200, được {$r['code']}: {$r['raw']}");
        }
    }

    // ------------------------------------------------------------------
    //  updateGlvYear: chỉ 'staff.edit'
    // ------------------------------------------------------------------

    public function testUpdateGlvYear_ViewOnly_Forbidden(): void
    {
        // GLV/Thủ thư gọi updateGlvYear -> 403 (không có staff.edit).
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
    //  saveServiceHistory: chỉ 'staff.edit'
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
        self::assertSame(400, $r['code'],
            "xoá is_auto=1 phải 400 (không phải 403 do thiếu quyền), được {$r['code']}: {$r['raw']}");
        self::assertStringContainsString('tự động', (string) $r['raw'], 'thông điệp phải nói dòng do hệ thống tự tạo');
        self::assertNotNull(
            db_one('SELECT 1 FROM member_service_history WHERE member_id = ? AND year_label = ? AND is_auto = 1', [$target, '2020-2021']),
            'dòng tự sinh phải còn nguyên sau khi bị từ chối xoá');

        // Dọn
        db_run('DELETE FROM member_service_history WHERE member_id = ?', [$target]);
        db_run('UPDATE members SET glv_year = NULL WHERE id = ?', [$target]);
    }

    // ------------------------------------------------------------------
    //  Ca học (shift): bootstrap + data.php phải đưa ra cho giao diện
    // ------------------------------------------------------------------

    /** Gói khởi động page_bootstrap() nhúng trong trang chủ (window.TNTT_BOOT). */
    private function bootJson(int $memberId): array
    {
        $r = $this->http($memberId, 'GET', '/index.php');
        self::assertSame(200, $r['code'], 'Trang chủ sau đăng nhập phải 200: ' . substr($r['raw'], 0, 200));
        self::assertSame(1, preg_match('/window\.TNTT_BOOT = (\{.*?\});<\/script>/s', $r['raw'], $m),
            'Trang chủ phải nhúng window.TNTT_BOOT');
        $boot = json_decode($m[1], true);
        self::assertIsArray($boot, 'TNTT_BOOT phải là JSON hợp lệ');
        return $boot;
    }

    /** Phần 'core' của data.php dưới quyền người gọi. */
    private function dataCore(int $memberId): array
    {
        $r = $this->http($memberId, 'GET', '/api/data.php?part=core');
        self::assertSame(200, $r['code'], 'data.php phải 200: ' . substr($r['raw'], 0, 200));
        self::assertTrue($r['json']['ok'] ?? false, 'data.php phải ok: ' . substr($r['raw'], 0, 200));
        return $r['json'];
    }

    public function testBootstrapShift_AppearsInClasses(): void
    {
        $cls = db_one('SELECT c.id, c.name, c.shift FROM enrollments e JOIN classes c ON c.id = e.class_id
                        WHERE e.year_id = ? ORDER BY c.id LIMIT 1', [$this->yearId]);
        self::assertNotNull($cls, 'Cần ít nhất 1 em được ghi danh (tests/fixtures/ci_seed.php).');
        $old = $cls['shift'];

        try {
            db_run("UPDATE classes SET shift = 'chieu' WHERE id = ?", [$cls['id']]);

            // 1. Bootstrap (this.classes ở giao diện): mọi lớp có khoá shift, lớp vừa đặt khớp CSDL.
            $boot = $this->bootJson($this->who['glv']['id']);
            self::assertNotEmpty($boot['classes'], 'bootstrap phải có danh sách lớp');
            foreach ($boot['classes'] as $c) {
                self::assertArrayHasKey('shift', $c, "lớp {$c['name']} thiếu khoá shift");
            }
            $found = array_values(array_filter($boot['classes'], fn($c) => (int) $c['id'] === (int) $cls['id']));
            self::assertCount(1, $found);
            self::assertSame('chieu', $found[0]['shift'], 'shift trong bootstrap phải khớp CSDL');

            // 2. data.php: students[].classShift — chính trường màn điểm danh dùng để gắn nhãn ca.
            $data = $this->dataCore($this->who['bdh']['id']);
            $stu = array_values(array_filter($data['students'], fn($s) => $s['className'] === $cls['name']));
            self::assertNotEmpty($stu, 'phải có em thuộc lớp ' . $cls['name']);
            foreach ($stu as $s) self::assertSame('chieu', $s['classShift'], 'students[].classShift phải khớp ca của lớp');
            self::assertArrayNotHasKey('classes', $data, 'data.php không còn danh sách lớp thứ hai (đã có BOOT.classes + classCounts)');
        } finally {
            db_run('UPDATE classes SET shift = ? WHERE id = ?', [$old, $cls['id']]);
        }
    }

    // ------------------------------------------------------------------
    //  Phân quyền theo đối tượng (Trưởng khối có org.edit nhưng KHÔNG có staff.edit)
    // ------------------------------------------------------------------

    private function statusOf(int $id): string
    {
        return (string) db_one('SELECT status FROM members WHERE id = ?', [$id])['status'];
    }

    public function testWrites_TruongKhoi_Forbidden_AndNothingChanges(): void
    {
        // Tiền điều kiện của lỗ hổng: TK qua được cổng org.edit cũ.
        $tk = $this->makeMember('truong_khoi');
        $this->loginAs($tk);
        self::assertSame('edit', db_one("SELECT level FROM permissions WHERE module_key='org' AND role_code='truong_khoi'")['level'],
            'giả định của test: truong_khoi có org=edit');

        $victims = [$this->who['glv'], $this->who['admin'], $this->who['bdh']];
        foreach ($victims as $v) {
            $t = (int) $v['id'];
            $before = $this->statusOf($t);
            foreach ([
                ['changeMemberStatus',   ['member_id' => $t, 'status' => 'đã nghỉ', 'note' => 'tấn công']],
                ['updateGlvYear',        ['member_id' => $t, 'glv_year' => 2020]],
                ['saveServiceHistory',   ['member_id' => $t, 'year_label' => '2019-2020', 'role_code' => 'glv']],
                ['deleteServiceHistory', ['member_id' => $t, 'year_label' => '2019-2020']],
            ] as [$act, $body]) {
                $r = $this->http($tk['id'], 'POST', "/api/personal.php?action=$act", $body);
                self::assertSame(403, $r['code'], "[truong_khoi→{$v['role_code']}] $act phải 403, được {$r['code']}: {$r['raw']}");
            }
            self::assertSame($before, $this->statusOf($t), "trạng thái {$v['role_code']} không được đổi");
            $n = db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ?', [$t])['n'];
            self::assertSame(0, (int) $n, 'không được sinh lịch sử cho ' . $v['role_code']);
        }
    }

    public function testGetProfile_TruongKhoi_CannotReadOthers_OrAdmin(): void
    {
        $tk = $this->makeMember('truong_khoi');
        $this->loginAs($tk);

        $own = $this->http($tk['id'], 'GET', '/api/personal.php?action=getProfile');
        self::assertSame(200, $own['code'], 'TK xem của mình phải 200: ' . $own['raw']);

        // Người không có staff.edit: MỌI id khác mình đều 403 như nhau — người thường, admin, hay id không
        // tồn tại — để không dò được ai có/không có (hay là admin) bằng cách so 403/404.
        foreach (['glv' => $this->who['glv']['id'], 'admin' => $this->who['admin']['id'], 'không tồn tại' => 999999999] as $ten => $id) {
            $r = $this->http($tk['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $id);
            self::assertSame(403, $r['code'], "TK xem hồ sơ [$ten] phải 403, được {$r['code']}: {$r['raw']}");
        }
    }

    public function testAdminTarget_IsInvisibleToBdh_Returns404(): void
    {
        // F9: với người không phải admin, id của admin trả 404 y như id không tồn tại.
        $admin = (int) $this->who['admin']['id'];
        $bdh   = $this->who['bdh'];
        $before = $this->statusOf($admin);

        foreach ([
            ['GET',  'getProfile&member_id=' . $admin, null],
            ['POST', 'changeMemberStatus',   ['member_id' => $admin, 'status' => 'đã nghỉ', 'note' => 'khoá admin']],
            ['POST', 'updateGlvYear',        ['member_id' => $admin, 'glv_year' => 2020]],
            ['POST', 'saveServiceHistory',   ['member_id' => $admin, 'year_label' => '2019-2020', 'role_code' => 'glv']],
            ['POST', 'deleteServiceHistory', ['member_id' => $admin, 'year_label' => '2019-2020']],
        ] as [$method, $act, $body]) {
            $r = $this->http($bdh['id'], $method, "/api/personal.php?action=$act", $body);
            self::assertSame(404, $r['code'], "[bdh→admin] $act phải 404, được {$r['code']}: {$r['raw']}");
        }
        $none = $this->http($bdh['id'], 'GET', '/api/personal.php?action=getProfile&member_id=999999999');
        self::assertSame(404, $none['code'], 'id không tồn tại cũng 404 (không phân biệt được với admin)');
        self::assertSame($before, $this->statusOf($admin), 'admin không được bị khoá');

        // Chính admin thì thấy và sửa được hồ sơ của mình.
        $adm = $this->who['admin'];
        $r = $this->http($adm['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $admin);
        self::assertSame(200, $r['code'], 'admin xem hồ sơ admin phải 200: ' . $r['raw']);
    }

    public function testBdhTarget_OnlyAdminCanEditOtherBdh(): void
    {
        $bdh2 = $this->makeMember('bdh');                 // BĐH thứ hai (mục tiêu)
        $this->loginAs($bdh2);
        $bdh1 = $this->who['bdh'];
        $t = (int) $bdh2['id'];

        // BĐH khác -> 403 (#97), không đổi gì
        $r = $this->http($bdh1['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $t, 'status' => 'tạm nghỉ', 'note' => 'x']);
        self::assertSame(403, $r['code'], "BĐH→BĐH khác phải 403, được {$r['code']}: {$r['raw']}");
        self::assertSame('đang phục vụ', $this->statusOf($t));

        // Xem thì được (danh bạ vốn đã cho xem)
        $v = $this->http($bdh1['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $t);
        self::assertSame(200, $v['code'], 'BĐH xem hồ sơ BĐH khác phải 200: ' . $v['raw']);

        // Quản trị -> được
        $r = $this->http($this->who['admin']['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $t, 'status' => 'tạm nghỉ', 'note' => 'nghỉ phép']);
        self::assertSame(200, $r['code'], "admin→BĐH phải 200, được {$r['code']}: {$r['raw']}");
        self::assertSame('tạm nghỉ', $this->statusOf($t));

        // BĐH tự sửa năm GLV của chính mình thì được (guardTarget cho phép tự sửa)
        $self = $this->http($bdh2['id'], 'POST', '/api/personal.php?action=updateGlvYear',
            ['member_id' => $t, 'glv_year' => 2023]);
        self::assertSame(200, $self['code'], "BĐH tự đặt năm GLV của mình phải 200, được {$self['code']}: {$self['raw']}");
        db_run('DELETE FROM member_service_history WHERE member_id = ?', [$t]);
    }

    // ------------------------------------------------------------------
    //  changeMemberStatus
    // ------------------------------------------------------------------

    public function testChangeMemberStatus_Bdh_HappyPath_LogsAndFlushesCache(): void
    {
        $bdh = $this->who['bdh'];
        $t = (int) $this->who['glv']['id'];

        // Mồi cache của data.php rồi đổi trạng thái: người ghi phải thấy NGAY (Cache::flush).
        $find = fn(array $d) => array_values(array_filter($d['members'], fn($m) => (int) $m['id'] === $t))[0] ?? null;
        self::assertSame('đang phục vụ', $find($this->dataCore($bdh['id']))['status']);

        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $t, 'status' => 'tạm nghỉ', 'note' => 'Nghỉ thai sản']);
        self::assertSame(200, $r['code'], "đổi trạng thái phải 200, được {$r['code']}: {$r['raw']}");
        self::assertSame('tạm nghỉ', $this->statusOf($t));
        self::assertSame('Nghỉ thai sản', db_one('SELECT register_note FROM members WHERE id = ?', [$t])['register_note']);
        self::assertSame('tạm nghỉ', $find($this->dataCore($bdh['id']))['status'],
            'data.php phải phản ánh ngay trạng thái mới (thiếu Cache::flush sẽ trả bản cũ tới 60 giây)');

        self::assertNotNull(
            db_one("SELECT 1 FROM activity_logs WHERE actor_id = ? AND what LIKE 'Đổi trạng thái%'", [$bdh['id']]),
            'phải ghi nhật ký thao tác');

        // Quay lại phục vụ: ghi chú không bắt buộc
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus',
            ['member_id' => $t, 'status' => 'đang phục vụ']);
        self::assertSame(200, $r['code'], "quay lại phục vụ phải 200, được {$r['code']}: {$r['raw']}");
        self::assertSame('đang phục vụ', $this->statusOf($t));
    }

    public function testChangeMemberStatus_Rules(): void
    {
        $bdh = $this->who['bdh'];
        $glv = (int) $this->who['glv']['id'];
        $post = fn(array $b) => $this->http($bdh['id'], 'POST', '/api/personal.php?action=changeMemberStatus', $b);

        // thiếu ghi chú khi rời 'đang phục vụ' -> 400
        $r = $post(['member_id' => $glv, 'status' => 'đã nghỉ']);
        self::assertSame(400, $r['code'], 'thiếu ghi chú phải 400: ' . $r['raw']);
        // trạng thái lạ / 'chờ duyệt' không đặt được qua đây -> 400
        foreach (['bậy bạ', 'chờ duyệt'] as $bad) {
            $r = $post(['member_id' => $glv, 'status' => $bad, 'note' => 'x']);
            self::assertSame(400, $r['code'], "status '$bad' phải 400: " . $r['raw']);
        }
        self::assertSame('đang phục vụ', $this->statusOf($glv));

        // tự chuyển CHÍNH MÌNH sang 'đã nghỉ' (sẽ tự khoá tài khoản) -> 400
        $r = $post(['member_id' => (int) $bdh['id'], 'status' => 'đã nghỉ', 'note' => 'tự khoá']);
        self::assertSame(400, $r['code'], 'tự khoá mình phải 400: ' . $r['raw']);
        self::assertSame('đang phục vụ', $this->statusOf((int) $bdh['id']));

        // hồ sơ đang chờ duyệt -> 409 (phải đi qua Duyệt/Từ chối)
        $pending = $this->makeMember('glv', [], ['status' => 'chờ duyệt']);
        $r = $post(['member_id' => (int) $pending['id'], 'status' => 'đang phục vụ']);
        self::assertSame(409, $r['code'], 'chờ duyệt phải 409: ' . $r['raw']);
        self::assertSame('chờ duyệt', $this->statusOf((int) $pending['id']));

        // thành viên không tồn tại -> 404
        $r = $post(['member_id' => 999999999, 'status' => 'tạm nghỉ', 'note' => 'x']);
        self::assertSame(404, $r['code'], 'không tồn tại phải 404: ' . $r['raw']);
    }

    // ------------------------------------------------------------------
    //  Ghi lịch sử / năm GLV: dữ liệu vào phải hợp lệ
    // ------------------------------------------------------------------

    public function testSaveServiceHistory_RejectsBadInput_Without500(): void
    {
        $bdh = $this->who['bdh'];
        $t = (int) $this->who['thu_thu']['id'];
        foreach ([
            'năm không liền nhau' => ['year_label' => '2020-2025'],
            'vai trò lạ'          => ['year_label' => '2020-2021', 'role_code' => 'khong_co'],
            'vai admin'           => ['year_label' => '2020-2021', 'role_code' => 'admin'],
            'khối không có'       => ['year_label' => '2020-2021', 'block_id' => 987654],
            'lớp không có'        => ['year_label' => '2020-2021', 'class_id' => 987654],
            'ghi chú quá dài'     => ['year_label' => '2020-2021', 'note' => str_repeat('a', 256)],
        ] as $why => $body) {
            $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=saveServiceHistory', ['member_id' => $t] + $body);
            self::assertSame(400, $r['code'], "$why: phải 400 (không 500), được {$r['code']}: {$r['raw']}");
            self::assertStringNotContainsString('SQLSTATE', $r['raw'], "$why: không được lộ lỗi SQL");
        }
        self::assertSame(0, (int) db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ?', [$t])['n']);
    }

    public function testUpdateGlvYear_RejectsBadYear_AndUnknownMember(): void
    {
        $bdh = $this->who['bdh'];
        $t = (int) $this->who['thu_thu']['id'];
        foreach ([1899, PersonalService::currentSchoolStartYear() + 1] as $y) {
            $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => $t, 'glv_year' => $y]);
            self::assertSame(400, $r['code'], "năm $y phải 400, được {$r['code']}: {$r['raw']}");
        }
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => 999999999, 'glv_year' => 2020]);
        self::assertSame(404, $r['code'], 'member không tồn tại phải 404: ' . $r['raw']);
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['glv_year' => 2020]);
        self::assertSame(400, $r['code'], 'thiếu member_id phải 400: ' . $r['raw']);
    }

    public function testUpdateGlvYear_FlushesDataCache(): void
    {
        $bdh = $this->who['bdh'];
        $t = (int) $this->who['glv']['id'];
        $find = fn(array $d) => array_values(array_filter($d['members'], fn($m) => (int) $m['id'] === $t))[0] ?? null;
        self::assertNull($find($this->dataCore($bdh['id']))['glv_year'], 'tiền điều kiện: chưa có năm GLV (đồng thời mồi cache)');

        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => $t, 'glv_year' => 2022]);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertSame(2022, $find($this->dataCore($bdh['id']))['glv_year'],
            'data.php phải phản ánh ngay năm GLV mới (Cache::flush)');
    }

    // ------------------------------------------------------------------
    //  Toàn vẹn: xoá thành viên thì lịch sử đi theo (khoá ngoại)
    // ------------------------------------------------------------------

    public function testDeletingMember_CascadesServiceHistory(): void
    {
        $m = $this->makeMember('glv');
        $id = (int) $m['id'];
        PersonalService::saveServiceHistory($id, ['year_label' => '2020-2021', 'role_code' => 'glv'], (int) $this->who['bdh']['id']);
        self::assertSame(1, (int) db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ?', [$id])['n']);

        db_run('DELETE FROM members WHERE id = ?', [$id]);
        self::assertSame(0, (int) db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ?', [$id])['n'],
            'xoá thành viên phải xoá luôn lịch sử phục vụ (FK ON DELETE CASCADE), không để dòng mồ côi');
    }

    // ------------------------------------------------------------------
    //  Năm GLV: chặn trên theo NĂM HỌC, bắt buộc có khoá glv_year
    // ------------------------------------------------------------------

    public function testUpdateGlvYear_RejectsYearAfterCurrentSchoolYear_AndMissingKey(): void
    {
        $bdh = $this->who['bdh'];
        $t = (int) $this->who['glv']['id'];
        $latest = PersonalService::currentSchoolStartYear();

        // Năm học chưa bắt đầu (từ tháng 1–7 đây chính là năm dương lịch hiện tại): lỗi cũ cho qua rồi xoá sạch dòng tự sinh.
        PersonalService::updateGlvYear($t, $latest - 1, (int) $bdh['id']);
        $truoc = db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ? AND is_auto = 1', [$t])['n'];
        self::assertGreaterThan(0, (int) $truoc);

        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => $t, 'glv_year' => $latest + 1]);
        self::assertSame(400, $r['code'], "năm " . ($latest + 1) . " (sau niên khoá hiện tại) phải 400, được {$r['code']}: {$r['raw']}");

        // Thiếu khoá glv_year KHÔNG được hiểu là xoá năm GLV.
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => $t]);
        self::assertSame(400, $r['code'], "thiếu glv_year phải 400, được {$r['code']}: {$r['raw']}");

        self::assertSame($latest - 1, (int) db_one('SELECT glv_year FROM members WHERE id = ?', [$t])['glv_year'], 'glv_year không đổi');
        self::assertSame((int) $truoc, (int) db_one('SELECT COUNT(*) n FROM member_service_history WHERE member_id = ? AND is_auto = 1', [$t])['n'],
            'các dòng tự sinh không được mất');

        // null (có khoá) mới là xoá
        $r = $this->http($bdh['id'], 'POST', '/api/personal.php?action=updateGlvYear', ['member_id' => $t, 'glv_year' => null]);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertNull(db_one('SELECT glv_year FROM members WHERE id = ?', [$t])['glv_year']);
    }

    // ------------------------------------------------------------------
    //  Mục tiêu "được bảo vệ" chỉ nhờ PHÂN CÔNG (vai gốc bình thường)
    // ------------------------------------------------------------------

    public function testTargetProtectedOnlyByAssignment(): void
    {
        $bdh1  = $this->who['bdh'];
        $adm   = $this->who['admin'];
        $coBdh = $this->makeMember('glv', [['bdh', null, null]]);     // vai gốc glv, đang giữ phân công BĐH
        $coAdm = $this->makeMember('glv', [['admin', null, null]]);   // vai gốc glv, đang giữ phân công Quản trị
        $this->loginAs($coAdm);

        $post = fn(int $caller, int $t, string $act, array $b) =>
            $this->http($caller, 'POST', "/api/personal.php?action=$act", ['member_id' => $t] + $b);
        $status = ['status' => 'tạm nghỉ', 'note' => 'x'];

        // BĐH → người giữ phân công BĐH: 403 (#97); Quản trị → được.
        self::assertSame(403, $post($bdh1['id'], (int) $coBdh['id'], 'changeMemberStatus', $status)['code'], 'BĐH→co-BĐH phải 403');
        self::assertSame('đang phục vụ', $this->statusOf((int) $coBdh['id']));
        self::assertSame(200, $post($adm['id'], (int) $coBdh['id'], 'changeMemberStatus', $status)['code'], 'admin→co-BĐH phải 200');

        // BĐH → người giữ phân công Quản trị: 404 như không tồn tại (F9); xem hồ sơ cũng 404.
        self::assertSame(404, $post($bdh1['id'], (int) $coAdm['id'], 'changeMemberStatus', $status)['code'], 'BĐH→co-admin phải 404');
        self::assertSame(404, $this->http($bdh1['id'], 'GET', '/api/personal.php?action=getProfile&member_id=' . $coAdm['id'])['code']);
        self::assertSame('đang phục vụ', $this->statusOf((int) $coAdm['id']));
        self::assertSame(200, $post($adm['id'], (int) $coAdm['id'], 'changeMemberStatus', $status)['code'], 'admin→co-admin phải 200');
        db_run("UPDATE members SET status = 'đang phục vụ' WHERE id = ?", [$coAdm['id']]);

        // Người chỉ giữ phân công Quản trị vẫn xem được hồ sơ CỦA MÌNH (không bị ẩn khỏi chính mình).
        $r = $this->http($coAdm['id'], 'GET', '/api/personal.php?action=getProfile');
        self::assertSame(200, $r['code'], 'co-admin xem hồ sơ của mình phải 200, được ' . $r['code'] . ': ' . $r['raw']);
    }

    // ------------------------------------------------------------------
    //  Không lộ cột nội bộ: assigned_by (thường là Quản trị), note, created_by
    // ------------------------------------------------------------------

    public function testResponses_DoNotLeakInternalColumns(): void
    {
        $bdh = $this->who['bdh'];
        $t = $this->makeMember('glv', [['glv_chu_nhiem', null, (int) db_one('SELECT id FROM classes ORDER BY id LIMIT 1')['id']]]);
        db_run("UPDATE member_assignments SET note = 'ghi chú nội bộ của BĐH' WHERE member_id = ?", [$t['id']]);
        PersonalService::updateGlvYear((int) $t['id'], PersonalService::currentSchoolStartYear() - 1, (int) $bdh['id']);
        $this->loginAs($t);

        $kiem = function (array $rows, array $cam, string $nhan) {
            self::assertNotEmpty($rows, "$nhan phải có dòng để kiểm");
            foreach ($rows as $row) foreach ($cam as $col) self::assertArrayNotHasKey($col, $row, "$nhan không được trả cột $col");
        };

        // data.php (danh bạ nhân sự): all_assignments
        $d = $this->dataCore($bdh['id']);
        $m = array_values(array_filter($d['members'], fn($x) => (int) $x['id'] === (int) $t['id']))[0];
        $kiem($m['all_assignments'], ['assigned_by', 'note'], 'data.php members[].all_assignments');
        self::assertSame('glv_chu_nhiem', $m['all_assignments'][0]['role_code']);
        self::assertNotEmpty($m['all_assignments'][0]['class_name'], 'vẫn có tên lớp để hiển thị');

        // personal.php: người dùng xem hồ sơ CỦA MÌNH (GLV) và BĐH xem người khác
        foreach ([$t['id'] => 'GLV tự xem', $bdh['id'] => 'BĐH xem người khác'] as $caller => $nhan) {
            $r = $this->http($caller, 'GET', '/api/personal.php?action=getProfile&member_id=' . $t['id']);
            self::assertSame(200, $r['code'], "$nhan: " . $r['raw']);
            $kiem($r['json']['data']['assignments'], ['assigned_by', 'note'], "$nhan: assignments");
            $kiem($r['json']['data']['history'], ['created_by'], "$nhan: history");
        }
    }
}
