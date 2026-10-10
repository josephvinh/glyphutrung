<?php
// tests/unit/DataScopeTest.php
//
// PHẠM VI DỮ LIỆU CỦA data.php (P1 / #78) — đặc tả: docs/audit/P1_design.md mục 1.3, 5.2.
//
// Hai tầng:
//  1. Hàm data_scope_for($me, $mod): giao P(me)=allowed_class_ids() với
//     A(me,mod)=accessible_class_ids(.., 'view'). null = toàn đoàn, [] = không gì.
//     data.php chạy thẳng khi nạp (require_login → exit) nên không `require` được;
//     test TRÍCH ĐÚNG mã nguồn hàm từ data.php rồi nạp — vẫn là mã thật, không phải
//     bản sao: sửa hàm trong data.php thì test đổi theo (và đỏ nếu sai đặc tả).
//  2. Đầu-cuối qua HTTP thật (`php -S`, phiên + CSRF thật) tới api/data.php: kiểm
//     scores/leaveRequests/reports lọc đúng lớp, members theo quyền staff (không
//     lộ admin / hồ sơ chờ duyệt / registerNote), logs chỉ Quản trị, mọi khoá luôn
//     là mảng (client gọi .filter/.find nên thiếu khoá = trắng màn hình).
//
// Mọi dữ liệu (thành viên, phân công, em, điểm, đơn, phiếu, nhật ký, quyền đã đổi)
// được tạo trong từng test và dọn trong tearDown. Không phụ thuộc thứ tự chạy.

