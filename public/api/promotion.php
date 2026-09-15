<?php
/**
 * LÊN LỚP CUỐI NĂM
 *
 *   POST api/promotion.php?action=run { block, targetYearId, results: { studentId: 'len'|'olai' } }
 *
 * Khác hẳn bản chạy thử: KHÔNG ghi đè lớp của em trong năm cũ, mà
 * TẠO BẢN GHI GHI DANH cho niên khoá mới. Nhờ vậy lịch sử học của em
 * còn nguyên — năm ngoái học lớp nào, kết quả ra sao, tra lại được hết.
 */

require __DIR__ . '/_bootstrap.php';

require_write();  // hành động ghi: bắt buộc POST + CSRF
$me   = require_permission('promotion', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);

$in     = json_input();
$block  = trim((string) ($in['block'] ?? ''));
$target = (int) ($in['targetYearId'] ?? 0);
$results = $in['results'] ?? [];
if (!is_array($results)) $results = [];

if ($block === '') json_fail('Vui lòng chọn khối.');
if (!$target)      json_fail('Vui lòng chọn niên khoá đích để chuyển các em sang.');
if ($target === (int) $year['id']) {
    json_fail('Niên khoá đích phải khác niên khoá hiện tại. Hãy mở niên khoá mới trước.');
}

$ty = db_one('SELECT * FROM school_years WHERE id=?', [$target]);
if (!$ty) json_fail('Không tìm thấy niên khoá đích.', 404);
if ($ty['status'] === 'đã khóa') json_fail('Niên khoá đích đã khoá sổ.');

$b = db_one('SELECT id FROM blocks WHERE name=?', [$block]);
if (!$b) json_fail('Không tìm thấy khối.', 404);

// Các em đang sinh hoạt trong khối, kèm lớp và sơ đồ lên lớp
$rows = db_all(
    'SELECT e.student_id, e.class_id, s.full_name, c.name AS class_name,
            c.next_class_id, c.is_final, n.block_id AS next_block
       FROM enrollments e
       JOIN students s ON s.id = e.student_id
       JOIN classes  c ON c.id = e.class_id
       LEFT JOIN classes n ON n.id = c.next_class_id
      WHERE e.year_id = ? AND c.block_id = ? AND e.status = ?',
    [$year['id'], $b['id'], 'đang sinh hoạt']);

if (count($rows) === 0) json_fail('Khối này không có em nào đang sinh hoạt.');

// Lớp chưa khai sơ đồ thì chặn ngay, không chuyển nửa vời
$thieu = [];
foreach ($rows as $r) {
    if (!$r['is_final'] && !$r['next_class_id']) $thieu[$r['class_name']] = true;
}
if ($thieu) json_fail('Còn lớp chưa khai báo lớp kế tiếp: ' . implode(', ', array_keys($thieu)));

$up = 0; $stay = 0; $graduate = 0;

db()->beginTransaction();
try {
    foreach ($rows as $r) {
        $sid     = (int) $r['student_id'];
        $verdict = $results[$sid] ?? $results[(string) $sid] ?? 'olai';

        if ($verdict !== 'len') {
            // Ở lại: ghi danh năm mới vào ĐÚNG LỚP CŨ
            db_run('INSERT INTO enrollments (year_id, student_id, class_id, status, year_result)
                    VALUES (?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), year_result=VALUES(year_result)',
                   [$target, $sid, $r['class_id'], 'đang sinh hoạt', 'ở lại']);
            db_run("UPDATE enrollments SET year_result='ở lại' WHERE year_id=? AND student_id=?",
                   [$year['id'], $sid]);
            $stay++;
            continue;
        }

        if ($r['is_final']) {
            // Ra trường: đóng lại ở năm cũ, không ghi danh năm mới
            db_run("UPDATE enrollments SET status='đã ra trường', year_result='ra trường'
                     WHERE year_id=? AND student_id=?", [$year['id'], $sid]);
            $graduate++;
            continue;
        }

        db_run('INSERT INTO enrollments (year_id, student_id, class_id, status, year_result)
                VALUES (?,?,?,?,?)
                ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), year_result=VALUES(year_result)',
               [$target, $sid, (int) $r['next_class_id'], 'đang sinh hoạt', 'chưa xét']);
        db_run("UPDATE enrollments SET year_result='lên lớp' WHERE year_id=? AND student_id=?",
               [$year['id'], $sid]);
        $up++;
    }
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    json_fail(safe_error($e, 'Chuyển lớp thất bại, đã hoàn tác toàn bộ: '), 500);
}

log_action('sua', 'promotion', 'Lên lớp cuối năm khối ' . $block,
           $up . ' lên lớp · ' . $stay . ' ở lại · ' . $graduate . ' ra trường → ' . $ty['name']);

json_out([
    'ok' => true, 'up' => $up, 'stay' => $stay, 'graduate' => $graduate,
    'targetYear' => $ty['name'], 'at' => date('Y-m-d H:i'),
]);
