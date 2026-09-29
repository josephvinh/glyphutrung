<?php
/**
 * Test logic thuần của Tra cứu điểm số · điểm danh · sổ liên lạc (public/tracuu.php, api/_tracuu.php):
 * chuẩn hoá ngày sinh làm mật mã, điểm trung bình hệ số, xếp ô điểm danh.
 * Không đụng CSDL (phần truy vấn được kiểm bằng cách chạy trang thật).
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../public/api/_tracuu.php';

use PHPUnit\Framework\TestCase;

class TracuuTest extends TestCase
{
    public function testNormDobAcceptsCommonFormats(): void
    {
        $this->assertSame('03152014', tracuu_norm_dob('03152014'));
        $this->assertSame('03152014', tracuu_norm_dob('03/15/2014'));
        $this->assertSame('03152014', tracuu_norm_dob(' 03-15-2014 '));
        $this->assertSame('03052014', tracuu_norm_dob('3.5.2014'));
    }

    public function testNormDobRejectsInvalid(): void
    {
        foreach (['', 'abc', '02312014', '00012014', '3152014', '03/15/14', '15032014'] as $bad) {
            $this->assertNull(tracuu_norm_dob($bad), $bad);
        }
    }

    public function testDobFromDb(): void
    {
        $this->assertSame('03152014', tracuu_dob_from_db('2014-03-15'));
        $this->assertNull(tracuu_dob_from_db(null));
        $this->assertNull(tracuu_dob_from_db(''));
    }

    public function testWeightedAvgSkipsMissingColumns(): void
    {
        $this->assertSame(5.0, tracuu_weighted_avg([
            ['weight' => 1, 'value' => 8.0],
            ['weight' => 3, 'value' => 4.0],
            ['weight' => 2, 'value' => null],
        ]));
        $this->assertNull(tracuu_weighted_avg([['weight' => 1, 'value' => null]]));
        $this->assertNull(tracuu_weighted_avg([]));
    }

    public function testAttMark(): void
    {
        $this->assertSame('P', tracuu_att_mark('có mặt', false));
        $this->assertSame('L', tracuu_att_mark('đi trễ', true));
        $this->assertSame('E', tracuu_att_mark(null, true));
        $this->assertSame('A', tracuu_att_mark(null, false));
    }
}
