<?php
/**
 * TRẠM ĐỔI QUÀ (POS) — lõi giao dịch dùng chung cho rewards.php.
 *
 * Tách phần TRỪ MỘC + TRỪ TỒN + GHI AUDIT ra khỏi handler để unit test được
 * mà không phải chạy qua toàn bộ endpoint (rewards.php đọc php://input và gọi
 * require_login() ngay khi được require, không hợp để test trực tiếp) — giống
 * cách _gifts.php tách validate khỏi gifts.php.
 *
 * Nguyên tắc (SPEC-MOC-DIEN-TU §3.4, §6.4b, §6bis):
 *   - Đổi quà là SPEND, ĐỘC LẬP với engine recalc (recalc không đụng spend/held).
 *   - Khả dụng để tiêu = current_balance − held_balance, và LUÔN ≥ 0.
 *   - Toàn bộ trừ Mộc + trừ tồn chạy trong MỘT transaction có KHOÁ DÒNG
 *     (SELECT ... FOR UPDATE ví + từng quà) để chống đua khi nhiều quầy thao
 *     tác cùng lúc. Từ chối là ALL-OR-NOTHING: rollback, không đổi bất cứ gì.
 *   - Module rewards KHÔNG chia theo lớp: định danh em bằng mã thẻ, gác quyền
 *     chỉ bằng require_permission('rewards',...). Không gọi can_access_class /
 *     allowed_class_ids / scan_class_ids ở đây (chống leo thang, §6bis).
 */

require_once __DIR__ . '/../../config/db.php';

/**
 * Lỗi nghiệp vụ khi đổi quà — thông điệp tiếng Việt hiện thẳng cho người dùng.
 * rewards.php bắt riêng loại này để json_fail(400); mọi lỗi khác bị coi là lỗi
 * hệ thống (500) và không lộ chi tiết.
 */
class RewardsError extends RuntimeException {}

/**
 * ĐỔI QUÀ TRỰC TIẾP TẠI QUẦY — trừ Mộc + trừ tồn + ghi giao dịch, nguyên tử.
 *
 * @param int   $studentId  em nhận quà (đã tra ra từ mã thẻ ở handler)
 * @param int   $yearId     năm học (ví theo năm)
 * @param array $items      [['giftId'=>int, 'qty'=>int], ...] — giỏ nhiều món
 * @param int   $actorId    Thủ thư/Quản trị đang giao quà (members.id)
 * @return array{orderId:int,total:int,available:int,currentBalance:int,heldBalance:int}
 * @throws RewardsError  khi giỏ không hợp lệ, thiếu Mộc, thiếu tồn, quà ẩn/không có
 */
