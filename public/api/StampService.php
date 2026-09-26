<?php
/**
 * ENGINE SỔ MỘC — tính Mộc (điểm thưởng) & chuỗi đi lễ liên tiếp.
 *
 * NGUỒN CHÂN LÝ là bảng `attendances`. Sau mỗi thay đổi điểm danh của một
 * em, gọi recalc_stamps(student, year): engine TÍNH LẠI TỪ ĐẦU toàn bộ phần
 * Mộc kiếm được + chuỗi, rồi đồng bộ ví (student_stamps) và các giao dịch
 * loại 'attendance'/'streak_bonus' (stamp_transactions). Nhờ tính lại từ đầu:
 *   - gỡ điểm danh → tự hoàn Mộc, số liệu không bao giờ lệch;
 *   - gọi nhiều lần cho cùng kết quả (idempotent).
 *
 * recalc CHỈ đụng phần Earn/Streak. Phần Spend/Held (đổi quà, điều chỉnh tay)
 * do luồng khác quản lý bằng transaction có khoá dòng và được engine GIỮ NGUYÊN.
 *
 * Quy tắc nghiệp vụ (SPEC-MOC-DIEN-TU §3):
 *   - Earn theo NGÀY: ngày có ≥1 buổi emulation → +1; nếu là Chúa Nhật → +2.
 *     Đi trễ vẫn được Mộc "đi lễ".
 *   - Streak: xét trên các NGÀY CÓ LỊCH của (các) buổi emulation trong khoảng
 *     [effective_from, min(hôm nay, effective_to)]. Đi lễ (đúng giờ HOẶC trễ)
 *     nối chuỗi; ngày có lịch mà vắng → chuỗi về 0. KHÔNG đọc leave_requests
 *     (nghỉ có phép vẫn đứt chuỗi). Hôm nay chưa qua buổi thì chưa tính vắng.
 *   - Thưởng chuỗi khi chuỗi CHẠM mốc: 3→+1, 7→+3, 30→+15; nhưng nếu ngày chạm
 *     mốc là "đi trễ" thì MẤT mốc thưởng đó (không trả bù), chuỗi vẫn chạy tiếp.
 *   - Chỉ tính điểm danh vào/sau effective_from của chương trình.
 */

require_once __DIR__ . '/../../config/db.php';

if (!defined('STAMP_MILESTONES')) {
    // mốc chuỗi => số Mộc thưởng
    define('STAMP_MILESTONES', [3 => 1, 7 => 3, 30 => 15]);
}

if (!defined('STAMP_RECENT_LIMIT')) {
    // số giao dịch gần nhất hiển thị ở card Sổ Mộc hồ sơ — dùng chung bởi
    // stamp_summary() (một em) và stamp_summaries_bulk() (nhiều em, data.php)
    // để hai đường không lệch giới hạn nhau.
    define('STAMP_RECENT_LIMIT', 15);
}

/**
 * Buổi này có tính Mộc hay không — luật duy nhất là cờ count_for_emulation
 * trên chương trình. Tách riêng thành helper để nơi gọi (attendance.php)
 * và test đều dùng chung một chỗ quyết định, tránh lệch điều kiện.
 */
function program_earns_stamps(array $prog): bool
{
    return !empty($prog['count_for_emulation']);
}

/**
 * Gọi recalc_stamps() một cách AN TOÀN: mỗi lần điểm danh của một buổi có
 * tính Mộc thay đổi thì ví/chuỗi của em đó phải được tính lại, nhưng lỗi ở
 * Engine Sổ Mộc TUYỆT ĐỐI không được làm hỏng việc ghi điểm danh — vì vậy
 * bọc try/catch, có lỗi chỉ ghi log rồi bỏ qua (KHÔNG rethrow).
 *
 * @param callable|null $fn  Cho phép TIÊM hàm thay recalc_stamps() thật —
 *   chỉ dùng để test (ép lỗi) mà không cần chạm CSDL thật.
 * @return bool  true nếu recalc thành công, false nếu bắt được lỗi.
 */
function recalc_stamps_safe(int $studentId, int $yearId, ?callable $fn = null): bool
{
    $fn ??= 'recalc_stamps';
    try {
        $fn($studentId, $yearId);
        return true;
    } catch (Throwable $e) {
        TNTT\Logger::getInstance()->warning('Sổ Mộc: recalc_stamps lỗi', [
            'student_id' => $studentId,
            'year_id'    => $yearId,
            'error'      => $e->getMessage(),
        ]);
        return false;
    }
}

