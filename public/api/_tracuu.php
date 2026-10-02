<?php
/**
 * LOGIC TRA CỨU ĐIỂM (tracuu.php; Sổ Mộc ở somoc.php / _somoc.php) — trang tra cứu điểm số / điểm danh / sổ liên lạc
 * công khai (public/tracuu.php). Thuần, test được, KHÔNG nạp _bootstrap.php.
 *
 * Xác thực: mã thiếu nhi (students.code) + mật mã là THÁNG-NGÀY-NĂM SINH
 * (mmddyyyy). Sai mã, sai ngày sinh, em chưa có ngày sinh: TẤT CẢ trả cùng
 * một kết quả null để kẻ dò không phân biệt được "mã có tồn tại" hay không.
 * Rate-limit dùng chung tracuu_throttle() (cùng bảng tracuu_attempts).
 *
 * Chỉ lộ: tên, lớp, điểm, điểm danh, phiếu liên lạc ĐÃ GỬI. KHÔNG lộ SĐT,
 * địa chỉ, tên cha mẹ, phiếu nháp.
 */

require_once __DIR__ . '/_somoc.php'; // db.php + tracuu_throttle()/tracuu_attempt_record()

/**
 * Chuẩn hoá ngày sinh người dùng gõ về 'mmddyyyy' (THÁNG trước, NGÀY sau).
 * Ưu tiên dd/mm/yyyy nếu không mơ hồ (ngày > 12), thử cả hai nếu mơ hồ (cả hai < 13).
 * Nhận 03152014, 03/15/2014, 3-15-2014, 03.15.2014, 15/03/2014, 15-03-2014 (không đệm 0).
 * @return string|null null nếu không đọc ra ngày hợp lệ
 */
function tracuu_norm_dob(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '') return null;

    if (preg_match('/^(\d{1,2})\D+(\d{1,2})\D+(\d{4})$/', $raw, $m)) {
        [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } elseif (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $raw, $m)) {
        [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    } else {
        return null;
    }

    // Ưu tiên dd/mm/yyyy nếu không mơ hồ (a > 12)
    if ($a > 12 && $a <= 31) {
        if (checkdate($b, $a, $y)) {
            return sprintf('%02d%02d%04d', $b, $a, $y); // mmddyyyy
        }
        // Thử ngược lại (mm/dd)
        if (checkdate($a, $b, $y)) {
            return sprintf('%02d%02d%04d', $a, $b, $y);
        }
        return null;
    }

    // Mơ hồ (cả hai <= 12): thử mm/dd trước
    if (checkdate($a, $b, $y)) {
        return sprintf('%02d%02d%04d', $a, $b, $y); // mmddyyyy
    }
    // Thử dd/mm
    if (checkdate($b, $a, $y)) {
        return sprintf('%02d%02d%04d', $b, $a, $y); // mmddyyyy
    }
    return null;
}

/** 'Y-m-d' (students.birth_date) -> 'mmddyyyy' */
function tracuu_dob_from_db(?string $ymd): ?string
{
    if (!$ymd || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $ymd, $m)) return null;
    return $m[2] . $m[3] . $m[1];
}

/**
 * Xác thực mã + ngày sinh.
 * @return array{id:int,code:string,full_name:string,holy_name:?string}|null
 */
function tracuu_auth(string $code, string $dobInput): ?array
{
    $code = mb_substr(trim($code), 0, 32, 'UTF-8');
    $dob  = tracuu_norm_dob($dobInput);
    $st   = $code === '' ? null
        : db_one('SELECT id, code, full_name, holy_name, birth_date FROM students WHERE code = ?', [$code]);

    // Luôn so sánh (kể cả khi không có em) để thời gian phản hồi không lộ mã tồn tại.
    $real = $st ? tracuu_dob_from_db($st['birth_date'] ?? null) : null;
    $ok   = $st && $dob !== null && $real !== null && hash_equals($real, $dob);
    if (!$ok) return null;

    return [
        'id'        => (int) $st['id'],
        'code'      => $st['code'],
        'full_name' => $st['full_name'],
        'holy_name' => $st['holy_name'],
    ];
}

