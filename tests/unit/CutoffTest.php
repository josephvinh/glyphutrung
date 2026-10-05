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

    // cutoff_time rỗng/NULL -> mặc định start_time + cutoff_minutes toàn cục.
    public function test_mac_dinh_start_cong_cutoff_minutes_khi_trong(): void
    {
        $min = (int) app_config('cutoff_minutes');
        foreach (['', null] as $empty) {
            $prog = ['start_time' => '06:00:00', 'cutoff_time' => $empty];
            $this->assertSame(
                strtotime('2026-10-05 06:00:00') + $min * 60,
                program_cutoff_ts($prog, '2026-10-05'),
                'cutoff_time rỗng -> start + ' . $min . ' phút'
            );
        }
    }

    // Thiếu hẳn khóa cutoff_time (chương trình cũ) -> vẫn chạy, dùng mặc định.
    public function test_khong_co_khoa_cutoff_van_chay(): void
    {
        $min = (int) app_config('cutoff_minutes');
        $prog = ['start_time' => '07:30:00'];
        $this->assertSame(
            strtotime('2026-10-05 07:30:00') + $min * 60,
            program_cutoff_ts($prog, '2026-10-05')
        );
    }
}
