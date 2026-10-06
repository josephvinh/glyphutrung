<?php
/**
 * Test cho program_cutoff_ts() — giờ CHỐT SỔ của một buổi.
 *
 * Bảo vệ lỗi "điểm danh trong giờ quy định bị ghi đi trễ": máy chủ phải
 * tôn trọng GIỜ CHỐT riêng của buổi (programs.cutoff_time), khớp với
 * giao diện (attendance.js: cutoffOf). Không được luôn tính start_time+30'.
 */

require_once __DIR__ . '/../bootstrap.php';

use PHPUnit\Framework\TestCase;

class CutoffTest extends TestCase
{
    // Đúng kịch bản trong hình: Thánh Lễ Thiếu Nhi, bắt đầu 06:00, chốt 08:00.
    public function test_dung_gio_chot_rieng_khi_co(): void
    {
        $prog = ['start_time' => '06:00:00', 'cutoff_time' => '08:00:00'];
        $this->assertSame(
            strtotime('2026-10-05 08:00:00'),
            program_cutoff_ts($prog, '2026-10-05')
        );
    }

    // Em chạm tên lúc 06:36 — trước giờ chốt 08:00 — KHÔNG được tính đi trễ.
    public function test_truoc_gio_chot_rieng_khong_tre(): void
    {
        $prog = ['start_time' => '06:00:00', 'cutoff_time' => '08:00:00'];
        $cutoff = program_cutoff_ts($prog, '2026-10-05');
        $this->assertLessThan($cutoff, strtotime('2026-10-05 06:36:00'),
            '06:36 phải nằm TRƯỚC giờ chốt 08:00 -> có mặt');
        $this->assertGreaterThanOrEqual($cutoff, strtotime('2026-10-05 08:06:00'),
            '08:06 phải nằm SAU giờ chốt -> đi trễ');
    }

    // cutoff_time rỗng/NULL -> coi GIỜ BẮT ĐẦU là mốc (KHÔNG còn +30' ngầm).
    public function test_trong_thi_lay_gio_bat_dau(): void
    {
        foreach (['', null] as $empty) {
            $prog = ['start_time' => '06:00:00', 'cutoff_time' => $empty];
            $this->assertSame(
                strtotime('2026-10-05 06:00:00'),
                program_cutoff_ts($prog, '2026-10-05'),
                'cutoff_time rỗng -> dùng giờ bắt đầu, không +30'
            );
        }
    }

    // Thiếu hẳn khóa cutoff_time (chương trình cũ) -> vẫn chạy = giờ bắt đầu.
    public function test_khong_co_khoa_cutoff_van_chay(): void
    {
        $prog = ['start_time' => '07:30:00'];
        $this->assertSame(
            strtotime('2026-10-05 07:30:00'),
            program_cutoff_ts($prog, '2026-10-05')
        );
    }
}
