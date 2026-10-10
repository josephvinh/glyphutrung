<?php
// tests/unit/StaffGuardTest.php
//
// AI ĐƯỢC LÀM GÌ VỚI AI trên hồ sơ nhân sự (P1 / #97) — đặc tả: docs/audit/P1_design.md
// mục 2.2 (bảng), 5.3 (STAFF-06a…j).
//
// Hai tầng:
//  1. StaffService::guardTarget($target, $op): kiểm đơn vị TRỰC TIẾP từng ô của bảng 2.2
//     (người gọi × mục tiêu × thao tác), gồm cả phòng thủ theo chiều sâu qua phân công
//     admin/bdh (has_active_role cần DB thật).
//  2. Đầu-cuối qua HTTP thật tới api/org.php (saveMember, deleteMember, resetPassword,
//     approveMember, rejectMember) bằng phiên + CSRF thật — để chứng minh các thao tác
//     THỰC SỰ gọi guard (saveMember/deleteMember ở StaffService; approve/reject/reset
//     là mã inline trong org.php nên không gọi được trực tiếp) và DB không đổi khi bị chặn.
//
// Dữ liệu tạo trong từng test, dọn trong tearDown; không phụ thuộc thứ tự chạy.

require_once __DIR__ . '/P1ApiHarness.php';
require_once __DIR__ . '/../../public/api/StaffService.php';

use PHPUnit\Framework\TestCase;

class StaffGuardTest extends TestCase
{
    use P1ApiHarness;

    private const MSG_NOT_FOUND = 'Không tìm thấy thành viên.';
    private const OPS = ['edit', 'delete', 'reset', 'approve'];

    private int $yearId = 0;
    private array $classA;
    private array $classA2;

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
        $year = current_year();
        self::assertNotNull($year, 'Cần niên khoá hiện tại.');
        $this->yearId = (int) $year['id'];
        $a = db_one('SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1');
        self::assertNotNull($a);
        $a2 = db_one('SELECT id, block_id FROM classes WHERE block_id = ? AND id <> ? ORDER BY id LIMIT 1',
                     [$a['block_id'], $a['id']]);
        self::assertNotNull($a2, 'Cần khối có ≥ 2 lớp.');
        [$this->classA, $this->classA2] = [$a, $a2];

        // Ma trận mặc định của install.php, đặt tường minh: chỉ admin/bdh sửa được nhân sự;
        // truong_khoi mặc định chỉ xem (từng test đặt staff=edit khi cần).
        foreach (['admin' => 'edit', 'bdh' => 'edit', 'truong_khoi' => 'view', 'glv_chu_nhiem' => 'view',
                  'glv' => 'view', 'du_bi' => 'view', 'thu_thu' => 'none'] as $role => $lv) {
            $this->setPerm('staff', $role, $lv);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanupHarness();
    }

    // ==================================================================
    //  Dựng người
    // ==================================================================

    private function admin(): array { return $this->makeMember('admin', [['admin', null, null]]); }
    private function bdh(): array   { return $this->makeMember('bdh', [['bdh', null, null]]); }

    private function truongKhoi(): array
    {
        $b = (int) $this->classA['block_id'];
        return $this->makeMember('truong_khoi', [['truong_khoi', $b, null]], ['block_id' => $b]);
    }

