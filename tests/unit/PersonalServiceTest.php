<?php
/**
 * Test PersonalService (T2.2 — module Cá Nhân).
 *
 *  Phạm vi: tầng DB thật (DB test đã seed). Bám theo Review Focus của plan:
 *    #3 — Auto-fill vs Manual history: dòng is_auto = 1 KHÔNG được sửa/xoá
 *         bằng tay; dòng is_auto = 0 ĐƯỢC.
 *    #5 — glv_year trigger: đổi glv_year sinh ra dòng lịch sử đúng quy tắc
 *         (năm đầu có note "Năm đầu làm GLV", is_auto = 1, đến hết năm hiện tại).
 *
 *  Mỗi test dựng member DÙNG-MỘT-LẦN (code/phone random) và dọn trong
 *  tearDown; không phụ thuộc thứ tự chạy.
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/PersonalService.php';

use PHPUnit\Framework\TestCase;

class PersonalServiceTest extends TestCase
{
    /** @var int[] danh sách member_id cần dọn (member + history + assignments) */
    private array $createdMemberIds = [];

    protected function setUp(): void
    {
        if (!db_one("SHOW TABLES LIKE 'member_service_history'")) {
            $this->markTestSkipped('Cần migration 031 (member_service_history).');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMemberIds as $mid) {
            if (!$mid) continue;
            // Xoá history trước (FK) rồi mới xoá assignments rồi tới member.
            db_run("DELETE FROM member_service_history WHERE member_id = ?", [$mid]);
            db_run("DELETE FROM member_assignments    WHERE member_id = ?", [$mid]);
            db_run("DELETE FROM members               WHERE id = ?", [$mid]);
        }
        $this->createdMemberIds = [];
    }

    /** Tạo một member dùng-một-lần với status + role tuỳ ý. */
    private function makeMember(string $roleCode, array $overrides = []): int
    {
        $suffix = random_int(100000, 999999);
        $phone  = '09' . random_int(10000000, 99999999);
        $id = db_insert(
            "INSERT INTO members
                 (code, holy_name, full_name, phone, password_hash, role_code, status, register_note)
             VALUES (?, 'Test', ?, ?, ?, ?, ?, ?)",
            [
                'TPS' . $suffix,
                'Personal Test ' . $suffix,
                $phone,
                password_hash('test', PASSWORD_DEFAULT),
                $roleCode,
                $overrides['status']        ?? 'đang phục vụ',
                $overrides['register_note'] ?? null,
            ]
        );
        $this->createdMemberIds[] = $id;
        return $id;
    }

    private function firstHistory(int $memberId, string $yearLabel): ?array
    {
        return db_one(
            "SELECT * FROM member_service_history
              WHERE member_id = ? AND year_label = ?",
            [$memberId, $yearLabel]
        );
    }

    // =================================================================
    // Review Focus #5 — glv_year trigger
    // =================================================================

    public function test_updateGlvYear_sinh_history_tu_nam_do_den_nam_hien_tai(): void
    {
        $mid = $this->makeMember('glv');

        $result = PersonalService::updateGlvYear($mid, 2021, 1);

        $this->assertTrue($result['ok']);
        $this->assertSame(2021, $result['glv_year']);

        // Cột glv_year phải được cập nhật trong members.
        $m = db_one("SELECT glv_year FROM members WHERE id = ?", [$mid]);
        $this->assertSame(2021, (int) $m['glv_year'], 'members.glv_year phải = 2021');

        $history = PersonalService::getServiceHistory($mid);
        $this->assertNotEmpty($history, 'History phải được sinh tự động.');

        // Năm đầu phải có note "Năm đầu làm GLV" và is_auto = 1.
        $first = $this->firstHistory($mid, '2021-2022');
        $this->assertNotNull($first, 'Phải có dòng cho 2021-2022.');
        $this->assertSame(1, (int) $first['is_auto'], 'Dòng tự sinh = is_auto 1.');
        $this->assertSame('Năm đầu làm GLV', $first['note']);

        // Năm học hiện tại phải có dòng (giới hạn trên của vòng for).
        $cur = PersonalService::currentSchoolStartYear();
        $currentLabel = $cur . '-' . ($cur + 1);
        $this->assertNotNull(
            $this->firstHistory($mid, $currentLabel),
            'Phải có dòng cho năm hiện tại ' . $currentLabel
        );

        // Các năm ở giữa KHÔNG có note "Năm đầu làm GLV".
        $middle = $this->firstHistory($mid, '2022-2023');
        $this->assertNotNull($middle);
        $this->assertNull($middle['note'], 'Năm giữa không được gán note năm đầu.');

        // Không được sinh dòng năm học SAU năm học hiện tại.
        $overflow = ($cur + 1) . '-' . ($cur + 2);
        $this->assertNull(
            $this->firstHistory($mid, $overflow),
            'Không được sinh dòng cho năm tương lai.'
        );
    }

    public function test_updateGlvYear_voi_null_xoa_cac_dong_tu_sinh(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2021, 1);
        // Cộng thêm một dòng nhập tay để xác minh KHÔNG bị xoá nhầm.
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2019-2020', 'role_code' => 'du_bi', 'note' => 'trước khi lên GLV'],
            1
        );

        $result = PersonalService::updateGlvYear($mid, null, 1);
        $this->assertTrue($result['ok']);
        $this->assertNull($result['glv_year']);

        $autoRows = db_all(
            "SELECT 1 FROM member_service_history WHERE member_id = ? AND is_auto = 1",
            [$mid]
        );
        $this->assertEmpty($autoRows, 'Sau khi đặt glv_year = null, không còn dòng tự sinh.');

        $manual = $this->firstHistory($mid, '2019-2020');
        $this->assertNotNull($manual, 'Dòng nhập tay phải còn nguyên.');
        $this->assertSame(0, (int) $manual['is_auto']);
    }

    /**
     * Có dòng nhập tay (is_auto = 0) cùng year_label với dải năm mà
     * updateGlvYear sắp sinh ra → phải throw, liệt kê năm bị xung đột,
     * và KHÔNG đổi members.glv_year (rollback toàn bộ).
     */
    public function test_updateGlvYear_throw_khi_co_dong_nhap_tay_trung_nam(): void
    {
        $mid = $this->makeMember('glv');
        // Tạo dòng nhập tay cho năm nằm trong dải sinh tự động [2024..2026].
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2024-2025', 'role_code' => 'bdh', 'note' => 'làm BĐH năm đó'],
            1
        );

        $thrown = null;
        try {
            PersonalService::updateGlvYear($mid, 2024, 1);
        } catch (\Exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'Phải throw khi có dòng nhập tay trùng năm.');
        $this->assertStringContainsString('2024-2025', $thrown->getMessage(),
            'Message phải liệt kê year_label bị xung đột.');

        // members.glv_year phải KHÔNG đổi (vẫn null — rollback).
        $m = db_one("SELECT glv_year FROM members WHERE id = ?", [$mid]);
        $this->assertNull($m['glv_year'], 'glv_year phải giữ nguyên khi xung đột.');

        // Dòng nhập tay phải còn nguyên.
        $manual = $this->firstHistory($mid, '2024-2025');
        $this->assertNotNull($manual, 'Dòng nhập tay phải còn nguyên.');
        $this->assertSame(0, (int) $manual['is_auto']);
        $this->assertSame('bdh', $manual['role_code']);

        // Không được tạo dòng is_auto = 1 nào cho member này.
        $autoRows = db_all(
            "SELECT 1 FROM member_service_history WHERE member_id = ? AND is_auto = 1",
            [$mid]
        );
        $this->assertEmpty($autoRows, 'Không được tạo dòng tự sinh khi xung đột.');
    }

    /**
     * Nhiều dòng nhập tay cùng nằm trong dải → message phải liệt kê
     * TẤT CẢ các năm xung đột (không chỉ năm đầu).
     */
    public function test_updateGlvYear_throw_liet_ke_tat_ca_nam_xung_dot(): void
    {
        $mid = $this->makeMember('glv');
        // Hai dòng nhập tay nằm trong dải [2022..2026].
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2023-2024', 'role_code' => 'du_bi', 'note' => 'dự bị'],
            1
        );
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2025-2026', 'role_code' => 'truong_khoi', 'note' => 'trưởng khối'],
            1
        );

        $thrown = null;
        try {
            PersonalService::updateGlvYear($mid, 2022, 1);
        } catch (\Exception $e) {
            $thrown = $e;
        }
        $this->assertNotNull($thrown, 'Phải throw khi có dòng nhập tay trùng năm.');
        $msg = $thrown->getMessage();
        $this->assertStringContainsString('2023-2024', $msg,
            'Message phải liệt kê 2023-2024.');
        $this->assertStringContainsString('2025-2026', $msg,
            'Message phải liệt kê 2025-2026.');

        // Cả hai dòng nhập tay phải còn nguyên.
        $r1 = $this->firstHistory($mid, '2023-2024');
        $r2 = $this->firstHistory($mid, '2025-2026');
        $this->assertNotNull($r1);
        $this->assertNotNull($r2);
        $this->assertSame(0, (int) $r1['is_auto']);
        $this->assertSame(0, (int) $r2['is_auto']);
    }

    // =================================================================
    // Review Focus #3 — Auto-fill vs Manual history
    // =================================================================

    public function test_khong_the_xoa_dong_auto(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2021, 1);

        $cur = PersonalService::currentSchoolStartYear();
        $currentLabel = $cur . '-' . ($cur + 1);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Không thể xóa năm được tự động/');
        PersonalService::deleteServiceHistory($mid, $currentLabel);
    }

    public function test_khong_the_sua_dong_auto(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2021, 1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Không thể sửa năm được tự động/');
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2021-2022', 'role_code' => 'bdh', 'note' => 'hack'],
            1
        );
    }

    public function test_co_the_them_va_xoa_dong_nhap_tay(): void
    {
        $mid = $this->makeMember('glv');

        // Thêm dòng nhập tay.
        $add = PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2019-2020', 'role_code' => 'du_bi', 'note' => 'Năm trước khi lên GLV'],
            1
        );
        $this->assertTrue($add['ok']);

        $row = $this->firstHistory($mid, '2019-2020');
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row['is_auto']);
        $this->assertSame('du_bi', $row['role_code']);
        $this->assertSame('Năm trước khi lên GLV', $row['note']);

        // Cập nhật lại dòng đó (cùng year_label, nội dung khác) — phải UPSERT, không INSERT thêm.
        PersonalService::saveServiceHistory(
            $mid,
            ['year_label' => '2019-2020', 'role_code' => 'du_bi', 'note' => 'Sửa lại ghi chú'],
            1
        );
        $count = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_service_history
              WHERE member_id = ? AND year_label = '2019-2020'",
            [$mid]
        )['c'];
        $this->assertSame(1, $count, 'Cùng year_label phải UPSERT, không thêm dòng.');

        // Xoá dòng nhập tay — phải ok.
        $del = PersonalService::deleteServiceHistory($mid, '2019-2020');
        $this->assertTrue($del['ok']);
        $this->assertNull($this->firstHistory($mid, '2019-2020'), 'Dòng nhập tay đã bị xoá.');
    }

    public function test_getServiceHistory_sap_xep_nam_moi_truoc(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::saveServiceHistory($mid, ['year_label' => '2018-2019', 'role_code' => 'du_bi'], 1);
        PersonalService::saveServiceHistory($mid, ['year_label' => '2017-2018', 'role_code' => 'du_bi'], 1);
        PersonalService::saveServiceHistory($mid, ['year_label' => '2020-2021', 'role_code' => 'glv'],   1);

        $rows = PersonalService::getServiceHistory($mid);
        $this->assertCount(3, $rows);
        $this->assertSame('2020-2021', $rows[0]['year_label']);
        $this->assertSame('2018-2019', $rows[1]['year_label']);
        $this->assertSame('2017-2018', $rows[2]['year_label']);
    }

    // =================================================================
    // Member status
    // =================================================================

    public function test_changeMemberStatus_voi_gia_tri_hop_le(): void
    {
        $mid = $this->makeMember('glv', ['status' => 'đang phục vụ']);

        $r = PersonalService::changeMemberStatus(
            $mid,
            'tạm nghỉ',
            'Nghỉ thai sản từ 01/03/2025'
        );
        $this->assertTrue($r['ok']);
        $this->assertSame('tạm nghỉ', $r['status']);

        $row = db_one("SELECT status, register_note FROM members WHERE id = ?", [$mid]);
        $this->assertSame('tạm nghỉ', $row['status']);
        $this->assertSame('Nghỉ thai sản từ 01/03/2025', $row['register_note']);
    }

    public function test_changeMemberStatus_tu_choi_gia_tri_khong_hop_le(): void
    {
        $mid = $this->makeMember('glv');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Trạng thái không hợp lệ.');
        PersonalService::changeMemberStatus($mid, 'invalid_status', null);
    }

    // =================================================================
    // calculateYearsOfService — thâm niên
    // =================================================================

    public function test_calculateYearsOfService(): void
    {
        // Theo NĂM HỌC (cùng mốc với dải dòng lịch sử), không theo năm dương lịch: tăng 1 khi sang niên khoá mới.
        $this->assertSame(
            PersonalService::currentSchoolStartYear() - 2021,
            PersonalService::calculateYearsOfService(2021),
            'glv_year = 2021 -> năm bắt đầu niên khoá hiện tại - 2021.'
        );
        $this->assertSame(0, PersonalService::calculateYearsOfService(PersonalService::currentSchoolStartYear()), 'năm đầu = 0 năm');
        $this->assertSame(0, PersonalService::calculateYearsOfService(PersonalService::currentSchoolStartYear() + 3), 'không âm');
        $this->assertNull(
            PersonalService::calculateYearsOfService(null),
            'glv_year = null -> trả null.'
        );
    }

    // =================================================================
    // Năm học hiện tại — mốc 01/08
    // =================================================================

    public function test_schoolStartYearFromDate_theo_moc_thang_8(): void
    {
        // Dự phòng khi chưa có niên khoá đang mở: mốc 01/08.
        $f = fn(string $d) => PersonalService::schoolStartYearFromDate(new \DateTimeImmutable($d));
        $this->assertSame(2025, $f('2026-07-31'), 'trước 01/08 vẫn là năm học bắt đầu năm trước');
        $this->assertSame(2026, $f('2026-08-01'));
        $this->assertSame(2026, $f('2026-12-31'));
        $this->assertSame(2026, $f('2027-01-01'), 'tháng 1–7 thuộc năm học bắt đầu năm trước (lỗi cũ: sinh dòng 2027-2028)');
        $this->assertSame(2026, $f('2027-07-31'));
        $this->assertSame(2027, $f('2027-08-01'));
    }

    public function test_currentSchoolStartYear_lay_tu_nien_khoa_dang_mo_khong_phai_tu_lich(): void
    {
        $cu = db_all('SELECT id FROM school_years WHERE is_current = 1');
        $this->assertNotEmpty($cu, 'Cần một niên khoá đang mở (install.php).');
        $moi = null;
        try {
            // Niên khoá đang mở là "2031 - 2032" (xa hơn mọi ngày thật): kết quả phải theo nó, không theo đồng hồ.
            db_run('UPDATE school_years SET is_current = 0');
            $moi = db_insert("INSERT INTO school_years (name, start_date, end_date, is_current, status)
                              VALUES ('2031 - 2032', '2031-08-01', '2032-05-31', 1, 'đang mở')");
            $this->assertSame(2031, PersonalService::currentSchoolStartYear());

            // Không có niên khoá nào đang mở: dự phòng theo ngày.
            db_run('UPDATE school_years SET is_current = 0');
            $this->assertSame(PersonalService::schoolStartYearFromDate(), PersonalService::currentSchoolStartYear());
        } finally {
            if ($moi) db_run('DELETE FROM school_years WHERE id = ?', [$moi]);
            db_run('UPDATE school_years SET is_current = 0');
            foreach ($cu as $r) db_run('UPDATE school_years SET is_current = 1 WHERE id = ?', [$r['id']]);
        }
    }

    // =================================================================
    // assignmentForYear — hàm thuần, không đụng CSDL
    // =================================================================

    private function asg(int $id, string $role, string $from, ?string $to, int $primary = 0, ?int $block = null, ?int $class = null): array
    {
        return ['id' => $id, 'role_code' => $role, 'block_id' => $block, 'class_id' => $class,
                'is_primary' => $primary, 'from_date' => $from, 'to_date' => $to];
    }

    public function test_assignmentForYear_phan_cong_bat_dau_tu_truoc_van_duoc_tinh(): void
    {
        // GLV lớp 7 (khối 3) từ 2022, còn hiệu lực. Lỗi cũ: điều kiện from_date >= 01/09 của CHÍNH năm đó
        // nên chỉ năm 2022 khớp, các năm sau (phân công vẫn đang chạy) rơi về mặc định.
        $a = [$this->asg(1, 'glv', '2022-09-05', null, 1, 3, 7)];
        foreach ([2022, 2023, 2024, 2025, 2026] as $y) {
            $this->assertSame(['role_code' => 'glv', 'block_id' => 3, 'class_id' => 7],
                PersonalService::assignmentForYear($a, $y), "năm học $y");
        }
        $this->assertNull(PersonalService::assignmentForYear($a, 2021), 'chưa có phân công thì không có kết quả');
    }

    public function test_assignmentForYear_ranh_gioi_nam_hoc_va_phan_cong_da_ket_thuc(): void
    {
        $a = [$this->asg(1, 'glv', '2022-09-01', '2023-07-31', 1, 3, 7)];   // đúng năm học 2022-2023
        $this->assertNotNull(PersonalService::assignmentForYear($a, 2022));
        $this->assertNull(PersonalService::assignmentForYear($a, 2023), 'đã kết thúc 31/07/2023, năm học 2023 bắt đầu 01/08');

        $b = [$this->asg(2, 'du_bi', '2026-08-01', null)];
        $this->assertNull(PersonalService::assignmentForYear($b, 2025), 'bắt đầu đúng 01/08/2026 thì chưa thuộc 2025-2026');
        $this->assertNotNull(PersonalService::assignmentForYear($b, 2026));

        $c = [$this->asg(3, 'glv', '2026-07-31', '2026-07-31')];            // một ngày cuối năm học 2025-2026
        $this->assertNotNull(PersonalService::assignmentForYear($c, 2025));
        $this->assertNull(PersonalService::assignmentForYear($c, 2026));
    }

    public function test_assignmentForYear_nhieu_phan_cong_uu_tien_vai_cao_roi_phan_cong_chinh(): void
    {
        // Cùng năm: vai cao hơn thắng dù không phải phân công chính.
        $a = [$this->asg(1, 'glv', '2024-09-01', null, 1, 3, 7), $this->asg(2, 'truong_khoi', '2025-01-10', null, 0, 3, null)];
        $this->assertSame('truong_khoi', PersonalService::assignmentForYear($a, 2024)['role_code']);
        // Cùng vai: phân công chính thắng.
        $b = [$this->asg(3, 'glv', '2024-09-01', null, 0, 3, 8), $this->asg(4, 'glv', '2024-09-01', null, 1, 3, 7)];
        $this->assertSame(7, PersonalService::assignmentForYear($b, 2024)['class_id']);
        // Cùng vai, cùng mức chính: bắt đầu gần nhất thắng.
        $c = [$this->asg(5, 'glv', '2024-09-01', null, 0, 3, 8), $this->asg(6, 'glv', '2025-02-01', null, 0, 3, 9)];
        $this->assertSame(9, PersonalService::assignmentForYear($c, 2024)['class_id']);
        // Vai Thủ thư (phụ trợ) thua mọi vai chính.
        $d = [$this->asg(7, 'thu_thu', '2024-09-01', null, 1), $this->asg(8, 'du_bi', '2024-09-01', null, 0, 3, 7)];
        $this->assertSame('du_bi', PersonalService::assignmentForYear($d, 2024)['role_code']);
    }

    public function test_updateGlvYear_dung_phan_cong_that_cua_tung_nam(): void
    {
        $classes = db_all('SELECT id, block_id FROM classes ORDER BY id LIMIT 2');
        $this->assertCount(2, $classes, 'Cần ít nhất 2 lớp (install.php).');
        [$a, $b] = $classes;
        $adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1")['id'];

        $mid = $this->makeMember('glv');
        // Dự bị lớp A một năm, rồi Chủ nhiệm lớp B từ đó tới nay (block_id để trống: phải suy ra từ lớp).
        db_run("INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, to_date, assigned_by)
                VALUES (?, 'du_bi', ?, 1, '2023-09-01', '2024-06-30', ?)", [$mid, $a['id'], $adminId]);
        db_run("INSERT INTO member_assignments (member_id, role_code, class_id, is_primary, from_date, to_date, assigned_by)
                VALUES (?, 'glv_chu_nhiem', ?, 1, '2024-09-01', NULL, ?)", [$mid, $b['id'], $adminId]);

        PersonalService::updateGlvYear($mid, 2023, $adminId);

        $y23 = $this->firstHistory($mid, '2023-2024');
        $this->assertSame('du_bi', $y23['role_code']);
        $this->assertSame((int) $a['id'], (int) $y23['class_id']);
        $this->assertSame((int) $a['block_id'], (int) $y23['block_id'], 'khối suy ra từ lớp');
        $this->assertSame('Năm đầu làm GLV', $y23['note']);

        foreach (['2024-2025', '2025-2026'] as $label) {   // phân công B bắt đầu 2024 và còn hiệu lực
            $row = $this->firstHistory($mid, $label);
            $this->assertSame('glv_chu_nhiem', $row['role_code'], $label);
            $this->assertSame((int) $b['id'], (int) $row['class_id'], $label);
        }
    }

    public function test_updateGlvYear_khong_co_phan_cong_thi_mac_dinh_glv(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2022, 1);
        $row = $this->firstHistory($mid, '2022-2023');
        $this->assertSame('glv', $row['role_code']);
        $this->assertNull($row['class_id']);
        $this->assertNull($row['block_id']);
    }

    // =================================================================
    // Giao dịch — hỏng giữa chừng phải hoàn tác sạch
    // =================================================================

    public function test_updateGlvYear_hoan_tac_khi_loi_giua_chung(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2021, 1);
        $nhan = fn() => array_column(PersonalService::getServiceHistory($mid), 'year_label');
        $truoc = $nhan();
        $this->assertNotEmpty($truoc);

        // created_by là INT: PHP_INT_MAX làm lệnh INSERT đầu tiên của vòng tái dựng hỏng
        // (sau khi UPDATE glv_year và DELETE dòng tự sinh cũ đã chạy).
        $thrown = null;
        try {
            PersonalService::updateGlvYear($mid, 2022, PHP_INT_MAX);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $this->assertInstanceOf(\PDOException::class, $thrown, 'Lỗi CSDL phải nổi lên (không bị nuốt).');

        $m = db_one('SELECT glv_year FROM members WHERE id = ?', [$mid]);
        $this->assertSame(2021, (int) $m['glv_year'], 'glv_year phải về nguyên trạng');
        $this->assertSame($truoc, $nhan(), 'các dòng tự sinh cũ phải còn nguyên, không mất');
        $this->assertFalse(db()->inTransaction(), 'không được để giao dịch treo');
    }

    // =================================================================
    // Validate dữ liệu vào + toàn vẹn
    // =================================================================

    private function baoLoi(callable $fn, int $code, string $chua): void
    {
        try {
            $fn();
        } catch (PersonalException $e) {
            $this->assertSame($code, $e->getCode(), $e->getMessage());
            $this->assertStringContainsString($chua, $e->getMessage());
            return;
        }
        $this->fail("Phải ném PersonalException chứa '$chua'.");
    }

    public function test_updateGlvYear_tu_choi_nam_sai_va_thanh_vien_khong_ton_tai(): void
    {
        $mid = $this->makeMember('glv');
        $this->baoLoi(fn() => PersonalService::updateGlvYear($mid, 1899, 1), 400, 'Năm không hợp lệ');
        // Chặn trên là năm bắt đầu NIÊN KHOÁ hiện tại: từ tháng 1–7 năm dương lịch đã sang nhưng niên khoá mới chưa bắt đầu.
        $latest = PersonalService::currentSchoolStartYear();
        $this->baoLoi(fn() => PersonalService::updateGlvYear($mid, $latest + 1, 1), 400, 'Năm không hợp lệ');
        $this->assertNull(db_one('SELECT glv_year FROM members WHERE id = ?', [$mid])['glv_year'], 'không đổi gì khi bị từ chối');
        $this->assertTrue(PersonalService::updateGlvYear($mid, $latest, 1)['ok'], 'đúng năm bắt đầu niên khoá hiện tại thì được');
        $this->assertNotNull($this->firstHistory($mid, $latest . '-' . ($latest + 1)), 'và sinh đúng một dòng (năm đầu)');
        $this->baoLoi(fn() => PersonalService::updateGlvYear(999999999, 2020, 1), 404, 'Không tìm thấy thành viên');
    }

    public function test_saveServiceHistory_validate_dau_vao(): void
    {
        $mid = $this->makeMember('glv');
        $save = fn(array $d) => fn() => PersonalService::saveServiceHistory($mid, $d, 1);
        $this->baoLoi($save(['year_label' => '2020-2025']), 400, 'hai năm liền nhau');
        $this->baoLoi($save(['year_label' => '1800-1801']), 400, 'hai năm liền nhau');
        $this->baoLoi($save(['year_label' => '2020-2021', 'role_code' => 'khong_co']), 400, 'Vai trò không hợp lệ');
        $this->baoLoi($save(['year_label' => '2020-2021', 'role_code' => 'admin']), 400, 'Vai trò không hợp lệ');
        $this->baoLoi($save(['year_label' => '2020-2021', 'block_id' => 987654]), 400, 'Khối không tồn tại');
        $this->baoLoi($save(['year_label' => '2020-2021', 'class_id' => 987654]), 400, 'Lớp không tồn tại');
        $this->baoLoi($save(['year_label' => '2020-2021', 'note' => str_repeat('a', 256)]), 400, 'tối đa 255');
        $this->assertNull($this->firstHistory($mid, '2020-2021'), 'dữ liệu sai không được ghi');
    }

    public function test_saveServiceHistory_luu_khoi_lop_hop_le_va_chuoi_rong_thanh_null(): void
    {
        $cls = db_one('SELECT id, block_id FROM classes ORDER BY id LIMIT 1');
        $mid = $this->makeMember('glv');
        PersonalService::saveServiceHistory($mid, [
            'year_label' => '2020-2021', 'role_code' => 'glv_chu_nhiem',
            'block_id' => (string) $cls['block_id'], 'class_id' => $cls['id'], 'note' => '  ghi chú  ',
        ], 1);
        $row = $this->firstHistory($mid, '2020-2021');
        $this->assertSame((int) $cls['id'], (int) $row['class_id']);
        $this->assertSame((int) $cls['block_id'], (int) $row['block_id']);
        $this->assertSame('ghi chú', $row['note'], 'ghi chú được cắt khoảng trắng');

        PersonalService::saveServiceHistory($mid, ['year_label' => '2020-2021', 'role_code' => '', 'block_id' => '', 'class_id' => 0, 'note' => ''], 1);
        $row = $this->firstHistory($mid, '2020-2021');
        $this->assertNull($row['role_code']);
        $this->assertNull($row['block_id']);
        $this->assertNull($row['class_id']);
        $this->assertNull($row['note']);
    }

    public function test_xoa_thanh_vien_xoa_theo_lich_su_phuc_vu(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::saveServiceHistory($mid, ['year_label' => '2020-2021', 'role_code' => 'glv'], 1);
        $this->assertNotNull($this->firstHistory($mid, '2020-2021'));
        db_run('DELETE FROM members WHERE id = ?', [$mid]);
        $this->assertNull($this->firstHistory($mid, '2020-2021'), 'FK ON DELETE CASCADE: không để dòng mồ côi');
    }

    public function test_changeMemberStatus_cac_quy_tac(): void
    {
        $mid = $this->makeMember('glv');
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($mid, 'đã nghỉ', null), 400, 'ghi chú');
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($mid, 'tạm nghỉ', '   '), 400, 'ghi chú');
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($mid, 'tạm nghỉ', str_repeat('a', 256)), 400, 'tối đa 255');
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($mid, 'chờ duyệt', 'x'), 400, 'không hợp lệ');
        $this->baoLoi(fn() => PersonalService::changeMemberStatus(999999999, 'tạm nghỉ', 'x'), 404, 'Không tìm thấy');
        // Tự chuyển chính mình khỏi 'đang phục vụ' bị chặn; chuyển người khác hoặc về 'đang phục vụ' thì được.
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($mid, 'đã nghỉ', 'x', $mid), 400, 'chính mình');
        $this->assertSame('đang phục vụ', db_one('SELECT status FROM members WHERE id = ?', [$mid])['status']);
        $this->assertTrue(PersonalService::changeMemberStatus($mid, 'tạm nghỉ', 'x', $mid + 1)['ok']);
        $this->assertTrue(PersonalService::changeMemberStatus($mid, 'đang phục vụ', null, $mid)['ok']);
        $row = db_one('SELECT status, register_note FROM members WHERE id = ?', [$mid]);
        $this->assertSame('đang phục vụ', $row['status']);
        $this->assertSame('x', $row['register_note'], 'không có ghi chú mới thì KHÔNG ghi đè lời nhắn/lý do cũ');

        // Hồ sơ đang chờ duyệt phải vòng qua bước Duyệt
        $cho = $this->makeMember('glv', ['status' => 'chờ duyệt']);
        $this->baoLoi(fn() => PersonalService::changeMemberStatus($cho, 'đang phục vụ', null), 409, 'chờ duyệt');
        $this->assertSame('chờ duyệt', db_one('SELECT status FROM members WHERE id = ?', [$cho])['status']);
    }

    // =================================================================
    // authorizeTarget — phân quyền theo đối tượng
    // =================================================================

    private function hang(int $id): array
    {
        return db_one('SELECT * FROM members WHERE id = ?', [$id]);
    }

    /** Gán một phân công đang hiệu lực (kiêm nhiệm) cho thành viên — vai gốc của họ không đổi. */
    private function giaoViec(int $memberId, string $role): void
    {
        $adminId = (int) db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1")['id'];
        db_run("INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
                VALUES (?, ?, 0, CURDATE(), ?)", [$memberId, $role, $adminId]);
    }

    public function test_authorizeTarget_xem(): void
    {
        $tk  = $this->hang($this->makeMember('truong_khoi'));
        $glv = $this->hang($this->makeMember('glv'));
        $adm = $this->hang((int) db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1")['id']);
        $bdh = $this->hang($this->makeMember('bdh'));
        $coAdm = $this->makeMember('glv'); $this->giaoViec($coAdm, 'admin'); $coAdm = $this->hang($coAdm);
        $xem = fn($me, bool $nhanSu, int $id) => PersonalService::authorizeTarget($me, $nhanSu, $id, 'view');

        $this->assertNull($xem($glv, false, (int) $glv['id']), 'xem của mình luôn được');
        $this->assertNull($xem($coAdm, false, (int) $coAdm['id']), 'người chỉ giữ phân công Quản trị vẫn xem được hồ sơ CỦA MÌNH');
        // Không có staff.edit: MỌI id khác mình đều 403 như nhau (người thường, admin, không tồn tại) — không có oracle 403/404.
        foreach ([(int) $glv['id'], (int) $adm['id'], (int) $coAdm['id'], 999999999] as $id) {
            $this->assertSame(403, $xem($tk, false, $id)['code'], "không staff.edit xem id $id phải 403");
        }
        // Có staff.edit: xem được người thường; admin (vai gốc hoặc phân công) bị ẩn -> 404 giống id không tồn tại.
        $this->assertNull($xem($bdh, true, (int) $glv['id']), 'có staff.edit thì xem được');
        $this->assertSame(404, $xem($bdh, true, (int) $adm['id'])['code'], 'admin bị ẩn với người không phải admin');
        $this->assertSame(404, $xem($bdh, true, (int) $coAdm['id'])['code'], 'phân công Quản trị cũng bị ẩn');
        $this->assertSame(404, $xem($bdh, true, 999999999)['code']);
        $this->assertNull($xem($adm, true, (int) $adm['id']), 'admin thấy admin');
        $this->assertNull($xem($adm, true, (int) $coAdm['id']), 'admin thấy người giữ phân công Quản trị');
    }

    public function test_authorizeTarget_ghi(): void
    {
        $tk   = $this->hang($this->makeMember('truong_khoi'));
        $glv  = $this->hang($this->makeMember('glv'));
        $adm  = $this->hang((int) db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1")['id']);
        $bdh1 = $this->hang($this->makeMember('bdh'));
        $bdh2 = $this->hang($this->makeMember('bdh'));
        $coBdh = $this->makeMember('glv'); $this->giaoViec($coBdh, 'bdh'); $coBdh = $this->hang($coBdh);
        $ghi  = fn($me, bool $nhanSu, int $id) => PersonalService::authorizeTarget($me, $nhanSu, $id, 'edit');

        // Không staff.edit: 403 với mọi id (kể cả admin/không tồn tại) — không dò được qua mã lỗi.
        foreach ([(int) $glv['id'], (int) $adm['id'], 999999999] as $id) {
            $this->assertSame(403, $ghi($tk, false, $id)['code'], "không staff.edit ghi id $id phải 403");
        }
        $this->assertSame(403, $ghi($glv, false, (int) $glv['id'])['code'], 'thành viên không tự sửa hồ sơ phục vụ của mình');
        $this->assertNull($ghi($bdh1, true, (int) $glv['id']));
        $this->assertNull($ghi($bdh1, true, (int) $bdh1['id']), 'BĐH tự sửa mình');
        $this->assertSame(403, $ghi($bdh1, true, (int) $bdh2['id'])['code'], 'BĐH khác chỉ Quản trị mới sửa (#97)');
        $this->assertSame(403, $ghi($bdh1, true, (int) $coBdh['id'])['code'], 'giữ phân công BĐH cũng được bảo vệ');
        $this->assertNull($ghi($adm, true, (int) $bdh2['id']), 'Quản trị sửa được BĐH');
        $this->assertNull($ghi($adm, true, (int) $coBdh['id']));
        $this->assertSame(404, $ghi($bdh1, true, (int) $adm['id'])['code'], 'admin: 404 giống id không tồn tại');
        $this->assertNull($ghi($adm, true, (int) $adm['id']));
        $this->assertSame(404, $ghi($bdh1, true, 999999999)['code']);
    }

    // =================================================================
    // Chọn phân công khi luân chuyển giữa chừng / đầu năm học
    // =================================================================

    public function test_assignmentForYear_luan_chuyen_dau_nam_hoc_phan_cong_cu_chi_giao_vai_ngay_khong_thang(): void
    {
        // Chủ nhiệm lớp 5 kết thúc 05/09/2024 (repo kết thúc phân công bằng to_date = ngày phân công lại),
        // cùng ngày chuyển sang GLV lớp 8. Năm học 2024-2025 phải ghi GLV lớp 8, không phải CN lớp 5
        // dù hạng vai cao hơn (lỗi cũ: ưu tiên hạng trước khi xét thời lượng).
        $a = [
            $this->asg(1, 'glv_chu_nhiem', '2023-09-01', '2024-09-05', 0, 3, 5),
            $this->asg(2, 'glv', '2024-09-05', null, 1, 3, 8),
        ];
        $r = PersonalService::assignmentForYear($a, 2024);
        $this->assertSame('glv', $r['role_code']);
        $this->assertSame(8, $r['class_id']);
        // Năm học trước vẫn đúng là CN lớp 5 (phân công mới chưa bắt đầu).
        $this->assertSame(5, PersonalService::assignmentForYear($a, 2023)['class_id']);
    }

    public function test_assignmentForYear_kiem_nhiem_vai_cao_phai_giu_it_nhat_nua_so_ngay(): void
    {
        // GLV cả năm + trưởng khối từ giữa năm học (15/01 → hơn nửa số ngày của GLV): qua ngưỡng 50%, vai cao thắng.
        $a = [$this->asg(1, 'glv', '2024-08-01', null, 1, 3, 7), $this->asg(2, 'truong_khoi', '2025-01-15', null, 0, 3, null)];
        $this->assertSame('truong_khoi', PersonalService::assignmentForYear($a, 2024)['role_code']);
        // Dưới nửa (từ 01/02 → 181/365 ngày) thì không.
        $a2 = [$this->asg(1, 'glv', '2024-08-01', null, 1, 3, 7), $this->asg(2, 'truong_khoi', '2025-02-01', null, 0, 3, null)];
        $this->assertSame('glv', PersonalService::assignmentForYear($a2, 2024)['role_code']);
        // Nhưng nếu vai cao chỉ có vài tuần cuối năm thì không thắng.
        $b = [$this->asg(1, 'glv', '2024-08-01', null, 1, 3, 7), $this->asg(2, 'truong_khoi', '2025-07-01', null, 0, 3, null)];
        $this->assertSame('glv', PersonalService::assignmentForYear($b, 2024)['role_code']);
    }

    public function test_getServiceHistory_khong_tra_created_by(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::saveServiceHistory($mid, ['year_label' => '2020-2021', 'role_code' => 'glv'], 1);
        $rows = PersonalService::getServiceHistory($mid);
        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('created_by', $rows[0], 'created_by (id người nhập, thường là Quản trị) không được ra client');
        foreach (['id', 'year_label', 'role_code', 'is_auto', 'note', 'block_name', 'class_name', 'role_label'] as $col) {
            $this->assertArrayHasKey($col, $rows[0], "giao diện cần cột $col");
        }
    }
}
