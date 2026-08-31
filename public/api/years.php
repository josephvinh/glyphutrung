<?php
/**
 * NIÊN KHOÁ
 *
 *   GET  api/years.php?action=list
 *   POST api/years.php?action=create   { name, startDate, endDate }
 *   POST api/years.php?action=activate { id }
 *   POST api/years.php?action=lock     { id }
 *   POST api/years.php?action=unlock   { id }
 *
 * Chỉ Quản Trị Hệ Thống được đụng vào — đây là ranh giới của toàn bộ
 * dữ liệu nghiệp vụ, đổi nhầm là lệch hết mọi báo cáo.
 */

require __DIR__ . '/_bootstrap.php';

// Niên khoá đã tách thành module riêng, nên hỏi hệ thống phân quyền
// thay vì khoá cứng vào vai trò admin. Ai cũng XEM được mình đang ở
// niên khoá nào; chỉ người có quyền sửa mới mở/sửa/khoá được.
$me     = require_permission('years', 'view');
$action = $_GET['action'] ?? 'list';

if ($action !== 'list') require_permission('years', 'edit');
$in     = json_input();

/** Đếm dữ liệu đang gắn vào một niên khoá, để cảnh báo trước khi khoá */
function year_usage(int $yearId): array
{
    return [
        'enrollments' => (int) db_one('SELECT COUNT(*) n FROM enrollments WHERE year_id=?', [$yearId])['n'],
        'programs'    => (int) db_one('SELECT COUNT(*) n FROM programs    WHERE year_id=?', [$yearId])['n'],
        'attendances' => (int) db_one('SELECT COUNT(*) n FROM attendances WHERE year_id=?', [$yearId])['n'],
        'leaves'      => (int) db_one('SELECT COUNT(*) n FROM leave_requests WHERE year_id=?', [$yearId])['n'],
    ];
}