    /** GLV thường (có phân công lớp A → chỉ sửa được danh tính, không đòi chọn lớp). */
    private function glv(): array
    {
        $A = (int) $this->classA['id'];
        return $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]);
    }

    private function pending(): array
    {
        return $this->makeMember('glv', [], ['status' => 'chờ duyệt', 'register_note' => 'xin vào']);
    }

    private function svc(array $caller): StaffService
    {
        return new StaffService($caller, $this->yearId, []);
    }

    /** Mục tiêu đúng dạng các nơi gọi guardTarget truyền vào (id, role_code, …). */
    private function target(array $m): array
    {
        return ['id' => (int) $m['id'], 'role_code' => $m['role_code'], 'full_name' => $m['full_name']];
    }

    private function assertAllowed(array $caller, array $target, string $op, string $msg): void
    {
        self::assertNull($this->svc($caller)->guardTarget($this->target($target), $op), "$msg ($op) phải được phép");
    }

    private function assertDenied(int $code, array $caller, array $target, string $op, string $msg): array
    {
        $r = $this->svc($caller)->guardTarget($this->target($target), $op);
        self::assertIsArray($r, "$msg ($op) phải bị chặn");
        self::assertFalse($r['ok'], "$msg ($op)");
        self::assertSame($code, $r['code'], "$msg ($op): mã lỗi");
        self::assertNotSame('', (string) $r['error'], "$msg ($op): phải có thông điệp");
        return $r;
    }

    // ==================================================================
    //  TẦNG 1 — guardTarget() (đơn vị)
    // ==================================================================

    public function test_admin_may_edit_reset_and_approve_anyone(): void
    {
        $admin = $this->admin();
        $others = [
            'admin khác' => $this->admin(), 'bdh' => $this->bdh(), 'người thường' => $this->glv(),
            'hồ sơ chờ duyệt' => $this->pending(),
        ];
        foreach ($others as $label => $t) {
            foreach (['edit', 'reset', 'approve'] as $op) {
                $this->assertAllowed($admin, $t, $op, "admin → $label");
            }
        }
        foreach (['edit', 'reset', 'approve'] as $op) {
            $this->assertAllowed($admin, $admin, $op, 'admin → chính mình');
        }
    }

    public function test_admin_delete_rules(): void
    {
        $admin = $this->admin();
        // Người thường / hồ sơ chờ duyệt: xoá được.
        $this->assertAllowed($admin, $this->glv(), 'delete', 'admin → người thường');
        $this->assertAllowed($admin, $this->pending(), 'delete', 'admin → chờ duyệt');
        // admin / bdh: 400, thông điệp cũ giữ nguyên.
        $r = $this->assertDenied(400, $admin, $this->admin(), 'delete', 'admin → admin khác');
        self::assertSame('Không thể xóa tài khoản Quản trị hoặc Ban Điều Hành.', $r['error']);
        $r = $this->assertDenied(400, $admin, $this->bdh(), 'delete', 'admin → bdh');
        self::assertSame('Không thể xóa tài khoản Quản trị hoặc Ban Điều Hành.', $r['error']);
        // Không tự xoá chính mình.
        $r = $this->assertDenied(400, $admin, $admin, 'delete', 'admin → chính mình');
        self::assertSame('Không thể tự xoá tài khoản của chính mình.', $r['error']);
    }

    public function test_bdh_gets_404_identical_to_missing_id_for_admin_target_on_every_op(): void
    {
        $bdh = $this->bdh();
        $admin = $this->admin();
        foreach (self::OPS as $op) {
            $r = $this->assertDenied(404, $bdh, $admin, $op, 'bdh → admin');
            self::assertSame(self::MSG_NOT_FOUND, $r['error'], "bdh → admin ($op): thông điệp phải GIỐNG HỆT id không tồn tại");
            self::assertSame(['ok', 'error', 'code'], array_keys($r));
        }
    }

    public function test_bdh_gets_403_for_other_bdh_on_every_op_with_per_op_message(): void
    {
        $bdh = $this->bdh();
        $other = $this->bdh();
        $expected = [
            'edit'    => 'Chỉ Quản Trị Hệ Thống mới sửa được hồ sơ thành viên Ban Điều Hành.',
            'delete'  => 'Chỉ Quản Trị Hệ Thống mới xoá được hồ sơ thành viên Ban Điều Hành.',
            'reset'   => 'Chỉ Quản Trị Hệ Thống mới cấp lại mật khẩu cho Ban Điều Hành.',
            'approve' => 'Chỉ Quản Trị Hệ Thống mới duyệt được hồ sơ thành viên Ban Điều Hành.',
        ];
        foreach (self::OPS as $op) {
            $r = $this->assertDenied(403, $bdh, $other, $op, 'bdh → bdh khác');
            self::assertSame($expected[$op], $r['error'], "thông điệp 403 cho $op");
        }
    }

    public function test_bdh_may_edit_own_identity_but_not_delete_reset_or_approve_self(): void
    {
        $bdh = $this->bdh();
        $this->assertAllowed($bdh, $bdh, 'edit', 'bdh → chính mình');
        $this->assertDenied(403, $bdh, $bdh, 'delete', 'bdh → chính mình');
        $this->assertDenied(403, $bdh, $bdh, 'reset', 'bdh → chính mình');
        $this->assertDenied(403, $bdh, $bdh, 'approve', 'bdh → chính mình');

        // Cả khi bdh chỉ có vai gốc (không phân công).
        $bare = $this->makeMember('bdh');
        $this->assertAllowed($bare, $bare, 'edit', 'bdh (không phân công) → chính mình');
        $this->assertDenied(403, $bare, $bare, 'delete', 'bdh (không phân công) → chính mình');
    }

    public function test_bdh_may_operate_on_ordinary_members_and_pending_profiles(): void
    {
        $bdh = $this->bdh();
        foreach (['người thường' => $this->glv(), 'chờ duyệt' => $this->pending()] as $label => $t) {
            foreach (self::OPS as $op) {
                $this->assertAllowed($bdh, $t, $op, "bdh → $label");
            }
        }
    }

    public function test_truong_khoi_with_staff_edit_gets_404_for_admin_and_403_for_bdh(): void
    {
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $tk = $this->truongKhoi();
        $admin = $this->admin();
        $bdh = $this->bdh();
        foreach (self::OPS as $op) {
            $r = $this->assertDenied(404, $tk, $admin, $op, 'tk(staff=edit) → admin');
            self::assertSame(self::MSG_NOT_FOUND, $r['error']);
            $this->assertDenied(403, $tk, $bdh, $op, 'tk(staff=edit) → bdh');
        }
        foreach (self::OPS as $op) {
            $this->assertAllowed($tk, $this->glv(), $op, 'tk(staff=edit) → người thường');
        }
        $this->assertAllowed($tk, $tk, 'edit', 'tk → chính mình');
        $r = $this->assertDenied(400, $tk, $tk, 'delete', 'tk → chính mình');
        self::assertSame('Không thể tự xoá tài khoản của chính mình.', $r['error']);
    }

    public function test_nobody_below_admin_can_delete_self_with_400(): void
    {
        foreach (['glv' => $this->glv(), 'tk' => $this->truongKhoi()] as $label => $m) {
            $r = $this->assertDenied(400, $m, $m, 'delete', "$label → chính mình");
            self::assertSame('Không thể tự xoá tài khoản của chính mình.', $r['error']);
        }
    }

    public function test_protection_follows_active_bdh_or_admin_assignment(): void
    {
        // Phòng thủ theo chiều sâu: vai gốc glv nhưng đang kiêm phân công bdh/admin.
        $A = (int) $this->classA['id'];
        $glvAsBdh   = $this->makeMember('glv', [['glv', null, $A], ['bdh', null, null]], ['class_id' => $A]);
        $glvAsAdmin = $this->makeMember('glv', [['glv', null, $A], ['admin', null, null]], ['class_id' => $A]);
        $bdh = $this->bdh();
        $tk = $this->truongKhoi();
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $admin = $this->admin();

        foreach ([$bdh, $tk] as $caller) {
            foreach (self::OPS as $op) {
                $this->assertDenied(403, $caller, $glvAsBdh, $op, "{$caller['role_code']} → glv kiêm bdh");
                $r = $this->assertDenied(404, $caller, $glvAsAdmin, $op, "{$caller['role_code']} → glv kiêm admin");
                self::assertSame(self::MSG_NOT_FOUND, $r['error']);
            }
        }
        // Admin: sửa được, nhưng không xoá được người đang giữ vai bảo vệ.
        $this->assertAllowed($admin, $glvAsBdh, 'edit', 'admin → glv kiêm bdh');
        $this->assertDenied(400, $admin, $glvAsBdh, 'delete', 'admin → glv kiêm bdh');
        $this->assertDenied(400, $admin, $glvAsAdmin, 'delete', 'admin → glv kiêm admin');
        // Người kiêm bdh tự sửa danh tính mình: được.
        $this->assertAllowed($glvAsBdh, $glvAsBdh, 'edit', 'glv kiêm bdh → chính mình');

        // Phân công ĐÃ KẾT THÚC thì không còn được bảo vệ.
        $ended = $this->makeMember('glv', [['glv', null, $A], ['bdh', null, null]], ['class_id' => $A]);
        db_run("UPDATE member_assignments SET to_date = CURDATE() WHERE member_id = ? AND role_code = 'bdh'", [$ended['id']]);
        foreach (self::OPS as $op) {
            $this->assertAllowed($bdh, $ended, $op, 'bdh → glv hết kiêm bdh');
        }
    }

    public function test_is_protected_matches_role_or_active_assignment(): void
    {
        $svc = $this->svc($this->admin());
        $A = (int) $this->classA['id'];
        self::assertTrue($svc->isProtected($this->target($this->admin())));
        self::assertTrue($svc->isProtected($this->target($this->bdh())));
        self::assertTrue($svc->isProtected($this->target(
            $this->makeMember('glv', [['glv', null, $A], ['bdh', null, null]], ['class_id' => $A]))));
        self::assertTrue($svc->isProtected($this->target(
            $this->makeMember('glv', [['glv', null, $A], ['admin', null, null]], ['class_id' => $A]))));
        self::assertFalse($svc->isProtected($this->target($this->glv())));
        self::assertFalse($svc->isProtected($this->target($this->truongKhoi())));
    }

    // ==================================================================
    //  TẦNG 2 — api/org.php qua HTTP thật (STAFF-06a…j)
    // ==================================================================

    /** @return array{code:int, raw:string, json:?array} */
    private function org(array $caller, string $action, array $body): array
    {
        $this->loginAs($caller);
        return $this->http((int) $caller['id'], 'POST', '/api/org.php?action=' . $action, $body);
    }

    private function row(int $id): ?array
    {
        return db_one('SELECT id, role_code, holy_name, full_name, phone, title_id, status, must_change_pw, password_hash
                         FROM members WHERE id = ?', [$id]);
    }

    private function newPhone(): string
    {
        do { $p = '09' . random_int(10000000, 99999999); } while (db_one('SELECT id FROM members WHERE phone=?', [$p]));
        return $p;
    }

    /** Hai chức danh khác nhau của BĐH (seed có 5). @return array{0:array,1:array} */
    private function twoBdhTitles(): array
    {
        $t = db_all("SELECT id, label FROM titles WHERE role_code = 'bdh' ORDER BY sort_order LIMIT 2");
        self::assertCount(2, $t, 'Cần ≥ 2 chức danh BĐH để kiểm "không đổi chức danh".');
        return [$t[0], $t[1]];
    }

    private function identityBody(array $m, array $over = []): array
    {
        return array_merge(['id' => (int) $m['id'], 'holyName' => 'Phêrô', 'fullName' => 'Ten Moi Test',
                            'phone' => $this->newPhone(), 'role' => $m['role_code']], $over);
    }

    public function test_06a_bdh_cannot_edit_admin_404_and_admin_can_still_login(): void
    {
        $bdh = $this->bdh();
        $admin = $this->admin();
        $before = $this->row((int) $admin['id']);

        $r = $this->org($bdh, 'saveMember', $this->identityBody($admin, ['fullName' => 'Bi Doi Ten']));
        self::assertSame(404, $r['code'], $r['raw']);
        // New API: error.message (or old: error as string)
        $errorMsg = $r['json']['error']['message'] ?? $r['json']['error'] ?? null;
        self::assertSame(self::MSG_NOT_FOUND, $errorMsg);
        self::assertSame($before, $this->row((int) $admin['id']), 'DB không được đổi');

        // admin vẫn đăng nhập được bằng SĐT cũ (200) — đây chính là hậu quả #97.
        $this->loginAs($admin);
    }

    public function test_06b_bdh_cannot_edit_other_bdh_403(): void
    {
        $bdh = $this->bdh();
        $other = $this->bdh();
        $before = $this->row((int) $other['id']);

        $r = $this->org($bdh, 'saveMember', $this->identityBody($other));
        self::assertSame(403, $r['code'], $r['raw']);
        $errorMsg = $r['json']['error']['message'] ?? $r['json']['error'] ?? null;
        self::assertSame('Chỉ Quản Trị Hệ Thống mới sửa được hồ sơ thành viên Ban Điều Hành.', $errorMsg);
        self::assertSame($before, $this->row((int) $other['id']));
    }

    public function test_06c_bdh_edits_own_identity_but_title_and_role_stay(): void
    {
        [$t1, $t2] = $this->twoBdhTitles();
        // hai biến thể: có phân công (nhánh identityOnly) và chỉ vai gốc (nhánh đơn vai).
        foreach (['có phân công' => $this->bdh(), 'vai gốc' => $this->makeMember('bdh')] as $label => $bdh) {
            db_run('UPDATE members SET title_id = ? WHERE id = ?', [$t1['id'], $bdh['id']]);
            $newPhone = $this->newPhone();

            $r = $this->org($bdh, 'saveMember', $this->identityBody($bdh, [
                'fullName' => 'Bdh Tu Sua', 'phone' => $newPhone, 'title' => $t2['label'],
                'role' => 'glv',          // thử hạ vai: phải bị bỏ qua
            ]));
            self::assertSame(200, $r['code'], "$label: " . $r['raw']);
            $after = $this->row((int) $bdh['id']);
            self::assertSame('Bdh Tu Sua', $after['full_name'], "$label: họ tên phải đổi");
            self::assertSame($newPhone, $after['phone'], "$label: SĐT (tên đăng nhập) phải đổi");
            self::assertSame((int) $t1['id'], (int) $after['title_id'], "$label: chức danh KHÔNG được đổi");
            self::assertSame('bdh', $after['role_code'], "$label: vai KHÔNG được đổi");
        }
    }

    public function test_06c_bdh_cannot_promote_self_to_admin(): void
    {
        $bdh = $this->bdh();
        $r = $this->org($bdh, 'saveMember', $this->identityBody($bdh, ['role' => 'admin']));
        self::assertSame(200, $r['code'], 'vai bị khoá nên chỉ lưu danh tính: ' . $r['raw']);
        self::assertSame('bdh', $this->row((int) $bdh['id'])['role_code'], 'không được leo thang lên admin');
    }

    public function test_06d_bdh_delete_admin_404_other_bdh_403_self_403(): void
    {
        $bdh = $this->bdh();
        $admin = $this->admin();
        $other = $this->bdh();
        $cases = [[$admin, 404], [$other, 403], [$bdh, 403]];
        foreach ($cases as [$t, $code]) {
            $r = $this->org($bdh, 'deleteMember', ['id' => (int) $t['id']]);
            self::assertSame($code, $r['code'], "xoá #{$t['id']}: " . $r['raw']);
            self::assertNotNull($this->row((int) $t['id']), "#{$t['id']} không được bị xoá");
        }
    }

    public function test_06e_bdh_reset_password_admin_404_other_bdh_403_hash_unchanged(): void
    {
        $bdh = $this->bdh();
        $admin = $this->admin();
        $other = $this->bdh();
        foreach ([[$admin, 404], [$other, 403], [$bdh, 403]] as [$t, $code]) {
            $before = $this->row((int) $t['id']);
            $r = $this->org($bdh, 'resetPassword', ['id' => (int) $t['id']]);
            self::assertSame($code, $r['code'], "reset #{$t['id']}: " . $r['raw']);
            self::assertSame($before['password_hash'], $this->row((int) $t['id'])['password_hash'], 'password_hash không được đổi');
            self::assertSame((int) $before['must_change_pw'], (int) $this->row((int) $t['id'])['must_change_pw']);
        }
    }

    public function test_06f_bdh_approve_and_reject_admin_404_and_other_bdh_403(): void
    {
        $bdh = $this->bdh();
        $admin = $this->admin();
        $other = $this->bdh();
        foreach (['approveMember', 'rejectMember'] as $action) {
            $r = $this->org($bdh, $action, ['id' => (int) $admin['id'], 'role' => 'glv']);
            self::assertSame(404, $r['code'], "$action admin: " . $r['raw']);
            $r = $this->org($bdh, $action, ['id' => (int) $other['id'], 'role' => 'glv']);
            self::assertSame(403, $r['code'], "$action bdh khác: " . $r['raw']);
            self::assertNotNull($this->row((int) $admin['id']));
            self::assertNotNull($this->row((int) $other['id']));
        }
        self::assertSame('admin', $this->row((int) $admin['id'])['role_code']);
    }

    public function test_06g_admin_edits_bdh_identity_but_role_stays(): void
    {
        $admin = $this->admin();
        $bdh = $this->bdh();
        $newPhone = $this->newPhone();
        $r = $this->org($admin, 'saveMember', $this->identityBody($bdh, ['fullName' => 'Admin Sua Bdh', 'phone' => $newPhone, 'role' => 'glv']));
        self::assertSame(200, $r['code'], $r['raw']);
        $after = $this->row((int) $bdh['id']);
        self::assertSame('Admin Sua Bdh', $after['full_name']);
        self::assertSame($newPhone, $after['phone']);
        self::assertSame('bdh', $after['role_code'], 'vai BĐH vẫn bị khoá kể cả với admin');
    }

    public function test_06h_bdh_edits_ordinary_glv(): void
    {
        $bdh = $this->bdh();
        $glv = $this->glv();
        $newPhone = $this->newPhone();
        $r = $this->org($bdh, 'saveMember', $this->identityBody($glv, ['fullName' => 'Glv Duoc Sua', 'phone' => $newPhone]));
        self::assertSame(200, $r['code'], $r['raw']);
        $after = $this->row((int) $glv['id']);
        self::assertSame('Glv Duoc Sua', $after['full_name']);
        self::assertSame($newPhone, $after['phone']);
    }

    public function test_06i_admin_reset_password_for_bdh_sets_must_change_pw(): void
    {
        $admin = $this->admin();
        $bdh = $this->bdh();
        $before = $this->row((int) $bdh['id']);
        self::assertSame(0, (int) $before['must_change_pw']);

        $r = $this->org($admin, 'resetPassword', ['id' => (int) $bdh['id']]);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertTrue($r['json']['ok']);
        $after = $this->row((int) $bdh['id']);
        self::assertSame(1, (int) $after['must_change_pw']);
        self::assertNotSame($before['password_hash'], $after['password_hash']);
        self::assertTrue(password_verify($r['json']['password'], $after['password_hash']), 'mật khẩu tạm trả về phải khớp hash mới');
    }

    public function test_06j_404_for_admin_is_identical_to_404_for_unknown_id(): void
    {
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $admin = $this->admin();
        $unknown = 2147483000;
        self::assertNull($this->row($unknown));
        foreach (['bdh' => $this->bdh(), 'tk' => $this->truongKhoi()] as $who => $caller) {
            foreach (['saveMember', 'deleteMember', 'resetPassword', 'approveMember', 'rejectMember'] as $action) {
                $forAdmin   = $this->org($caller, $action, $this->identityBody($admin) + ['role' => 'glv']);
                $forUnknown = $this->org($caller, $action, $this->identityBody(['id' => $unknown, 'role_code' => 'glv']) + ['role' => 'glv']);
                self::assertSame(404, $forAdmin['code'], "$who/$action admin");
                self::assertSame(404, $forUnknown['code'], "$who/$action id lạ");
                // Security: compare error structure, ignore meta (has random requestId/timestamp)
                $errorAdmin = $forAdmin['json']['error'] ?? null;
                $errorUnknown = $forUnknown['json']['error'] ?? null;
                self::assertSame($errorUnknown, $errorAdmin,
                    "$who/$action: thông điệp 404 cho admin phải GIỐNG HỆT id không tồn tại");
            }
        }
    }

    public function test_truong_khoi_with_staff_edit_is_blocked_on_admin_and_bdh_over_http(): void
    {
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $tk = $this->truongKhoi();
        $admin = $this->admin();
        $bdh = $this->bdh();
        $bAdmin = $this->row((int) $admin['id']);
        $bBdh = $this->row((int) $bdh['id']);

        $calls = [
            'saveMember'    => fn($t) => $this->identityBody($t),
            'deleteMember'  => fn($t) => ['id' => (int) $t['id']],
            'resetPassword' => fn($t) => ['id' => (int) $t['id']],
            'approveMember' => fn($t) => ['id' => (int) $t['id'], 'role' => 'glv'],
            'rejectMember'  => fn($t) => ['id' => (int) $t['id']],
        ];
        foreach ($calls as $action => $body) {
            self::assertSame(404, $this->org($tk, $action, $body($admin))['code'], "tk $action admin");
            self::assertSame(403, $this->org($tk, $action, $body($bdh))['code'], "tk $action bdh");
        }
        self::assertSame($bAdmin, $this->row((int) $admin['id']));
        self::assertSame($bBdh, $this->row((int) $bdh['id']));
    }

    public function test_tk_with_staff_edit_can_edit_ordinary_glv_but_not_delete_self(): void
    {
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $tk = $this->truongKhoi();
        $glv = $this->glv();
        $r = $this->org($tk, 'saveMember', $this->identityBody($glv, ['fullName' => 'Tk Sua Glv']));
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertSame('Tk Sua Glv', $this->row((int) $glv['id'])['full_name']);

        $r = $this->org($tk, 'deleteMember', ['id' => (int) $tk['id']]);
        self::assertSame(400, $r['code'], $r['raw']);
        $errorMsg = $r['json']['error']['message'] ?? $r['json']['error'] ?? null;
        self::assertSame('Không thể tự xoá tài khoản của chính mình.', $errorMsg);
        self::assertNotNull($this->row((int) $tk['id']));
    }

    public function test_delete_rules_over_http(): void
    {
        $admin = $this->admin();
        $bdh = $this->bdh();
        $victim = $this->glv();
        $pending = $this->pending();

        // admin: xoá người thường + hồ sơ chờ duyệt được; admin/bdh/chính mình thì 400, không xoá.
        self::assertSame(200, $this->org($admin, 'deleteMember', ['id' => (int) $victim['id']])['code']);
        self::assertNull($this->row((int) $victim['id']));
        self::assertSame(200, $this->org($admin, 'deleteMember', ['id' => (int) $pending['id']])['code']);
        self::assertNull($this->row((int) $pending['id']));
        foreach ([$bdh, $this->admin(), $admin] as $t) {
            $r = $this->org($admin, 'deleteMember', ['id' => (int) $t['id']]);
            self::assertSame(400, $r['code'], "admin xoá #{$t['id']}: " . $r['raw']);
            self::assertNotNull($this->row((int) $t['id']));
        }

        // bdh: xoá được người thường.
        $victim2 = $this->glv();
        self::assertSame(200, $this->org($bdh, 'deleteMember', ['id' => (int) $victim2['id']])['code']);
        self::assertNull($this->row((int) $victim2['id']));
    }

    public function test_approve_reject_reset_work_on_ordinary_targets_for_bdh(): void
    {
        $bdh = $this->bdh();

        $p1 = $this->pending();
        $r = $this->org($bdh, 'approveMember', ['id' => (int) $p1['id'], 'role' => 'glv']);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertSame('đang phục vụ', $this->row((int) $p1['id'])['status']);

        $p2 = $this->pending();
        $r = $this->org($bdh, 'rejectMember', ['id' => (int) $p2['id']]);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertNull($this->row((int) $p2['id']));

        $glv = $this->glv();
        $r = $this->org($bdh, 'resetPassword', ['id' => (int) $glv['id']]);
        self::assertSame(200, $r['code'], $r['raw']);
        self::assertSame(1, (int) $this->row((int) $glv['id'])['must_change_pw']);
    }

    public function test_admin_approve_on_already_active_admin_or_bdh_is_rejected_by_status(): void
    {
        // Bảng 2.2: admin approve/reject admin/bdh ✗ vì "không ở trạng thái chờ" (guard cho qua, trạng thái chặn).
        $admin = $this->admin();
        foreach ([$this->admin(), $this->bdh()] as $t) {
            foreach (['approveMember', 'rejectMember'] as $action) {
                $r = $this->org($admin, $action, ['id' => (int) $t['id'], 'role' => 'glv']);
                self::assertSame(400, $r['code'], "$action #{$t['id']}: " . $r['raw']);
                self::assertNotNull($this->row((int) $t['id']), 'không được xoá/đổi người đang hoạt động');
                self::assertNotSame('chờ duyệt', $this->row((int) $t['id'])['status']);
            }
        }
    }

    public function test_glv_with_staff_view_cannot_use_any_staff_action(): void
    {
        // Cổng module staff=edit vẫn đứng trước guard.
        $glv = $this->glv();
        $target = $this->glv();
        $before = $this->row((int) $target['id']);
        foreach (['saveMember' => $this->identityBody($target), 'deleteMember' => ['id' => (int) $target['id']],
                  'resetPassword' => ['id' => (int) $target['id']]] as $action => $body) {
            self::assertSame(403, $this->org($glv, $action, $body)['code'], "glv $action");
        }
        self::assertSame($before, $this->row((int) $target['id']));
    }
}