/**
 * Bản đồ NGÀY-CÓ-ĐIỂM-DANH của một em trong năm, đã gộp theo ngày.
 *
 * @return array<string,string>  ['Y-m-d' => 'có mặt'|'đi trễ']
 *   Mỗi ngày có ≥1 buổi emulation một mục. Nếu trong ngày vừa có "có mặt" vừa
 *   "đi trễ" thì "có mặt" thắng (ưu tiên trạng thái tốt hơn).
 *   Chỉ tính buổi vào/sau effective_from của chương trình.
 */
function stamp_earn_days(int $studentId, int $yearId): array
{
    // CỐ Ý bất đối xứng: earn chỉ chặn bằng effective_from, KHÔNG chặn bằng
    // effective_to. Một buổi được điểm danh (đã qua kiểm tra lịch ở attendance.php)
    // vẫn sinh Mộc dù ngoài khoảng effective_to; chỉ chuỗi (streak) mới bó theo
    // effective_to. test_untoggle_refunds dựa vào hành vi này.
    $rows = db_all(
        "SELECT a.session_date AS d, a.status AS st
           FROM attendances a
           JOIN programs p ON p.id = a.program_id
          WHERE a.student_id = ?
            AND a.year_id   = ?
            AND p.count_for_emulation = 1
            AND (p.effective_from IS NULL OR a.session_date >= p.effective_from)",
        [$studentId, $yearId]
    );

    $days = [];
    foreach ($rows as $r) {
        $d  = $r['d'];
        $st = $r['st'];
        // "có mặt" thắng "đi trễ" khi cùng một ngày có nhiều buổi.
        if (!isset($days[$d]) || $st === 'có mặt') {
            $days[$d] = $st;
        }
    }
    ksort($days);
    return $days;
}

/**
 * Một attendance ĐẠI DIỆN cho mỗi ngày (để gắn ref_attendance_id cho giao dịch
 * earn/bonus — earn tính theo NGÀY nên mỗi ngày chỉ một giao dịch attendance).
 * Ưu tiên buổi "có mặt"; trong cùng trạng thái lấy id nhỏ nhất cho ổn định.
 *
 * @return array<string,int>  ['Y-m-d' => attendance_id]
 */
function stamp_rep_attendance(int $studentId, int $yearId): array
{
    // Cùng bộ lọc với stamp_earn_days: chỉ chặn effective_from, KHÔNG chặn
    // effective_to (xem chú thích ở stamp_earn_days).
    $rows = db_all(
        "SELECT a.id, a.session_date AS d, a.status AS st
           FROM attendances a
           JOIN programs p ON p.id = a.program_id
          WHERE a.student_id = ?
            AND a.year_id   = ?
            AND p.count_for_emulation = 1
            AND (p.effective_from IS NULL OR a.session_date >= p.effective_from)
          ORDER BY a.session_date, a.id",
        [$studentId, $yearId]
    );

    $rep = [];      // date => id
    $repIsPresent = []; // date => bool (đại diện hiện tại có phải "có mặt")
    foreach ($rows as $r) {
        $d = $r['d'];
        $isPresent = ($r['st'] === 'có mặt');
        if (!isset($rep[$d])) {
            $rep[$d] = (int) $r['id'];
            $repIsPresent[$d] = $isPresent;
            continue;
        }
        // Nâng cấp lên buổi "có mặt" nếu đại diện cũ là "đi trễ".
        if ($isPresent && !$repIsPresent[$d]) {
            $rep[$d] = (int) $r['id'];
            $repIsPresent[$d] = true;
        }
    }
    return $rep;
}

/**
 * Tập NGÀY-CÓ-LỊCH của các buổi emulation trong năm (để tính chuỗi).
 * Cửa sổ mỗi chương trình: [start, min(hôm nay, effective_to)].
 *   - start = effective_from nếu có; nếu NULL, lùi về ngày điểm danh sớm nhất
 *     của em (fallback an toàn — thực tế chương trình "đi lễ" luôn đặt
 *     effective_from). Không có mốc bắt đầu và cũng không có điểm danh → bỏ qua.
 *   - Buổi lặp: các ngày có thứ ∈ days_of_week (CSV) hoặc day_of_week.
 *   - Buổi chiến dịch: đúng event_date.
 *
 * @param string $today  'Y-m-d' (tham số hoá để test được; mặc định hôm nay)
 * @return string[]  danh sách ngày 'Y-m-d' đã sắp tăng dần, không trùng
 */
