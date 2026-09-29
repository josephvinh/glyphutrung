<?php
// tests/unit/QrScanTest.php
//
// KIỂM THỬ CHỨC NĂNG QUÉT QR ĐIỂM DANH
//
// Máy quét QR (public/assets/js/modules/qrscan.js) dựa vào HAI nhánh của
// public/api/attendance.php và một hàm phạm vi:
//
//   1. scan_class_ids($me)              — PHẠM VI quét, tính theo KHỐI.
//   2. ?action=lookup                   — bảng tra "mã số -> em" theo phạm vi.
//   3. ?action=scan  { codes: [...] }   — ghi điểm danh HÀNG LOẠT, CHỈ THÊM.
//
// attendance.php là endpoint request-scoped (require _bootstrap.php, gọi
// require_login()/require_permission() ngay lúc require, và json_out() gọi
// exit) nên KHÔNG gọi trực tiếp file đó ở đây. Theo đúng cách GiftsApiTest.php
// và ScopeTest.php làm, ta kiểm thử ở mức HÀM + DB:
//
//   A. scan_class_ids() — logic phạm vi thật (an ninh: chống leo thang, quét
//      theo khối chứ không theo lớp).
//   B. Ràng buộc DB mà nhánh 'scan' dựa vào: câu INSERT IGNORE được CHÉP lại
//      trong test (scanInsert) để chứng minh khoá uq_att khiến quét trùng là
//      idempotent và CHỈ THÊM, không gỡ. ĐÂY LÀ BẢN SAO, không chạy mã của
//      attendance.php — hành vi thật của endpoint được kiểm ở
//      QrScanApiTest.php (gọi HTTP thật).
//   C. Tương tự cho câu SELECT của 'lookup' (lookupCodes là bản sao).

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_bootstrap.php';

use PHPUnit\Framework\TestCase;

class QrScanTest extends TestCase
{
    private int $adminId = 0;
    private int $memberId = 0;
    private int $yearId = 0;
    private array $blockA = [];    // một khối có lớp
    private array $blockB = [];    // khối khác
    private ?array $classA = null; // lớp thuộc khối A (null nếu DB chưa đủ dữ liệu -> skip)
    private ?array $classB = null; // lớp thuộc khối B
    private array $savedPerms = [];
    private array $createdStudentIds = [];
    private array $createdAttIds = [];
    private ?int $tmpProgramId = null;