function rewards_redeem(int $studentId, int $yearId, array $items, int $actorId): array
{
    // --- Chuẩn hoá + gộp số lượng theo từng quà -------------------------
    // Gộp để mỗi quà chỉ khoá & kiểm tồn một lần dù giỏ lặp cùng một món.
    $qtyByGift = [];
    foreach ($items as $it) {
        $gid = (int) ($it['giftId'] ?? 0);
        $qty = (int) ($it['qty'] ?? 0);
        if ($gid <= 0 || $qty <= 0) {
            throw new RewardsError('Món quà hoặc số lượng không hợp lệ.');
        }
        $qtyByGift[$gid] = ($qtyByGift[$gid] ?? 0) + $qty;
    }
    if (!$qtyByGift) {
        throw new RewardsError('Giỏ quà đang trống.');
    }
    // Khoá theo thứ tự id tăng dần cho ổn định (chống deadlock giữa các quầy).
    ksort($qtyByGift);

    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        // 1) KHOÁ ví trước. Chưa có ví (em chưa từng có Mộc) coi như số dư 0.
        $wallet  = db_one(
            "SELECT current_balance, held_balance FROM student_stamps
              WHERE student_id=? AND year_id=? FOR UPDATE",
            [$studentId, $yearId]
        );
        $current = (int) ($wallet['current_balance'] ?? 0);
        $held    = (int) ($wallet['held_balance'] ?? 0);
        $available = $current - $held;

        // 2) KHOÁ từng quà, kiểm trạng thái + tồn, cộng tổng.
        $total = 0;
        $lines = [];   // gid => ['name','unit','qty','line']
        foreach ($qtyByGift as $gid => $qty) {
            $g = db_one(
                "SELECT id, name, stamp_cost, stock, status FROM gifts WHERE id=? FOR UPDATE",
                [$gid]
            );
            if (!$g || $g['status'] === 'ẩn') {
                throw new RewardsError('Có món quà không còn bán hoặc không tồn tại.');
            }
            if ((int) $g['stock'] < $qty) {
                throw new RewardsError('Quà "' . $g['name'] . '" không đủ tồn kho (còn ' . (int) $g['stock'] . ').');
            }
            $unit = (int) $g['stamp_cost'];
            $line = $unit * $qty;
            $total += $line;
            $lines[$gid] = ['name' => $g['name'], 'unit' => $unit, 'qty' => $qty, 'line' => $line];
        }

        // 3) Kiểm khả dụng ≥ tổng — bảo đảm số dư không bao giờ âm.
        if ($available < $total) {
            throw new RewardsError('Số Mộc khả dụng không đủ (' . $available . '/' . $total . ').');
        }

        // 4) Tạo đơn 'đã giao' (bán trực tiếp: không mật mã, hết hạn = ngay).
        $orderId = db_insert(
            "INSERT INTO gift_orders
                (year_id, student_id, total_cost, redeem_code_hash, status, expires_at, delivered_by, delivered_at)
             VALUES (?,?,?, '', 'đã giao', NOW(), ?, NOW())",
            [$yearId, $studentId, $total, $actorId]
        );

        // 5) Ghi dòng quà + trừ tồn từng món.
        $descParts = [];
        foreach ($lines as $gid => $l) {
            db_run(
                "INSERT INTO gift_order_items (order_id, gift_id, qty, unit_cost, line_cost)
                 VALUES (?,?,?,?,?)",
                [$orderId, $gid, $l['qty'], $l['unit'], $l['line']]
            );
            db_run("UPDATE gifts SET stock = stock - ? WHERE id=?", [$l['qty'], $gid]);
            $descParts[] = $l['name'] . ' x' . $l['qty'];
        }

        // 6) Trừ Mộc thật khỏi ví. Ví CHẮC CHẮN tồn tại ở đây: nếu thiếu ví thì
        //    available = 0 − 0 = 0 < total (total>0), đã bị chặn ở bước (3).
        db_run(
            "UPDATE student_stamps SET current_balance = current_balance - ?
              WHERE student_id=? AND year_id=?",
            [$total, $studentId, $yearId]
        );

        // 7) MỘT giao dịch audit 'spend' cho cả đơn (amount âm, gắn ref_order_id).
        db_run(
            "INSERT INTO stamp_transactions
                (year_id, student_id, amount, type, ref_order_id, description, actor_id)
             VALUES (?,?,?, 'spend', ?, ?, ?)",
            [$yearId, $studentId, -$total, $orderId, 'Đổi quà tại quầy: ' . implode(', ', $descParts), $actorId]
        );

        if ($ownTx) db()->commit();
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    $newBalance = $current - $total;
    return [
        'orderId'        => (int) $orderId,
        'total'          => (int) $total,
        'available'      => (int) ($newBalance - $held),
        'currentBalance' => (int) $newBalance,
        'heldBalance'    => (int) $held,
    ];
}

/**
 * TRA CỨU EM theo mã thẻ (students.code) — tên + số dư khả dụng năm hiện tại.
 * KHÔNG chia lớp (§6bis): chỉ tra theo mã, không lọc theo phạm vi lớp/khối.
 *
 * @return array{id:int,code:string,fullName:string,available:int,currentBalance:int,heldBalance:int}|null
 *   null nếu không có em nào mang mã này.
 */
function rewards_lookup(string $code, int $yearId): ?array
{
    $code = trim($code);
    if ($code === '') return null;

    $s = db_one("SELECT id, code, full_name FROM students WHERE code=?", [$code]);
    if (!$s) return null;

    $w = db_one(
        "SELECT current_balance, held_balance FROM student_stamps WHERE student_id=? AND year_id=?",
        [(int) $s['id'], $yearId]
    );
    $current = (int) ($w['current_balance'] ?? 0);
    $held    = (int) ($w['held_balance'] ?? 0);

    // Hiển thị không bao giờ âm: số dư có thể âm trong tình huống hiếm (admin
    // xoá buổi điểm danh đã "nuôi" mộc mà em đã đổi quà) — giá trị thật vẫn được
    // giữ trong CSDL để chặn tiêu tiếp (rewards_redeem đọc thẳng bản ghi, không
    // qua hàm này), nhưng ngoài màn hình chỉ cho thấy 0 trở lên.
    return [
        'id'             => (int) $s['id'],
        'code'           => $s['code'],
        'fullName'       => $s['full_name'],
        'available'      => max(0, $current - $held),
        'currentBalance' => max(0, $current),
        'heldBalance'    => $held,
    ];
}
