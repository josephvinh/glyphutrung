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

/*
 * =====================================================================
 *  VÒNG ĐỜI ĐƠN ĐẶT QUÀ ONLINE (SPEC §3.4, §6.4a, §6.5)
 * =====================================================================
 * Khác với đổi trực tiếp tại quầy (rewards_redeem):
 *   - ĐẶT (place): GIỮ Mộc (`held_balance += tổng`) + GIỮ tồn (`stock -= qty`),
 *     KHÔNG trừ `current_balance`, KHÔNG ghi `stamp_transactions`. Đơn 'chờ lấy'.
 *   - GIAO (confirm): chuyển held→spend: `held -= tổng`, `current -= tổng`, ghi
 *     đúng MỘT `stamp_transactions type='spend'` gắn ref_order_id + actor; tồn
 *     GIỮ NGUYÊN (đã trừ lúc đặt). Đơn 'đã giao'.
 *   - HỦY / QUÁ HẠN: NHẢ held + hoàn tồn; đơn 'đã hủy' / 'quá hạn'.
 *
 * AN TOÀN VỚI RECALC: recalc_stamps (StampService) chỉ đặt
 *   current_balance = total_earned + Σ(spend/manual_adjust) và KHÔNG BAO GIỜ
 *   đụng `held_balance`. Vì vòng đời đơn quản `held_balance` riêng và chỉ ghi
 *   'spend' lúc GIAO (đúng thứ recalc cộng vào), hai hệ không giẫm chân nhau.
 *   recalc còn khoá cùng DÒNG ví (SELECT ... FOR UPDATE) nên serialize với các
 *   hàm dưới đây.
 *
 * KHOÁ DÒNG (thứ tự toàn cục chống deadlock — mọi hàm tuân theo):
 *   (1) ví (student_stamps) TRƯỚC, rồi (2) đơn (gift_orders), rồi (3) từng quà
 *   theo id TĂNG DẦN (ksort) — cùng khuôn với rewards_redeem. Từ chối là
 *   ALL-OR-NOTHING: ném RewardsError + rollback, không đổi bất cứ thứ gì.
 */

/**
 * Helper NỘI BỘ: nhả GIỮ (held + tồn) cho một đơn 'chờ lấy' và đặt trạng thái
 * mới ('đã hủy' hoặc 'quá hạn'). PHẢI gọi trong một transaction.
 *
 * Khoá theo thứ tự toàn cục: ví → đơn → quà (id tăng dần).
 *
 * @return array{studentId:int,yearId:int,total:int}
 * @throws RewardsError nếu đơn không tồn tại hoặc không còn 'chờ lấy'.
 */
function _rewards_release_order(int $orderId, string $newStatus): array
{
    // Đọc nhẹ để biết ví nào cần khoá TRƯỚC (giữ đúng thứ tự ví → đơn).
    $head = db_one("SELECT student_id, year_id FROM gift_orders WHERE id=?", [$orderId]);
    if (!$head) {
        throw new RewardsError('Không tìm thấy đơn đặt quà.');
    }
    $sid = (int) $head['student_id'];
    $yid = (int) $head['year_id'];

    // 1) Khoá ví.
    db_one("SELECT id FROM student_stamps WHERE student_id=? AND year_id=? FOR UPDATE", [$sid, $yid]);

    // 2) Khoá + kiểm đơn (phải còn 'chờ lấy' sau khi khoá — chống đua với confirm/hủy).
    $o = db_one("SELECT id, total_cost, status FROM gift_orders WHERE id=? FOR UPDATE", [$orderId]);
    if (!$o || $o['status'] !== 'chờ lấy') {
        throw new RewardsError('Đơn không còn ở trạng thái chờ lấy.');
    }
    $total = (int) $o['total_cost'];

    // 3) Khoá từng quà theo id tăng dần (items đã ORDER BY gift_id), rồi hoàn tồn.
    $items = db_all(
        "SELECT gift_id, qty FROM gift_order_items WHERE order_id=? ORDER BY gift_id",
        [$orderId]
    );
    foreach ($items as $it) {
        db_one("SELECT id FROM gifts WHERE id=? FOR UPDATE", [(int) $it['gift_id']]);
    }
    foreach ($items as $it) {
        db_run("UPDATE gifts SET stock = stock + ? WHERE id=?", [(int) $it['qty'], (int) $it['gift_id']]);
    }

    // Nhả Mộc giữ — GREATEST(0,…) bảo đảm held_balance không bao giờ âm.
    db_run(
        "UPDATE student_stamps SET held_balance = GREATEST(0, held_balance - ?)
          WHERE student_id=? AND year_id=?",
        [$total, $sid, $yid]
    );
    db_run("UPDATE gift_orders SET status=? WHERE id=?", [$newStatus, $orderId]);

    return ['studentId' => $sid, 'yearId' => $yid, 'total' => $total];
}

