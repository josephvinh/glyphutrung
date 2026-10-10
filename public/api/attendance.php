<?php
/**
 * ĐIỂM DANH
 *
 *   POST api/attendance.php?action=toggle { programId, date, studentId }
 *   POST api/attendance.php?action=mark   { programId, date, studentId }
 *        (ghi điểm danh, idempotent — dùng cho hàng đợi offline)
 *   POST api/attendance.php?action=set_status { programId, date, studentId, status }
 *        (đổi có mặt <-> đi trễ của bản ghi đã có; chỉ người có "cửa sửa")
 *
 * Một endpoint duy nhất vì thao tác ở hiện trường chỉ có một: chạm vào
 * tên. Chưa có bản ghi thì ghi vào, có rồi thì gỡ ra.
 *
 * Trạng thái có mặt hay đi trễ do MÁY CHỦ quyết định theo giờ chốt,
 * không nhận từ trình duyệt — nếu không thì đổi giờ máy điện thoại là
 * biến đi trễ thành có mặt.
 */

require __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/StampService.php';

require_write();
$me   = require_permission('attendance', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không điểm danh được.', 409);

$in        = json_input();
$programId = (int) ($in['programId'] ?? 0);
$rawDate   = $in['date'] ?? '';

if (!$programId || $rawDate === '' || $rawDate === null) json_fail('Thiếu thông tin buổi điểm danh.');

// Ngày phải ĐÚNG dạng YYYY-MM-DD, là ngày có thật, không thừa ký tự nào
// (\z: cả xuống dòng cuối cũng bị loại). Không cho rác lọt xuống SQL:
// INSERT IGNORE sẽ hạ lỗi strict thành cảnh báo và MariaDB cắt chuỗi
// '2026-10-01abc' còn '2026-10-01' rồi lưu.
$date = is_string($rawDate) ? $rawDate : '';
if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $date, $md)
    || !checkdate((int) $md[2], (int) $md[3], (int) $md[1])) {
    json_fail('Ngày không hợp lệ (cần dạng năm-tháng-ngày, ví dụ 2026-10-04).', 400);
}

$prog = db_one('SELECT * FROM programs WHERE id = ? AND year_id = ?', [$programId, $year['id']]);
if (!$prog) json_fail('Không tìm thấy chương trình.', 404);
if ($prog['status'] !== 'kích hoạt') json_fail('Chương trình này đã đóng.');

// Buổi phải thật sự diễn ra vào ngày đó
$dow = (int) date('w', strtotime($date));
if ($prog['type'] === 'chiến dịch') {
    $hopLe = ($prog['event_date'] === $date);
} else {
    // LẶP NHIỀU THỨ: days_of_week (CSV) nếu có, ngược lại day_of_week (một thứ)
    $days = !empty($prog['days_of_week'] ?? '')
        ? array_map('intval', explode(',', $prog['days_of_week']))
        : ($prog['day_of_week'] === null ? [] : [(int) $prog['day_of_week']]);
    $hopLe = in_array($dow, $days, true);
    // Khoảng ngày áp dụng (nếu đặt)
    $effFrom = $prog['effective_from'] ?? '';
    $effTo   = $prog['effective_to'] ?? '';
    if ($hopLe && !empty($effFrom) && $date < $effFrom) $hopLe = false;
    if ($hopLe && !empty($effTo)   && $date > $effTo)   $hopLe = false;
}
if (!$hopLe) json_fail('Buổi này không diễn ra vào ngày ' . $date . '.');

// Không điểm danh trước cho buổi CHƯA diễn ra (giờ Việt Nam). Ngày quá khứ
// vẫn được (điểm danh bù). $date đã chuẩn ISO nên so thẳng chuỗi.
$laTuongLai = $date > date('Y-m-d');

// Giờ chốt do máy chủ tính — tôn trọng GIỜ CHỐT riêng của buổi
// (cutoff_time), đúng như giao diện. Để trống thì lấy giờ bắt đầu làm mốc.
$cutoffTs   = program_cutoff_ts($prog, $date);
$pastCutoff = time() >= $cutoffTs;
$status     = $pastCutoff ? 'đi trễ' : 'có mặt';

// Ngưỡng "VẮNG" (mốc 2): sau giờ này KHÔNG cho ghi có mặt nữa (tính vắng).
$pastAbsent = !empty($prog['absent_time'] ?? '')
    && time() >= strtotime($date . ' ' . $prog['absent_time']);

