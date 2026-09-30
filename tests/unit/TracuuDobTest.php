<?php
/**
 * P7b - Date Format Tests for tracuu_norm_dob()
 *
 * Kiểm tra hàm chuẩn hoá ngày sinh:
 * - Ưu tiên dd/mm/yyyy (Việt Nam)
 * - Backup mm/dd/yyyy (legacy US)
 * - Xử lý mơ hồ
 *
 * #108
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../public/api/_common.php';
require_once __DIR__ . '/../../public/api/_tracuu.php';

use PHPUnit\Framework\TestCase;

class TracuuDobTest extends TestCase
{
    // ==================== dd/mm/yyyy (Việt Nam) ====================

    public function test_ddmmyyyy_with_slashes(): void
    {
        // 16/03/2020 → ngày 16 tháng 3 năm 2020
        $result = tracuu_norm_dob('16/03/2020');
        $this->assertSame('16032020', $result);
    }

    public function test_ddmmyyyy_with_dashes(): void
    {
        // 16-03-2020 → ngày 16 tháng 3 năm 2020
        $result = tracuu_norm_dob('16-03-2020');
        $this->assertSame('16032020', $result);
    }

    public function test_ddmmyyyy_no_separator(): void
    {
        // 16032020 → ngày 16 tháng 3 năm 2020
        $result = tracuu_norm_dob('16032020');
        $this->assertSame('16032020', $result);
    }

    public function test_ddmmyyyy_single_digit_day(): void
    {
        // 5/03/2015 → ngày 5 tháng 3 năm 2015 (NGÀY ≤ 12 nhưng ≤ 12 đầu tiên = ngày)
        $result = tracuu_norm_dob('05/03/2015');
        $this->assertSame('05032015', $result);
    }

    // ==================== mm/dd/yyyy (Legacy US) ====================

    public function test_mdyyyy_with_slashes(): void
    {
        // 03/15/2014 → ngày 15 tháng 3 năm 2014 (backup khi dd/mm không hợp lệ)
        $result = tracuu_norm_dob('03/15/2014');
        $this->assertSame('15032014', $result);
    }

    public function test_mdyyyy_no_separator(): void
    {
        // 03152014 → ngày 15 tháng 3 năm 2014 (backup)
        $result = tracuu_norm_dob('03152014');
        $this->assertSame('15032014', $result);
    }

    // ==================== Mơ hồ ====================

    public function test_ambiguous_uses_ddmm_when_valid(): void
    {
        // 03/05/2015 → mơ hồ (cả ngày 3 tháng 5 và ngày 5 tháng 3 đều hợp lệ)
        // Ưu tiên dd/mm = ngày 3 tháng 5 năm 2015
        $result = tracuu_norm_dob('03/05/2015');
        $this->assertSame('03052015', $result);
    }

    public function test_ambiguous_uses_mmdd_when_ddmm_invalid(): void
    {
        // 13/02/2015 → rõ ràng là dd/mm = ngày 13 tháng 2 năm 2015
        $result = tracuu_norm_dob('13/02/2015');
        $this->assertSame('13022015', $result);
    }

    public function test_leap_day_vn_format(): void
    {
        // 29/02/2020 → ngày 29 tháng 2 năm 2020 (năm nhuận)
        $result = tracuu_norm_dob('29/02/2020');
        $this->assertSame('29022020', $result);
    }

    public function test_leap_day_us_format(): void
    {
        // 02/29/2020 → ngày 29 tháng 2 năm 2020 (năm nhuận, backup US format)
        $result = tracuu_norm_dob('02/29/2020');
        $this->assertSame('29022020', $result);
    }

    // ==================== Invalid ====================

    public function test_invalid_day_32(): void
    {
        // 32/03/2020 → ngày 32 không tồn tại
        $result = tracuu_norm_dob('32/03/2020');
        $this->assertNull($result);
    }

    public function test_invalid_month_13(): void
    {
        // 16/13/2020 → tháng 13 không tồn tại
        $result = tracuu_norm_dob('16/13/2020');
        $this->assertNull($result);
    }

    public function test_invalid_feb_30(): void
    {
        // 30/02/2020 → ngày 30 tháng 2 không tồn tại
        $result = tracuu_norm_dob('30/02/2020');
        $this->assertNull($result);
    }

    public function test_invalid_string(): void
    {
        // Chuỗi không hợp lệ
        $this->assertNull(tracuu_norm_dob(''));
        $this->assertNull(tracuu_norm_dob('abc'));
        $this->assertNull(tracuu_norm_dob('2020/03/15')); // Sai thứ tự năm/đầu
    }

    public function test_whitespace_trimmed(): void
    {
        $result = tracuu_norm_dob('  16/03/2020  ');
        $this->assertSame('16032020', $result);
    }

    // ==================== tracuu_dob_from_db ====================

    public function test_dob_from_db(): void
    {
        // '2020-03-15' → '15032020'
        $result = tracuu_dob_from_db('2020-03-15');
        $this->assertSame('15032020', $result);
    }

    public function test_dob_from_db_null(): void
    {
        $this->assertNull(tracuu_dob_from_db(null));
        $this->assertNull(tracuu_dob_from_db(''));
        $this->assertNull(tracuu_dob_from_db('invalid'));
    }

    // ==================== End-to-end ====================

    public function test_round_trip(): void
    {
        // DB → ddmmyyyy → parse → same
        $ymd = '2020-03-15';
        $ddmmyyyy = tracuu_dob_from_db($ymd);
        $this->assertSame('15032020', $ddmmyyyy);

        // ddmmyyyy → normalize → same (vì đã đúng format)
        $result = tracuu_norm_dob($ddmmyyyy);
        $this->assertSame('15032020', $result);
    }

    public function test_user_input_matches_db_format(): void
    {
        // Phụ huynh nhập 15/03/2020 → parse → 15032020 → khớp với DB
        $userInput = '15/03/2020';
        $dbDob = tracuu_dob_from_db('2020-03-15');

        $parsed = tracuu_norm_dob($userInput);
        $this->assertSame($dbDob, $parsed, 'User input 15/03/2020 must match DB format 15032020');
    }
}