require_once __DIR__ . '/P1ApiHarness.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataScopeTest extends TestCase
{
    use P1ApiHarness;

    private int $yearId = 0;
    private array $classA;    // lớp A, khối 1
    private array $classA2;   // lớp khác CÙNG khối 1
    private array $classB;    // lớp khối 2
    private array $classC;    // lớp khối 3 (khác khối A, khác khối B)
    /** @var int[] */
    private array $blockAClassIds = [];

    /** @var int[] */
    private array $madeStudentIds = [];
    /** @var int[] */
    private array $madeProgramIds = [];
    /** @var array<string,array> memoize data.php theo (memberId, part) trong MỘT test */
    private array $dataCache = [];

    public static function setUpBeforeClass(): void
    {
        self::loadDataScopeFor();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    /** Nạp data_scope_for() từ đúng mã nguồn data.php (fail, không skip, nếu không tìm thấy). */
    private static function loadDataScopeFor(): void
    {
        if (function_exists('data_scope_for')) return;
        $src = file_get_contents(__DIR__ . '/../../public/api/data.php');
        self::assertIsString($src);
        self::assertSame(1, preg_match('/^function data_scope_for\(.*?^}\s*$/ms', $src, $m),
            'Không tìm thấy hàm data_scope_for() ở cột 0 trong public/api/data.php');
        eval($m[0]);
        self::assertTrue(function_exists('data_scope_for'));
    }

    protected function setUp(): void
    {
        $year = current_year();
        self::assertNotNull($year, 'Cần niên khoá hiện tại (install.php).');
        $this->yearId = (int) $year['id'];

        $classes = db_all('SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id');
        $a = $classes[0] ?? null;
        self::assertNotNull($a, 'Cần ít nhất một lớp có khối.');
        $a2 = $b = $c = null;
        foreach ($classes as $k) {
            if ($a2 === null && $k['block_id'] === $a['block_id'] && $k['id'] !== $a['id']) $a2 = $k;
            if ($b === null && $k['block_id'] !== $a['block_id']) $b = $k;
        }
        foreach ($classes as $k) {
            if ($b && $k['block_id'] !== $a['block_id'] && $k['block_id'] !== $b['block_id']) { $c = $k; break; }
        }
        self::assertNotNull($a2, 'Cần khối có ≥ 2 lớp.');
        self::assertNotNull($b, 'Cần lớp ở khối thứ hai.');
        self::assertNotNull($c, 'Cần lớp ở khối thứ ba.');
        [$this->classA, $this->classA2, $this->classB, $this->classC] = [$a, $a2, $b, $c];
        $this->blockAClassIds = array_map('intval',
            array_column(db_all('SELECT id FROM classes WHERE block_id = ?', [$a['block_id']]), 'id'));

        // Ma trận mặc định theo config/install.php / đặc tả 1.3 — đặt TƯỜNG MINH để test
        // không ngầm phụ thuộc DB. tearDown khôi phục.
        $matrix = [
            //             scores  leave   reports students attendance staff
            'admin'         => ['edit', 'edit', 'edit', 'edit', 'edit', 'edit'],
            'bdh'           => ['view', 'edit', 'view', 'edit', 'edit', 'edit'],
            'truong_khoi'   => ['edit', 'edit', 'edit', 'edit', 'edit', 'view'],
            'glv_chu_nhiem' => ['edit', 'edit', 'edit', 'edit', 'edit', 'view'],
            'glv'           => ['edit', 'view', 'view', 'view', 'edit', 'view'],
            'du_bi'         => ['view', 'view', 'view', 'view', 'edit', 'view'],
            'thu_thu'       => ['none', 'none', 'none', 'none', 'none', 'none'],
        ];
        $mods = ['scores', 'leave', 'reports', 'students', 'attendance', 'staff'];
        foreach ($matrix as $role => $levels) {
            foreach ($mods as $i => $mod) $this->setPerm($mod, $role, $levels[$i]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->madeProgramIds as $pid) {
            db_run('DELETE FROM leave_requests WHERE program_id = ?', [$pid]);
            db_run('DELETE FROM programs WHERE id = ?', [$pid]);
        }
        foreach ($this->madeStudentIds as $sid) {
            db_run('DELETE FROM scores WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM reports WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM leave_requests WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM enrollments WHERE student_id = ?', [$sid]);
            db_run('DELETE FROM students WHERE id = ?', [$sid]);
        }
        $this->madeProgramIds = $this->madeStudentIds = [];
        $this->dataCache = [];
        $this->cleanupHarness();
    }

    // ==================================================================
    //  Tiện ích
    // ==================================================================

    /** Chuẩn hoá tập id lớp để so sánh không phụ thuộc thứ tự. */
    private function ids(?array $a): ?array
    {
        if ($a === null) return null;
        $a = array_values(array_unique(array_map('intval', $a)));
        sort($a);
        return $a;
    }

    private function sorted(array $a): array
    {
        $a = array_map('intval', $a);
        sort($a);
        return $a;
    }

    private function assertScope(?array $expected, ?array $actual, string $msg): void
    {
        if ($expected === null) {
            self::assertNull($actual, $msg . ' — phải là null (toàn đoàn)');
            return;
        }
        self::assertIsArray($actual, $msg . ' — phải là mảng (không phải null/toàn đoàn)');
        self::assertSame($this->sorted($expected), $this->ids($actual), $msg);
        self::assertTrue(array_is_list($actual), $msg . ' — phải là danh sách có chỉ số liên tục');
    }

    private function scopeAll(array $me): array
    {
        return [
            'scores'  => data_scope_for($me, 'scores'),
            'leave'   => data_scope_for($me, 'leave'),
            'reports' => data_scope_for($me, 'reports'),
        ];
    }

    private function blockOf(array $class): int { return (int) $class['block_id']; }

    /** Bộ 9 vai thử nghiệm đúng đặc tả 1.3 (xem bảng vai × khoá). */
    private function makeRoleUsers(): array
    {
        $A = (int) $this->classA['id']; $C = (int) $this->classC['id'];
        $bA = $this->blockOf($this->classA);
        return [
            'admin'  => $this->makeMember('admin', [['admin', null, null]]),
            'bdh'    => $this->makeMember('bdh', [['bdh', null, null]]),
            'tk'     => $this->makeMember('truong_khoi', [['truong_khoi', $bA, null]], ['block_id' => $bA]),
            'gvcn'   => $this->makeMember('glv_chu_nhiem', [['glv_chu_nhiem', null, $A]], ['class_id' => $A]),
            'glv'    => $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]),
            'glvAC'  => $this->makeMember('glv', [['glv', null, $A], ['glv', null, $C]], ['class_id' => $A]),
            'glvTT'  => $this->makeMember('glv', [['glv', null, $A], ['thu_thu', null, null]], ['class_id' => $A]),
            'dubi'   => $this->makeMember('du_bi', [['du_bi', null, $A]], ['class_id' => $A]),
            'thuthu' => $this->makeMember('thu_thu', [['thu_thu', null, null]]),
        ];
    }

    /** Lớp mà từng vai được XEM (scores/leave/reports) theo đặc tả — null = toàn đoàn. */
    private function expectedClasses(): array
    {
        $A = (int) $this->classA['id']; $C = (int) $this->classC['id'];
        return [
            'admin' => null, 'bdh' => null,
            'tk' => $this->blockAClassIds,
            'gvcn' => [$A], 'glv' => [$A], 'glvAC' => [$A, $C], 'glvTT' => [$A], 'dubi' => [$A],
            'thuthu' => [],
        ];
    }

    // ==================================================================
    //  TẦNG 1 — data_scope_for() (đơn vị, không qua HTTP)
    // ==================================================================

    public function test_admin_is_unrestricted_for_all_three_modules(): void
    {
        $me = $this->makeMember('admin', [['admin', null, null]]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope(null, $scope, "admin / $mod");
        }
    }

    public function test_bdh_is_unrestricted_with_and_without_assignment(): void
    {
        $withAssign = $this->makeMember('bdh', [['bdh', null, null]]);
        $noAssign   = $this->makeMember('bdh');      // dự phòng: suy từ members + roles.scope = toàn đoàn
        foreach ([$withAssign, $noAssign] as $i => $me) {
            foreach ($this->scopeAll($me) as $mod => $scope) {
                $this->assertScope(null, $scope, "bdh#$i / $mod");
            }
        }
    }

    public function test_truong_khoi_sees_only_classes_of_own_block(): void
    {
        $bA = $this->blockOf($this->classA);
        $me = $this->makeMember('truong_khoi', [['truong_khoi', $bA, null]], ['block_id' => $bA]);
        self::assertGreaterThanOrEqual(2, count($this->blockAClassIds), 'Khối A phải có nhiều lớp để phép thử có nghĩa');
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope($this->blockAClassIds, $scope, "truong_khoi / $mod");
            self::assertNotContains((int) $this->classB['id'], $this->ids($scope), "$mod không được chứa lớp khối khác");
            self::assertNotContains((int) $this->classC['id'], $this->ids($scope), "$mod không được chứa lớp khối khác");
        }
    }

    public function test_glv_chu_nhiem_sees_only_own_class(): void
    {
        $A = (int) $this->classA['id'];
        $me = $this->makeMember('glv_chu_nhiem', [['glv_chu_nhiem', null, $A]], ['class_id' => $A]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "glv_chu_nhiem / $mod");
        }
    }

    public function test_glv_sees_own_class_and_is_not_widened_to_its_block(): void
    {
        $A = (int) $this->classA['id'];
        $me = $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "glv / $mod");
            self::assertNotContains((int) $this->classA2['id'], $scope, "$mod: lớp cùng khối không được lọt vào");
        }
    }

    public function test_glv_without_assignment_falls_back_to_members_class(): void
    {
        $A = (int) $this->classA['id'];
        $me = $this->makeMember('glv', [], ['class_id' => $A, 'block_id' => $this->classA['block_id']]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "glv (không phân công) / $mod");
        }
    }

    public function test_glv_two_classes_in_different_blocks_gets_union(): void
    {
        $A = (int) $this->classA['id']; $C = (int) $this->classC['id'];
        $me = $this->makeMember('glv', [['glv', null, $A], ['glv', null, $C]], ['class_id' => $A]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A, $C], $scope, "glv A+C / $mod");
            self::assertNotContains((int) $this->classB['id'], $scope, "$mod: lớp B không được có");
        }
    }

    public function test_glv_who_is_also_thu_thu_keeps_only_glv_classes(): void
    {
        // thu_thu có scope toàn đoàn nhưng không có quyền gì trên miền thiếu nhi:
        // kiêm nhiệm vai đó KHÔNG được mở rộng phạm vi.
        $A = (int) $this->classA['id'];
        $me = $this->makeMember('glv', [['glv', null, $A], ['thu_thu', null, null]], ['class_id' => $A]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "glv+thu_thu / $mod");
        }
    }

    public function test_thu_thu_alone_gets_empty_scope(): void
    {
        $me = $this->makeMember('thu_thu', [['thu_thu', null, null]]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([], $scope, "thu_thu / $mod");
        }
    }

    public function test_thu_thu_stays_empty_even_if_admin_grants_it_view_on_the_modules(): void
    {
        // Giao với P(me): người không nhận hồ sơ em nào thì không nhận điểm/đơn/phiếu của em,
        // dù ma trận quyền (chỉnh được trong app) cho thu_thu xem các module này.
        foreach (['scores', 'leave', 'reports'] as $mod) $this->setPerm($mod, 'thu_thu', 'view');
        $me = $this->makeMember('thu_thu', [['thu_thu', null, null]]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([], $scope, "thu_thu (có quyền view) / $mod");
        }
    }

    public function test_du_bi_gets_own_class_for_view_level_modules(): void
    {
        $A = (int) $this->classA['id'];
        $me = $this->makeMember('du_bi', [['du_bi', null, $A]], ['class_id' => $A]);
        // du_bi có scores=view, leave=view, reports=view: quyền xem là đủ cho phạm vi ĐỌC.
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "du_bi / $mod");
        }
    }

    public function test_view_only_role_still_reads_but_none_role_does_not(): void
    {
        // bdh: scores=view vẫn xem toàn đoàn; đặt scores=none thì phạm vi về [] (KHÔNG phải null).
        $bdhView = $this->makeMember('bdh', [['bdh', null, null]]);
        $this->assertScope(null, data_scope_for($bdhView, 'scores'), 'bdh scores=view');

        $this->setPerm('scores', 'bdh', 'none');
        $bdhNone = $this->makeMember('bdh', [['bdh', null, null]]);
        $this->assertScope([], data_scope_for($bdhNone, 'scores'), 'bdh scores=none');
        $this->assertScope(null, data_scope_for($bdhNone, 'reports'), 'bdh reports vẫn view');
    }

    /** @return array<string,array{string}> */
    public static function modulesProvider(): array
    {
        return ['scores' => ['scores'], 'leave' => ['leave'], 'reports' => ['reports']];
    }

    #[DataProvider('modulesProvider')]
    public function test_admin_with_module_set_to_none_gets_empty_scope(string $mod): void
    {
        $this->setPerm($mod, 'admin', 'none');
        $me = $this->makeMember('admin', [['admin', null, null]]);
        $this->assertScope([], data_scope_for($me, $mod), "admin $mod=none");
        foreach (array_diff(['scores', 'leave', 'reports'], [$mod]) as $other) {
            $this->assertScope(null, data_scope_for($me, $other), "admin $other không bị ảnh hưởng");
        }
    }

    #[DataProvider('modulesProvider')]
    public function test_module_none_removes_only_that_roles_assignments(string $mod): void
    {
        // SCOPE-05: glv (lớp A) kiêm glv_chu_nhiem (lớp A2). Đặt glv.$mod = none:
        // phân công glv không góp lớp; phân công gvcn vẫn góp. Hai module còn lại không đổi.
        $A = (int) $this->classA['id']; $A2 = (int) $this->classA2['id'];
        $this->setPerm($mod, 'glv', 'none');
        $me = $this->makeMember('glv', [['glv', null, $A], ['glv_chu_nhiem', null, $A2]], ['class_id' => $A]);

        foreach (['scores', 'leave', 'reports'] as $m) {
            $this->assertScope($m === $mod ? [$A2] : [$A, $A2], data_scope_for($me, $m), "$m (glv.$mod=none)");
        }
    }

    #[DataProvider('modulesProvider')]
    public function test_module_none_for_the_only_role_gives_empty_scope(string $mod): void
    {
        $A = (int) $this->classA['id'];
        $this->setPerm($mod, 'glv', 'none');
        $me = $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]);
        $this->assertScope([], data_scope_for($me, $mod), "glv $mod=none");
    }

    public function test_scope_is_intersection_not_union_of_profile_and_module_scope(): void
    {
        // truong_khoi (khối A) kiêm glv (lớp B). Hồ sơ P = khối A ∪ {B}.
        // Đặt truong_khoi.scores = none: scores chỉ còn phạm vi glv = {B};
        // leave vẫn là khối A ∪ {B}.
        $bA = $this->blockOf($this->classA); $B = (int) $this->classB['id'];
        $this->setPerm('scores', 'truong_khoi', 'none');
        $me = $this->makeMember('truong_khoi',
            [['truong_khoi', $bA, null], ['glv', null, $B]], ['block_id' => $bA]);

        $this->assertScope([$B], data_scope_for($me, 'scores'), 'scores = giao');
        $this->assertScope(array_merge($this->blockAClassIds, [$B]), data_scope_for($me, 'leave'), 'leave = hồ sơ');
    }

    public function test_assignment_with_ended_date_does_not_count(): void
    {
        $A = (int) $this->classA['id']; $B = (int) $this->classB['id'];
        $me = $this->makeMember('glv', [['glv', null, $A], ['glv', null, $B]], ['class_id' => $A]);
        db_run("UPDATE member_assignments SET to_date = CURDATE() WHERE member_id = ? AND class_id = ?",
               [$me['id'], $B]);
        foreach ($this->scopeAll($me) as $mod => $scope) {
            $this->assertScope([$A], $scope, "phân công đã kết thúc không góp lớp / $mod");
        }
    }

    public function test_can_view_logs_is_admin_only(): void
    {
        self::assertTrue(can_view_logs(['role_code' => 'admin']));
        foreach (['bdh', 'truong_khoi', 'glv_chu_nhiem', 'glv', 'du_bi', 'thu_thu'] as $r) {
            self::assertFalse(can_view_logs(['role_code' => $r]), "$r không được xem nhật ký");
        }
        self::assertFalse(can_view_logs(null));
        self::assertFalse(can_view_logs([]));
    }

    // ==================================================================
    //  TẦNG 2 — data.php qua HTTP thật
    // ==================================================================

    /** Gọi data.php?part=… bằng phiên của $member (nhớ trong một test). */
    private function data(array $member, string $part = 'all'): array
    {
        $k = $member['id'] . ':' . $part;
        if (isset($this->dataCache[$k])) return $this->dataCache[$k];
        $this->loginAs($member);
        $r = $this->http((int) $member['id'], 'GET', '/api/data.php?part=' . $part);
        self::assertSame(200, $r['code'], "data.php?part=$part cho {$member['role_code']}: " . $r['raw']);
        self::assertIsArray($r['json']);
        self::assertTrue($r['json']['ok'] ?? false);
        return $this->dataCache[$k] = $r['json'];
    }

    /**
     * Mỗi lớp A, A2, B, C một em có: 1 điểm, 1 đơn xin phép, 1 phiếu liên lạc.
     * @return array<string,int> tag => studentId
     */
    private function seedChildren(): array
    {
        $term = db_one('SELECT id FROM terms WHERE year_id = ? ORDER BY sort_order LIMIT 1', [$this->yearId]);
        $type = db_one('SELECT code FROM score_types ORDER BY code LIMIT 1');
        self::assertNotNull($term, 'Cần học kỳ của niên khoá hiện tại.');
        self::assertNotNull($type, 'Cần loại điểm.');

        // Tạo exam ngầm định cho loại điểm này
        $examId = db_insert(
            'INSERT INTO score_exams (year_id, term_id, type_code, name) VALUES (?,?,?,?)',
            [$this->yearId, $term['id'], $type['code'], 'Bài test']
        );

        $pid = db_insert(
            "INSERT INTO programs (year_id, name, type, status, day_of_week, start_time)
             VALUES (?, 'P1 Test Buổi', 'bắt buộc', 'kích hoạt', ?, '23:59:00')",
            [$this->yearId, (int) date('w')]
        );
        $this->madeProgramIds[] = $pid;

        $out = [];
        $byTag = ['A' => $this->classA, 'A2' => $this->classA2, 'B' => $this->classB, 'C' => $this->classC];
        foreach ($byTag as $tag => $class) {
            $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, ?, 1)",
                             ['P1T' . random_int(100000, 999999), "P1 Em $tag"]);
            $this->madeStudentIds[] = $sid;
            db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?, 'đang sinh hoạt')",
                   [$this->yearId, $sid, $class['id']]);
            db_run('INSERT INTO scores (exam_id, student_id, term_id, type_code, value) VALUES (?,?,?,?,8.5)',
                   [$examId, $sid, $term['id'], 'mieng']);
            db_run("INSERT INTO leave_requests (year_id, student_id, program_id, session_date, reason, status)
                    VALUES (?,?,?, CURDATE(), ?, 'chờ duyệt')", [$this->yearId, $sid, $pid, "NHAYCAM-$tag"]);
            db_run("INSERT INTO reports (term_id, student_id, remark) VALUES (?,?,?)",
                   [$term['id'], $sid, "NHANXET-$tag"]);
            $out[$tag] = $sid;
        }
        return $out;
    }

    /** Lớp (năm hiện tại) của từng em trong $studentIds. @return array<int,int> studentId => classId */
    private function classOfStudents(array $studentIds): array
    {
        if (!$studentIds) return [];
        $ph = implode(',', array_fill(0, count($studentIds), '?'));
        $rows = db_all("SELECT student_id, class_id FROM enrollments WHERE year_id = ? AND student_id IN ($ph)",
                       array_merge([$this->yearId], $studentIds));
        $m = [];
        foreach ($rows as $r) $m[(int) $r['student_id']] = (int) $r['class_id'];
        return $m;
    }

    /**
     * Khẳng định danh sách $rows (mỗi dòng có studentId) đúng phạm vi $scope:
     *  - $scope=null: đủ mọi em mẫu (toàn đoàn);
     *  - ngược lại: KHÔNG dòng nào của em ngoài $scope, và có ĐỦ em mẫu trong $scope.
     */
    private function assertRowsScoped(array $rows, ?array $scope, array $kids, string $msg): void
    {
        $classByKid = $this->classOfStudents(array_values($kids));
        $seen = array_values(array_unique(array_map(fn($r) => (int) $r['studentId'], $rows)));

        if ($scope !== null) {
            $inScope = array_map('intval', $scope);
            $cls = $this->classOfStudents($seen);
            foreach ($seen as $sid) {
                self::assertTrue(isset($cls[$sid]) && in_array($cls[$sid], $inScope, true),
                    "$msg: em #$sid (lớp " . ($cls[$sid] ?? '?') . ') ngoài phạm vi ' . json_encode($inScope));
            }
        }
        foreach ($kids as $tag => $sid) {
            $expected = $scope === null || in_array($classByKid[$sid], array_map('intval', $scope), true);
            self::assertSame($expected, in_array($sid, $seen, true),
                "$msg: em mẫu $tag " . ($expected ? 'phải có mặt (không lọc quá tay)' : 'không được có mặt'));
        }
    }

    public function test_every_key_is_a_list_for_every_role_and_part(): void
    {
        // KEYS-01: không khoá nào được thiếu/null, vì client gọi .filter/.find/.unshift trên chúng.
        $users = $this->makeRoleUsers();
        $this->seedChildren();
        foreach ($users as $name => $u) {
            $all = $this->data($u, 'all');
            foreach (['leaveRequests', 'scores', 'reports', 'members', 'logs', 'attendances'] as $k) {
                self::assertArrayHasKey($k, $all, "$name/all thiếu khoá $k");
                self::assertIsArray($all[$k], "$name/all: $k phải là mảng");
                self::assertTrue(array_is_list($all[$k]), "$name/all: $k phải là list JSON (không phải object)");
            }
            $core = $this->data($u, 'core');
            foreach (['leaveRequests', 'scores', 'reports', 'members', 'logs'] as $k) {
                self::assertArrayHasKey($k, $core, "$name/core thiếu khoá $k");
                self::assertIsArray($core[$k], "$name/core: $k phải là mảng");
            }
            self::assertSame([], $core['scores'], "$name/core: điểm chỉ tải ở bước heavy");
            $heavy = $this->data($u, 'heavy');
            foreach (['attendances', 'scores'] as $k) {
                self::assertArrayHasKey($k, $heavy, "$name/heavy thiếu khoá $k");
                self::assertIsArray($heavy[$k], "$name/heavy: $k phải là mảng");
            }
        }
    }

    public function test_scores_are_scoped_to_classes_in_view_scope(): void
    {
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        foreach ($this->expectedClasses() as $name => $scope) {
            $this->assertRowsScoped($this->data($users[$name], 'all')['scores'], $scope, $kids, "scores/$name");
        }
    }

    public function test_heavy_part_scores_match_all_part_scores(): void
    {
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        foreach ($this->expectedClasses() as $name => $scope) {
            $this->assertRowsScoped($this->data($users[$name], 'heavy')['scores'], $scope, $kids, "heavy.scores/$name");
        }
    }

    public function test_leave_requests_are_scoped_and_do_not_leak_other_classes(): void
    {
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        foreach ($this->expectedClasses() as $name => $scope) {
            $rows = $this->data($users[$name], 'all')['leaveRequests'];
            $this->assertRowsScoped($rows, $scope, $kids, "leaveRequests/$name");
            if ($scope !== null && !in_array((int) $this->classB['id'], $scope, true)) {
                foreach ($rows as $r) {
                    self::assertStringNotContainsString('NHAYCAM-B', (string) $r['reason'], "$name thấy đơn của lớp B");
                }
            }
        }
        self::assertSame([], $this->data($users['thuthu'], 'all')['leaveRequests'], 'thu_thu nhận []');
    }

    public function test_reports_are_scoped_and_truong_khoi_gets_whole_block(): void
    {
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        foreach ($this->expectedClasses() as $name => $scope) {
            $this->assertRowsScoped($this->data($users[$name], 'all')['reports'], $scope, $kids, "reports/$name");
        }
        $remarks = array_column($this->data($users['tk'], 'all')['reports'], 'remark');
        self::assertContains('NHANXET-A', $remarks);
        self::assertContains('NHANXET-A2', $remarks, 'Trưởng khối nhận phiếu mọi lớp của khối');
        self::assertNotContains('NHANXET-B', $remarks);
        self::assertNotContains('NHANXET-C', $remarks);
    }

    public function test_admin_and_bdh_row_counts_equal_database_counts(): void
    {
        // SCOPE-07: admin/BĐH không đổi hành vi (câu SQL cũ, không lọc).
        $users = $this->makeRoleUsers();
        $this->seedChildren();
        $y = $this->yearId;
        $db = [
            'scores'        => (int) db_one('SELECT COUNT(*) n FROM scores sc JOIN score_exams e ON e.id = sc.exam_id JOIN terms t ON t.id = e.term_id WHERE t.year_id = ?', [$y])['n'],
            'leaveRequests' => (int) db_one('SELECT COUNT(*) n FROM leave_requests WHERE year_id = ?', [$y])['n'],
            'reports'       => (int) db_one('SELECT COUNT(*) n FROM reports r JOIN terms t ON t.id = r.term_id WHERE t.year_id = ?', [$y])['n'],
        ];
        self::assertGreaterThanOrEqual(4, $db['scores']);
        foreach (['admin', 'bdh'] as $name) {
            $d = $this->data($users[$name], 'all');
            foreach ($db as $k => $n) {
                self::assertCount($n, $d[$k], "$name/$k phải bằng COUNT(*) trong DB");
            }
        }
    }

    public function test_thu_thu_gets_empty_for_every_child_data_key_and_members(): void
    {
        $users = $this->makeRoleUsers();
        $this->seedChildren();
        $d = $this->data($users['thuthu'], 'all');
        foreach (['scores', 'leaveRequests', 'reports', 'members', 'logs', 'attendances'] as $k) {
            self::assertSame([], $d[$k], "thu_thu: $k phải là []");
        }
    }

    public function test_glv_who_is_also_thu_thu_is_scoped_like_a_glv(): void
    {
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        $d = $this->data($users['glvTT'], 'all');
        $A = (int) $this->classA['id'];
        $this->assertRowsScoped($d['scores'], [$A], $kids, 'glv+thu_thu scores');
        $this->assertRowsScoped($d['leaveRequests'], [$A], $kids, 'glv+thu_thu leave');
        $this->assertRowsScoped($d['reports'], [$A], $kids, 'glv+thu_thu reports');
        self::assertNotSame([], $d['members'], 'quyền staff đến từ vai glv: vẫn có danh bạ');
    }

    /** @return array<string,array{string,string}> module => khoá data.php */
    public static function moduleKeyProvider(): array
    {
        return ['scores' => ['scores', 'scores'], 'leave' => ['leave', 'leaveRequests'], 'reports' => ['reports', 'reports']];
    }

    #[DataProvider('moduleKeyProvider')]
    public function test_module_none_for_glv_empties_that_key_only(string $mod, string $key): void
    {
        // SCOPE-05: admin đặt $mod=none cho glv → glv(A) và glv+thu_thu nhận [] ở khoá đó,
        // gvcn(A) không đổi, các khoá khác của glv không đổi.
        $A = (int) $this->classA['id'];
        $this->setPerm($mod, 'glv', 'none');
        $glv  = $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]);
        $glvT = $this->makeMember('glv', [['glv', null, $A], ['thu_thu', null, null]], ['class_id' => $A]);
        $gvcn = $this->makeMember('glv_chu_nhiem', [['glv_chu_nhiem', null, $A]], ['class_id' => $A]);
        $kids = $this->seedChildren();

        self::assertSame([], $this->data($glv, 'all')[$key], "glv.$mod=none → $key = []");
        self::assertSame([], $this->data($glvT, 'all')[$key], "glv+thu_thu: $key = []");
        $this->assertRowsScoped($this->data($gvcn, 'all')[$key], [$A], $kids, "gvcn $key không đổi");
        self::assertNotSame([], $this->data($gvcn, 'all')[$key]);
        foreach (['scores', 'leaveRequests', 'reports'] as $other) {
            if ($other === $key) continue;
            self::assertNotSame([], $this->data($glv, 'all')[$other], "glv: $other không bị ảnh hưởng");
        }
    }

    public function test_second_call_is_served_from_cache_with_same_scope(): void
    {
        // SCOPE-08: cache theo (năm, người, part): lần hai (trúng cache) vẫn đúng phạm vi và giống lần một.
        $users = $this->makeRoleUsers();
        $kids = $this->seedChildren();
        $u = $users['glv'];
        $this->loginAs($u);
        $first  = $this->http((int) $u['id'], 'GET', '/api/data.php?part=all')['json'];
        $second = $this->http((int) $u['id'], 'GET', '/api/data.php?part=all')['json'];
        foreach (['scores', 'leaveRequests', 'reports', 'members', 'logs'] as $k) {
            self::assertSame($first[$k], $second[$k], "$k phải giống nhau giữa hai lần gọi");
        }
        $this->assertRowsScoped($second['scores'], [(int) $this->classA['id']], $kids, 'cache lần hai');
    }

    // ---------------------------- members ----------------------------

    /** Hồ sơ chờ duyệt có lời nhắn đăng ký nhận diện được. @return array{0:array,1:string} */
    private function makePending(): array
    {
        $tag = 'GHICHU-' . random_int(100000, 999999);
        $p = $this->makeMember('glv', [], ['status' => 'chờ duyệt', 'register_note' => $tag]);
        return [$p, $tag];
    }

    /**
     * Thành viên ĐANG PHỤC VỤ nhưng còn giữ lời nhắn đăng ký cũ (cột register_note không bị xoá
     * ở mọi đường tạo). Đây là ca duy nhất chứng minh việc che registerNote, vì hồ sơ chờ duyệt
     * vốn đã bị ẩn khỏi người chỉ có staff=view.
     * @return array{0:array,1:string}
     */
    private function makeNotedActive(): array
    {
        $tag = 'GHICHU-ACTIVE-' . random_int(100000, 999999);
        return [$this->makeMember('glv', [], ['register_note' => $tag]), $tag];
    }

    public function test_members_for_staff_view_roles_hide_admin_pending_and_register_note(): void
    {
        // SCOPE-02: tk/gvcn/glv/glvAC/glvTT/du_bi (staff=view).
        self::assertGreaterThan(0, (int) db_one("SELECT COUNT(*) n FROM members WHERE role_code='admin'")['n']);
        $users = $this->makeRoleUsers();
        [$pending, $note] = $this->makePending();
        [$noted, $notedTag] = $this->makeNotedActive();

        foreach (['tk', 'gvcn', 'glv', 'glvAC', 'glvTT', 'dubi'] as $name) {
            $members = $this->data($users[$name], 'all')['members'];
            $byId = array_column($members, null, 'id');
            self::assertArrayHasKey((int) $noted['id'], $byId, "$name thấy thành viên đang phục vụ");
            self::assertSame('', $byId[(int) $noted['id']]['registerNote'], "$name: registerNote của thành viên đang phục vụ phải bị che");
            self::assertStringNotContainsString($notedTag, json_encode($members), "$name lộ lời nhắn đăng ký");
            self::assertNotSame([], $members, "$name có staff=view nên phải có danh bạ");
            $ids = array_column($members, 'id');
            self::assertContains((int) $users[$name]['id'], $ids, "$name thấy chính mình trong danh bạ");
            foreach ($members as $m) {
                self::assertNotSame('admin', $m['role'], "$name không được thấy tài khoản admin");
                self::assertNotSame('chờ duyệt', $m['status'], "$name không được thấy hồ sơ chờ duyệt");
                self::assertSame('', $m['registerNote'], "$name không được thấy registerNote");
            }
            self::assertNotContains((int) $pending['id'], $ids, "$name thấy hồ sơ chờ duyệt");
            self::assertStringNotContainsString($note, json_encode($members), "$name lộ lời nhắn đăng ký");
        }
    }

    public function test_members_for_bdh_include_pending_and_note_but_not_admin(): void
    {
        $users = $this->makeRoleUsers();
        [$pending, $note] = $this->makePending();
        [$noted, $notedTag] = $this->makeNotedActive();
        $members = $this->data($users['bdh'], 'all')['members'];

        $byId = array_column($members, null, 'id');
        self::assertSame($notedTag, $byId[(int) $noted['id']]['registerNote'], 'BĐH thấy registerNote của thành viên đang phục vụ');
        self::assertArrayHasKey((int) $pending['id'], $byId, 'BĐH thấy hồ sơ chờ duyệt');
        self::assertSame('chờ duyệt', $byId[(int) $pending['id']]['status']);
        self::assertSame($note, $byId[(int) $pending['id']]['registerNote'], 'BĐH thấy registerNote');
        foreach ($members as $m) self::assertNotSame('admin', $m['role'], 'BĐH không thấy admin');
    }

    public function test_members_for_admin_include_admin_and_pending(): void
    {
        $users = $this->makeRoleUsers();
        [$pending, $note] = $this->makePending();
        $members = $this->data($users['admin'], 'all')['members'];
        $byId = array_column($members, null, 'id');

        self::assertContains('admin', array_column($members, 'role'), 'admin thấy admin');
        self::assertArrayHasKey((int) $pending['id'], $byId);
        self::assertSame($note, $byId[(int) $pending['id']]['registerNote']);
    }

    public function test_members_for_thu_thu_is_empty(): void
    {
        $users = $this->makeRoleUsers();
        $this->makePending();
        self::assertSame([], $this->data($users['thuthu'], 'all')['members']);
        self::assertSame([], $this->data($users['thuthu'], 'core')['members']);
    }

    public function test_members_follow_staff_permission_of_the_role(): void
    {
        $A = (int) $this->classA['id'];
        [$pending, $note] = $this->makePending();
        [$noted, $notedTag] = $this->makeNotedActive();

        // staff=none → danh bạ rỗng (khoá vẫn có mặt).
        $this->setPerm('staff', 'glv', 'none');
        $glv = $this->makeMember('glv', [['glv', null, $A]], ['class_id' => $A]);
        self::assertSame([], $this->data($glv, 'all')['members'], 'glv staff=none → members []');

        // staff=edit → thấy cả hồ sơ chờ duyệt và registerNote, vẫn không thấy admin.
        $this->setPerm('staff', 'truong_khoi', 'edit');
        $bA = $this->blockOf($this->classA);
        $tk = $this->makeMember('truong_khoi', [['truong_khoi', $bA, null]], ['block_id' => $bA]);
        $members = $this->data($tk, 'all')['members'];
        $byId = array_column($members, null, 'id');
        self::assertArrayHasKey((int) $pending['id'], $byId, 'staff=edit thấy hồ sơ chờ duyệt');
        self::assertSame($note, $byId[(int) $pending['id']]['registerNote']);
        self::assertSame($notedTag, $byId[(int) $noted['id']]['registerNote'], 'staff=edit thấy registerNote');
        foreach ($members as $m) self::assertNotSame('admin', $m['role'], 'tk(staff=edit) vẫn không thấy admin');
    }

    // ----------------------------- logs ------------------------------

    public function test_logs_only_for_admin_and_empty_for_everyone_else(): void
    {
        // SCOPE-03.
        $what = 'P1T-LOG-' . random_int(100000, 999999);
        db_run("INSERT INTO activity_logs (actor_id, actor_name, action, module, what) VALUES (NULL, 'P1 Test', 'sua', 'p1test', ?)", [$what]);
        $this->madeLogWhats[] = $what;

        $users = $this->makeRoleUsers();

        // Admin trước, và đếm ngay sau đó: mỗi lần đăng nhập của người khác ghi thêm một dòng nhật ký.
        $adminLogs = $this->data($users['admin'], 'all')['logs'];
        $total = (int) db_one('SELECT COUNT(*) n FROM activity_logs')['n'];
        self::assertContains($what, array_column($adminLogs, 'what'), 'admin thấy nhật ký mới nhất');
        self::assertCount(min(50, $total), $adminLogs, 'admin nhận min(50, COUNT) dòng');

        foreach ($users as $name => $u) {
            if ($name === 'admin') continue;
            self::assertSame([], $this->data($u, 'all')['logs'], "$name không phải Quản trị → logs phải là []");
        }
    }
}
