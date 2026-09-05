<?php
/**
 * XIN PHÉP
 *
 *   POST api/leave.php?action=create  { studentId, programId, date, reason }
 *   POST api/leave.php?action=approve { id }
 *   POST api/leave.php?action=reject  { id, reason }
 *
 * Hai quy tắc thời gian đều do MÁY CHỦ kiểm, không tin đồng hồ máy khách:
 *   · quá ngày diễn ra  -> khoá, không nộp được nữa
 *   · quá giờ chốt      -> chỉ nộp được cho em đang vắng không phép
 */

require __DIR__ . '/_bootstrap.php';
require dirname(__DIR__, 2) . '/config/push.php';

$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();

switch ($action) {

    // -------------------------------------------------------------
    case 'create':
        require_post();
        require_csrf();
        // Quyền 'view' đủ để nộp đơn; 'edit' mới được duyệt
        $me = require_permission('leave', 'view');

        $studentId = (int) ($in['studentId'] ?? 0);
        $programId = (int) ($in['programId'] ?? 0);
        $date      = (string) ($in['date'] ?? '');
        $reason    = trim((string) ($in['reason'] ?? ''));

        if (!$studentId || !$programId || !$date) json_fail('Thiếu thông tin buổi hoặc em cần xin phép.');
        if ($reason === '') json_fail('Vui lòng ghi lý do xin phép.');

        $prog = db_one('SELECT * FROM programs WHERE id=? AND year_id=?', [$programId, $yid]);
        if (!$prog) json_fail('Không tìm thấy chương trình.', 404);

        $st = db_one('SELECT s.full_name FROM enrollments e JOIN students s ON s.id=e.student_id
                       WHERE e.year_id=? AND e.student_id=? AND e.status=?',
                     [$yid, $studentId, 'đang sinh hoạt']);
        if (!$st) json_fail('Em này không có trong danh sách đang sinh hoạt.', 404);

        // Hết ngày diễn ra là khoá sổ
        if ($date < date('Y-m-d')) {
            json_fail('Đã hết hạn xin phép cho buổi này. Chỉ nộp được trong ngày diễn ra.');
        }

        // Sau giờ chốt thì chỉ còn xin được cho em đang vắng không phép
        $cutoffTs = strtotime($date . ' ' . $prog['start_time']) + ((int) app_config('cutoff_minutes')) * 60;
        if (time() >= $cutoffTs) {
            $daDiemDanh = db_one('SELECT id FROM attendances WHERE program_id=? AND session_date=? AND student_id=?',
                                 [$programId, $date, $studentId]);
            if ($daDiemDanh) json_fail('Em này đã được điểm danh có mặt, không cần xin phép.');
        }

        if (db_one('SELECT id FROM leave_requests WHERE program_id=? AND session_date=? AND student_id=?',
                   [$programId, $date, $studentId])) {
            json_fail('Em này đã có đơn cho buổi đó rồi.');
        }

        $id = db_insert('INSERT INTO leave_requests (year_id, student_id, program_id, session_date, reason, created_by)
                         VALUES (?,?,?,?,?,?)',
                        [$yid, $studentId, $programId, $date, $reason, $me['id']]);

        log_action('tao', 'leave', 'Nộp đơn xin phép cho ' . $st['full_name'],
                   $prog['name'] . ' · ' . $date);

        // Báo cho những người duyệt được đơn này: Ban Điều Hành, Quản trị,
        // và Trưởng khối của khối em đó.
        $khoi = db_one('SELECT c.block_id FROM enrollments e JOIN classes c ON c.id = e.class_id
                         WHERE e.student_id = ? AND e.year_id = ?', [$studentId, $yid]);
        $duyet = push_nguoi_duyet();
        if ($khoi && $khoi['block_id']) {
            $duyet = array_merge($duyet, array_map('intval', array_column(db_all(
                "SELECT id FROM members WHERE status = 'đang phục vụ'
                   AND role_code = 'truong_khoi' AND block_id = ?", [$khoi['block_id']]), 'id')));
        }
        push_bao(array_diff($duyet, [(int) $me['id']]), 'Đơn xin phép chờ duyệt',
                 $st['full_name'] . ' — ' . $prog['name'] . ' ngày ' . $date,
                 '/#leave', 'tntt-phep');
        json_out(['ok' => true, 'id' => $id]);

    // -------------------------------------------------------------
    case 'approve':
    case 'reject':
        require_post();
        require_csrf();
        $me = require_permission('leave', 'edit');
        $id = (int) ($in['id'] ?? 0);

        $req = db_one('SELECT l.*, s.full_name, e.class_id, c.block_id
                         FROM leave_requests l
                         JOIN students s ON s.id = l.student_id
                         JOIN enrollments e ON e.student_id = l.student_id AND e.year_id = l.year_id
                         JOIN classes c ON c.id = e.class_id
                        WHERE l.id = ? AND l.year_id = ?', [$id, $yid]);
        if (!$req) json_fail('Không tìm thấy đơn.', 404);
        if ($req['status'] !== 'chờ duyệt') json_fail('Đơn này đã được xử lý rồi.');

        // Chỉ duyệt được đơn của lớp mình thực sự phụ trách (xét theo TỪNG
        // phân công: một vai trò vừa được 'edit' đơn phép vừa phủ lớp em đó).
        if (!can_access_class($me, 'leave', (int) $req['class_id'], 'edit')) {
            json_fail('Đơn này không thuộc phạm vi bạn phụ trách.', 403);
        }

        if ($action === 'approve') {
            db_run("UPDATE leave_requests SET status='đã duyệt', approved_by=?, approved_at=NOW(), reject_reason=NULL
                     WHERE id=?", [$me['id'], $id]);
            log_action('duyet', 'leave', 'Duyệt đơn phép của ' . $req['full_name'],
                       $req['session_date'] . ' · ' . $req['reason']);
        } else {
            $why = trim((string) ($in['reason'] ?? ''));
            if ($why === '') json_fail('Vui lòng ghi lý do từ chối để GLV biết mà giải thích với phụ huynh.');
            db_run("UPDATE leave_requests SET status='từ chối', approved_by=?, approved_at=NOW(), reject_reason=?
                     WHERE id=?", [$me['id'], $why, $id]);
            log_action('tuchoi', 'leave', 'Từ chối đơn phép của ' . $req['full_name'], $why);
        }

        // Báo lại cho người đã nộp đơn biết kết quả
        if ($req['created_by'] && (int) $req['created_by'] !== (int) $me['id']) {
            push_bao([(int) $req['created_by']],
                     $action === 'approve' ? 'Đơn phép đã được duyệt' : 'Đơn phép bị từ chối',
                     $req['full_name'] . ' — ngày ' . $req['session_date'],
                     '/#leave', 'tntt-phep-kq');
        }

        json_out(['ok' => true, 'approvedBy' => $me['full_name'], 'approvedAt' => date('Y-m-d H:i')]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