/**
 * ĐẶT ĐƠN ĐẶT TRƯỚC — giữ Mộc + giữ tồn, tạo đơn 'chờ lấy', nguyên tử.
 *
 * @param int    $studentId  em đặt quà (đã tra từ mã thẻ ở handler)
 * @param int    $yearId     năm học (ví theo năm)
 * @param array  $items      [['giftId'=>int,'qty'=>int], ...]
 * @param string $plainCode  mật mã đổi quà do em tự đặt (chỉ lưu HASH)
 * @param int    $expireDays số ngày tới hạn lấy (mặc định 7)
 * @return array{orderId:int,total:int,expiresAt:string}
 * @throws RewardsError khi đã có đơn chờ lấy, giỏ không hợp lệ, thiếu tồn, thiếu Mộc khả dụng
 */
function rewards_place_order(int $studentId, int $yearId, array $items, string $plainCode, int $expireDays = 7): array
{
    // Chuẩn hoá + gộp số lượng theo từng quà (mỗi quà khoá & kiểm một lần).
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
    ksort($qtyByGift);   // khoá quà theo id tăng dần — chống deadlock giữa các quầy.

    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        // 1) KHOÁ ví trước. Chưa có ví coi như số dư 0 (mọi tổng>0 sẽ bị chặn ở bước 4).
        $wallet  = db_one(
            "SELECT current_balance, held_balance FROM student_stamps
              WHERE student_id=? AND year_id=? FOR UPDATE",
            [$studentId, $yearId]
        );
        $current   = (int) ($wallet['current_balance'] ?? 0);
        $held      = (int) ($wallet['held_balance'] ?? 0);
        $available = $current - $held;

        // 2) MỖI EM 1 ĐƠN 'chờ lấy'. Đã khoá ví ở (1) nên hai lượt đặt của cùng
        //    một em serialize tại đây; khoá luôn dòng đơn (nếu có) cho chắc.
        $existing = db_one(
            "SELECT id FROM gift_orders
              WHERE student_id=? AND year_id=? AND status='chờ lấy' LIMIT 1 FOR UPDATE",
            [$studentId, $yearId]
        );
        if ($existing) {
            throw new RewardsError('Em đang có một đơn chờ lấy. Hãy lấy hoặc hủy đơn đó trước khi đặt đơn mới.');
        }

        // 3) KHOÁ từng quà, kiểm trạng thái + tồn, cộng tổng.
        $total = 0;
        $lines = [];   // gid => ['unit','qty','line']
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
            $lines[$gid] = ['unit' => $unit, 'qty' => $qty, 'line' => $line];
        }

        // 4) Kiểm khả dụng ≥ tổng — bảo đảm available (current−held) không âm sau khi giữ.
        if ($available < $total) {
            throw new RewardsError('Số Mộc khả dụng không đủ (' . $available . '/' . $total . ').');
        }

        // 5) Tạo đơn 'chờ lấy' (lưu HASH mật mã; hạn lấy = NOW() + expireDays ngày).
        //    $expireDays là int nội bộ nên nội suy an toàn (INTERVAL không nhận placeholder ổn định).
        $orderId = db_insert(
            "INSERT INTO gift_orders
                (year_id, student_id, total_cost, redeem_code_hash, status, expires_at)
             VALUES (?,?,?,?, 'chờ lấy', DATE_ADD(NOW(), INTERVAL " . (int) $expireDays . " DAY))",
            [$yearId, $studentId, $total, password_hash($plainCode, PASSWORD_DEFAULT)]
        );

        // 6) Ghi dòng quà + GIỮ tồn (trừ tồn ngay lúc đặt, hoàn lại khi hủy/quá hạn).
        foreach ($lines as $gid => $l) {
            db_run(
                "INSERT INTO gift_order_items (order_id, gift_id, qty, unit_cost, line_cost)
                 VALUES (?,?,?,?,?)",
                [$orderId, $gid, $l['qty'], $l['unit'], $l['line']]
            );
            db_run("UPDATE gifts SET stock = stock - ? WHERE id=?", [$l['qty'], $gid]);
        }

        // 7) GIỮ Mộc (chỉ tăng held, KHÔNG đụng current_balance, KHÔNG ghi audit).
        db_run(
            "UPDATE student_stamps SET held_balance = held_balance + ?
              WHERE student_id=? AND year_id=?",
            [$total, $studentId, $yearId]
        );

        $expiresAt = (string) db_val("SELECT expires_at FROM gift_orders WHERE id=?", [$orderId]);

        if ($ownTx) db()->commit();
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    return [
        'orderId'   => (int) $orderId,
        'total'     => (int) $total,
        'expiresAt' => $expiresAt,
    ];
}