// Lớp tham gia chương trình (rỗng/NULL = áp dụng toàn đoàn)
$progClassIds = null;
if (db_has_table('program_classes')) {
    $pcRows = db_all('SELECT class_id FROM program_classes WHERE program_id=?', [$programId]);
    if ($pcRows) $progClassIds = array_map(fn($r) => (int) $r['class_id'], $pcRows);
}

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
    // Chỉ các lớp tham gia chương trình (nếu có gắn lớp)
    if ($progClassIds !== null) {
        $dk .= ' AND e.class_id IN (' . implode(',', array_fill(0, count($progClassIds), '?')) . ')';
        $tham = array_merge($tham, $progClassIds);
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
    if (isset($prog['allow_qr']) && !$prog['allow_qr']) json_fail('Buổi này không cho phép quét QR.');
    if ($laTuongLai) json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);
    // Sau giờ vắng: chỉ người có "cửa sửa" được bù (giống chạm tay)
    if ($pastAbsent && !can_override_session_lock($me, 0)) {
        json_fail('Đã quá giờ "tính vắng" của buổi — chỉ admin, BĐH, trưởng khối hoặc GLV chủ nhiệm mới bù được.');
    }

    // Chuẩn hóa mã: trim + không phân biệt hoa/thường
    $codesRaw = $in['codes'] ?? [];
    if (!is_array($codesRaw) || !$codesRaw) json_fail('Không có mã nào để ghi.');
    if (count($codesRaw) > 200) json_fail('Mỗi lần chỉ ghi tối đa 200 mã.');

    // Lọc và chuẩn hóa mã
    $codes = [];
    foreach ($codesRaw as $ma) {
        if (!is_string($ma) && !is_numeric($ma)) continue; // bỏ array/object
        $ma = trim((string) $ma);
        if ($ma !== '') $codes[] = $ma;
    }
    if (!$codes) json_fail('Không có mã nào hợp lệ.');

    // Lấy một lượt tất cả các em tương ứng, so sánh không phân biệt hoa/thường
    $ph  = implode(',', array_fill(0, count($codes), '?'));
    $ems = db_all("SELECT s.id, s.code, s.full_name, e.status, e.class_id
                     FROM students s
                     JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?
                    WHERE LOWER(s.code) IN (" . implode(',', array_fill(0, count($codes), '?')) . ")",
                  array_merge([$year['id']], array_map('strtolower', $codes)));

    $theoMa = [];
    foreach ($ems as $e) $theoMa[strtolower($e['code'])] = $e;

    // Phạm vi quét tính theo KHỐI (xem scan_class_ids trong _bootstrap.php)
    $them = 0; $daCo = 0; $bo = [];

    // Lọc trước các em hợp lệ (bỏ ra ngoài vòng ghi để chèn HÀNG LOẠT một
    // lượt, thay vì mỗi em một câu INSERT — trước đây là N+1).
    //
    // QUÉT THEO LỚP (nhất quán với chạm tay): chỉ quét được em thuộc lớp
    // mà mình có quyền 'edit' điểm danh. Admin/BĐH thì quét được mọi em.
    $hopLe = [];
    foreach ($codes as $ma) {
        $maKey = strtolower(trim((string) $ma));
        $em = $theoMa[$maKey] ?? null;

        if (!$em)                                   { $bo[] = [$ma, 'không có em nào mang mã này']; continue; }
        if ($em['status'] !== 'đang sinh hoạt')      { $bo[] = [$ma, $em['full_name'] . ' không còn sinh hoạt']; continue; }
        // Kiểm tra quyền edit điểm danh trên LỚP của em (nhất quán với chạm tay)
        if (!can_access_class($me, 'attendance', (int) $em['class_id'], 'edit')) {
            $bo[] = [$ma, $em['full_name'] . ' không thuộc lớp bạn phụ trách']; continue;
        }
        if ($progClassIds !== null && !in_array((int) $em['class_id'], $progClassIds, true)) {
            $bo[] = [$ma, $em['full_name'] . ' không thuộc lớp của buổi này']; continue;
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

        // Sổ Mộc: buổi có tính Mộc thì mọi em VỪA ĐƯỢC GHI (kể cả trùng, vì
        // recalc là idempotent) đều cần tính lại ví/chuỗi. Gọi SAU KHI COMMIT
        // cho sạch — recalc_stamps tự mở transaction riêng của nó.
        if (program_earns_stamps($prog)) {
            foreach ($hopLe as $sid) {
                recalc_stamps_safe($sid, $year['id']);
            }
        }
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

// ---------------------------------------------------------------------
//  SỬA TRẠNG THÁI: đổi "có mặt" <-> "đi trễ" của một bản ghi ĐÃ CÓ
//
//  Chạm tên chỉ ghi/gỡ, còn trạng thái do giờ chốt quyết định tại lúc ghi,
//  nên ghi bù buổi cũ luôn ra "đi trễ" và không có đường nào đổi lại. Thao
//  tác này dành cho người phụ trách (admin/BĐH/trưởng khối/GLV chủ nhiệm,
//  đúng nhóm có "cửa sửa" — can_override_session_lock) và chỉ trong phạm
//  vi lớp/khối của mình (can_access_class ở trên). GLV thường không dùng được.
//  Không tạo bản ghi mới: em chưa được ghi thì không có gì để sửa ("vắng"
//  là không có bản ghi, không phải một trạng thái).
// ---------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'set_status') {
    if (!can_override_session_lock($me, (int) $st['class_id'])) {
        json_fail('Chỉ admin, BĐH, trưởng khối hoặc GLV chủ nhiệm (trong phạm vi mình phụ trách) mới sửa được trạng thái điểm danh.', 403);
    }
    $moi = $in['status'] ?? '';
    if (!is_string($moi) || !in_array($moi, ['có mặt', 'đi trễ'], true)) {
        json_fail('Trạng thái không hợp lệ (chỉ "có mặt" hoặc "đi trễ").', 400);
    }
    $cu = db_one('SELECT id, status FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                 [$programId, $date, $studentId]);
    if (!$cu) {
        json_fail('Em ' . $st['full_name'] . ' chưa được ghi điểm danh buổi này, không có gì để sửa.', 404);
    }

    $doi = ($cu['status'] !== $moi);
    if ($doi) {
        db_run('UPDATE attendances SET status = ? WHERE id = ?', [$moi, $cu['id']]);
        log_action('diemdanh', 'attendance', 'Sửa trạng thái điểm danh của ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · ' . $cu['status'] . ' → ' . $moi);
        // Mốc thưởng chuỗi của Sổ Mộc phân biệt có mặt / đi trễ -> tính lại.
        if (program_earns_stamps($prog)) {
            recalc_stamps_safe($studentId, $year['id']);
        }
        Cache::flush();
    }
    json_out(['ok' => true, 'status' => $moi, 'changed' => $doi]);
}

// ---------------------------------------------------------------------
//  MARK: ghi điểm danh IDEMPOTENT (dùng cho hàng đợi offline)
//  Khác với toggle: luôn GHI, không bao giờ XOÁ.
//  Chạy lặp bao nhiêu lần cũng cho kết quả giống nhau.
// ---------------------------------------------------------------------
if (($_GET['action'] ?? '') === 'mark') {
    $existing = db_one('SELECT id FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                       [$programId, $date, $studentId]);
    if ($existing) {
        // Đã có -> idempotent: coi như thành công, không làm gì
        json_out(['ok' => true, 'added' => false]);
    }

    // Buổi tương lai: chặn
    if ($laTuongLai) {
        json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);
    }
    // Lớp không thuộc chương trình
    if ($progClassIds !== null && !in_array((int) $st['class_id'], $progClassIds, true)) {
        json_fail('Lớp của em ' . $st['full_name'] . ' không thuộc buổi này.', 400);
    }
    // Sau giờ vắng: không ghi có mặt, trả lỗi để client hiển thị
    if ($pastAbsent && !can_override_session_lock($me, (int) $st['class_id'])) {
        json_fail('Đã quá giờ "tính vắng" của buổi — em ' . $st['full_name'] . ' tính vắng.', 400);
    }

    $status = $pastCutoff ? 'đi trễ' : 'có mặt';
    // Đánh dấu nếu ghi từ hàng đợi offline — để BĐH rà soát
    // (phân biệt với ghi online thời gian thực)
    $offlineFlag = !empty($in['offlineMark']) ? 1 : 0;
    try {
        db_run('INSERT IGNORE INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by, offline_marked)
                VALUES (?,?,?,?,?,?,?,?)',
            [$year['id'], $programId, $date, $studentId, $status, 'tay', $me['id'], $offlineFlag]);
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
            json_out(['ok' => true, 'added' => false]); // race condition: đã có
        }
        json_fail(safe_error($e, 'Ghi điểm danh thất bại: '), 500);
    }

    if ($pastCutoff) {
        log_action('diemdanh', 'attendance', 'Ghi điểm danh (mark offline) cho ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · ' . $status);
    }
    if (program_earns_stamps($prog)) {
        recalc_stamps_safe($studentId, $year['id']);
    }
    Cache::flush();
    json_out(['ok' => true, 'added' => true, 'status' => $status,
              'markedAt' => date('H:i'), 'markedBy' => $me['full_name'],
              'offlineMarked' => $offlineFlag]);
}

$existing = db_one('SELECT * FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                   [$programId, $date, $studentId]);

// GỠ bản ghi cũ:
// - Sau giờ vắng: chỉ người có "cửa sửa" mới được gỡ (tránh GLV xóa lùi).
// - Nếu lớp đã bị gỡ khỏi chương trình: vẫn cho gỡ để không kẹt bản ghi.
if ($existing) {
    $lopBiGoiKhoiProg = ($progClassIds !== null && !in_array((int) $st['class_id'], $progClassIds, true));
    if ($pastAbsent && !$lopBiGoiKhoiProg && !can_override_session_lock($me, (int) $st['class_id'])) {
        json_fail('Đã quá giờ "tính vắng" — chỉ admin, BĐH, trưởng khối hoặc GLV chủ nhiệm mới gỡ được điểm danh buổi này.');
    }
    db_run('DELETE FROM attendances WHERE id = ?', [$existing['id']]);
    if ($pastCutoff) {
        log_action('diemdanh', 'attendance', 'Gỡ điểm danh của ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date . ' · đang là ' . $existing['status']);
    }
    // Sổ Mộc: gỡ điểm danh của buổi có tính Mộc là thao tác HOÀN Mộc lại.
    if (program_earns_stamps($prog)) {
        recalc_stamps_safe($studentId, $year['id']);
    }
    Cache::flush();
    json_out(['ok' => true, 'removed' => true]);
}

// GHI MỚI cho buổi tương lai bị chặn. (Đặt SAU nhánh gỡ ở trên: lỡ đã có
// dữ liệu tương lai thì vẫn bấm lần nữa để gỡ được, không bị kẹt.)
if ($laTuongLai) {
    json_fail('Buổi ngày ' . $date . ' chưa diễn ra, chưa điểm danh được.', 400);
}

// GHI MỚI: lớp của em phải tham gia chương trình (nếu chương trình có gắn lớp)
if ($progClassIds !== null && !in_array((int) $st['class_id'], $progClassIds, true)) {
    json_fail('Lớp của em ' . $st['full_name'] . ' không thuộc buổi này.', 400);
}

// Sau ngưỡng "vắng" thì không ghi có mặt nữa (em tính vắng) — trừ "cửa
// sửa": admin/BĐH/trưởng khối/GLV chủ nhiệm được bù buổi cũ trong phạm vi
// mình (đã qua can_access_class 'edit' ở trên). GLV thường vẫn bị khoá.
if ($pastAbsent && !can_override_session_lock($me, (int) $st['class_id'])) {
    json_fail('Đã quá giờ "tính vắng" của buổi — em ' . $st['full_name'] . ' tính vắng, không ghi được.');
}

$status = $pastCutoff ? 'đi trễ' : 'có mặt';
try {
    db_run('INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by, offline_marked)
            VALUES (?,?,?,?,?,?,?,?)',
        [$year['id'], $programId, $date, $studentId, $status, 'tay', $me['id'], 0]);
} catch (Throwable $e) {
    // Race condition: nếu bản ghi đã tồn tại (duplicated key) coi như thành công
    if (str_contains($e->getMessage(), 'SQLSTATE[23000]')) {
        json_out(['ok' => true, 'removed' => false, 'status' => $status, 'markedAt' => date('H:i'), 'markedBy' => $me['full_name']]);
    }
    json_fail(safe_error($e, 'Ghi điểm danh thất bại: '), 500);
}

if ($pastCutoff) {
    log_action('diemdanh', 'attendance', 'Ghi điểm danh cho ' . $st['full_name'],
               $prog['name'] . ' · ' . $date . ' · ' . $status);
}

// Sổ Mộc: buổi có tính Mộc thì ghi điểm danh xong phải tính lại ví/chuỗi.
if (program_earns_stamps($prog)) {
    recalc_stamps_safe($studentId, $year['id']);
}

Cache::flush();
json_out([
    'ok'       => true,
    'removed'  => false,
    'status'   => $status,
    'markedAt' => date('H:i'),
    'markedBy' => $me['full_name'],
]);
