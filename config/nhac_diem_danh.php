<?php
/**
 * NHẮC TRƯỚC GIỜ ĐIỂM DANH
 *
 * Chạy bằng cron trên cPanel, 15 phút một lần. Ở ô Command điền:
 *
 *     [mỗi 15 phút]  /usr/local/bin/php /home/TAI_KHOAN/config/nhac_diem_danh.php
 *
 * (Đường dẫn php lấy ở cPanel → Cron Jobs, thường là /usr/local/bin/php.
 *  Nhớ là config/ nằm NGOÀI public_html, nên đường dẫn không có public_html.)
 *
 * Việc của nó: tìm chương trình sắp bắt đầu trong khoảng NHAC_TRUOC_PHUT
 * tới, rồi rung chuông cho các anh chị. Chạy lại nhiều lần trong cùng một
 * cửa sổ cũng chỉ nhắc MỘT lần — nhờ dấu vết để lại trong push_outbox.
 *
 * Không cấu hình cron cũng không sao: mọi thứ khác vẫn chạy, chỉ là
 * không có lời nhắc tự động.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/push.php';

const NHAC_TRUOC_PHUT = 45;   // nhắc trước giờ bắt đầu bao lâu
const CUA_SO_PHUT     = 20;   // bề rộng cửa sổ, phải lớn hơn nhịp cron

if (PHP_SAPI !== 'cli') {
    // Cho chạy qua trình duyệt cũng được, nhưng phải có khoá — kẻo ai
    // cũng gọi được để spam thông báo cho cả đoàn.
    $key = app_config('setup_key');
    if (empty($key) || ($_GET['key'] ?? '') !== $key) {
        http_response_code(403);
        exit('Cấm.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$year = db_one("SELECT * FROM school_years WHERE status = 'đang mở' LIMIT 1");
if (!$year) { echo "Không có niên khoá nào đang mở.\n"; exit; }

$now   = new DateTimeImmutable('now');
$homNay = $now->format('Y-m-d');
$thu   = (int) $now->format('w');          // 0 Chúa Nhật ... 6 Thứ Bảy

$dsCT = db_all("SELECT * FROM programs
                 WHERE year_id = ? AND status = 'kích hoạt' AND count_for_attendance = 1
                   AND (day_of_week = ? OR event_date = ?)",
               [$year['id'], $thu, $homNay]);

$daNhac = 0;
foreach ($dsCT as $ct) {
    $batDau = new DateTimeImmutable($homNay . ' ' . $ct['start_time']);
    $conBaoLau = ($batDau->getTimestamp() - $now->getTimestamp()) / 60;

    // Ngoài cửa sổ nhắc thì bỏ qua. Đã qua giờ cũng bỏ qua — nhắc điểm
    // danh sau khi buổi đã bắt đầu thì chỉ tổ phiền.
    if ($conBaoLau > NHAC_TRUOC_PHUT || $conBaoLau < NHAC_TRUOC_PHUT - CUA_SO_PHUT) continue;

    $tag = 'tntt-nhac-' . $ct['id'] . '-' . $homNay;
    if (db_one('SELECT id FROM push_outbox WHERE tag = ? LIMIT 1', [$tag])) {
        echo "Đã nhắc rồi: {$ct['name']}\n";
        continue;
    }

    $nguoi = push_nguoi_nhan('toàn đoàn');
    $phut  = (int) round($conBaoLau);
    $n = push_bao($nguoi, 'Sắp tới giờ điểm danh',
                  $ct['name'] . ' bắt đầu lúc ' . substr($ct['start_time'], 0, 5)
                  . ' — còn ' . $phut . ' phút',
                  '/#attendance', $tag);

    echo "Nhắc {$ct['name']}: " . count($nguoi) . " người, xếp hàng rung $n máy.\n";
    $daNhac++;
}

if ($daNhac === 0) echo "Chưa tới giờ nhắc.\n";

// Dọn hộp thư đi: những dòng đã hiện quá 7 ngày thì không cần giữ nữa.
// Dòng CHƯA hiện thì giữ tới 30 ngày rồi mới bỏ — người ta có thể để
// máy tắt cả tuần, mở lên vẫn nên thấy việc còn tồn.
db_run('DELETE FROM push_outbox WHERE taken_at IS NOT NULL AND taken_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
db_run('DELETE FROM push_outbox WHERE taken_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');

// Dọn đăng ký cũ có endpoint ngoài danh sách dịch vụ push được phép (#99).
// Đã xoá lười lúc gửi, ở đây dọn chủ động cho sạch.
foreach (db_all('SELECT id, endpoint FROM push_subscriptions') as $dk) {
    if (!push_endpoint_hop_le((string) $dk['endpoint'])['ok']) {
        db_run('DELETE FROM push_subscriptions WHERE id = ?', [$dk['id']]);
    }
}

// Xả nốt hàng đợi chuông còn tồn (máy bị lỗi tạm ở các lượt trước, dòng bị
// bỏ dở do tiến trình web bị ngắt...).
$xa = push_xa_hang(200, 20.0);
echo "Xả hàng đợi thông báo đẩy: rung {$xa['rung']} máy, lỗi {$xa['loi']}.\n";