function stamp_scheduled_days(int $studentId, int $yearId, string $today, array $earnDays): array
{
    $progs = db_all(
        "SELECT id, type, day_of_week, days_of_week, event_date, effective_from, effective_to
           FROM programs
          WHERE year_id = ? AND count_for_emulation = 1",
        [$yearId]
    );
    if (!$progs) return [];

    $earliestAtt = $earnDays ? array_key_first($earnDays) : null; // earnDays đã ksort

    $set = [];
    foreach ($progs as $p) {
        if ($p['type'] === 'chiến dịch') {
            $ed = $p['event_date'] ?? null;
            if ($ed && $ed <= $today) $set[$ed] = true;
            continue;
        }

        // Buổi lặp theo thứ trong tuần. LƯU Ý: không dùng empty() cho days_of_week
        // vì "0" (chỉ Chúa Nhật) bị empty() coi là rỗng → mất lịch chuỗi. Giá trị
        // "0" vẫn qua được array_filter($x>=0 && $x<=6) bên dưới.
        $days = ($p['days_of_week'] !== null && $p['days_of_week'] !== '')
            ? array_values(array_filter(array_map('intval', explode(',', $p['days_of_week'])), fn($x) => $x >= 0 && $x <= 6))
            : ($p['day_of_week'] === null ? [] : [(int) $p['day_of_week']]);
        if (!$days) continue;

        $start = $p['effective_from'] ?: $earliestAtt;
        if (!$start) continue; // không có mốc bắt đầu → không dựng được cửa sổ

        $end = $today;
        if (!empty($p['effective_to']) && $p['effective_to'] < $end) $end = $p['effective_to'];
        if ($start > $end) continue;

        $cur = new DateTime($start);
        $endD = new DateTime($end);
        while ($cur <= $endD) {
            if (in_array((int) $cur->format('w'), $days, true)) {
                $set[$cur->format('Y-m-d')] = true;
            }
            $cur->modify('+1 day');
        }
    }

    $out = array_keys($set);
    sort($out);
    return $out;
}

/**
 * TÍNH LẠI toàn bộ Mộc + chuỗi cho một em trong một năm và đồng bộ CSDL.
 *
 * @param string|null $today  ghi đè "hôm nay" (chỉ dùng cho test). Mặc định date('Y-m-d').
 * @return array{current_balance:int,total_earned:int,current_streak:int,longest_streak:int,last_attendance_date:?string,held_balance:int}
 */