switch ($action) {

    // -------------------------------------------------------------
    case 'list':
        $rows = db_all('SELECT * FROM school_years ORDER BY start_date DESC');
        $out  = [];
        foreach ($rows as $y) {
            $terms = db_all('SELECT id, name, start_date, end_date FROM terms
                              WHERE year_id = ? ORDER BY sort_order', [$y['id']]);
            $out[] = [
                'id'        => (int) $y['id'],
                'name'      => $y['name'],
                'startDate' => $y['start_date'],
                'endDate'   => $y['end_date'],
                'isCurrent' => (bool) $y['is_current'],
                'status'    => $y['status'],
                'terms'     => array_map(fn($t) => [
                    'id' => (int) $t['id'], 'name' => $t['name'],
                    'from' => $t['start_date'], 'to' => $t['end_date'],
                ], $terms),
                'usage'     => year_usage((int) $y['id']),
            ];
        }
        json_out(['ok' => true, 'years' => $out]);

    // -------------------------------------------------------------
    case 'create':
        require_post();
        $name  = trim((string) ($in['name'] ?? ''));
        $start = (string) ($in['startDate'] ?? '');
        $end   = (string) ($in['endDate'] ?? '');

        if ($name === '' || $start === '' || $end === '') {
            json_fail('Vui lòng nhập đủ tên niên khoá, ngày bắt đầu và ngày kết thúc.');
        }
        if ($end <= $start) json_fail('Ngày kết thúc phải sau ngày bắt đầu.');
        if (db_one('SELECT id FROM school_years WHERE name = ?', [$name])) {
            json_fail('Niên khoá "' . $name . '" đã tồn tại.');
        }

        db()->beginTransaction();
        try {
            $yearId = db_insert('INSERT INTO school_years (name, start_date, end_date, is_current, status)
                                 VALUES (?,?,?,0,?)', [$name, $start, $end, 'đang mở']);

            // Chia đôi thành hai học kỳ theo mốc giữa, Ban Điều Hành sửa lại sau nếu cần
            $mid = date('Y-m-d', (int) ((strtotime($start) + strtotime($end)) / 2));
            $midNext = date('Y-m-d', strtotime($mid . ' +1 day'));
            db_run('INSERT INTO terms (year_id, name, start_date, end_date, sort_order)
                    VALUES (?,?,?,?,1), (?,?,?,?,2)',
                   [$yearId, 'Học kỳ I',  $start,   $mid,
                    $yearId, 'Học kỳ II', $midNext, $end]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không tạo được niên khoá: '), 500);
        }

        log_action('tao', 'settings', 'Tạo niên khoá ' . $name, $start . ' → ' . $end);
        json_out(['ok' => true, 'id' => $yearId]);

    // -------------------------------------------------------------
    // SỬA NIÊN KHOÁ
    //
    // Cho sửa cả niên khoá ĐANG DÙNG — vì đầu năm rất hay phải dời
    // ngày khai giảng. Chỉ chặn khi đã khoá sổ.
    //
    // Hai thứ phải giữ nhất quán:
    //   1. Học kỳ luôn nằm trong khoảng niên khoá  -> tự co lại cho vừa
    //   2. Buổi sinh hoạt và lượt điểm danh ĐÃ GHI không được rơi ra
    //      ngoài khoảng mới -> từ chối, nêu rõ ngày nào vướng
    // -------------------------------------------------------------
    case 'update':
        require_post();
        $id    = (int) ($in['id'] ?? 0);
        $name  = trim((string) ($in['name'] ?? ''));
        $start = (string) ($in['startDate'] ?? '');
        $end   = (string) ($in['endDate'] ?? '');

        $y = db_one('SELECT * FROM school_years WHERE id = ?', [$id]);
        if (!$y) json_fail('Không tìm thấy niên khoá.', 404);
        if ($y['status'] === 'đã khóa') {
            json_fail('Niên khoá "' . $y['name'] . '" đã khoá sổ. Hãy mở lại trước khi sửa.', 409);
        }
        if ($name === '' || $start === '' || $end === '') {
            json_fail('Vui lòng nhập đủ tên niên khoá, ngày bắt đầu và ngày kết thúc.');
        }
        if ($end <= $start) json_fail('Ngày kết thúc phải sau ngày bắt đầu.');
        if (db_one('SELECT id FROM school_years WHERE name = ? AND id <> ?', [$name, $id])) {
            json_fail('Đã có niên khoá tên "' . $name . '".');
        }

        // --- Thay đổi này có làm VĂNG dữ liệu ra ngoài không ---
        //
        // Chỉ tính bản ghi đang NẰM TRONG khoảng cũ mà sẽ rơi ra ngoài
        // khoảng mới. Không tính bản ghi vốn đã ngoài khoảng từ trước —
        // đó là chuyện có sẵn, không phải do lần sửa này gây ra, chặn
        // vì nó là phạt oan người dùng.
        $cs = $y['start_date'];
        $ce = $y['end_date'];
        $vuong = [];

        $ngoaiCT = db_one('SELECT MIN(session_date) lo, MAX(session_date) hi, COUNT(*) n
                             FROM attendances
                            WHERE year_id = ?
                              AND session_date BETWEEN ? AND ?
                              AND (session_date < ? OR session_date > ?)',
                          [$id, $cs, $ce, $start, $end]);
        if ((int) $ngoaiCT['n'] > 0) {
            $vuong[] = $ngoaiCT['n'] . ' lượt điểm danh (từ ' . $ngoaiCT['lo']
                     . ' đến ' . $ngoaiCT['hi'] . ')';
        }
        $ngoaiDon = db_one('SELECT COUNT(*) n FROM leave_requests
                             WHERE year_id = ?
                               AND session_date BETWEEN ? AND ?
                               AND (session_date < ? OR session_date > ?)',
                           [$id, $cs, $ce, $start, $end]);
        if ((int) $ngoaiDon['n'] > 0) $vuong[] = $ngoaiDon['n'] . ' đơn xin phép';

        if ($vuong) {
            json_fail('Thu hẹp như vậy sẽ đẩy ' . implode(' và ', $vuong)
                    . ' ra ngoài niên khoá. Hãy chọn khoảng rộng hơn.', 409);
        }

        db()->beginTransaction();
        try {
            db_run('UPDATE school_years SET name=?, start_date=?, end_date=? WHERE id=?',
                   [$name, $start, $end, $id]);

            // Học kỳ phải PHỦ KÍN niên khoá, không để hở hai đầu:
            // học kỳ đầu luôn bắt đầu cùng ngày với niên khoá, học kỳ
            // cuối luôn kết thúc cùng ngày. Buổi rơi vào khoảng hở sẽ
            // không thuộc học kỳ nào và mất tích khỏi sổ liên lạc.
            $daChinh = [];
            $ds  = db_all('SELECT * FROM terms WHERE year_id=? ORDER BY sort_order', [$id]);
            $cuoi = count($ds) - 1;
            foreach ($ds as $k => $t) {
                $ts = $k === 0     ? $start : max($t['start_date'], $start);
                $te = $k === $cuoi ? $end   : min($t['end_date'],   $end);
                if ($ts >= $te) {
                    db()->rollBack();
                    json_fail('Học kỳ "' . $t['name'] . '" (' . $t['start_date'] . ' → ' . $t['end_date']
                            . ') nằm hẳn ngoài khoảng mới. Hãy sửa ngày của học kỳ trước.', 409);
                }
                if ($ts !== $t['start_date'] || $te !== $t['end_date']) {
                    db_run('UPDATE terms SET start_date=?, end_date=? WHERE id=?', [$ts, $te, $t['id']]);
                    $daChinh[] = $t['name'] . ': ' . $ts . ' → ' . $te;
                }
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không sửa được niên khoá: '), 500);
        }

        log_action('sua', 'settings', 'Sửa niên khoá ' . $name,
                   $y['start_date'] . ' → ' . $y['end_date'] . '  thành  ' . $start . ' → ' . $end);

        json_out(['ok' => true, 'termsAdjusted' => $daChinh]);

    // -------------------------------------------------------------
    case 'activate':
        require_post();
        $id = (int) ($in['id'] ?? 0);
        $y  = db_one('SELECT * FROM school_years WHERE id = ?', [$id]);
        if (!$y) json_fail('Không tìm thấy niên khoá.', 404);
        if ($y['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, phải mở lại trước khi dùng.');

        // Chỉ một niên khoá được bật tại một thời điểm
        db()->beginTransaction();
        try {
            db_run('UPDATE school_years SET is_current = 0');
            db_run('UPDATE school_years SET is_current = 1 WHERE id = ?', [$id]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail('Không đổi được niên khoá đang dùng.', 500);
        }

        log_action('sua', 'settings', 'Chuyển sang niên khoá ' . $y['name'], '');
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'lock':
        require_post();
        $id = (int) ($in['id'] ?? 0);
        $y  = db_one('SELECT * FROM school_years WHERE id = ?', [$id]);
        if (!$y) json_fail('Không tìm thấy niên khoá.', 404);
        if ($y['is_current']) {
            json_fail('Không khoá được niên khoá đang dùng. Hãy chuyển sang niên khoá khác trước.');
        }

        db_run("UPDATE school_years SET status = 'đã khóa' WHERE id = ?", [$id]);
        log_action('sua', 'settings', 'Khoá sổ niên khoá ' . $y['name'], 'dữ liệu năm này chuyển sang chỉ đọc');
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    case 'unlock':
        require_post();
        $id = (int) ($in['id'] ?? 0);
        $y  = db_one('SELECT * FROM school_years WHERE id = ?', [$id]);
        if (!$y) json_fail('Không tìm thấy niên khoá.', 404);

        db_run("UPDATE school_years SET status = 'đang mở' WHERE id = ?", [$id]);
        log_action('sua', 'settings', 'Mở lại niên khoá ' . $y['name'], '');
        json_out(['ok' => true]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
