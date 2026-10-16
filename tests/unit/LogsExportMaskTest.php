<?php
// tests/unit/LogsExportMaskTest.php
//
// Kiểm tra hàm mask_phone_recursive() trong logs_export.php:
//   1) Mask các key phone khi giá trị là SĐT
//   2) Không mask các key khác (vd name)
//   3) Mask giá trị SĐT khi nằm ngoài object (chuỗi toàn số)
//   4) Mask nested trong mảng sâu
//   5) Không mask số ngắn (< 9 chữ số)

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../src/LogMask.php';

use PHPUnit\Framework\TestCase;
use TNTT\LogMask;

class LogsExportMaskTest extends TestCase
{
    protected function setUp(): void
    {
        $ten = db_one('SELECT DATABASE() AS d')['d'] ?? '';
        if ($ten !== 'tntt_test') {
            $this->markTestSkipped("Chỉ chạy mask test trên DB tntt_test (đang là '$ten').");
        }
    }

    private function m(mixed $v): mixed
    {
        return LogMask::phoneRecursive($v);
    }

    public function test_mask_parent_phone(): void
    {
        $r = $this->m(['parent_phone' => '0901234567', 'name' => 'An']);
        $this->assertSame('0901***567', $r['parent_phone']);
        $this->assertSame('An', $r['name']);
    }

    public function test_mask_phone_dang_so_9_den_12_chu_so(): void
    {
        $r = $this->m('0987654321');
        $this->assertSame('0987***321', $r);
    }

    public function test_khong_mask_so_ngan(): void
    {
        $r = $this->m('123');
        $this->assertSame('123', $r);
    }

    public function test_mask_nested_trong_mang_sau(): void
    {
        $input = [
            'before' => [
                'name' => 'A',
                'phone' => '0901234567',
            ],
            'after' => [
                'nested' => [
                    'mother_phone' => '0987654321',
                ],
            ],
        ];
        $r = $this->m($input);
        $this->assertSame('A', $r['before']['name']);
        $this->assertSame('0901***567', $r['before']['phone']);
        $this->assertSame('0987***321', $r['after']['nested']['mother_phone']);
    }

    public function test_mask_father_phone_va_mother_phone(): void
    {
        $r = $this->m([
            'father_phone' => '0901234567',
            'mother_phone' => '0987654321',
        ]);
        $this->assertSame('0901***567', $r['father_phone']);
        $this->assertSame('0987***321', $r['mother_phone']);
    }

    public function test_khong_mask_khi_gia_tri_khong_phai_so(): void
    {
        $r = $this->m('không phải số điện thoại');
        $this->assertSame('không phải số điện thoại', $r);
    }

    public function test_mask_sdt_voi_key_viet_tat(): void
    {
        $r = $this->m(['sdt' => '0901234567']);
        $this->assertSame('0901***567', $r['sdt']);
    }

    public function test_mask_doi_voi_sdt_10_va_11_chu_so(): void
    {
        $r1 = $this->m('0901234567');  // 10 chữ số
        $this->assertSame('0901***567', $r1);
        $r2 = $this->m('84901234567');  // 11 chữ số (84 + 9)
        $this->assertSame('8490***567', $r2);
    }
}