    protected function setUp(): void
    {
        $this->adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'];
        $this->yearId  = (int) current_year()['id'];

        // Hai khối khác nhau, mỗi khối có ít nhất một lớp.
        $this->classA = db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1");
        $this->classB = $this->classA ? db_one(
            "SELECT id, block_id FROM classes WHERE block_id IS NOT NULL AND block_id <> ? ORDER BY id LIMIT 1",
            [$this->classA['block_id']]) : null;
        if (!$this->classA || !$this->classB) {
            $this->markTestSkipped('Cần ít nhất 2 lớp ở 2 khối khác nhau.');
        }
        $this->blockA = ['id' => (int) $this->classA['block_id']];
        $this->blockB = ['id' => (int) $this->classB['block_id']];

        $phone = '09' . random_int(10000000, 99999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, full_name, phone, password_hash, role_code)
             VALUES ('QRSCAN_TEST', 'QR Scan Test', ?, ?, 'glv')",
            [$phone, password_hash('x', PASSWORD_DEFAULT)]
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdAttIds as $id) {
            db_run("DELETE FROM attendances WHERE id = ?", [$id]);
        }
        if ($this->tmpProgramId) {
            db_run("DELETE FROM attendances WHERE program_id = ?", [$this->tmpProgramId]);
            db_run("DELETE FROM program_classes WHERE program_id = ?", [$this->tmpProgramId]);
            db_run("DELETE FROM programs WHERE id = ?", [$this->tmpProgramId]);
        }
        foreach ($this->createdStudentIds as $sid) {
            db_run("DELETE FROM attendances WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM enrollments WHERE student_id = ?", [$sid]);
            db_run("DELETE FROM students WHERE id = ?", [$sid]);
        }
        if ($this->memberId) {
            db_run("DELETE FROM member_assignments WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
        foreach ($this->savedPerms as $p) {
            if ($p['level'] === null) {
                db_run("DELETE FROM permissions WHERE module_key=? AND role_code=?", [$p['mod'], $p['role']]);
            } else {
                db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                        ON DUPLICATE KEY UPDATE level = VALUES(level)", [$p['mod'], $p['role'], $p['level']]);
            }
        }
        $this->createdAttIds = $this->createdStudentIds = $this->savedPerms = [];
        $this->tmpProgramId = null;
    }

    private function setPerm(string $mod, string $role, string $level): void
    {
        $old = db_one("SELECT level FROM permissions WHERE module_key=? AND role_code=?", [$mod, $role]);
        $this->savedPerms[] = ['mod' => $mod, 'role' => $role, 'level' => $old['level'] ?? null];
        db_run("INSERT INTO permissions (module_key, role_code, level) VALUES (?,?,?)
                ON DUPLICATE KEY UPDATE level = VALUES(level)", [$mod, $role, $level]);
    }

    private function addAssignment(string $role, ?int $blockId, ?int $classId): void
    {
        db_run(
            "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
             VALUES (?, ?, ?, ?, 0, CURDATE(), ?)",
            [$this->memberId, $role, $blockId, $classId, $this->adminId]
        );
    }

    private function me(string $roleCode = 'glv'): array
    {
        return ['id' => $this->memberId, 'role_code' => $roleCode, 'role_scope' => 'lớp',
                'block_id' => null, 'class_id' => null];
    }

    private function classIdsOfBlock(int $blockId): array
    {
        return array_map('intval', array_column(
            db_all("SELECT id FROM classes WHERE block_id = ?", [$blockId]), 'id'));
    }

    /** Tạo một em test + ghi danh vào lớp cho trước, trả về [id, code]. */
    private function makeStudent(int $classId, string $status = 'đang sinh hoạt'): array
    {
        $code = 'QRT' . random_int(100000, 999999);
        $sid = db_insert(
            "INSERT INTO students (code, full_name, gender) VALUES (?, 'QR Test Em', 1)",
            [$code]
        );
        $this->createdStudentIds[] = $sid;
        db_run(
            "INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,?)",
            [$this->yearId, $sid, $classId, $status]
        );
        return ['id' => $sid, 'code' => $code];
    }

    /** Một chương trình test trong niên khoá hiện tại (Chúa Nhật). */
    private function makeProgram(): int
    {
        $this->tmpProgramId = db_insert(
            "INSERT INTO programs (year_id, name, type, status, day_of_week, start_time)
             VALUES (?, 'QR Test Buổi', 'bắt buộc', 'kích hoạt', 0, '07:00:00')",
            [$this->yearId]
        );
        return $this->tmpProgramId;
    }

    /**
     * Chạy đúng câu INSERT IGNORE mà nhánh 'scan' của attendance.php dùng.
     * Trả về SỐ DÒNG THẬT SỰ chèn (rowCount) — chính là "added" của endpoint.
     */
    private function scanInsert(int $programId, string $date, array $studentIds, string $status = 'có mặt'): int
    {
        if (!$studentIds) return 0;
        $vals   = implode(',', array_fill(0, count($studentIds), '(?,?,?,?,?,?,?)'));
        $params = [];
        foreach ($studentIds as $sid) {
            array_push($params, $this->yearId, $programId, $date, $sid, $status, 'qr', $this->memberId);
        }
        return db_run("INSERT IGNORE INTO attendances
                          (year_id, program_id, session_date, student_id, status, method, marked_by)
                       VALUES $vals", $params);
    }

    private function countAtt(int $programId, string $date): int
    {
        return (int) db_one(
            "SELECT COUNT(*) c FROM attendances WHERE program_id=? AND session_date=?",
            [$programId, $date])['c'];
    }

    // =================================================================
    //  A. scan_class_ids() — PHẠM VI QUÉT (an ninh)
    // =================================================================

    /** Admin & BĐH quét TOÀN ĐOÀN (null = không giới hạn). */
    public function test_scope_admin_and_bdh_unrestricted(): void
    {
        $this->assertNull(scan_class_ids(['id' => $this->adminId, 'role_code' => 'admin']));
        $this->assertNull(scan_class_ids(['id' => $this->adminId, 'role_code' => 'bdh']));
    }

    /** Chưa được phân khối/lớp nào -> KHÔNG quét được em nào ([] chứ không phải null). */
    public function test_scope_no_assignment_returns_empty(): void
    {
        $this->assertSame([], scan_class_ids($this->me('glv')));
    }

    /**
     * GLV được phân MỘT LỚP -> quét được CẢ KHỐI của lớp đó, không chỉ lớp mình.
     * Đây là hành vi cốt lõi của tính năng: các em xếp hàng theo khối.
     */
    public function test_scope_glv_by_class_covers_whole_block(): void
    {
        $this->setPerm('attendance', 'glv', 'edit');
        $this->addAssignment('glv', null, (int) $this->classA['id']);

        $ids = scan_class_ids($this->me('glv'));
        sort($ids);
        $expected = $this->classIdsOfBlock($this->blockA['id']);
        sort($expected);

        $this->assertSame($expected, $ids, 'GLV phải quét được mọi lớp trong khối của mình');
        $this->assertContains((int) $this->classA['id'], $ids);
        // Lớp khối khác KHÔNG được lọt vào phạm vi.
        $this->assertNotContains((int) $this->classB['id'], $ids);
    }

    /** Trưởng khối được phân theo KHỐI -> mọi lớp trong khối đó. */
    public function test_scope_truong_khoi_by_block(): void
    {
        $this->setPerm('attendance', 'truong_khoi', 'edit');
        $this->addAssignment('truong_khoi', $this->blockA['id'], null);

        $ids = scan_class_ids($this->me('truong_khoi'));
        sort($ids);
        $expected = $this->classIdsOfBlock($this->blockA['id']);
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    /** Kiêm nhiệm hai khối -> HỢP phạm vi cả hai khối. */
    public function test_scope_kiem_nhiem_unions_two_blocks(): void
    {
        $this->setPerm('attendance', 'glv', 'edit');
        $this->addAssignment('glv', null, (int) $this->classA['id']);
        $this->addAssignment('glv', null, (int) $this->classB['id']);

        $ids = scan_class_ids($this->me('glv'));
        $this->assertContains((int) $this->classA['id'], $ids);
        $this->assertContains((int) $this->classB['id'], $ids);
        // Đủ số lớp của cả hai khối.
        $expected = array_unique(array_merge(
            $this->classIdsOfBlock($this->blockA['id']),
            $this->classIdsOfBlock($this->blockB['id'])));
        sort($ids); sort($expected);
        $this->assertSame($expected, $ids);
    }

    /**
     * CHỐNG LEO THANG: một phân công thu_thu (scope 'toàn đoàn' NHƯNG không có
     * quyền gì trên attendance) KHÔNG được nới phạm vi quét thành toàn đoàn.
     * Chỉ mình thu_thu -> [] (không quét được ai).
     */
    public function test_scope_pure_thu_thu_cannot_scan(): void
    {
        // thu_thu mặc định 'none' trên attendance — xác nhận tiền đề.
        $this->assertSame('none', permission_of_role('thu_thu', 'attendance'));

        $this->addAssignment('thu_thu', null, null); // toàn đoàn
        $this->assertSame([], scan_class_ids($this->me('thu_thu')),
            'thu_thu (toàn đoàn nhưng none trên attendance) không được quét toàn đoàn');
    }

    /**
     * CHỐNG LEO THANG (kiêm nhiệm): GLV một lớp (khối A) + thu_thu toàn đoàn.
     * Phạm vi phải GIỚI HẠN ở khối A, KHÔNG bị thu_thu kéo thành null.
     */
    public function test_scope_glv_plus_thu_thu_stays_block_scoped(): void
    {
        $this->setPerm('attendance', 'glv', 'edit');
        $this->addAssignment('glv', null, (int) $this->classA['id']);
        $this->addAssignment('thu_thu', null, null);

        $ids = scan_class_ids($this->me('glv'));
        $this->assertNotNull($ids, 'thu_thu toàn đoàn KHÔNG được nới quyền quét thành toàn đoàn');
        $expected = $this->classIdsOfBlock($this->blockA['id']);
        sort($ids); sort($expected);
        $this->assertSame($expected, $ids);
    }

    // =================================================================
    //  B. Nhánh 'scan' — INSERT IGNORE (idempotent + CHỈ THÊM)
    // =================================================================

    /** Quét lô mới -> thêm đúng số em, và ghi method = 'qr'. */
    public function test_scan_inserts_new_rows_as_qr(): void
    {
        $pid = $this->makeProgram();
        $date = $this->nextSunday();
        $a = $this->makeStudent((int) $this->classA['id']);
        $b = $this->makeStudent((int) $this->classA['id']);

        $added = $this->scanInsert($pid, $date, [$a['id'], $b['id']]);
        $this->assertSame(2, $added, 'Hai em mới -> thêm 2 dòng');
        $this->assertSame(2, $this->countAtt($pid, $date));

        $row = db_one("SELECT method, status FROM attendances
                        WHERE program_id=? AND session_date=? AND student_id=?",
                      [$pid, $date, $a['id']]);
        $this->assertSame('qr', $row['method']);
        $this->assertSame('có mặt', $row['status']);
    }

    /**
     * Quét TRÙNG là idempotent: khoá duy nhất uq_att(program,date,student)
     * khiến INSERT IGNORE bỏ qua bản trùng. Lần hai "added" = 0 và tổng số
     * dòng KHÔNG tăng — không bao giờ nhân đôi điểm danh của một em.
     */
    public function test_scan_duplicate_is_idempotent(): void
    {
        $pid = $this->makeProgram();
        $date = $this->nextSunday();
        $a = $this->makeStudent((int) $this->classA['id']);

        $this->assertSame(1, $this->scanInsert($pid, $date, [$a['id']]));
        $this->assertSame(0, $this->scanInsert($pid, $date, [$a['id']]),
            'Quét lại cùng một em -> INSERT IGNORE bỏ qua, added = 0');
        $this->assertSame(1, $this->countAtt($pid, $date),
            'Vẫn chỉ MỘT dòng điểm danh cho em đó');
    }

    /**
     * Lô lẫn lộn: vài em đã có, vài em mới -> chỉ THÊM em mới; "added" đếm
     * đúng số dòng thật sự chèn, phần còn lại là "đã có".
     */
    public function test_scan_mixed_batch_counts_only_new(): void
    {
        $pid = $this->makeProgram();
        $date = $this->nextSunday();
        $a = $this->makeStudent((int) $this->classA['id']);
        $b = $this->makeStudent((int) $this->classA['id']);
        $c = $this->makeStudent((int) $this->classA['id']);

        // a đã điểm danh trước.
        $this->assertSame(1, $this->scanInsert($pid, $date, [$a['id']]));

        // Lô sau gồm a (trùng) + b + c (mới) -> chỉ thêm 2.
        $added = $this->scanInsert($pid, $date, [$a['id'], $b['id'], $c['id']]);
        $this->assertSame(2, $added);
        $this->assertSame(3, $this->countAtt($pid, $date));
    }

    /**
     * Nhánh 'scan' CHỈ THÊM, không bao giờ gỡ (khác 'toggle' bật/tắt). Quét
     * trúng em đã có mặt phải GIỮ NGUYÊN bản ghi, không xoá.
     */
    public function test_scan_never_removes_existing(): void
    {
        $pid = $this->makeProgram();
        $date = $this->nextSunday();
        $a = $this->makeStudent((int) $this->classA['id']);

        $this->scanInsert($pid, $date, [$a['id']]);
        $before = db_one("SELECT id FROM attendances
                           WHERE program_id=? AND session_date=? AND student_id=?",
                         [$pid, $date, $a['id']]);
        $this->assertNotNull($before);

        // Quét lại nhiều lần.
        $this->scanInsert($pid, $date, [$a['id']]);
        $this->scanInsert($pid, $date, [$a['id']]);

        $after = db_one("SELECT id FROM attendances
                          WHERE program_id=? AND session_date=? AND student_id=?",
                        [$pid, $date, $a['id']]);
        $this->assertNotNull($after, 'Bản ghi phải còn nguyên — scan không bao giờ gỡ');
        $this->assertSame((int) $before['id'], (int) $after['id'],
            'Cùng một dòng, không bị xoá-ghi-lại');
    }

    // =================================================================
    //  C. Nhánh 'lookup' — bảng tra theo PHẠM VI
    // =================================================================

    /**
     * Câu SELECT của 'lookup' (giới hạn theo tập class_id của phạm vi) chỉ
     * trả các em trong khối được phân, và không lọt em khối khác.
     */
    public function test_lookup_scope_limits_to_block(): void
    {
        $inBlock  = $this->makeStudent((int) $this->classA['id']);
        $outBlock = $this->makeStudent((int) $this->classB['id']);

        $ids = $this->classIdsOfBlock($this->blockA['id']);
        $codes = $this->lookupCodes($ids);

        $this->assertContains($inBlock['code'], $codes, 'Em trong khối phải có trong bảng tra');
        $this->assertNotContains($outBlock['code'], $codes, 'Em khối khác KHÔNG được lọt vào');
    }

    /** 'lookup' chỉ lấy em 'đang sinh hoạt'; em đã nghỉ không xuất hiện. */
    public function test_lookup_excludes_inactive_enrollment(): void
    {
        $active   = $this->makeStudent((int) $this->classA['id'], 'đang sinh hoạt');
        $inactive = $this->makeStudent((int) $this->classA['id'], 'dừng sinh hoạt');

        $codes = $this->lookupCodes($this->classIdsOfBlock($this->blockA['id']));
        $this->assertContains($active['code'], $codes);
        $this->assertNotContains($inactive['code'], $codes,
            'Em không còn sinh hoạt không được đưa vào bảng tra máy quét');
    }

    /**
     * Chạy đúng câu SELECT mà nhánh 'lookup' dùng, giới hạn theo $classIds.
     * Trả về mảng MÃ SỐ.
     */
    private function lookupCodes(array $classIds): array
    {
        if (!$classIds) return [];
        $ph = implode(',', array_fill(0, count($classIds), '?'));
        $rows = db_all(
            "SELECT s.code, s.id, s.full_name, c.name AS class_name
               FROM enrollments e
               JOIN students s ON s.id = e.student_id
               JOIN classes  c ON c.id = e.class_id
              WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'
                AND e.class_id IN ($ph)
              ORDER BY s.code",
            array_merge([$this->yearId], $classIds));
        return array_column($rows, 'code');
    }

    /** Ngày Chúa Nhật gần nhất (>= hôm nay) — buổi test lặp thứ 0. */
    private function nextSunday(): string
    {
        $ts = strtotime('now');
        while ((int) date('w', $ts) !== 0) $ts = strtotime('+1 day', $ts);
        return date('Y-m-d', $ts);
    }
}
