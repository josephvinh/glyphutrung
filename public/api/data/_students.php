<?php
/**
 * DATA API — Students Module
 *
 * Thiếu nhi + enrollments + Sổ Mộc
 */

/**
 * Lấy danh sách thiếu nhi (có phân trang).
 * Bao gồm: PR-2 hidden_at filter
 *
 * PR-4: Tối thiểu payload - chỉ gửi SĐT phụ huynh khi ?includeContacts=1
 *
 * @return array{data: array, total: int, page: int, limit: int}
 */
function data_load_students(int $yid, array $me, bool $isPaginated, int $page, int $limit, bool $includeContacts = false): array
{
    $ids = allowed_class_ids($me);
    if ($ids !== null && !$ids) return ['data' => [], 'total' => 0];

    // PR-2: Lọc các em đang bị ẩn
    $dk = ' AND s.hidden_at IS NULL';
    $tham = [$yid];
    if ($ids !== null) {
        $dk   .= ' AND e.class_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        $tham = array_merge($tham, $ids);
    }

    // Đếm tổng (cho pagination metadata) - JOIN với students để lọc hidden_at
    $total = (int) db_one(
        "SELECT COUNT(*) n FROM enrollments e
           JOIN students s ON s.id = e.student_id
          WHERE e.year_id = ?{$dk}", $tham)['n'];

    // Pagination
    $offset = ($page - 1) * $limit;
    $limitClause = $isPaginated ? "LIMIT {$limit} OFFSET {$offset}" : '';

    $rows = db_all(
        "SELECT s.*, e.status, c.name AS class_name, b.name AS block_name
           FROM enrollments e
           JOIN students s ON s.id = e.student_id
           JOIN classes  c ON c.id = e.class_id
           JOIN blocks   b ON b.id = c.block_id
          WHERE e.year_id = ?{$dk}
          ORDER BY s.code
          {$limitClause}", $tham);

    $data = array_map(fn($s) => [
        'id'          => (int) $s['id'],
        'code'        => $s['code'],
        'holyName'    => $s['holy_name'],
        'name'        => $s['full_name'],
        'gender'      => (int) $s['gender'],
        'birthDate'   => $s['birth_date'],
        'address'     => $s['address'],
        'fatherName'  => $s['father_name'],
        'motherName'  => $s['mother_name'],
        // PR-4: Chỉ gửi SĐT khi cần (màn thông tin phụ huynh)
        'fatherPhone' => $includeContacts ? $s['father_phone'] : null,
        'motherPhone' => $includeContacts ? $s['mother_phone'] : null,
        'status'      => $s['status'],
        'className'   => $s['class_name'],
        'block'       => $s['block_name'],
    ], $rows);

    return ['data' => $data, 'total' => $total, 'page' => $page, 'limit' => $limit];
}

/**
 * Lấy sĩ số từng lớp.
 *
 * @return array<string, int> [className => count]
 */
function data_load_class_counts(int $yid): array
{
    $classCounts = [];
    foreach (db_all(
        "SELECT c.name, COUNT(*) AS n
           FROM enrollments e
           JOIN classes c ON c.id = e.class_id
          WHERE e.year_id = ? AND e.status = 'đang sinh hoạt'
          GROUP BY c.id", [$yid]) as $r) {
        $classCounts[$r['name']] = (int) $r['n'];
    }
    return $classCounts;
}