/**
 * Điểm trung bình có trọng số (hệ số) của các cột ĐÃ có điểm; null nếu chưa có cột nào.
 * @param array<int,array{weight:int,value:?float}> $cols
 */
function tracuu_weighted_avg(array $cols): ?float
{
    $sum = 0.0; $w = 0;
    foreach ($cols as $c) {
        if ($c['value'] === null) continue;
        $sum += $c['value'] * $c['weight'];
        $w   += $c['weight'];
    }
    return $w > 0 ? round($sum / $w, 2) : null;
}

/**
 * TAB ĐIỂM SỐ: mỗi học kỳ của niên khoá là một bảng, cột = loại điểm.
 * @return array{types:array,terms:array}
 */
function tracuu_scores(int $studentId, int $yearId): array
{
    $types = db_all('SELECT code, label, short_label, weight FROM score_types ORDER BY sort_order, code');
    $terms = db_all('SELECT id, name, start_date, end_date FROM terms WHERE year_id = ? ORDER BY sort_order, start_date', [$yearId]);

    $rows = db_all(
        'SELECT sc.term_id, sc.type_code, sc.value
           FROM scores sc JOIN terms t ON t.id = sc.term_id
          WHERE sc.student_id = ? AND t.year_id = ?',
        [$studentId, $yearId]
    );
    $byTerm = [];
    foreach ($rows as $r) $byTerm[(int) $r['term_id']][$r['type_code']] = (float) $r['value'];

    $outTypes = array_map(fn($t) => [
        'code' => $t['code'], 'label' => $t['label'], 'short' => $t['short_label'], 'weight' => (int) $t['weight'],
    ], $types);

    $outTerms = [];
    foreach ($terms as $t) {
        $vals = $byTerm[(int) $t['id']] ?? [];
        $cols = [];
        foreach ($outTypes as $ty) {
            $cols[] = ['weight' => $ty['weight'], 'value' => $vals[$ty['code']] ?? null];
        }
        $outTerms[] = [
            'id'    => (int) $t['id'],
            'name'  => $t['name'],
            'from'  => $t['start_date'],
            'to'    => $t['end_date'],
            'cols'  => $cols,                       // song song với types
            'avg'   => tracuu_weighted_avg($cols),
        ];
    }
    return ['types' => $outTypes, 'terms' => $outTerms];
}

/**
 * Xếp một buổi vào ô sổ điểm danh.
 * @return string 'P' có mặt | 'L' đi trễ | 'E' vắng có phép | 'A' vắng không phép
 */
function tracuu_att_mark(?string $status, bool $hasLeave): string
{
    if ($status === 'có mặt') return 'P';
    if ($status === 'đi trễ') return 'L';
    return $hasLeave ? 'E' : 'A';
}

/**
 * TAB ĐIỂM DANH — sổ chi tiết theo từng buổi trong niên khoá, tới hôm nay.
 * Quy tắc như phiếu liên lạc (reports.js): chỉ tính buổi của chương trình
 * count_for_attendance mà LỚP của em có ít nhất một em được ghi nhận
 * (điểm danh hoặc xin phép đã duyệt) — buổi không ai điểm danh thì bỏ ra.
 * Em không ghi danh lớp nào trong năm: chỉ liệt kê buổi chính em có mặt/xin phép.
 *
 * @return array{sessions:array,summary:array}
 */
