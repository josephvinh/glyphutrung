<?php
/**
 * TRẠM ĐỔI QUÀ (POS) — Thủ thư đứng quầy đổi Mộc lấy quà cho các em.
 *
 *   POST api/rewards.php?action=lookup  { code }        -> { student:{...} | null }
 *   GET  api/rewards.php?action=lookup&code=GDGLPT260001       -> { student:{...} | null }
 *   POST api/rewards.php?action=redeem  { studentCode, items:[{giftId,qty}] }
 *          -> { ok, orderId, total, available, currentBalance }
 *
 *   -- Xác nhận ĐƠN ĐẶT TRƯỚC ONLINE (SPEC §6.4a, đơn do em tự đặt ở somoc.php) --
 *   POST api/rewards.php?action=staff_pending { code }
 *          -> { ok, student:{...}, pending:{...}|null }
 *   POST api/rewards.php?action=confirm { orderId? , studentCode?, password?, override? }
 *          -> { ok, orderId, total, available }
 *   POST api/rewards.php?action=cancel_staff { orderId?, studentCode? }
 *          -> { ok, orderId, total }
 *
 * Quyền (SPEC §6bis): module ĐOÀN-WIDE, KHÔNG chia lớp — chỉ gác bằng
 * require_permission('rewards', ...). TUYỆT ĐỐI không gọi can_access_class /
 * allowed_class_ids / scan_class_ids (chống leo thang qua vai thu_thu toàn đoàn).
 * Định danh em bằng MÃ THẺ (students.code), không qua lớp.
 *   - lookup, staff_pending: cần 'view' (admin, thu_thu).
 *   - redeem, confirm, cancel_staff: cần 'edit' (admin, thu_thu). BĐH/GLV: none.
 *   - confirm với override=true: BỎ QUA mật mã đổi quà (em quên mật mã) —
 *     CHỈ cho phép vì action đã gác 'edit' ở trên; luôn ghi log riêng
 *     ('Giao đơn (override mật mã)') để truy vết ai đã bỏ qua mật mã.
 */

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_rewards.php';

$action = $_GET['action'] ?? '';
$in     = json_input();

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

