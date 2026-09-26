<?php
/**
 * ĐẶT / HỦY / XEM ĐƠN ĐỔI QUÀ ONLINE — CỔNG CÔNG KHAI (không đăng nhập)
 *
 *   POST api/tracuu_order.php?action=place   { code, items:[{giftId,qty}], password }
 *          -> { ok, orderId, total, expiresAt }
 *   POST api/tracuu_order.php?action=cancel  { code, password }
 *          -> { ok, orderId, total }
 *   POST/GET api/tracuu_order.php?action=pending { code }   (CHỈ ĐỌC)
 *          -> { ok, pending: {...}|null }
 *
 * Theo mẫu `public/api/_tracuu.php` / `public/tracuu.php` (SPEC-MOC-DIEN-TU
 * §6.3, §6.4a, §6.5, §6bis) — KHÔNG nạp `_bootstrap.php` (khỏi kéo theo
 * session/CSP/require_login của tầng API nội bộ). Chỉ nạp đúng những gì
 * logic cần: config/db.php, _http_util.php (client_ip/json_out/json_fail),
 * _tracuu.php (throttle theo IP), _rewards.php (lõi P3-1) + StampService.php
 * (được _tracuu.php require lại, vô hại vì require_once) + cache.php (để
 * làm mới danh mục quà sau khi tồn kho đổi, cùng cách rewards.php/gifts.php
 * đang làm).
 *
 * BẢO MẬT (Global Constraints + spec §6.3/§6bis):
 *   - MỌI action đều tracuu_throttle() + tracuu_attempt_record() trước tiên
 *     (rate-limit theo IP, chặn dò mã hàng loạt) — kể cả 'pending' (chỉ đọc)
 *     vì đọc cũng lộ được "mã này có tồn tại đơn hay không".
 *   - place/cancel CHỈ nhận POST (require_post kiểu thủ công, không có
 *     CSRF ở đây vì trang không đăng nhập/không có session — an toàn nhờ
 *     rate-limit + mật mã đổi quà, giống cách `rewards_cancel_order` đã
 *     đòi mật mã đúng mới cho hủy).
 *   - Định danh em bằng `students.code`, KHÔNG qua id/lớp.
 *   - rewards_expire_due() chạy LAZY mỗi lượt trước khi đọc/đặt để đơn quá
 *     hạn được dọn kịp thời (SPEC §6.5).
 *   - LỖI GỘP: place/cancel thất bại vì bất kỳ lý do gì (mã sai, mật mã sai,
 *     hết tồn, thiếu Mộc, quà ẩn...) đều trả về ĐÚNG MỘT thông điệp chung,
 *     không lộ tên quà/tồn kho/số dư cụ thể — tránh giúp kẻ dò mã suy ra
 *     thông tin nội bộ. Lỗi nghiệp vụ chi tiết (RewardsError) chỉ ghi
 *     error_log() nội bộ để còn dò khi cần.
 *   - Không có đường nào ở đây đọc/ghi dữ liệu ngoài đúng em theo mã nhập.
 */

header('X-Robots-Tag: noindex, nofollow', true);
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/_http_util.php';
require_once __DIR__ . '/_tracuu.php';
require_once __DIR__ . '/_rewards.php';
require_once __DIR__ . '/cache.php';

/** Đọc thân request JSON (mẫu json_input() ở _bootstrap.php, tự đứng một mình
 *  vì trang này không nạp _bootstrap.php) — quay về $_POST nếu không phải JSON. */