function recalc_stamps(int $studentId, int $yearId, ?string $today = null): array
{
    $today ??= date('Y-m-d');

    $earnDays  = stamp_earn_days($studentId, $yearId);              // date => status
    $repAtt    = stamp_rep_attendance($studentId, $yearId);        // date => attendance_id
    $scheduled = stamp_scheduled_days($studentId, $yearId, $today, $earnDays);

    // --- Tính earn theo ngày --------------------------------------------
    $totalEarned = 0;
    $earnTx = [];   // mỗi phần tử: [amount, ref_attendance_id, date]
    foreach ($earnDays as $d => $st) {
        $amt = (date('w', strtotime($d)) === '0') ? 2 : 1;   // Chúa Nhật +2
        $totalEarned += $amt;
        $earnTx[] = ['amount' => $amt, 'ref' => $repAtt[$d] ?? null, 'date' => $d];
    }

    // --- Duyệt các ngày có lịch để tính chuỗi + thưởng mốc --------------
    $streak = 0;
    $longest = 0;
    $bonusTx = [];  // [amount, ref_attendance_id, streak_len, date]
    foreach ($scheduled as $d) {
        $attended = isset($earnDays[$d]);

        if (!$attended) {
            // Hôm nay chưa qua buổi thì chưa tính vắng (không reset).
            if ($d === $today) continue;
            $streak = 0;
            continue;
        }

        $streak++;
        if ($streak > $longest) $longest = $streak;

        // Thưởng khi chuỗi CHẠM mốc, nhưng mất thưởng nếu hôm đó đi trễ.
        if (isset(STAMP_MILESTONES[$streak]) && $earnDays[$d] === 'có mặt') {
            $bonus = STAMP_MILESTONES[$streak];
            $totalEarned += $bonus;
            $bonusTx[] = ['amount' => $bonus, 'ref' => $repAtt[$d] ?? null, 'len' => $streak, 'date' => $d];
        }
    }
    $currentStreak = $streak;

    // last_attendance_date = ngày điểm danh gần nhất (earnDays đã ksort tăng dần).
    $lastAtt = $earnDays ? array_key_last($earnDays) : null;

    // Số dư = earn (kể cả thưởng) + tổng spend/manual_adjust (engine không đụng).
    $adjust = (int) (db_val(
        "SELECT COALESCE(SUM(amount),0) FROM stamp_transactions
          WHERE student_id=? AND year_id=? AND type IN ('spend','manual_adjust')",
        [$studentId, $yearId]
    ) ?? 0);
    $currentBalance = $totalEarned + $adjust;

    // --- Ghi CSDL trong một transaction (chống lệch nửa chừng) ----------
    $ownTx = !db()->inTransaction();
    if ($ownTx) db()->beginTransaction();
    try {
        // Đồng bộ giao dịch earn/bonus: xoá sạch rồi ghi lại cho khớp.
        db_run(
            "DELETE FROM stamp_transactions
              WHERE student_id=? AND year_id=? AND type IN ('attendance','streak_bonus')",
            [$studentId, $yearId]
        );

        foreach ($earnTx as $t) {
            db_run(
                "INSERT INTO stamp_transactions
                    (year_id, student_id, amount, type, ref_attendance_id, description, actor_id)
                 VALUES (?,?,?, 'attendance', ?, ?, NULL)",
                [$yearId, $studentId, $t['amount'], $t['ref'],
                 'Đi lễ ngày ' . $t['date'] . ' (+' . $t['amount'] . ')']
            );
        }
        foreach ($bonusTx as $t) {
            db_run(
                "INSERT INTO stamp_transactions
                    (year_id, student_id, amount, type, ref_attendance_id, description, actor_id)
                 VALUES (?,?,?, 'streak_bonus', ?, ?, NULL)",
                [$yearId, $studentId, $t['amount'], $t['ref'],
                 'Thưởng chuỗi ' . $t['len'] . ' ngày (+' . $t['amount'] . ')']
            );
        }

        // UPSERT ví: giữ nguyên held_balance (không nêu trong danh sách cập nhật).
        db_run(
            "INSERT INTO student_stamps
                (year_id, student_id, current_balance, total_earned,
                 current_streak, longest_streak, last_attendance_date)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                current_balance      = VALUES(current_balance),
                total_earned         = VALUES(total_earned),
                current_streak       = VALUES(current_streak),
                longest_streak       = VALUES(longest_streak),
                last_attendance_date = VALUES(last_attendance_date)",
            [$yearId, $studentId, $currentBalance, $totalEarned,
             $currentStreak, $longest, $lastAtt]
        );

        if ($ownTx) db()->commit();
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) db()->rollBack();
        throw $e;
    }

    return [
        'current_balance'      => $currentBalance,
        'total_earned'         => $totalEarned,
        'current_streak'       => $currentStreak,
        'longest_streak'       => $longest,
        'last_attendance_date' => $lastAtt,
        'held_balance'         => (int) (db_val(
            "SELECT held_balance FROM student_stamps WHERE student_id=? AND year_id=?",
            [$studentId, $yearId]
        ) ?? 0),
    ];
}

/**
 * TỔNG HỢP SỔ MỘC cho hồ sơ thiếu nhi (SPEC-MOC-DIEN-TU §6.2): ví, chuỗi
 * và lịch sử giao dịch gần nhất của một em trong một năm học. CHỈ ĐỌC —
 * không tính lại (recalc_stamps là nguồn ghi duy nhất).
 *
 * Không có dòng student_stamps (em chưa từng có Mộc năm nay) → trả về
 * toàn số 0 và lịch sử rỗng, KHÔNG lỗi.
 *
 * @return array{
 *   current_balance:int, held_balance:int, total_earned:int,
 *   current_streak:int, longest_streak:int,
 *   recent_transactions: array<int,array{amount:int,type:string,description:string,created_at:string}>
 * }
 */
