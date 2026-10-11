<?php
/**
 * ĐIỂM SỐ — API endpoint
 *
 * Actions:
 *   GET  ?action=exams      — lấy danh sách bài kiểm tra của lớp + học kỳ
 *   POST ?action=exam       — tạo bài kiểm tra mới
 *   DELETE ?action=exam     — xóa bài kiểm tra
 *   POST ?action=set        — lưu/xóa điểm của một bài
 *
 * Lưu ý: ô trống (value rỗng) = XOÁ điểm, không phải chấm 0.
 */

require __DIR__ . '/_bootstrap.php';

$me    = require_permission('scores', 'view');  // view permission cho tất cả
$year  = current_year();
$method = $_SERVER['REQUEST_METHOD'];
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa' && $method !== 'GET') json_fail('Niên khoá đã khoá sổ, không sửa điểm được.', 409);

$in     = json_input();
$action = $in['action'] ?? ($_GET['action'] ?? '');

// =====================================================================
//  Helper: kiểm tra quyền truy cập lớp
// =====================================================================
function check_class_access(int $classId, string $perm = 'edit'): void
{
    global $me;
    if (!can_access_class($me, 'scores', $classId, $perm)) {
        json_fail('Bạn không phụ trách lớp này.', 403);
    }
}

/**
 * Lấy term_id + class_id của một student trong niên khoá hiện tại.
 * @return array{term_id:int, class_id:int}|null
 */
function get_student_scope(int $studentId): ?array
{
    global $year;
    $enr = db_one(
        'SELECT class_id FROM enrollments WHERE year_id=? AND student_id=?',
        [$year['id'], $studentId]
    );
    if (!$enr) return null;
    return ['class_id' => (int) $enr['class_id']];
}

// =====================================================================
//  GET ?action=types — danh sách loại điểm (để frontend không hardcode)
// =====================================================================
if ($method === 'GET' && $action === 'types') {
    $types = db_all('SELECT code AS `key`, label, short_label AS short, weight FROM score_types ORDER BY sort_order, code');
    json_out(['ok' => true, 'types' => $types]);
}

// =====================================================================
//  GET ?action=exams — danh sách bài kiểm tra
// =====================================================================
if ($method === 'GET' && $action === 'exams') {
    $termId  = (int) ($_GET['termId'] ?? 0);
    $classId = (int) ($_GET['classId'] ?? 0);

    if (!$termId) json_fail('Thiếu termId.');

    // Kiểm tra học kỳ thuộc niên khoá
    if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
        json_fail('Học kỳ không hợp lệ.', 400);
    }

    // Quyền xem
    if ($classId > 0) {
        check_class_access($classId, 'view');
    } else {
        // Lấy danh sách lớp được phép xem
        $allowed = accessible_class_ids($me, 'scores', 'view');
        if ($allowed !== null && empty($allowed)) {
            json_fail('Bạn chưa được phân công lớp nào.', 403);
        }
    }

    // Lấy exams theo term, join exam_name nếu có
    if ($classId > 0) {
        // Lấy exams của term đó, join điểm của học sinh lớp
        $exams = db_all(
            "SELECT e.id, e.type_code, e.name, e.exam_date,
                    st.label, st.short_label, st.weight,
                    COUNT(sc.id) AS score_count
               FROM score_exams e
               JOIN score_types st ON st.code = e.type_code
          LEFT JOIN scores sc ON sc.exam_id = e.id
          LEFT JOIN enrollments enr ON enr.student_id = sc.student_id AND enr.year_id = e.year_id AND enr.class_id = ?
              WHERE e.term_id = ?
              GROUP BY e.id
              ORDER BY st.sort_order, e.exam_date, e.id",
            [$classId, $termId]
        );
    } else {
        // Toàn đoàn: lấy exams không join class
        $exams = db_all(
            "SELECT e.id, e.type_code, e.name, e.exam_date,
                    st.label, st.short_label, st.weight,
                    (SELECT COUNT(*) FROM scores WHERE exam_id = e.id) AS score_count
               FROM score_exams e
               JOIN score_types st ON st.code = e.type_code
              WHERE e.term_id = ?
              ORDER BY st.sort_order, e.exam_date, e.id",
            [$termId]
        );
    }

    // Nhóm theo loại điểm để giao diện dễ hiển thị
    $byType = [];
    foreach ($exams as $e) {
        $tc = $e['type_code'];
        if (!isset($byType[$tc])) {
            $byType[$tc] = [
                'code'   => $tc,
                'label'  => $e['label'],
                'short'  => $e['short_label'],
                'weight' => (int) $e['weight'],
                'exams'  => [],
            ];
        }
        $byType[$tc]['exams'][] = [
            'id'         => (int) $e['id'],
            'name'       => $e['name'],
            'examDate'   => $e['exam_date'],
            'scoreCount' => (int) $e['score_count'],
        ];
    }

    json_out(['ok' => true, 'byType' => array_values($byType)]);
}

