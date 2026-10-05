<?php
/**
 * DATA API — Attendance Module
 *
 * Điểm danh + đơn xin phép
 */

/**
 * Lấy danh sách điểm danh.
 * Bao gồm: scope filtering, classId/programId filters, IDOR check (S3)
 *
 * @return array Điểm danh đã format
 */
function data_load_attendances(
    int $yid,
    array $me,
    string $part,
    ?int $filterClassId,
    ?int $filterProgramId
): array {
    if ($part === 'core') return [];

    $attScopeIds = allowed_class_ids($me);  // null = toàn đoàn
    $attRows = [];

    if ($filterProgramId !== null) {
        // Filter theo programId: chỉ tải điểm danh của chương trình này
        $sql = 'SELECT a.*, m.full_name AS marked_by_name
                FROM attendances a
                LEFT JOIN members m ON m.id = a.marked_by
                WHERE a.year_id = ? AND a.program_id = ?';
        $params = [$yid, $filterProgramId];

        if ($attScopeIds !== null && $attScopeIds !== []) {
            // Lấy studentIds trong phạm vi
            $stuInScope = db_all(
                'SELECT student_id FROM enrollments WHERE year_id = ? AND class_id IN (' . implode(',', array_fill(0, count($attScopeIds), '?')) . ')',
                array_merge([$yid], $attScopeIds));
            $stuIds = array_column($stuInScope, 'student_id');
            if ($stuIds) {
                $ph = implode(',', array_fill(0, count($stuIds), '?'));
                $attRows = db_all(
                    "SELECT a.*, m.full_name AS marked_by_name
                       FROM attendances a
                       LEFT JOIN members m ON m.id = a.marked_by
                      WHERE a.year_id = ? AND a.program_id = ? AND a.student_id IN ($ph)",
                    array_merge([$yid, $filterProgramId], $stuIds));
            }
        } else {
            $attRows = db_all($sql, $params);
        }
    } elseif ($filterClassId !== null) {
        // S3: IDOR check - chỉ tải điểm danh của lớp trong phạm vi được phép
        $allowed = allowed_class_ids($me);
        if ($allowed !== null && !in_array($filterClassId, $allowed, true)) {
            http_response_code(403);
            exit;
        }
        $stuInClass = db_all('SELECT student_id FROM enrollments WHERE year_id = ? AND class_id = ?', [$yid, $filterClassId]);
        $stuIds = array_column($stuInClass, 'student_id');
        if ($stuIds) {
            $ph = implode(',', array_fill(0, count($stuIds), '?'));
            $attRows = db_all(
                "SELECT a.*, m.full_name AS marked_by_name
                   FROM attendances a
                   LEFT JOIN members m ON m.id = a.marked_by
                  WHERE a.year_id = ? AND a.student_id IN ($ph)",
                array_merge([$yid], $stuIds));
        }
    } elseif ($attScopeIds === null) {
        // Không filter: tải toàn bộ (admin/BĐH)
        $attRows = db_all(
            'SELECT a.*, m.full_name AS marked_by_name
               FROM attendances a
               LEFT JOIN members m ON m.id = a.marked_by
              WHERE a.year_id = ?', [$yid]);
    } else {
        // Chỉ điểm danh của các em trong phạm vi
        $stuIds = []; // Sẽ được gán từ nơi gọi
        if ($stuIds) {
            $ph = implode(',', array_fill(0, count($stuIds), '?'));
            $attRows = db_all(
                "SELECT a.*, m.full_name AS marked_by_name
                   FROM attendances a
                   LEFT JOIN members m ON m.id = a.marked_by
                  WHERE a.year_id = ? AND a.student_id IN ($ph)",
                array_merge([$yid], $stuIds));
        }
    }

    return array_map(fn($a) => [
        'programId'  => (int) $a['program_id'],
        'scheduleId' => !empty($a['schedule_id']) ? (int) $a['schedule_id'] : null,
        'date'      => $a['session_date'],
        'studentId' => (int) $a['student_id'],
        'status'    => $a['status'],
        'method'    => $a['method'],
        'markedBy'  => $a['marked_by_name'] ?? '',
        'markedAt'  => substr($a['marked_at'], 11, 5),
    ], $attRows);
}

/**
 * Lấy đơn xin phép.
 * Chỉ đơn của em thuộc phạm vi được xem module 'leave' (#78)
 *
 * @return array Đơn xin phép đã format
 */
function data_load_leaves(int $yid, array $me): array
{
    return array_map(fn($l) => [
        'id'           => (int) $l['id'],
        'studentId'    => (int) $l['student_id'],
        'programId'    => (int) $l['program_id'],
        'date'         => $l['session_date'],
        'reason'       => $l['reason'],
        'status'       => $l['status'],
        'createdBy'    => $l['created_by_name'] ?? '',
        'createdAt'    => substr($l['created_at'], 0, 16),
        'approvedBy'   => $l['approved_by_name'] ?? '',
        'approvedAt'   => $l['approved_at'] ? substr($l['approved_at'], 0, 16) : '',
        'rejectReason' => $l['reject_reason'] ?? '',
    ], data_scoped_rows(data_scope_for($me, 'leave'),
        'SELECT l.*, c.full_name AS created_by_name, a.full_name AS approved_by_name
           FROM leave_requests l{JOIN}
           LEFT JOIN members c ON c.id = l.created_by
           LEFT JOIN members a ON a.id = l.approved_by
          WHERE l.year_id = ?
          ORDER BY l.session_date DESC, l.id DESC', 'l.student_id', $yid, [$yid]));
}