function tracuu_attendance(int $studentId, int $yearId, ?string $today = null): array
{
    $today = $today ?: date('Y-m-d');

    $enr = db_one('SELECT class_id FROM enrollments WHERE student_id = ? AND year_id = ?', [$studentId, $yearId]);
    $classId = $enr ? (int) $enr['class_id'] : 0;

    // Bản ghi của chính em
    $mine = [];
    foreach (db_all('SELECT program_id, session_date, status FROM attendances WHERE student_id = ? AND year_id = ?', [$studentId, $yearId]) as $r) {
        $mine[$r['program_id'] . '|' . $r['session_date']]['att'] = $r['status'];
    }
    foreach (db_all("SELECT program_id, session_date FROM leave_requests WHERE student_id = ? AND year_id = ? AND status = 'đã duyệt'", [$studentId, $yearId]) as $r) {
        $mine[$r['program_id'] . '|' . $r['session_date']]['leave'] = true;
    }

    // Buổi đã diễn ra của lớp (có ít nhất một em được ghi nhận)
    $sessions = [];
    if ($classId > 0) {
        $sessions = db_all(
            "SELECT s.program_id, s.session_date, p.name, p.start_time
               FROM (
                     SELECT a.program_id, a.session_date
                       FROM attendances a JOIN enrollments e ON e.student_id = a.student_id AND e.year_id = a.year_id
                      WHERE a.year_id = ? AND e.class_id = ?
                     UNION
                     SELECT l.program_id, l.session_date
                       FROM leave_requests l JOIN enrollments e ON e.student_id = l.student_id AND e.year_id = l.year_id
                      WHERE l.year_id = ? AND e.class_id = ? AND l.status = 'đã duyệt'
                    ) s
               JOIN programs p ON p.id = s.program_id
              WHERE p.count_for_attendance = 1 AND s.session_date <= ?
              ORDER BY s.session_date, p.start_time, p.id",
            [$yearId, $classId, $yearId, $classId, $today]
        );
    } elseif ($mine) {
        $ids = array_unique(array_map(fn($k) => (int) explode('|', $k)[0], array_keys($mine)));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $progs = [];
        foreach (db_all("SELECT id, name, start_time FROM programs WHERE count_for_attendance = 1 AND id IN ($ph)", $ids) as $p) $progs[(int) $p['id']] = $p;
        foreach (array_keys($mine) as $k) {
            [$pid, $d] = explode('|', $k, 2);
            if (isset($progs[(int) $pid]) && $d <= $today) {
                $sessions[] = ['program_id' => $pid, 'session_date' => $d, 'name' => $progs[(int) $pid]['name'], 'start_time' => $progs[(int) $pid]['start_time']];
            }
        }
        usort($sessions, fn($a, $b) => strcmp($a['session_date'], $b['session_date']));
    }

    $sum = ['present' => 0, 'late' => 0, 'excused' => 0, 'unexcused' => 0, 'total' => 0, 'rate' => 0];
    $out = [];
    foreach ($sessions as $s) {
        $m = $mine[$s['program_id'] . '|' . $s['session_date']] ?? [];
        $mark = tracuu_att_mark($m['att'] ?? null, !empty($m['leave']));
        $sum['total']++;
        $sum[['P' => 'present', 'L' => 'late', 'E' => 'excused', 'A' => 'unexcused'][$mark]]++;
        $out[] = [
            'date'    => $s['session_date'],
            'program' => $s['name'],
            'mark'    => $mark,
        ];
    }
    // Tỉ lệ chuyên cần: có mặt + trễ trên tổng buổi (cùng công thức build_attendance_rows)
    $sum['rate'] = $sum['total'] > 0 ? (int) round(($sum['present'] + $sum['late']) / $sum['total'] * 100) : 0;

    return ['sessions' => $out, 'summary' => $sum];
}

/**
 * TAB SỔ LIÊN LẠC — chỉ phiếu đã 'đã gửi'. Phiếu nháp / chưa lập = không có.
 * @return array<int,array>  mỗi học kỳ đã có phiếu gửi
 */
function tracuu_reports(int $studentId, int $yearId): array
{
    $rows = db_all(
        "SELECT r.*, t.name AS term_name, t.start_date, t.end_date
           FROM reports r JOIN terms t ON t.id = r.term_id
          WHERE r.student_id = ? AND t.year_id = ? AND r.status = 'đã gửi'
          ORDER BY t.sort_order, t.start_date",
        [$studentId, $yearId]
    );
    return array_map(fn($r) => [
        'term'      => $r['term_name'],
        'from'      => $r['start_date'],
        'to'        => $r['end_date'],
        'present'   => (int) $r['att_present'],
        'late'      => (int) $r['att_late'],
        'excused'   => (int) $r['att_excused'],
        'unexcused' => (int) $r['att_unexcused'],
        'total'     => (int) $r['att_total'],
        'rate'      => (int) $r['att_rate'],
        'score'     => $r['score'] !== null ? (float) $r['score'] : null,
        'conduct'   => $r['conduct'],
        'rank'      => $r['rank_label'],
        'remark'    => $r['remark'],
    ], $rows);
}