// =====================================================================
//  DELETE ?action=exam — xóa bài kiểm tra (check TRƯỚC POST exam)
// =====================================================================
if (($method === 'DELETE' && $action === 'exam') || ($method === 'POST' && $action === 'exam' && ($in['_method'] ?? '') === 'DELETE')) {
    require_write();
    $me = require_permission('scores', 'edit');
    $examId = (int) ($in['examId'] ?? 0);
    if (!$examId) json_fail('Thiếu examId.');

    // Lấy exam để kiểm tra quyền
    $exam = db_one('SELECT * FROM score_exams WHERE id=?', [$examId]);
    if (!$exam) json_fail('Bài kiểm tra không tồn tại.', 404);
    if ((int) $exam['year_id'] !== $year['id']) {
        json_fail('Bài kiểm tra không thuộc niên khoá hiện tại.', 400);
    }

    // Chỉ người tạo exam hoặc admin mới được xóa
    $isCreator = (int) ($exam['created_by'] ?? 0) === (int) ($me['id'] ?? 0);
    $isAdmin = ($me['role_code'] ?? '') === 'admin';
    if (!$isCreator && !$isAdmin) {
        json_fail('Chỉ người tạo bài kiểm tra này hoặc Quản trị mới được xóa.', 403);
    }

    // Kiểm tra bài đã có điểm chưa — nếu có thì không cho xóa
    $hasScores = db_one('SELECT 1 FROM scores WHERE exam_id=? LIMIT 1', [$examId]);
    if ($hasScores) {
        json_fail('Bài đã có điểm. Hãy xóa điểm trước.', 409);
    }

    db_run('DELETE FROM score_exams WHERE id=?', [$examId]);
    Cache::flush();
    json_out(['ok' => true]);
}

// =====================================================================
//  POST ?action=exam — tạo bài kiểm tra mới (chỉ khi không phải DELETE)
// =====================================================================
if ($method === 'POST' && $action === 'exam' && ($in['_method'] ?? '') !== 'DELETE') {
    require_write();
    $me = require_permission('scores', 'edit');
    $termId  = (int) ($in['termId'] ?? 0);
    $typeCode = trim((string) ($in['typeCode'] ?? ''));
    $name    = trim((string) ($in['name'] ?? ''));
    $examDate = $in['examDate'] ?? null;

    if (!$termId || $typeCode === '') json_fail('Thiếu termId hoặc typeCode.');

    // Học kỳ phải thuộc niên khoá
    if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
        json_fail('Học kỳ không thuộc niên khoá đang mở.', 400);
    }
    if (!db_one('SELECT code FROM score_types WHERE code=?', [$typeCode])) {
        json_fail('Loại điểm không hợp lệ.');
    }

    // Nếu có examDate, validate định dạng
    if ($examDate !== null && $examDate !== '') {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $examDate)) {
            json_fail('Ngày thi không hợp lệ. Dùng định dạng YYYY-MM-DD.');
        }
    } else {
        $examDate = null;
    }

    // Tạo exam
    $examId = db_insert(
        'INSERT INTO score_exams (year_id, term_id, type_code, name, exam_date, created_by)
         VALUES (?,?,?,?,?,?)',
        [$year['id'], $termId, $typeCode, $name ?: '', $examDate, $me['id']]
    );

    Cache::flush();
    json_out([
        'ok'       => true,
        'examId'   => $examId,
        'name'     => $name ?: '',
        'examDate' => $examDate,
    ]);
}

