<?php
/**
 * XẢ HÀNG ĐỢI THÔNG BÁO ĐẨY — CHẠY ĐỊNH KỲ (CRON)
 *
 * Vì sao cần: khi gửi thông báo qua web, chính request đó đã tự rung chuông
 * lúc trả phản hồi (push_sau_phan_hoi → push_xa_hang 50 máy / 8 giây). Nhưng:
 *   - Đoàn đông hơn 50 máy một lượt thì phần dư phải chờ lượt sau.
 *   - Máy gửi lỗi/timeout (mã 0, 429, 5xx) được hẹn THỬ LẠI sau 30s, 60s,
 *     120s... — không có ai kích thì cú thử lại đó nằm chờ mãi.
 *   - Lúc vắng, không ai mở app để "xả cơ hội" (push_hen_sau_phan_hoi).
 * Cron này là LƯỚI AN TOÀN cho ba trường hợp trên: cứ mỗi phút vét nốt những
 * cú chuông còn tồn và những cú tới hạn thử lại.
 *
 * KHÔNG tăng tốc ca thường (ca thường đã rung ngay lúc gửi) — nó chỉ bảo đảm
 * không cú nào kẹt lại.
 *
 * Dùng chung hạ tầng gửi với web: push_xa_hang() đã có khoá chống đua
 * (ring_lock_until 60s) nên chạy song song với request web vẫn an toàn,
 * không rung trùng.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * CÀI TRÊN AZDIGI (cPanel → Cron Jobs):
 *
 *   Chạy mỗi phút, tìm đường dẫn tuyệt đối tới repo bằng `pwd` trong SSH/Terminal
 *   của cPanel, rồi thêm dòng (Common Settings: "Once Per Minute"):
 *
 *     * * * * * /usr/local/bin/php /home/TAIKHOAN/DUONG-DAN-REPO/config/cron_push.php >/dev/null 2>&1
 *
 *   - /usr/local/bin/php là đường dẫn PHP CLI tiêu chuẩn trên cPanel. Nếu host
 *     dùng bản PHP khác, lấy đúng đường dẫn ở cPanel → "MultiPHP" / "Select PHP
 *     Version", hoặc chạy `which php` trong Terminal.
 *   - >/dev/null 2>&1 để cron không gửi email mỗi phút. Muốn xem log thì đổi
 *     thành >> /home/TAIKHOAN/cron_push.log 2>&1.
 *
 * Chạy thử một lần bằng tay trước khi hẹn giờ:
 *     php config/cron_push.php
 * ──────────────────────────────────────────────────────────────────────────
 */

// CHỈ chạy từ dòng lệnh. Tệp nằm ngoài web root (config/ là anh em của public/),
// về lý đã không gọi được qua HTTP; chặn thêm một lớp cho chắc.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/db.php';
require __DIR__ . '/push.php';

// Chưa cấu hình khoá VAPID thì không có gì để gửi — thoát êm, không coi là lỗi.
$cfg = app_config('push');
if (empty($cfg['public']) || empty($cfg['private'])) {
    fwrite(STDERR, "cron_push: chưa cấu hình khoá VAPID (config/config.local.php), bỏ qua.\n");
    exit(0);
}

@set_time_limit(0);

// Vét nhiều lượt trong một lần chạy để dọn hết hàng đợi lớn, nhưng có trần thời
// gian để lần chạy này kết thúc trước khi lượt cron kế tiếp (sau 60s) bắt đầu.
$hanChotGiay = 50.0;
$batDau      = hrtime(true);
$tongRung    = 0;
$tongLoi     = 0;

while (push_co_hang_doi()) {
    $kq        = push_xa_hang(200, 10.0);
    $tongRung += $kq['rung'];
    $tongLoi  += $kq['loi'];

    // Không nhích được gì (mọi dòng còn lại đều đang bị khoá/hẹn thử lại):
    // dừng, để lượt cron sau lo khi khoá hết hạn — tránh vòng lặp bận.
    if ($kq['rung'] === 0 && $kq['loi'] === 0) break;
    if ((hrtime(true) - $batDau) / 1e9 > $hanChotGiay) break;
}

if ($tongRung || $tongLoi) {
    error_log("cron_push: rung=$tongRung loi=$tongLoi");
}
exit(0);
