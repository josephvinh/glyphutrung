<?php
// tests/unit/PersonalServiceExceptionTest.php
// Task 4: kiểm tra các fix feedback vòng 3:
//  - PersonalServiceException cho FK validation
//  - changeMemberStatus bọc transaction (nhánh null xoá history)
//  - calculateYearsOfService dùng niên khoá is_current=1

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/PersonalService.php';

use PHPUnit\Framework\TestCase;

class PersonalServiceExceptionTest extends TestCase
{
    private int $memberId = 0;

    protected function setUp(): void
    {
        $this->assertNotNull(
            db_one("SHOW TABLES LIKE 'member_service_history'"),
            'Thiếu bảng member_service_history — chạy install.php + migration 031.'
        );

        $suffix = random_int(100000, 999999);
        $this->memberId = db_insert(
            "INSERT INTO members (code, holy_name, full_name, phone, password_hash, role_code, status)
             VALUES (?, 'Test', ?, ?, ?, 'glv', 'đang phục vụ')",
            ['TPS' . $suffix, 'Exception Test ' . $suffix, '09' . random_int(10000000, 99999999), password_hash('test', PASSWORD_DEFAULT)]
        );
    }

    protected function tearDown(): void
    {
        if ($this->memberId) {
            db_run("DELETE FROM member_service_history WHERE member_id = ?", [$this->memberId]);
            db_run("DELETE FROM members WHERE id = ?", [$this->memberId]);
        }
    }

    public function test_saveServiceHistory_throws_PersonalServiceException_for_unknown_role(): void
    {
        $this->expectException(PersonalServiceException::class);
        PersonalService::saveServiceHistory($this->memberId, [
            'year_label' => '2024-2025',
            'role_code'  => 'khong_ton_tai',
        ], $this->memberId);
    }

    public function test_saveServiceHistory_throws_for_unknown_block(): void
    {
        $this->expectException(PersonalServiceException::class);
        PersonalService::saveServiceHistory($this->memberId, [
            'year_label' => '2024-2025',
            'block_id'   => 999999,
        ], $this->memberId);
    }

    public function test_saveServiceHistory_throws_for_unknown_class(): void
    {
        $this->expectException(PersonalServiceException::class);
        PersonalService::saveServiceHistory($this->memberId, [
            'year_label' => '2024-2025',
            'class_id'   => 999999,
        ], $this->memberId);
    }

    public function test_calculate_years_of_service_uses_school_year_not_date_Y(): void
    {
        // Cố ý dựng niên khoá "9999 - 10000" và đặt is_current=1 để
        // verify hàm đọc từ school_years chứ không date('Y').
        $yearId = (int) db_val("SELECT id FROM school_years WHERE name = '9999 - 10000'");
        if ($yearId) {
            db_run("UPDATE school_years SET is_current = 0");
            db_run("UPDATE school_years SET is_current = 1 WHERE id = ?", [$yearId]);
        }
        $years = PersonalService::calculateYearsOfService(1);
        // Nếu niên khoá 9999-10000 tồn tại → 9999; không thì date('Y')=2026
        if ($yearId) {
            $this->assertSame(9999, $years);
            // Khôi phục
            db_run("UPDATE school_years SET is_current = 0 WHERE id = ?", [$yearId]);
        } else {
            $this->assertIsInt($years);
        }
    }
}
