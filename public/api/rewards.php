<?php
/**
 * TRẠM ĐỔI QUÀ (POS) — Thủ thư đứng quầy đổi Mộc lấy quà cho các em.
 *
 *   POST api/rewards.php?action=lookup  { code }        -> { student:{...} | null }
 *   GET  api/rewards.php?action=lookup&code=HS001       -> { student:{...} | null }
 *   POST api/rewards.php?action=redeem  { studentCode, items:[{giftId,qty}] }
 *          -> { ok, orderId, total, available, currentBalance }
 *
 * Quyền (SPEC §6bis): module ĐOÀN-WIDE, KHÔNG chia lớp — chỉ gác bằng
 * require_permission('rewards', ...). TUYỆT ĐỐI không gọi can_access_class /
 * allowed_class_ids / scan_class_ids (chống leo thang qua vai thu_thu toàn đoàn).
 * Định danh em bằng MÃ THẺ (students.code), không qua lớp.
 *   - lookup: cần 'view' (admin, thu_thu).
 *   - redeem: cần 'edit' (admin, thu_thu). BĐH/ GLV: none.
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
    default:
        json_fail('Hành động không hợp lệ.', 400);
}
