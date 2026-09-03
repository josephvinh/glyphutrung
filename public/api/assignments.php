<?php
/**
 * QUẢN LÝ PHÂN CÔNG KIÊM NHIỆM
 *
 *   GET  ?action=list&memberId=N       tất cả assignments (kể cả lịch sử)
 *   GET  ?action=active&memberId=N     chỉ assignments đang hiệu lực
 *   POST ?action=create                tạo phân công mới
 *   POST ?action=end                   kết thúc phân công (set to_date)
 *   POST ?action=set_primary           đánh dấu phân công chính
 *   POST ?action=delete                xóa hẳn (chỉ khi đã kết thúc)
 */

require __DIR__ . '/_bootstrap.php';

$action = $_GET['action'] ?? '';
$me     = require_permission('org', 'view');

switch ($action) {

    // -------------------------------------------------------------
    case 'list':
        $memberId = (int) ($_GET['memberId'] ?? 0);
        if (!$memberId) json_fail('Thiếu memberId.');

        $rows = db_all(
            "SELECT a.*, r.label AS role_label, r.scope AS role_scope,
                    b.name AS block_name, c.name AS class_name,
                    ab.full_name AS assigned_by_name
               FROM member_assignments a
               JOIN roles r ON r.code = a.role_code
               LEFT JOIN blocks b ON b.id = a.block_id
               LEFT JOIN classes c ON c.id = a.class_id
               LEFT JOIN members ab ON ab.id = a.assigned_by
              WHERE a.member_id = ?
              ORDER BY a.is_primary DESC, a.from_date DESC",
            [$memberId]
        );
        json_out(['ok' => true, 'assignments' => $rows]);
        break;

    // -------------------------------------------------------------
    case 'active':
        $memberId = (int) ($_GET['memberId'] ?? 0);
        if (!$memberId) json_fail('Thiếu memberId.');
        json_out(['ok' => true, 'assignments' => effective_assignments($memberId)]);
        break;

    // -------------------------------------------------------------
    case 'create':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $memberId = (int) ($in['memberId'] ?? 0);
        $role     = trim((string) ($in['role'] ?? ''));
        $blockId  = $in['blockId']  ? (int) $in['blockId']  : null;
        $classId  = $in['classId']  ? (int) $in['classId']  : null;
        $fromDate = $in['fromDate'] ?? date('Y-m-d');
        $note     = trim((string) ($in['note'] ?? ''));

        if (!$memberId)        json_fail('Thiếu memberId.');
        if ($role === '')      json_fail('Thiếu vai trò.');

        // Validate role
        $roleRow = db_one('SELECT scope FROM roles WHERE code = ?', [$role]);
        if (!$roleRow) json_fail('Vai trò không tồn tại.');

        // Validate scope vs block_id/class_id
        if ($roleRow['scope'] === 'khối' && !$blockId)
            json_fail('Vai trò phạm vi khối cần chọn khối.');
        if ($roleRow['scope'] === 'lớp' && !$classId)
            json_fail('Vai trò phạm vi lớp cần chọn lớp.');
        if ($roleRow['scope'] === 'toàn đoàn') {
            $blockId = null;
            $classId = null;
        }

        // Nếu đây là assignment đầu tiên → tự động primary
        $hasActive = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_assignments
              WHERE member_id = ? AND to_date IS NULL",
            [$memberId]
        )['c'];
        $isPrimary = $hasActive === 0 ? 1 : 0;

        $newId = db_insert(
            "INSERT INTO member_assignments
                (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$memberId, $role, $blockId, $classId, $isPrimary, $fromDate, $meEditor['id'], $note]
        );

        // Nếu user yêu cầu primary, đẩy các cái khác xuống
        if (!empty($in['isPrimary'])) {
            enforce_single_primary($memberId, $newId);
        }

        json_out(['ok' => true, 'id' => $newId, 'isPrimary' => (bool) $isPrimary]);
        break;

    // -------------------------------------------------------------
    case 'end':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] !== null) json_fail('Phân công đã kết thúc trước đó.');

        db_run(
            "UPDATE member_assignments SET to_date = CURDATE() WHERE id = ?",
            [$assignmentId]
        );

        // Nếu vừa kết thúc phân công primary → đẩy phân công active cũ nhất lên primary
        if ($row['is_primary']) {
            db_run(
                "UPDATE member_assignments
                    SET is_primary = 1
                  WHERE member_id = ? AND to_date IS NULL
                  ORDER BY from_date ASC LIMIT 1",
                [$row['member_id']]
            );
        }

        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'set_primary':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT member_id FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');

        enforce_single_primary((int) $row['member_id'], $assignmentId);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'delete':
        require_csrf();
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] === null) json_fail('Chỉ xóa được phân công đã kết thúc.');

        db_run('DELETE FROM member_assignments WHERE id = ?', [$assignmentId]);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
