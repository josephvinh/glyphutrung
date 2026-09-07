<?php
/**
 * THI ĐUA — hàm tính điểm & xếp hạng (THUẦN, không đụng DB)
 *
 * Tách riêng để test được và để trang công khai (public/bxh.php) tái dùng.
 * Mọi dữ liệu truyền vào dạng mảng; hàm không truy vấn, không phụ thuộc session.
 */

/**
 * Điểm chuyên cần trong MỘT khoảng (dùng cho bảng Tuần).
 *   Có mặt +10 · Đi trễ +6 · Vắng có phép +3 · Vắng không phép 0
 */
function td_diem_tuan(int $coMat, int $diTre, int $coPhep): int
{
    return $coMat * 10 + $diTre * 6 + $coPhep * 3;
}

/**
 * Điểm học tập quy về thang 100 = trung bình CÓ TRỌNG SỐ (thang 10) × 10.
 * @param array $scores  list các ['value' => float, 'weight' => int|float]
 * @return float 0..100 (chưa có điểm -> 0.0)
 */
function td_hoc_tap_100(array $scores): float
{
    $tongDiem = 0.0;
    $tongTrong = 0.0;
    foreach ($scores as $s) {
        $w = (float) ($s['weight'] ?? 1);
        if ($w <= 0) $w = 1;
        $tongDiem  += (float) ($s['value'] ?? 0) * $w;
        $tongTrong += $w;
    }
    if ($tongTrong <= 0) return 0.0;
    $tb10 = $tongDiem / $tongTrong;      // thang 10
    $d = $tb10 * 10;                     // -> thang 100
    return max(0.0, min(100.0, $d));
}

/**
 * Điểm học kỳ (thang 100) = 60% chuyên cần + 40% học tập.
 * @param float $tyLeCoMat100  tỷ lệ có mặt cả kỳ, 0..100
 * @param float $hocTap100     điểm học tập, 0..100
 */
function td_diem_ky(float $tyLeCoMat100, float $hocTap100): float
{
    $tyLeCoMat100 = max(0.0, min(100.0, $tyLeCoMat100));
    $hocTap100    = max(0.0, min(100.0, $hocTap100));
    return round($tyLeCoMat100 * 0.6 + $hocTap100 * 0.4, 1);
}

/**
 * Tỷ lệ có mặt (%) cả kỳ: (có mặt + đi trễ) / tổng buổi đã điểm danh.
 */
function td_ty_le_co_mat(int $coMat, int $diTre, int $tongBuoiDaDiemDanh): float
{
    if ($tongBuoiDaDiemDanh <= 0) return 0.0;
    return round(($coMat + $diTre) / $tongBuoiDaDiemDanh * 100, 1);
}

/**
 * Xếp hạng: sắp giảm dần theo $key, gán 'rank' (đồng hạng chuẩn thi đấu:
 * hai người bằng điểm cùng hạng, hạng kế bị nhảy) và 'medal'
 * (1=>'vang', 2=>'bac', 3=>'dong', còn lại '').
 *
 * @param array  $rows  mỗi phần tử là mảng có sẵn khoá $key (số)
 * @param string $key   tên khoá điểm để xếp
 * @return array        bản sao đã sắp + thêm 'rank','medal'
 */
function td_xep_hang(array $rows, string $key): array
{
    usort($rows, function ($a, $b) use ($key) {
        return ($b[$key] ?? 0) <=> ($a[$key] ?? 0);
    });

    $medals = [1 => 'vang', 2 => 'bac', 3 => 'dong'];
    $rank = 0;
    $stt = 0;
    $diemTruoc = null;
    foreach ($rows as $i => &$r) {
        $stt++;
        $diem = $r[$key] ?? 0;
        // Đồng điểm -> cùng hạng; khác điểm -> hạng = số thứ tự hiện tại
        if ($diemTruoc === null || $diem != $diemTruoc) {
            $rank = $stt;
            $diemTruoc = $diem;
        }
        $r['rank']  = $rank;
        $r['medal'] = $medals[$rank] ?? '';
    }
    unset($r);
    return $rows;
}