// =====================================================================
//  POST ?action=set — lưu điểm (giữ nguyên logic cũ, thêm exam_id)
// =====================================================================
if ($method === 'POST' && $action === 'set') {
    require_write();
    $me = require_permission('scores', 'edit');
    $studentId = (int) ($in['studentId'] ?? 0);
    $termId    = (int) ($in['termId'] ?? 0);
    $type      = (string) ($in['type'] ?? '');
    $examId    = (int) ($in['examId'] ?? 0);
    $raw       = trim((string) ($in['value'] ?? ''));

    if (!$studentId || !$termId) json_fail('Thiếu studentId hoặc termId.');
    if ($type === '' && $examId === 0) json_fail('Thiếu type hoặc examId.');

    // Học kỳ phải thuộc niên khoá
    if (!db_one('SELECT id FROM terms WHERE id=? AND year_id=?', [$termId, $year['id']])) {
        json_fail('Học kỳ không thuộc niên khoá đang mở.', 409);
    }

    // Kiểm tra exam hợp lệ (nếu dùng examId)
    if ($examId > 0) {
        $exam = db_one('SELECT * FROM score_exams WHERE id=? AND term_id=?', [$examId, $termId]);
        if (!$exam) json_fail('Bài kiểm tra không tồn tại hoặc không thuộc học kỳ này.', 400);
        $type = $exam['type_code']; // Lấy type_code từ exam
    } else {
        // Dùng type cũ: kiểm tra type hợp lệ
        if (!db_one('SELECT code FROM score_types WHERE code=?', [$type])) {
            json_fail('Loại điểm không hợp lệ.');
        }
        // Tìm hoặc tạo exam ngầm định cho (term_id, type)
        $exam = db_one(
            'SELECT id FROM score_exams WHERE term_id=? AND type_code=? ORDER BY id ASC LIMIT 1',
            [$termId, $type]
        );
        if (!$exam) {
            // Tạo exam ngầm nếu chưa có
            $examId = db_insert(
                'INSERT INTO score_exams (year_id, term_id, type_code, name, created_by) VALUES (?,?,?,?,?)',
                [$year['id'], $termId, $type, '', $me['id']]
            );
        } else {
            $examId = (int) $exam['id'];
        }
    }

    // Sinh viên phải thuộc niên khoá
    $scope = get_student_scope($studentId);
    if (!$scope) {
        json_fail('Em này không có trong danh sách năm nay.', 404);
    }

    // Kiểm tra quyền
    check_class_access($scope['class_id'], 'edit');

    // Ô trống = xóa điểm
    if ($raw === '') {
        db_run('DELETE FROM scores WHERE exam_id=? AND student_id=?', [$examId, $studentId]);
        Cache::flush();
        json_out(['ok' => true, 'removed' => true]);
    }

    $value = (float) str_replace(',', '.', $raw);
    if (!is_numeric(str_replace(',', '.', $raw)) || $value < 0 || $value > 10) {
        json_fail('Điểm phải là số từ 0 đến 10.');
    }
    $value = round($value, 1);

    db_run('INSERT INTO scores (exam_id, student_id, term_id, type_code, value, updated_by)
            VALUES (?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)',
        [$examId, $studentId, $termId, $type, $value, $me['id']]);

    Cache::flush();
    json_out(['ok' => true, 'removed' => false, 'value' => $value]);
}

json_fail('Action không hợp lệ.', 400);
