<?php
require_once __DIR__ . '/../../config/thi_dua.php';

use PHPUnit\Framework\TestCase;

class ThiDuaTest extends TestCase
{
    public function test_diem_tuan_cong_dung_trong_so(): void
    {
        // 3 có mặt (+30), 1 đi trễ (+6), 2 có phép (+6) = 42
        $this->assertSame(42, td_diem_tuan(3, 1, 2));
        $this->assertSame(0, td_diem_tuan(0, 0, 0));
    }

    public function test_hoc_tap_100_trung_binh_co_trong_so(): void
    {
        // value 8 (w=1) và 10 (w=3) -> TB = (8 + 30)/4 = 9.5 (thang 10) -> 95
        $d = td_hoc_tap_100([
            ['value' => 8,  'weight' => 1],
            ['value' => 10, 'weight' => 3],
        ]);
        $this->assertEqualsWithDelta(95.0, $d, 0.001);
    }

    public function test_hoc_tap_100_chua_co_diem_tra_ve_0(): void
    {
        $this->assertSame(0.0, td_hoc_tap_100([]));
    }

    public function test_hoc_tap_100_kep_toi_da_100(): void
    {
        // value 12 (vượt thang) -> vẫn kẹp 100
        $this->assertSame(100.0, td_hoc_tap_100([['value' => 12, 'weight' => 1]]));
    }

    public function test_diem_ky_trong_so_60_40(): void
    {
        // 60% * 100 + 40% * 50 = 60 + 20 = 80
        $this->assertSame(80.0, td_diem_ky(100, 50));
        // kẹp biên
        $this->assertSame(0.0, td_diem_ky(-10, -5));
        $this->assertSame(100.0, td_diem_ky(200, 200));
    }

    public function test_ty_le_co_mat(): void
    {
        // td_ty_le_co_mat($coMat, $diTre, $coPhep, $tongBuoiDaDiemDanh):
        // có mặt = 1 buổi, đi trễ = 0.6, có phép = 0.3 (xem config/thi_dua.php)
        // (7 có mặt + 1 đi trễ * 0.6) / 10 buổi = 76%
        $this->assertSame(76.0, td_ty_le_co_mat(7, 1, 0, 10));
        // (7 + 1 * 0.6 + 2 * 0.3) / 10 = 82%
        $this->assertSame(82.0, td_ty_le_co_mat(7, 1, 2, 10));
        // chưa điểm danh buổi nào -> 0, không chia cho 0
        $this->assertSame(0.0, td_ty_le_co_mat(0, 0, 0, 0));
    }

    public function test_xep_hang_sap_giam_va_gan_huy_chuong(): void
    {
        $rows = [
            ['id' => 1, 'diem' => 50],
            ['id' => 2, 'diem' => 90],
            ['id' => 3, 'diem' => 70],
        ];
        $xh = td_xep_hang($rows, 'diem');
        $this->assertSame(2, $xh[0]['id']);      // 90 đứng đầu
        $this->assertSame(1, $xh[0]['rank']);
        $this->assertSame('vang', $xh[0]['medal']);
        $this->assertSame(3, $xh[1]['id']);      // 70 nhì
        $this->assertSame('bac', $xh[1]['medal']);
        $this->assertSame(1, $xh[2]['id']);      // 50 ba
        $this->assertSame('dong', $xh[2]['medal']);
    }

    public function test_xep_hang_dong_hang(): void
    {
        $rows = [
            ['id' => 1, 'diem' => 90],
            ['id' => 2, 'diem' => 90],
            ['id' => 3, 'diem' => 80],
        ];
        $xh = td_xep_hang($rows, 'diem');
        // Hai người 90 cùng hạng 1; người 80 nhảy xuống hạng 3
        $this->assertSame(1, $xh[0]['rank']);
        $this->assertSame(1, $xh[1]['rank']);
        $this->assertSame('vang', $xh[1]['medal']); // đồng hạng nhất -> cùng vàng
        $this->assertSame(3, $xh[2]['rank']);        // nhảy hạng (không có hạng 2)
        $this->assertSame('dong', $xh[2]['medal']);  // hạng 3 -> đồng
    }
}
