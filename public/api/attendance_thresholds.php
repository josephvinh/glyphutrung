<?php
/**
 * QUẢN LÝ NGƯỠNG CHUYÊN CẦN
 *
 *   GET    api/attendance_thresholds.php
 *   POST   api/attendance_thresholds.php  { label, min_percent, description }
 *   PUT    api/attendance_thresholds.php?id=x  { label, min_percent, description }
 *   DELETE api/attendance_thresholds.php?id=x
 */

require __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

if ($action === 'GET' && $id === 0) {
    $thresholds = db_all('SELECT id, sort_order, label, min_percent, description
                         FROM attendance_thresholds ORDER BY sort_order DESC');
    json_out($thresholds);
}

// ============================================================
// GET — lấy 1 mốc
// ============================================================

if ($action === 'GET' && $id > 0) {
    $t = db_one('SELECT id, sort_order, label, min_percent, description
                 FROM attendance_thresholds WHERE id = ?', [$id]);
    if (!$t) json_fail('Không tìm thấy mốc.', 404);
    json_out($t);
}

// ============================================================
// POST — thêm mới
// ============================================================

if ($action === 'POST') {
    $me = require_admin_or_bdh();
    $in = json_input();

    $label = trim((string) ($in['label'] ?? ''));
    $minPercent = max(0, min(100, (float) ($in['min_percent'] ?? 0)));
    $description = trim((string) ($in['description'] ?? ''));

    if ($label === '') json_fail('Thiếu nhãn.');
    if ($minPercent < 0 || $minPercent > 100) json_fail('Ngưỡng phần trăm phải từ 0 đến 100.');

    // Lấy sort_order lớn nhất + 1 (để thứ tự đúng)
    $maxOrder = (int) (db_one('SELECT MAX(sort_order) as m FROM attendance_thresholds')['m'] ?? 0);

    db_run('INSERT INTO attendance_thresholds (sort_order, label, min_percent, description)
            VALUES (?,?,?,?)',
        [$maxOrder + 1, $label, $minPercent, $description ?: null]);

    log_action('tao', 'attendance_thresholds', 'Thêm mốc chuyên cần: ' . $label,
        'min: ' . $minPercent . '%');
    Cache::flush();

    json_out(['ok' => true]);
}

// ============================================================
// PUT — cập nhật
// ============================================================

if ($action === 'PUT' && $id > 0) {
    $me = require_admin_or_bdh();

    $t = db_one('SELECT * FROM attendance_thresholds WHERE id = ?', [$id]);
    if (!$t) json_fail('Không tìm thấy mốc.', 404);

    $in = json_input();

    $label = trim((string) ($in['label'] ?? $t['label']));
    $minPercent = max(0, min(100, (float) ($in['min_percent'] ?? $t['min_percent'])));
    $description = trim((string) ($in['description'] ?? $t['description']));

    if ($label === '') json_fail('Nhãn không được trống.');

    db_run('UPDATE attendance_thresholds
            SET label = ?, min_percent = ?, description = ?
            WHERE id = ?',
        [$label, $minPercent, $description ?: null, $id]);

    log_action('sudung', 'attendance_thresholds', 'Sửa mốc chuyên cần: ' . $label, '');
    Cache::flush();

    json_out(['ok' => true]);
}

// ============================================================
// DELETE — xoá
// ============================================================

if ($action === 'DELETE' && $id > 0) {
    $me = require_admin_or_bdh();

    $t = db_one('SELECT * FROM attendance_thresholds WHERE id = ?', [$id]);
    if (!$t) json_fail('Không tìm thấy mốc.', 404);

    db_run('DELETE FROM attendance_thresholds WHERE id = ?', [$id]);

    log_action('xoa', 'attendance_thresholds', 'Xóa mốc chuyên cần: ' . $t['label'], '');
    Cache::flush();

    json_out(['ok' => true]);
}
