<?php
/**
 * QUẢN LÝ LOẠI ĐẦU ĐIỂM
 *
 *   GET    api/score_types.php
 *   POST   api/score_types.php           { code, label, short_label, weight, max_exams, category }
 *   PUT    api/score_types.php?code=xxx { ... }
 *   DELETE api/score_types.php?code=xxx
 */

require __DIR__ . '/_bootstrap.php';

require_login(); // ai cũng đọc

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
$action = $_SERVER['REQUEST_METHOD'];

// Chỉ admin/bdh mới sửa
function require_admin_or_bdh(): array {
    global $me;
    if (!in_array($me['role_code'], ['admin', 'bdh'])) {
        json_fail('Chỉ Quản Trị hoặc Ban Điều Hành mới có quyền.', 403);
    }
    return $me;
}

$me = $me ?? require_login();

// ============================================================
// GET — lấy danh sách
// ============================================================

if ($action === 'GET' && $code === '') {
    $types = db_all('SELECT code, label, short_label, weight, max_exams, category, sort_order
                     FROM score_types ORDER BY sort_order');
    json_out($types);
}

// ============================================================
// GET — lấy 1 loại
// ============================================================

if ($action === 'GET' && $code !== '') {
    $type = db_one('SELECT code, label, short_label, weight, max_exams, category, sort_order
                    FROM score_types WHERE code = ?', [$code]);
    if (!$type) json_fail('Không tìm thấy loại đầu điểm.', 404);
    json_out($type);
}

// ============================================================
// POST — thêm mới
// ============================================================

if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();

    $code = trim((string) ($in['code'] ?? ''));
    $label = trim((string) ($in['label'] ?? ''));
    $short = trim((string) ($in['short_label'] ?? ''));
    $weight = max(1, (int) ($in['weight'] ?? 1));
    $maxExams = max(1, min(20, (int) ($in['max_exams'] ?? 1)));
    $category = in_array($in['category'] ?? '', ['homework', 'exam', 'attendance'], true)
        ? $in['category'] : 'homework';

    if ($code === '' || $label === '') json_fail('Thiếu mã hoặc tên.');
    if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $code)) {
        json_fail('Mã chỉ gồm chữ thường, số, gạch dưới, tối đa 16 ký tự, bắt đầu bằng chữ.');
    }

    // Kiểm tra trùng mã
    if (db_one('SELECT code FROM score_types WHERE code = ?', [$code])) {
        json_fail('Mã này đã tồn tại.');
    }

    // Lấy sort_order lớn nhất + 1
    $maxOrder = (int) (db_one('SELECT MAX(sort_order) as m FROM score_types')['m'] ?? 0);

    db_run('INSERT INTO score_types (code, label, short_label, weight, max_exams, category, sort_order)
            VALUES (?,?,?,?,?,?,?)',
        [$code, $label, $short ?: substr($label, 0, 3), $weight, $maxExams, $category, $maxOrder + 1]);

    log_action('tao', 'score_types', 'Thêm loại đầu điểm: ' . $label, '');
    Cache::flush();

    json_out(['ok' => true, 'code' => $code]);
}

// ============================================================
// PUT — cập nhật
// ============================================================

if ($action === 'PUT' && $code !== '') {
    $me = require_admin_or_bdh();

    $type = db_one('SELECT * FROM score_types WHERE code = ?', [$code]);
    if (!$type) json_fail('Không tìm thấy loại đầu điểm.', 404);

    $in = json_input();

    $label = trim((string) ($in['label'] ?? $type['label']));
    $short = trim((string) ($in['short_label'] ?? $type['short_label']));
    if ($short === '') $short = substr($label, 0, 3);

    $weight = max(1, (int) ($in['weight'] ?? $type['weight']));
    $maxExams = max(1, min(20, (int) ($in['max_exams'] ?? $type['max_exams'])));
    $category = in_array($in['category'] ?? '', ['homework', 'exam', 'attendance'], true)
        ? $in['category'] : $type['category'];

    db_run('UPDATE score_types
            SET label = ?, short_label = ?, weight = ?, max_exams = ?, category = ?
            WHERE code = ?',
        [$label, $short, $weight, $maxExams, $category, $code]);

    log_action('sudung', 'score_types', 'Sửa loại đầu điểm: ' . $code, '');
    Cache::flush();

    json_out(['ok' => true]);
}

// ============================================================
// DELETE — xoá
// ============================================================

if ($action === 'DELETE' && $code !== '') {
    $me = require_admin_or_bdh();

    $type = db_one('SELECT * FROM score_types WHERE code = ?', [$code]);
    if (!$type) json_fail('Không tìm thấy loại đầu điểm.', 404);

    // Kiểm tra có điểm đã nhập không
    $hasScores = db_one('SELECT COUNT(*) as c FROM scores WHERE type_code = ?', [$code]);
    if ($hasScores && $hasScores['c'] > 0) {
        json_fail('Loại đầu điểm này đã có điểm. Không xóa được.');
    }

    db_run('DELETE FROM score_types WHERE code = ?', [$code]);

    log_action('xoa', 'score_types', 'Xóa loại đầu điểm: ' . $code, '');
    Cache::flush();

    json_out(['ok' => true]);
}