switch ($action) {

    // -------------------------------------------------------------
    //  TRA CỨU EM theo mã thẻ: tên + số Mộc khả dụng.
    // -------------------------------------------------------------
    case 'lookup':
        require_permission('rewards', 'view');

        $code = trim((string) ($in['code'] ?? $_GET['code'] ?? ''));
        if ($code === '') json_fail('Vui lòng quét thẻ hoặc nhập mã thiếu nhi.');

        $student = rewards_lookup($code, (int) $year['id']);
        if (!$student) json_fail('Không tìm thấy em nào mang mã "' . $code . '".', 404);

        json_out(['ok' => true, 'student' => $student]);

    // -------------------------------------------------------------
    //  ĐỔI QUÀ TRỰC TIẾP TẠI QUẦY (giỏ nhiều món) — transaction khoá dòng.
    // -------------------------------------------------------------
    case 'redeem':
        require_write();
        $me = require_permission('rewards', 'edit');

        if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không đổi quà được.', 409);

        $code  = trim((string) ($in['studentCode'] ?? ''));
        $items = $in['items'] ?? [];
        if ($code === '') json_fail('Thiếu mã thiếu nhi của em nhận quà.');
        if (!is_array($items) || !$items) json_fail('Giỏ quà đang trống.');

        $student = rewards_lookup($code, (int) $year['id']);
        if (!$student) json_fail('Không tìm thấy em nào mang mã "' . $code . '".', 404);

        try {
            $r = rewards_redeem((int) $student['id'], (int) $year['id'], $items, (int) $me['id']);
        } catch (RewardsError $e) {
            // Lỗi nghiệp vụ (thiếu Mộc/tồn, quà ẩn…): transaction đã rollback,
            // không đổi gì — báo thẳng thông điệp cho người đứng quầy.
            json_fail($e->getMessage());
        } catch (Throwable $e) {
            json_fail(safe_error($e, 'Đổi quà thất bại, đã hoàn tác.'), 500);
        }

        log_action('doi_qua', 'rewards',
                   'Đổi quà cho ' . $student['fullName'] . ' (' . $student['code'] . ')',
                   '-' . $r['total'] . ' Mộc · đơn #' . $r['orderId']);

        Cache::flush();   // tồn kho quà đổi -> danh mục nạp lại thấy ngay
        json_out([
            'ok'             => true,
            'orderId'        => $r['orderId'],
            'total'          => $r['total'],
            'available'      => $r['available'],
            'currentBalance' => $r['currentBalance'],
            'student'        => [
                'id'        => $student['id'],
                'code'      => $student['code'],
                'fullName'  => $student['fullName'],
                'available' => $r['available'],
            ],
        ]);

    // -------------------------------------------------------------
    //  TRA ĐƠN ĐANG CHỜ LẤY của một em theo mã thẻ (cho màn xác nhận đơn).
    // -------------------------------------------------------------
    case 'staff_pending':
        require_permission('rewards', 'view');

        $code = trim((string) ($in['code'] ?? $_GET['code'] ?? ''));
        if ($code === '') json_fail('Vui lòng quét thẻ hoặc nhập mã thiếu nhi.');

        $student = rewards_lookup($code, (int) $year['id']);
        if (!$student) json_fail('Không tìm thấy em nào mang mã "' . $code . '".', 404);

        $pending = rewards_pending_order((int) $student['id'], (int) $year['id']);
        json_out(['ok' => true, 'student' => $student, 'pending' => $pending]);

    // -------------------------------------------------------------
    //  GIAO đơn đặt trước ONLINE cho em — cần mật mã đổi quà, HOẶC
    //  override (Thủ thư xác nhận bằng quyền, em quên mật mã, có log).
    // -------------------------------------------------------------
    case 'confirm':
        require_write();
        $me = require_permission('rewards', 'edit');

        if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không giao quà được.', 409);

        $orderId  = (int) ($in['orderId'] ?? 0);
        // filter_var chấp đúng cả bool JSON lẫn chuỗi "true"/"false"/"1"/"0"
        // (khác !empty(): !empty("false") sẽ SAI thành true vì chuỗi không rỗng).
        $override = filter_var($in['override'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $password = array_key_exists('password', $in) ? (string) $in['password'] : null;

        // Cho phép tra theo mã thiếu nhi thay vì orderId (màn quét thẻ Thủ thư).
        if ($orderId <= 0) {
            $studentCode = trim((string) ($in['studentCode'] ?? ''));
            if ($studentCode === '') json_fail('Thiếu mã đơn hoặc mã thiếu nhi.');

            $student = rewards_lookup($studentCode, (int) $year['id']);
            if (!$student) json_fail('Không tìm thấy em nào mang mã "' . $studentCode . '".', 404);

            $pending = rewards_pending_order((int) $student['id'], (int) $year['id']);
            if (!$pending) json_fail('Em này không có đơn nào đang chờ lấy.', 404);
            $orderId = (int) $pending['orderId'];
        }

        try {
            $r = rewards_confirm_order($orderId, $override ? null : $password, (int) $me['id'], $override);
        } catch (RewardsError $e) {
            json_fail($e->getMessage());
        } catch (Throwable $e) {
            json_fail(safe_error($e, 'Giao đơn thất bại, đã hoàn tác.'), 500);
        }

        // Log RIÊNG cho nhánh override để truy vết ai đã bỏ qua mật mã (SPEC §6bis).
        if ($override) {
            log_action('doi-qua', 'rewards', 'Giao đơn (override mật mã)',
                       'Đơn #' . $r['orderId'] . ' · -' . $r['total'] . ' Mộc');
        } else {
            log_action('doi-qua', 'rewards', 'Giao đơn đặt trước',
                       'Đơn #' . $r['orderId'] . ' · -' . $r['total'] . ' Mộc');
        }

        // Trả số dư khả dụng mới nhất của em nhận đơn.
        $head   = db_one('SELECT student_id, year_id FROM gift_orders WHERE id=?', [$r['orderId']]);
        $wallet = db_one('SELECT current_balance, held_balance FROM student_stamps WHERE student_id=? AND year_id=?',
                          [(int) $head['student_id'], (int) $head['year_id']]);
        $available = max(0, (int) ($wallet['current_balance'] ?? 0) - (int) ($wallet['held_balance'] ?? 0));

        json_out(['ok' => true, 'orderId' => $r['orderId'], 'total' => $r['total'], 'available' => $available]);

    // -------------------------------------------------------------
    //  THỦ THƯ HỦY HỘ đơn đặt trước (em quên/không lấy được) — bỏ qua mật mã.
    // -------------------------------------------------------------
    case 'cancel_staff':
        require_write();
        $me = require_permission('rewards', 'edit');

        $orderId = (int) ($in['orderId'] ?? 0);
        if ($orderId <= 0) {
            $studentCode = trim((string) ($in['studentCode'] ?? ''));
            if ($studentCode === '') json_fail('Thiếu mã đơn hoặc mã thiếu nhi.');

            $student = rewards_lookup($studentCode, (int) $year['id']);
            if (!$student) json_fail('Không tìm thấy em nào mang mã "' . $studentCode . '".', 404);

            $pending = rewards_pending_order((int) $student['id'], (int) $year['id']);
            if (!$pending) json_fail('Em này không có đơn nào đang chờ lấy.', 404);
            $orderId = (int) $pending['orderId'];
        }

        try {
            $r = rewards_cancel_order_staff($orderId, (int) $me['id']);
        } catch (RewardsError $e) {
            json_fail($e->getMessage());
        } catch (Throwable $e) {
            json_fail(safe_error($e, 'Hủy đơn thất bại, đã hoàn tác.'), 500);
        }

        log_action('doi-qua', 'rewards', 'Hủy đơn đặt trước (Thủ thư)',
                   'Đơn #' . $r['orderId'] . ' · +' . $r['total'] . ' Mộc hoàn lại');

        Cache::flush();   // hoàn tồn -> danh mục quà nạp lại thấy ngay
        json_out(['ok' => true, 'orderId' => $r['orderId'], 'total' => $r['total']]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