function stamp_summary(int $studentId, int $yearId): array
{
    $wallet = db_one(
        "SELECT current_balance, held_balance, total_earned, current_streak, longest_streak
           FROM student_stamps
          WHERE student_id = ? AND year_id = ?",
        [$studentId, $yearId]
    );

    // Mới nhất trước; created_at có thể trùng giây khi ghi hàng loạt (recalc)
    // nên xếp thêm theo id giảm dần cho ổn định.
    $rows = db_all(
        "SELECT amount, type, description, created_at
           FROM stamp_transactions
          WHERE student_id = ? AND year_id = ?
          ORDER BY created_at DESC, id DESC
          LIMIT " . STAMP_RECENT_LIMIT,
        [$studentId, $yearId]
    );

    return [
        'current_balance'  => (int) ($wallet['current_balance'] ?? 0),
        'held_balance'     => (int) ($wallet['held_balance'] ?? 0),
        'total_earned'     => (int) ($wallet['total_earned'] ?? 0),
        'current_streak'   => (int) ($wallet['current_streak'] ?? 0),
        'longest_streak'   => (int) ($wallet['longest_streak'] ?? 0),
        'recent_transactions' => array_map(fn($t) => [
            'amount'      => (int) $t['amount'],
            'type'        => $t['type'],
            'description' => $t['description'],
            'created_at'  => $t['created_at'],
        ], $rows),
    ];
}

/**
 * BẢN HÀNG LOẠT của stamp_summary() cho nhiều em cùng lúc — dùng ở
 * api/data.php để tránh N+1 (một cặp truy vấn riêng cho mỗi em) trên
 * đường tải dữ liệu chính của app. Gộp đúng HAI truy vấn cho toàn bộ
 * $studentIds (giống cách attendances/leaves/scores đã làm trong
 * data.php: một câu SELECT ... WHERE student_id IN (...) rồi ghép/cắt
 * ở PHP), thay vì gọi stamp_summary() trong vòng lặp.
 *
 * Hình dạng mỗi phần tử trả về Y HỆT stamp_summary() cho từng em, kể cả
 * trường hợp em không có dòng student_stamps (toàn số 0, danh sách rỗng).
 *
 * @param int[] $studentIds
 * @return array<int,array> studentId => (hình dạng của stamp_summary())
 */
function stamp_summaries_bulk(array $studentIds, int $yearId): array
{
    $out = [];
    foreach ($studentIds as $sid) {
        $out[(int) $sid] = [
            'current_balance'     => 0,
            'held_balance'        => 0,
            'total_earned'        => 0,
            'current_streak'      => 0,
            'longest_streak'      => 0,
            'recent_transactions' => [],
        ];
    }
    if (!$out) return $out;

    $ids = array_keys($out);
    $ph  = implode(',', array_fill(0, count($ids), '?'));

    foreach (db_all(
        "SELECT student_id, current_balance, held_balance, total_earned, current_streak, longest_streak
           FROM student_stamps
          WHERE year_id = ? AND student_id IN ($ph)",
        array_merge([$yearId], $ids)
    ) as $w) {
        $sid = (int) $w['student_id'];
        $out[$sid]['current_balance'] = (int) $w['current_balance'];
        $out[$sid]['held_balance']    = (int) $w['held_balance'];
        $out[$sid]['total_earned']    = (int) $w['total_earned'];
        $out[$sid]['current_streak']  = (int) $w['current_streak'];
        $out[$sid]['longest_streak']  = (int) $w['longest_streak'];
    }

    // MỘT truy vấn cho lịch sử giao dịch của mọi em trong $ids, mới nhất
    // trước (id giảm dần làm chốt phụ cho các dòng trùng created_at khi
    // recalc ghi hàng loạt). SQL LIMIT là limit của CẢ câu, không phải theo
    // từng em, nên cắt còn STAMP_RECENT_LIMIT dòng/em ở PHP trong lúc duyệt
    // (đã ở đúng thứ tự mới→cũ nên chỉ cần đếm và bỏ qua khi đủ).
    foreach (db_all(
        "SELECT student_id, amount, type, description, created_at
           FROM stamp_transactions
          WHERE year_id = ? AND student_id IN ($ph)
          ORDER BY created_at DESC, id DESC",
        array_merge([$yearId], $ids)
    ) as $t) {
        $sid = (int) $t['student_id'];
        if (count($out[$sid]['recent_transactions']) >= STAMP_RECENT_LIMIT) continue;
        $out[$sid]['recent_transactions'][] = [
            'amount'      => (int) $t['amount'],
            'type'        => $t['type'],
            'description' => $t['description'],
            'created_at'  => $t['created_at'],
        ];
    }

    return $out;
}
