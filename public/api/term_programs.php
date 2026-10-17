<?php
/**
 * QUẢN LÝ CHƯƠNG TRÌNH THEO HỌC KỲ
 *
 *   GET    api/term_programs.php?term_id=1
 *   POST   api/term_programs.php  { term_id, program_id, is_active }
 */

require __DIR__ . '/_bootstrap.php';

$action = $_SERVER['REQUEST_METHOD'];
$termId = isset($_GET['term_id']) ? (int) $_GET['term_id'] : 0;

// Chỉ admin/bdh mới sửa
function require_admin_or_bdh(): array {
    global $me;
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

$me = require_login();

// ============================================================
// GET — lấy programs của 1 học kỳ
// ============================================================

if ($action === 'GET') {
    if (!$termId) json_fail('Thiếu term_id.');

    // Chặn term_id bất kỳ — mục 🟡 2 review vòng 2 PR #285. Trước đây
    // term_id không tồn tại vẫn LEFT JOIN về 0 dòng, dễ nhầm với "term
    // mới chưa có program nào". 404 rõ ràng giúp UI biết phải báo lỗi.
    $term = db_one('SELECT id, start_date, end_date FROM terms WHERE id = ?', [$termId]);
    if (!$term) json_fail('Học kỳ không tồn tại.', 404);

    // Lấy TẤT CẢ programs count_for_attendance=1 của niên khoá — kể cả
    // đã tắt — để admin thấy và bật lại được trong UI Cài đặt. Hai
    // endpoint chuyên cần (attendance_scores.php, attendance_class.php)
    // mới lọc `COALESCE(tp.is_active, 1) = 1` (xem #290).
    $yearId = (int) (db_one('SELECT year_id FROM terms WHERE id = ?', [$termId])['year_id'] ?? 0);
    $programs = db_all('
        SELECT p.id, p.name, p.type, p.count_for_attendance,
               tp.id as tp_id, COALESCE(tp.is_active, 1) as is_active
        FROM programs p
        LEFT JOIN term_programs tp ON tp.program_id = p.id AND tp.term_id = ?
        WHERE p.year_id = ? AND p.status = "kích hoạt" AND p.count_for_attendance = 1
        ORDER BY p.sort_order, p.name
    ', [$termId, $yearId]);

    // Đếm số buổi dự kiến mỗi program (buổi đã điểm danh trong kỳ)
    foreach ($programs as &$p) {
        $count = db_one('
            SELECT COUNT(DISTINCT session_date) as c
            FROM attendances a
            WHERE a.program_id = ?
              AND a.session_date BETWEEN ? AND ?
        ', [$p['id'], $term['start_date'], $term['end_date']]);
        $p['expected_sessions'] = (int) ($count['c'] ?? 0);
        $p['is_active'] = (bool) $p['is_active'];
    }

    json_out($programs);
}

// ============================================================
// POST — cập nhật is_active
// ============================================================

if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();

    $termId = (int) ($in['term_id'] ?? 0);
    $programId = (int) ($in['program_id'] ?? 0);
    $isActive = isset($in['is_active']) ? (bool) $in['is_active'] : true;

    if (!$termId || !$programId) json_fail('Thiếu term_id hoặc program_id.');

    // Validate FK trước insert — 404 thay vì 500 do FK violation (#9 review #285).
    if (!db_one('SELECT id FROM terms WHERE id = ?', [$termId])) {
        json_fail('Học kỳ không tồn tại.', 404);
    }
    if (!db_one('SELECT id FROM programs WHERE id = ?', [$programId])) {
        json_fail('Chương trình không tồn tại.', 404);
    }

    // Upsert
    db_run('INSERT INTO term_programs (term_id, program_id, is_active)
            VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE is_active = VALUES(is_active)',
        [$termId, $programId, $isActive ? 1 : 0]);

    $program = db_one('SELECT name FROM programs WHERE id = ?', [$programId]);
    log_action('sudung', 'term_programs',
        'Cập nhật chương trình HK#' . $termId . ': ' . ($program['name'] ?? '#' . $programId),
        $isActive ? 'bật' : 'tắt');
    Cache::flush();

    json_out(['ok' => true]);
}
