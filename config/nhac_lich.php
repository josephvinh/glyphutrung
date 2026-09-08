<?php
/**
 * NHẮC LỊCH — chạy bằng CRON, gửi thông báo đẩy khi tới giờ.
 *
 * Đặt cron trên máy chủ (cPanel/AZDIGI), 5 phút một lần. Dòng cron:
 *
 *     (mỗi 5 phút)  php  /home/<user>/public_html/config/nhac_lich.php
 *
 * Biểu thức cron 5 phút là:  [sao]/5 [sao] [sao] [sao] [sao]  (thay [sao] = *).
 * Đường dẫn php và thư mục tuỳ máy chủ — xem docs/DEPLOY-AZDIGI.md.
 *
 * Việc nó làm:
 *   1) Ghi chú cá nhân tới giờ nhắc  -> đẩy cho chủ ghi chú, đánh dấu đã nhắc.
 *   2) Buổi họp sắp diễn ra (trong 3 giờ tới) -> đẩy cho người được mời
 *      (trừ ai đã trả lời "không tham gia"), đánh dấu đã nhắc một lần.
 *
 * An toàn: chỉ chạy dòng lệnh (CLI), bọc try/catch, giới hạn số bản ghi.
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Chỉ chạy bằng dòng lệnh (CLI).\n"); }

require __DIR__ . '/db.php';
require __DIR__ . '/push.php';

$now    = date('Y-m-d H:i:s');
$daNhac = 0;

try {
    // -------- 1) GHI CHÚ CÁ NHÂN TỚI GIỜ --------
    $notes = db_all(
        "SELECT id, member_id, title FROM personal_notes
          WHERE done = 0 AND notified_at IS NULL AND remind_at <= ?
          ORDER BY remind_at LIMIT 200", [$now]);

    foreach ($notes as $n) {
        push_bao([(int) $n['member_id']], 'Nhắc việc', $n['title'], '/', 'tntt-note-' . $n['id']);
        db_run('UPDATE personal_notes SET notified_at = ? WHERE id = ?', [$now, $n['id']]);
        $daNhac++;
    }

    // -------- 2) BUỔI HỌP SẮP TỚI (trong 3 giờ tới) --------
    $hops = db_all(
        "SELECT id, title, meeting_at, meeting_place, audience_type, audience_block, audience_class
           FROM announcements
          WHERE is_meeting = 1 AND status = 'đã phát' AND reminded_at IS NULL
            AND meeting_at IS NOT NULL
            AND meeting_at >= ?
            AND meeting_at <= DATE_ADD(?, INTERVAL 180 MINUTE)
          LIMIT 100", [$now, $now]);

    foreach ($hops as $h) {
        $ids = push_nguoi_nhan(
            $h['audience_type'],
            $h['audience_block'] !== null ? (int) $h['audience_block'] : null,
            $h['audience_class'] !== null ? (int) $h['audience_class'] : null);

        // Bỏ người đã trả lời "không tham gia"
        if ($ids) {
            $tuChoi = array_map('intval', array_column(
                db_all("SELECT member_id FROM meeting_rsvp WHERE announcement_id = ? AND status = 'không tham gia'",
                       [$h['id']]), 'member_id'));
            $ids = array_values(array_diff($ids, $tuChoi));
        }

        $gio     = substr($h['meeting_at'], 11, 5) . ' ngày ' . date('d/m', strtotime($h['meeting_at']));
        $noiDung = 'Họp lúc ' . $gio . ($h['meeting_place'] ? ' · ' . $h['meeting_place'] : '');
        if ($ids) push_bao($ids, 'Nhắc họp: ' . $h['title'], $noiDung, '/', 'tntt-hop-' . $h['id']);

        // Đánh dấu đã nhắc dù không có ai nhận (khỏi lặp mỗi 5 phút)
        db_run('UPDATE announcements SET reminded_at = ? WHERE id = ?', [$now, $h['id']]);
        $daNhac++;
    }

    echo $now . " — đã xử lý nhắc: " . $daNhac . " mục.\n";
} catch (Throwable $e) {
    error_log('nhac_lich: ' . $e->getMessage());
    echo "Lỗi: " . $e->getMessage() . "\n";
    exit(1);
}