/** Lớp của em trong niên khoá (null nếu chưa ghi danh). */
function tracuu_class_name(int $studentId, int $yearId): ?string
{
    $r = db_one(
        'SELECT c.name FROM enrollments e JOIN classes c ON c.id = e.class_id WHERE e.student_id = ? AND e.year_id = ? LIMIT 1',
        [$studentId, $yearId]
    );
    return $r['name'] ?? null;
}

/** Đã vượt ngưỡng tra cứu theo IP chưa? (không exit — trang HTML tự báo lỗi thân thiện) */
function tracuu_throttled(): bool
{
    $moc = date('Y-m-d H:i:s', time() - TRACUU_CUA_SO_PHUT * 60);
    $n = (int) (db_one('SELECT COUNT(*) n FROM tracuu_attempts WHERE ip = ? AND tried_at > ?', [client_ip(), $moc])['n'] ?? 0);
    return $n >= TRACUU_TOI_DA_IP;
}


/* =====================================================================
   KHOÁ TẠM THEO MÃ EM — bổ sung cho rate-limit theo IP.
   Quá TRACUU_MA_TOI_DA_SAI lần nhập sai mật mã cho CÙNG một mã trong
   TRACUU_MA_KHOA_PHUT phút thì khoá mã đó tạm thời, bất kể IP nào.
   Áp cho MỌI chuỗi mã được gửi (kể cả không tồn tại) nên trạng thái khoá
   không tiết lộ mã nào có thật. Đánh đổi có chủ đích: người ngoài cố ý
   nhập sai có thể khoá tạm mã của một em (tối đa TRACUU_MA_KHOA_PHUT phút,
   tự mở) — chấp nhận để đổi lấy việc không dò được ngày sinh.
   Bảng chưa được tạo (chưa chạy migration 005): bỏ qua + ghi log, KHÔNG
   làm sập trang.
   ===================================================================== */
if (!defined('TRACUU_MA_TOI_DA_SAI')) define('TRACUU_MA_TOI_DA_SAI', 5);
if (!defined('TRACUU_MA_KHOA_PHUT'))  define('TRACUU_MA_KHOA_PHUT', 15);

/** Chuẩn hoá mã để đếm: cắt khoảng trắng, IN HOA, tối đa 32 ký tự. */
function tracuu_code_key(string $code): string
{
    return mb_strtoupper(mb_substr(trim($code), 0, 32, 'UTF-8'), 'UTF-8');
}

function tracuu_code_fails_ready(): bool
{
    if (db_has_table('tracuu_code_fails')) return true;
    error_log('[tracuu] thiếu bảng tracuu_code_fails — chạy migration 005, khoá theo mã đang TẮT');
    return false;
}

/** Mã này đang bị khoá tạm (sai quá ngưỡng trong cửa sổ)? */
function tracuu_code_locked(string $code): bool
{
    $key = tracuu_code_key($code);
    if ($key === '' || !tracuu_code_fails_ready()) return false;
    $moc = date('Y-m-d H:i:s', time() - TRACUU_MA_KHOA_PHUT * 60);
    $n = (int) (db_one('SELECT COUNT(*) n FROM tracuu_code_fails WHERE code = ? AND tried_at > ?', [$key, $moc])['n'] ?? 0);
    return $n >= TRACUU_MA_TOI_DA_SAI;
}

/** Ghi một lần nhập sai cho mã (và dọn bản ghi đã quá cửa sổ). */
function tracuu_code_fail(string $code): void
{
    $key = tracuu_code_key($code);
    if ($key === '' || !tracuu_code_fails_ready()) return;
    db_run('INSERT INTO tracuu_code_fails (code, tried_at) VALUES (?, NOW())', [$key]);
    db_run('DELETE FROM tracuu_code_fails WHERE tried_at < ?', [date('Y-m-d H:i:s', time() - TRACUU_MA_KHOA_PHUT * 60)]);
}

/** Nhập đúng: xoá bộ đếm sai của mã. */
function tracuu_code_clear(string $code): void
{
    $key = tracuu_code_key($code);
    if ($key === '' || !tracuu_code_fails_ready()) return;
    db_run('DELETE FROM tracuu_code_fails WHERE code = ?', [$key]);
}
