<?php
/**
 * QUẢN LÝ PHÂN CÔNG KIÊM NHIỆM
 *
 *   GET  ?action=list&memberId=N       tất cả assignments (kể cả lịch sử)
 *   GET  ?action=active&memberId=N     chỉ assignments đang hiệu lực
 *   POST ?action=create                tạo phân công mới (kiểm tra phạm vi quản lý)
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
    // Phân công đang hiệu lực — lọc theo phạm vi người gọi.
    //
    // Admin/BĐH (phạm vi null = toàn đoàn): thấy mọi phân công.
    // Trưởng khối: chỉ phân công TRONG khối mình.
    // GLV / Dự Bị: chỉ phân công TRONG lớp mình.
    //
    // Kết quả gồm vai trò + khối/lớp — KHÔNG có tên cá nhân — đủ cho
    // màn Khối & Lớp dựng roster kiêm nhiệm mà không lộ thông tin riêng.
    case 'list_active':
        $scopeIds = scan_class_ids($me);  // null = toàn đoàn, [] = không có quyền
        if ($scopeIds === []) {
            json_out(['ok' => true, 'assignments' => []]);
            break;
        }

        $sql  = "SELECT a.id, a.member_id, a.role_code, a.is_primary,
                        r.scope AS role_scope,
                        b.name AS block_name, c.name AS class_name
                   FROM member_assignments a
                   JOIN roles r ON r.code = a.role_code
                   JOIN members m ON m.id = a.member_id
                   LEFT JOIN blocks b ON b.id = a.block_id
                   LEFT JOIN classes c ON c.id = a.class_id
                  WHERE a.to_date IS NULL";
        $params = [];

        if ($scopeIds !== null) {
            $ph = implode(',', array_fill(0, count($scopeIds), '?'));
            $sql .= " AND a.class_id IN ($ph)";
            $params = $scopeIds;
        }

        $sql .= " ORDER BY a.is_primary DESC, r.level DESC";
        $rows = db_all($sql, $params);
        json_out(['ok' => true, 'assignments' => array_map(fn($a) => [
            'id'        => (int) $a['id'],
            'memberId'  => (int) $a['member_id'],
            'role'      => $a['role_code'],
            'scope'     => $a['role_scope'],
            'className' => $a['class_name'] ?? '',
            'blockName' => $a['block_name'] ?? '',
            'isPrimary' => (bool) $a['is_primary'],
        ], $rows)]);
        break;

    // -------------------------------------------------------------
    case 'create':
        require_write();   // hành động ghi: bắt buộc POST + CSRF
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $memberId = (int) ($in['memberId'] ?? 0);
        $role     = trim((string) ($in['role'] ?? ''));
        $blockId  = !empty($in['blockId']) ? (int) $in['blockId'] : null;
        $classId  = !empty($in['classId']) ? (int) $in['classId'] : null;
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
        if ($roleRow['scope'] === 'khối') $classId = null;

        if (!db_one('SELECT id FROM members WHERE id = ?', [$memberId]))
            json_fail('Không tìm thấy thành viên.', 404);
        if ($classId) {
            // Khối của phân công luôn lấy từ lớp — không tin block_id client gửi
            $cls = db_one('SELECT block_id FROM classes WHERE id = ?', [$classId]);
            if (!$cls) json_fail('Không tìm thấy lớp.', 404);
            $blockId = (int) $cls['block_id'];
        } elseif ($blockId && !db_one('SELECT id FROM blocks WHERE id = ?', [$blockId])) {
            json_fail('Không tìm thấy khối.', 404);
        }

        // Phạm vi: Trưởng khối chỉ phân công trong khối mình; phân công toàn đoàn
        // (BĐH, Quản trị, Thủ Thư) chỉ Ban Điều Hành trở lên được cấp.
        if (!can_manage_assignment_scope($meEditor, $blockId, $classId))
            json_fail('Bạn không có quyền phân công ngoài phạm vi mình quản lý.', 403);
        if (in_array($role, ['admin', 'bdh'], true) && ($meEditor['role_code'] ?? '') !== 'admin')
            json_fail('Chỉ Quản Trị Hệ Thống mới được gán vai Quản trị hoặc Ban Điều Hành.', 403);

        // Thủ Thư là vai PHỤ TRỢ (kiêm nhiệm thêm, không đổi vai gốc): không bao
        // giờ làm phân công chính, và cấp lặp thì trả lại phân công đang có.
        if ($role === 'thu_thu') {
            $dup = db_one(
                "SELECT id FROM member_assignments
                  WHERE member_id = ? AND role_code = 'thu_thu' AND to_date IS NULL",
                [$memberId]
            );
            if ($dup) json_out(['ok' => true, 'id' => (int) $dup['id'], 'isPrimary' => false]);
        }

        // Nếu đây là assignment đầu tiên → tự động primary
        $hasActive = (int) db_one(
            "SELECT COUNT(*) AS c FROM member_assignments
              WHERE member_id = ? AND to_date IS NULL",
            [$memberId]
        )['c'];
        $isPrimary = ($hasActive === 0 && $role !== 'thu_thu') ? 1 : 0;

        $newId = db_insert(
            "INSERT INTO member_assignments
                (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$memberId, $role, $blockId, $classId, $isPrimary, $fromDate, $meEditor['id'], $note]
        );

        // Nếu user yêu cầu primary, đẩy các cái khác xuống
        if (!empty($in['isPrimary']) && $role !== 'thu_thu') {
            enforce_single_primary($memberId, $newId);
        }
        recompute_member_primary($memberId);

        json_out(['ok' => true, 'id' => $newId, 'isPrimary' => (bool) $isPrimary]);
        break;

    // -------------------------------------------------------------
    case 'end':
        require_write();   // hành động ghi: bắt buộc POST + CSRF
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] !== null) json_fail('Phân công đã kết thúc trước đó.');
        if (!can_manage_assignment_scope($meEditor, $row['block_id'] ? (int) $row['block_id'] : null,
                                         $row['class_id'] ? (int) $row['class_id'] : null))
            json_fail('Bạn không có quyền kết thúc phân công này.', 403);

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
        recompute_member_primary((int) $row['member_id']);

        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'set_primary':
        require_write();   // hành động ghi: bắt buộc POST + CSRF
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT member_id, block_id, class_id, to_date FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] !== null) json_fail('Phân công đã kết thúc, không đặt làm chính được.');
        if (!can_manage_assignment_scope($meEditor, $row['block_id'] ? (int) $row['block_id'] : null,
                                         $row['class_id'] ? (int) $row['class_id'] : null))
            json_fail('Bạn không có quyền đổi phân công này.', 403);

        enforce_single_primary((int) $row['member_id'], $assignmentId);
        recompute_member_primary((int) $row['member_id']);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    case 'delete':
        require_write();   // hành động ghi: bắt buộc POST + CSRF
        $meEditor = require_permission('org', 'edit');
        $in = json_input();

        $assignmentId = (int) ($in['assignmentId'] ?? 0);
        if (!$assignmentId) json_fail('Thiếu assignmentId.');

        $row = db_one('SELECT * FROM member_assignments WHERE id = ?', [$assignmentId]);
        if (!$row) json_fail('Không tìm thấy phân công.');
        if ($row['to_date'] === null) json_fail('Chỉ xóa được phân công đã kết thúc.');
        if (!can_manage_assignment_scope($meEditor, $row['block_id'] ? (int) $row['block_id'] : null,
                                         $row['class_id'] ? (int) $row['class_id'] : null))
            json_fail('Bạn không có quyền xóa phân công này.', 403);

        db_run('DELETE FROM member_assignments WHERE id = ?', [$assignmentId]);
        json_out(['ok' => true]);
        break;

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
