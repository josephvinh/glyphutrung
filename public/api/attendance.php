<?php
/**
 * ĐIỂM DANH
 *
 *   POST api/attendance.php?action=toggle { programId, date, studentId }
 *
 * Một endpoint duy nhất vì thao tác ở hiện trường chỉ có một: chạm vào
 * tên. Chưa có bản ghi thì ghi vào, có rồi thì gỡ ra.
 *
 * Trạng thái có mặt hay đi trễ do MÁY CHỦ quyết định theo giờ chốt,
 * không nhận từ trình duyệt — nếu không thì đổi giờ máy điện thoại là
 * biến đi trễ thành có mặt.
 */

require __DIR__ . '/_bootstrap.php';

require_post();          // hành động ghi — chặn CSRF qua GET
require_csrf();
$me   = require_permission('attendance', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không điểm danh được.', 409);

$in        = json_input();
$programId = (int) ($in['programId'] ?? 0);
$date      = (string) ($in['date'] ?? '');

if (!$programId || !$date) json_fail('Thiếu thông tin buổi điểm danh.');

$prog = db_one('SELECT * FROM programs WHERE id = ? AND year_id = ?', [$programId, $year['id']]);
if (!$prog) json_fail('Không tìm thấy chương trình.', 404);
if ($prog['status'] !== 'kích hoạt') json_fail('Chương trình này đã đóng.');

// Buổi phải thật sự diễn ra vào ngày đó
$dow = (int) date('w', strtotime($date));
$hopLe = $prog['type'] === 'chiến dịch'
    ? ($prog['event_date'] === $date)
    : ((int) $prog['day_of_week'] === $dow);
if (!$hopLe) json_fail('Buổi này không diễn ra vào ngày ' . $date . '.');

// Giờ chốt do máy chủ tính
$cutoffMin  = (int) app_config('cutoff_minutes');
$cutoffTs   = strtotime($date . ' ' . $prog['start_time']) + $cutoffMin * 60;
$pastCutoff = time() >= $cutoffTs;
$status     = $pastCutoff ? 'đi trễ' : 'có mặt';

// =====================================================================
//  BẢNG TRA CHO MÁY QUÉT
//
//  Máy quét chỉ cần đổi MÃ SỐ trên thẻ thành một cái tên để hiện lên
//  màn hình. Nó KHÔNG cần ngày sinh, địa chỉ hay số điện thoại cha mẹ.
//
//  Vì thế bảng này tách riêng khỏi api/data.php và chỉ trả ba trường.
//  Nhờ vậy data.php thu hẹp được xuống đúng phạm vi từng người xem,
//  mà việc quét cả khối vẫn chạy.
//
//  Phạm vi: theo KHỐI, khớp đúng với phạm vi mà nhánh scan cho ghi.
// =====================================================================
if (($_GET['action'] ?? '') === 'lookup') {
    $ids = scan_class_ids($me);
    if ($ids !== null && !$ids) json_out(['ok' => true, 'items' => []]);

    $dk = ''; $tham = [$year['id']];
    if ($ids !== null) {
        $dk = ' AND e.class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $tham = array_merge($tham, $ids);
    }

    $rows = db_all(
        "SELECT s.code, s.id, s.full_name, c.name AS class_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes  c ON c.id = e.class_id
          WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'{$dk}
          ORDER BY s.code", $tham);

    // Mảng gọn thay vì mảng đối tượng: 500 em thì tiết kiệm đáng kể
    // đường truyền, mà máy khách dựng lại Map cũng nhanh hơn.
    json_out([
        'ok'    => true,
        'items' => array_map(fn($r) => [$r['code'], (int) $r['id'], $r['full_name'], $r['class_name']], $rows),
    ]);
}

// =====================================================================
//  GHI THEO LÔ TỪ MÁY QUÉT QR
//
//  Vì sao cần riêng một nhánh thay vì gọi toggle 500 lần:
//    - 500 lượt HTTP, mỗi lượt 5 truy vấn = 2500 truy vấn và 500 vòng
//      mạng. Ở sân nhà thờ sóng yếu thì không kịp trong 10 phút.
//    - toggle là BẬT/TẮT. Quét trúng em đã có sẽ GỠ em ra. Nhánh này
//      CHỈ THÊM, không bao giờ xoá.
//
//  Nhận MÃ SỐ chứ không nhận id, vì thẻ QR in mã số.
// =====================================================================
if (($_GET['action'] ?? '') === 'scan') {
    $codes = $in['codes'] ?? [];
    if (!is_array($codes) || !$codes) json_fail('Không có mã nào để ghi.');
    if (count($codes) > 200) json_fail('Mỗi lần chỉ ghi tối đa 200 mã.');

    // Lấy một lượt tất cả các em tương ứng, thay vì hỏi từng em
    $ph  = implode(',', array_fill(0, count($codes), '?'));
    $ems = db_all("SELECT s.id, s.code, s.full_name, e.status, e.class_id
                     FROM students s
                     JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?
                    WHERE s.code IN ($ph)",
                  array_merge([$year['id']], array_values($codes)));

    $theoMa = [];
    foreach ($ems as $e) $theoMa[$e['code']] = $e;

    // Phạm vi quét tính theo KHỐI (xem scan_class_ids trong _bootstrap.php)
    $chophep = scan_class_ids($me);

    $them = 0; $daCo = 0; $bo = [];

    // Lọc trước các em hợp lệ (bỏ ra ngoài vòng ghi để chèn HÀNG LOẠT một
    // lượt, thay vì mỗi em một câu INSERT — trước đây là N+1).
    $hopLe = [];
    foreach ($codes as $ma) {
        $ma = trim((string) $ma);
        $em = $theoMa[$ma] ?? null;

        if (!$em)                                   { $bo[] = [$ma, 'không có em nào mang mã này']; continue; }
        if ($em['status'] !== 'đang sinh hoạt')      { $bo[] = [$ma, $em['full_name'] . ' không còn sinh hoạt']; continue; }
        if ($chophep !== null && !in_array((int) $em['class_id'], $chophep, true)) {
            $bo[] = [$ma, $em['full_name'] . ' không thuộc khối bạn phụ trách']; continue;
        }
        $hopLe[] = (int) $em['id'];
    }

    if ($hopLe) {
        db()->beginTransaction();
        try {
            // INSERT IGNORE nhiều dòng trong MỘT câu. Khoá duy nhất
            // (program, date, student) khiến bản ghi trùng bị bỏ qua, không
            // đè bản cũ. rowCount() trả về SỐ DÒNG THẬT SỰ CHÈN -> "thêm mới";
            // phần còn lại là "đã có". Chia lô 200 (đã giới hạn từ trên).
            foreach (array_chunk($hopLe, 200) as $lo) {
                $vals   = implode(',', array_fill(0, count($lo), '(?,?,?,?,?,?,?)'));
                $params = [];
                foreach ($lo as $sid) {
                    array_push($params, $year['id'], $programId, $date, $sid, $status, 'qr', $me['id']);
                }
                $them += db_run("INSERT IGNORE INTO attendances
                                    (year_id, program_id, session_date, student_id, status, method, marked_by)
                                 VALUES $vals", $params);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Ghi điểm danh thất bại, đã hoàn tác: '), 500);
        }
        $daCo = count($hopLe) - $them;
    }

    // Một dòng nhật ký cho cả lô, không phải 500 dòng
    if ($them > 0) {
        log_action('diemdanh', 'attendance', 'Quét QR ' . $them . ' em',
                   $prog['name'] . ' · ' . $date . ' · ' . $status);
    }

    Cache::flush();
    json_out(['ok' => true, 'added' => $them, 'already' => $daCo,
              'skipped' => $bo, 'status' => $status]);
}

// ---------------------------------------------------------------------
//  CHẠM TAY VÀO TÊN: bật/tắt một em
// ---------------------------------------------------------------------
$studentId = (int) ($in['studentId'] ?? 0);
if (!$studentId) json_fail('Thiếu thông tin em cần điểm danh.');

$st = db_one('SELECT s.full_name, e.status, e.class_id
                FROM enrollments e JOIN students s ON s.id = e.student_id
               WHERE e.year_id = ? AND e.student_id = ?', [$year['id'], $studentId]);
if (!$st) json_fail('Em này không có trong danh sách năm nay.', 404);
if ($st['status'] !== 'đang sinh hoạt') json_fail('Em này không còn sinh hoạt.');

// Chạm tay = GHI. Xét theo TỪNG phân công: phải có một vai trò vừa được
// 'edit' điểm danh vừa phủ đúng lớp của em này. Tránh ghép 'edit' của vai
// trò lớp khác với phạm vi rộng của vai trò chỉ được xem.
if (!can_access_class($me, 'attendance', (int) $st['class_id'], 'edit')) {
    json_fail('Bạn không phụ trách lớp của em ' . $st['full_name'] . '.', 403);
}

$existing = db_one('SELECT * FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                   [$programId, $date, $studentId]);

if ($existing) {
    db_run('DELETE FROM attendances WHERE id = ?', [$existing['id']]);
    if ($pastCutoff) {
        log_action('diemdanh', 'attendance', 'Gỡ điểm danh của ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · đang là ' . $existing['status']);
    }
    Cache::flush();
    json_out(['ok' => true, 'removed' => true]);
}

$status = $pastCutoff ? 'đi trễ' : 'có mặt';
db_run('INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by)
        VALUES (?,?,?,?,?,?,?)',
    [$year['id'], $programId, $date, $studentId, $status, $in['method'] ?? 'tay', $me['id']]);

if ($pastCutoff) {
    log_action('diemdanh', 'attendance', 'Ghi điểm danh cho ' . $st['full_name'],
               $prog['name'] . ' · ' . $date . ' · ' . $status);
}

Cache::flush();
json_out([
    'ok'       => true,
    'removed'  => false,
    'status'   => $status,
    'markedAt' => date('H:i'),
    'markedBy' => $me['full_name'],
]);