function tracuu_order_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    if (strlen($raw) > 1 * 1024 * 1024) { // chặn DoS bằng body khổng lồ
        json_fail('Yêu cầu quá lớn.', 413);
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// place/cancel LÀM THAY ĐỔI DỮ LIỆU -> chỉ nhận POST (mẫu require_post()).
if (in_array($action, ['place', 'cancel'], true) && $method !== 'POST') {
    json_fail('Hành động này phải gửi bằng POST.', 405);
}

// Rate-limit theo IP TRƯỚC MỌI THỨ (kể cả action không hợp lệ/pending).
tracuu_throttle();
tracuu_attempt_record();

$year = db_one("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) json_fail('Chưa mở niên khoá.', 503);
$yearId = (int) $year['id'];

// Dọn đơn quá hạn lazy trước khi đọc/đặt (SPEC §6.5).
rewards_expire_due($yearId);

$in = tracuu_order_input();

switch ($action) {

    // -------------------------------------------------------------
    //  ĐẶT ĐƠN TRƯỚC — em tự chọn quà + tự đặt mật mã đổi quà.
    // -------------------------------------------------------------
    case 'place':
        $code     = trim((string) ($in['code'] ?? ''));
        $password = (string) ($in['password'] ?? '');
        $items    = rewards_normalize_items($in['items'] ?? []);

        if ($code === '' || $password === '' || !$items) {
            json_fail('Không đặt được đơn, vui lòng kiểm tra lại.');
        }

        $student = db_one('SELECT id FROM students WHERE code = ?', [$code]);
        if (!$student) {
            json_fail('Không đặt được đơn, vui lòng kiểm tra lại.');
        }

        try {
            $r = rewards_place_order((int) $student['id'], $yearId, $items, $password);
        } catch (RewardsError $e) {
            error_log('[tracuu_order place] ' . $e->getMessage());
            json_fail('Không đặt được đơn, vui lòng kiểm tra lại.');
        } catch (Throwable $e) {
            error_log('[tracuu_order place] ' . $e->getMessage());
            json_fail('Không đặt được đơn, vui lòng kiểm tra lại.', 500);
        }

        Cache::flush(); // giữ tồn -> danh mục quà nạp lại thấy ngay

        json_out([
            'ok'        => true,
            'orderId'   => $r['orderId'],
            'total'     => $r['total'],
            'expiresAt' => $r['expiresAt'],
        ]);

    // -------------------------------------------------------------
    //  EM TỰ HỦY đơn đang chờ lấy — cần đúng mật mã đổi quà.
    // -------------------------------------------------------------
    case 'cancel':
        $code     = trim((string) ($in['code'] ?? ''));
        $password = (string) ($in['password'] ?? '');

        if ($code === '' || $password === '') {
            json_fail('Không hủy được đơn, vui lòng kiểm tra lại.');
        }

        $student = db_one('SELECT id FROM students WHERE code = ?', [$code]);
        if (!$student) {
            json_fail('Không hủy được đơn, vui lòng kiểm tra lại.');
        }

        try {
            $r = rewards_cancel_order((int) $student['id'], $yearId, $password);
        } catch (RewardsError $e) {
            error_log('[tracuu_order cancel] ' . $e->getMessage());
            json_fail('Không hủy được đơn, vui lòng kiểm tra lại.');
        } catch (Throwable $e) {
            error_log('[tracuu_order cancel] ' . $e->getMessage());
            json_fail('Không hủy được đơn, vui lòng kiểm tra lại.', 500);
        }

        Cache::flush(); // hoàn tồn -> danh mục quà nạp lại thấy ngay

        json_out(['ok' => true, 'orderId' => $r['orderId'], 'total' => $r['total']]);

    // -------------------------------------------------------------
    //  XEM đơn đang chờ lấy của chính em (chỉ đọc) — cho tab Sổ Mộc.
    // -------------------------------------------------------------
    case 'pending':
        $code = trim((string) ($in['code'] ?? $_GET['code'] ?? ''));
        if ($code === '') {
            json_fail('Vui lòng nhập mã thiếu nhi.');
        }

        // Mã không tồn tại: trả pending=null như "không có đơn" — không phân
        // biệt lỗi cụ thể để không lộ hơn những gì tracuu_public_summary() đã
        // lộ (trang Sổ Mộc vốn đã báo "không tìm thấy mã" công khai).
        $student = db_one('SELECT id FROM students WHERE code = ?', [$code]);
        $pending = $student ? rewards_pending_order((int) $student['id'], $yearId) : null;

        json_out(['ok' => true, 'pending' => $pending]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