/**
 * EM TỰ HỦY đơn 'chờ lấy' — cần mật mã đổi quà đúng; nhả held + hoàn tồn.
 *
 * @return array{orderId:int,total:int}
 * @throws RewardsError khi không có đơn chờ lấy hoặc mật mã sai.
 */
function rewards_cancel_order(int $studentId, int $yearId, string $plainCode): array
{
    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        // 1) Khoá ví trước (giữ thứ tự toàn cục).
        db_one("SELECT id FROM student_stamps WHERE student_id=? AND year_id=? FOR UPDATE", [$studentId, $yearId]);

        // 2) Khoá đơn 'chờ lấy' của em + đọc hash.
        $o = db_one(
            "SELECT id, redeem_code_hash FROM gift_orders
              WHERE student_id=? AND year_id=? AND status='chờ lấy'
              ORDER BY id DESC LIMIT 1 FOR UPDATE",
            [$studentId, $yearId]
        );
        if (!$o) {
            throw new RewardsError('Không có đơn nào đang chờ lấy.');
        }
        if (!password_verify($plainCode, (string) $o['redeem_code_hash'])) {
            throw new RewardsError('Mật mã đổi quà không đúng.');
        }

        // 3) Nhả held + tồn (helper tự khoá lại ví/đơn/quà — reentrant, an toàn).
        $res = _rewards_release_order((int) $o['id'], 'đã hủy');

        if ($ownTx) db()->commit();
        return ['orderId' => (int) $o['id'], 'total' => $res['total']];
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * THỦ THƯ HỦY HỘ đơn 'chờ lấy' — bỏ qua mật mã (caller đã gác quyền + ghi log
 * ở lớp API); nhả held + hoàn tồn.
 *
 * @return array{orderId:int,total:int}
 * @throws RewardsError khi đơn không tồn tại hoặc không còn 'chờ lấy'.
 */
function rewards_cancel_order_staff(int $orderId, int $actorId): array
{
    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        $res = _rewards_release_order($orderId, 'đã hủy');
        if ($ownTx) db()->commit();
        return ['orderId' => $orderId, 'total' => $res['total']];
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * XÁC NHẬN GIAO đơn 'chờ lấy' — chuyển held→spend, ghi audit, đơn 'đã giao'.
 *
 * @param int         $orderId
 * @param string|null $plainCode  mật mã đổi quà (bỏ qua nếu $override=true)
 * @param int         $actorId    Thủ thư/Quản trị giao quà
 * @param bool        $override   true = bỏ qua mật mã (đã gác quyền edit + log ở API)
 * @return array{orderId:int,total:int}
 * @throws RewardsError khi đơn không còn 'chờ lấy' hoặc mật mã sai (không override).
 */
function rewards_confirm_order(int $orderId, ?string $plainCode, int $actorId, bool $override = false): array
{
    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        // Đọc nhẹ để biết ví cần khoá TRƯỚC (thứ tự ví → đơn).
        $head = db_one("SELECT student_id, year_id FROM gift_orders WHERE id=?", [$orderId]);
        if (!$head) {
            throw new RewardsError('Không tìm thấy đơn đặt quà.');
        }
        $sid = (int) $head['student_id'];
        $yid = (int) $head['year_id'];

        // 1) Khoá ví.
        db_one("SELECT id FROM student_stamps WHERE student_id=? AND year_id=? FOR UPDATE", [$sid, $yid]);

        // 2) Khoá + kiểm đơn.
        $o = db_one(
            "SELECT id, total_cost, redeem_code_hash, status FROM gift_orders WHERE id=? FOR UPDATE",
            [$orderId]
        );
        if (!$o || $o['status'] !== 'chờ lấy') {
            throw new RewardsError('Đơn không còn ở trạng thái chờ lấy.');
        }
        if (!$override) {
            if ($plainCode === null || !password_verify($plainCode, (string) $o['redeem_code_hash'])) {
                throw new RewardsError('Mật mã đổi quà không đúng.');
            }
        }
        $total = (int) $o['total_cost'];

        // Mô tả liệt kê quà cho giao dịch audit.
        $items = db_all(
            "SELECT i.qty, g.name FROM gift_order_items i JOIN gifts g ON g.id=i.gift_id
              WHERE i.order_id=? ORDER BY i.gift_id",
            [$orderId]
        );
        $descParts = [];
        foreach ($items as $it) {
            $descParts[] = $it['name'] . ' x' . (int) $it['qty'];
        }

        // 3) Chuyển held→spend: nhả held (GREATEST giữ ≥0) + trừ current thật. Tồn GIỮ NGUYÊN.
        db_run(
            "UPDATE student_stamps
                SET held_balance = GREATEST(0, held_balance - ?),
                    current_balance = current_balance - ?
              WHERE student_id=? AND year_id=?",
            [$total, $total, $sid, $yid]
        );

        // 4) MỘT giao dịch audit 'spend' cho cả đơn (amount âm, gắn ref_order_id + actor).
        db_run(
            "INSERT INTO stamp_transactions
                (year_id, student_id, amount, type, ref_order_id, description, actor_id)
             VALUES (?,?,?, 'spend', ?, ?, ?)",
            [$yid, $sid, -$total, $orderId, 'Giao đơn đặt quà: ' . implode(', ', $descParts), $actorId]
        );

        // 5) Đơn 'đã giao'.
        db_run(
            "UPDATE gift_orders SET status='đã giao', delivered_by=?, delivered_at=NOW() WHERE id=?",
            [$actorId, $orderId]
        );

        if ($ownTx) db()->commit();
        return ['orderId' => $orderId, 'total' => $total];
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}

/**
 * QUÁ HẠN TỰ HỦY — mọi đơn 'chờ lấy' có expires_at < $now chuyển 'quá hạn',
 * nhả held + hoàn tồn. Gọi lazy trước khi đọc/đặt.
 *
 * Mỗi đơn xử lý trong MỘT transaction riêng (nguyên tử theo từng đơn) để tránh
 * khoá chéo nhiều ví/quà cùng lúc; đơn bị luồng khác đổi trạng thái giữa chừng
 * sẽ bị bỏ qua (helper ném RewardsError trước khi ghi).
 *
 * @param int         $yearId
 * @param string|null $now  thời điểm mốc (test bơm); mặc định NOW() phía PHP.
 * @return int số đơn đã chuyển 'quá hạn'.
 */
function rewards_expire_due(int $yearId, ?string $now = null): int
{
    $now = $now ?? date('Y-m-d H:i:s');
    $rows = db_all(
        "SELECT id FROM gift_orders
          WHERE year_id=? AND status='chờ lấy' AND expires_at < ? ORDER BY id",
        [$yearId, $now]
    );

    $count = 0;
    foreach ($rows as $r) {
        $oid   = (int) $r['id'];
        $ownTx = !db()->inTransaction();
        if ($ownTx) db()->beginTransaction();
        try {
            _rewards_release_order($oid, 'quá hạn');
            if ($ownTx) db()->commit();
            $count++;
        } catch (RewardsError $e) {
            // Đơn đã đổi trạng thái bởi luồng khác giữa lúc đọc và khoá → bỏ qua.
            if ($ownTx && db()->inTransaction()) db()->rollBack();
        } catch (Throwable $e) {
            if ($ownTx && db()->inTransaction()) db()->rollBack();
            throw $e;
        }
    }
    return $count;
}

/**
 * ĐƠN 'chờ lấy' HIỆN TẠI của em (kèm items + hạn lấy), hoặc null.
 * Chỉ đọc — dùng cho tab Sổ Mộc / màn Thủ thư.
 *
 * @return array{orderId:int,total:int,expiresAt:string,createdAt:string,items:array}|null
 */
function rewards_pending_order(int $studentId, int $yearId): ?array
{
    $o = db_one(
        "SELECT id, total_cost, expires_at, created_at FROM gift_orders
          WHERE student_id=? AND year_id=? AND status='chờ lấy' ORDER BY id DESC LIMIT 1",
        [$studentId, $yearId]
    );
    if (!$o) return null;

    $items = db_all(
        "SELECT i.gift_id, g.name, i.qty, i.unit_cost, i.line_cost
           FROM gift_order_items i JOIN gifts g ON g.id=i.gift_id
          WHERE i.order_id=? ORDER BY i.id",
        [(int) $o['id']]
    );
    $out = [];
    foreach ($items as $it) {
        $out[] = [
            'giftId'   => (int) $it['gift_id'],
            'name'     => $it['name'],
            'qty'      => (int) $it['qty'],
            'unitCost' => (int) $it['unit_cost'],
            'lineCost' => (int) $it['line_cost'],
        ];
    }

    return [
        'orderId'   => (int) $o['id'],
        'total'     => (int) $o['total_cost'],
        'expiresAt' => (string) $o['expires_at'],
        'createdAt' => (string) $o['created_at'],
        'items'     => $out,
    ];
}
