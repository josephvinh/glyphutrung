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

        // Năm hiện tại phải có dòng (giới hạn trên của vòng for).
        $currentLabel = date('Y') . '-' . (date('Y') + 1);
        $this->assertNotNull(
            $this->firstHistory($mid, $currentLabel),
            'Phải có dòng cho năm hiện tại ' . $currentLabel
        );

        // Các năm ở giữa KHÔNG có note "Năm đầu làm GLV".
        $middle = $this->firstHistory($mid, '2022-2023');
        $this->assertNotNull($middle);
        $this->assertNull($middle['note'], 'Năm giữa không được gán note năm đầu.');

        // Không được sinh dòng năm SAU năm hiện tại.
        $overflow = (date('Y') + 1) . '-' . (date('Y') + 2);
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

    // =================================================================
    // Review Focus #3 — Auto-fill vs Manual history
    // =================================================================

    public function test_khong_the_xoa_dong_auto(): void
    {
        $mid = $this->makeMember('glv');
        PersonalService::updateGlvYear($mid, 2021, 1);

        $currentLabel = date('Y') . '-' . (date('Y') + 1);
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
        $this->assertSame(
            (int) date('Y') - 2021,
            PersonalService::calculateYearsOfService(2021),
            'glv_year = 2021 -> bằng năm hiện tại - 2021.'
        );
        $this->assertNull(
            PersonalService::calculateYearsOfService(null),
            'glv_year = null -> trả null.'
        );
    }
}
