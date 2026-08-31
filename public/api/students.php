<?php
/**
 * DANH SÁCH THIẾU NHI
 *
 *   POST api/students.php?action=save   { id?, code, holyName, name, gender, ... , className, status }
 *   POST api/students.php?action=import { rows: [ {...}, ... ] }
 *
 * Lưu ý: thông tin bền của em nằm ở bảng students, còn lớp và tình
 * trạng nằm ở enrollments của niên khoá đang mở — nên mỗi lần lưu
 * phải chạm vào hai bảng, bọc trong một giao dịch.
 */

require __DIR__ . '/_bootstrap.php';

$me   = require_permission('students', 'edit');
$year = current_year();
if (!$year) json_fail('Chưa có niên khoá nào đang mở.', 409);
if ($year['status'] === 'đã khóa') json_fail('Niên khoá đã khoá sổ, không sửa được dữ liệu.', 409);

$yid    = (int) $year['id'];
$action = $_GET['action'] ?? '';
$in     = json_input();


/** Tra id lớp theo tên, báo lỗi rõ nếu không có */
function class_id_by_name(string $name): int
{
    $c = db_one('SELECT id FROM classes WHERE name = ?', [$name]);
    if (!$c) json_fail('Không tìm thấy lớp "' . $name . '".');
    return (int) $c['id'];
}

/** Chuẩn hoá một bản ghi em từ giao diện gửi lên */
function clean_student(array $s): array
{
    return [
        'code'        => trim((string) ($s['code'] ?? '')),
        'holyName'    => trim((string) ($s['holyName'] ?? '')),
        'name'        => trim((string) ($s['name'] ?? '')),
        'gender'      => (int) ($s['gender'] ?? 1) === 0 ? 0 : 1,
        'birthDate'   => ($s['birthDate'] ?? '') ?: null,
        'address'     => trim((string) ($s['address'] ?? '')),
        'fatherName'  => trim((string) ($s['fatherName'] ?? '')),
        'fatherPhone' => trim((string) ($s['fatherPhone'] ?? '')),
        'motherName'  => trim((string) ($s['motherName'] ?? '')),
        'motherPhone' => trim((string) ($s['motherPhone'] ?? '')),
        'className'   => trim((string) ($s['className'] ?? '')),
        'status'      => trim((string) ($s['status'] ?? 'đang sinh hoạt')),
    ];
}

/**
 * Ghi một em: cập nhật nếu đã có mã số, chèn mới nếu chưa.
 * Trả về id của em.
 */
function upsert_student(array $s, int $yid, int $actorId): int
{
    $classId = class_id_by_name($s['className']);
    $existing = db_one('SELECT id FROM students WHERE code = ?', [$s['code']]);

    if ($existing) {
        $sid = (int) $existing['id'];
        db_run('UPDATE students SET holy_name=?, full_name=?, gender=?, birth_date=?, address=?,
                       father_name=?, father_phone=?, mother_name=?, mother_phone=?
                 WHERE id=?',
            [$s['holyName'], $s['name'], $s['gender'], $s['birthDate'], $s['address'],
             $s['fatherName'], $s['fatherPhone'], $s['motherName'], $s['motherPhone'], $sid]);
    } else {
        $sid = db_insert('INSERT INTO students (code, holy_name, full_name, gender, birth_date, address,
                                                father_name, father_phone, mother_name, mother_phone)
                          VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$s['code'], $s['holyName'], $s['name'], $s['gender'], $s['birthDate'], $s['address'],
             $s['fatherName'], $s['fatherPhone'], $s['motherName'], $s['motherPhone']]);
    }

    // Ghi danh của năm nay: mỗi năm một em một lớp
    db_run('INSERT INTO enrollments (year_id, student_id, class_id, status)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), status = VALUES(status)',
        [$yid, $sid, $classId, $s['status']]);

    return $sid;
}

switch ($action) {

    // -------------------------------------------------------------
    case 'save':
        require_post();
        $s = clean_student($in);
        if ($s['code'] === '' || $s['name'] === '') json_fail('Thiếu mã số hoặc họ tên.');
        if ($s['className'] === '') json_fail('Vui lòng chọn lớp cho em.');

        // Chỉ được ghi vào lớp thuộc phạm vi mình phụ trách
        $chophep = allowed_class_ids($me);
        if ($chophep !== null && !in_array(class_id_by_name($s['className']), $chophep, true)) {
            json_fail('Bạn không phụ trách lớp "' . $s['className'] . '".'
                    . ' Bạn chỉ ghi được vào: ' . allowed_class_names($chophep) . '.', 403);
        }

        // Thêm mới thì mã PHẢI chưa tồn tại. upsert_student() lưu theo
        // mã số nên mã trùng sẽ ghi đè hồ sơ em khác. Máy khách đã chặn,
        // nhưng nó chỉ thấy các em trong phạm vi mình — một chủ nhiệm gõ
        // trúng mã của em lớp khác thì máy khách không biết.
        if (!empty($in['isNew']) && db_one('SELECT id FROM students WHERE code = ?', [$s['code']])) {
            json_fail('Mã số "' . $s['code'] . '" đã có người dùng. Vui lòng đặt mã khác.', 409);
        }

        db()->beginTransaction();
        try {
            $sid = upsert_student($s, $yid, (int) $me['id']);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không lưu được: '), 500);
        }

        log_action('sua', 'students', 'Sửa hồ sơ ' . $s['name'], $s['code'] . ' · ' . $s['className']);
        json_out(['ok' => true, 'id' => $sid]);

    // -------------------------------------------------------------
    case 'import':
        require_post();
        $rows = $in['rows'] ?? [];
        if (!is_array($rows) || count($rows) === 0) json_fail('Không có dòng nào để nhập.');

        $added = 0; $updated = 0; $skipped = 0; $errors = [];

        // Phạm vi ghi của người đang nhập. Kiểm từng dòng chứ không
        // tin cột "Lớp" trong file — file là do người dùng gửi lên.
        $chophep = allowed_class_ids($me);
        if ($chophep !== null && !$chophep) {
            json_fail('Bạn chưa được phân công lớp nào nên chưa nhập danh sách được.'
                    . ' Vui lòng liên hệ Ban Điều Hành.', 403);
        }

        db()->beginTransaction();
        try {
            foreach ($rows as $i => $raw) {
                $s = clean_student($raw);
                if ($s['code'] === '' || $s['name'] === '') { $skipped++; continue; }
                $lop = $s['className'] === '' ? null
                     : db_one('SELECT id FROM classes WHERE name=?', [$s['className']]);
                if (!$lop) {
                    $skipped++;
                    $errors[] = 'Dòng ' . ($i + 2) . ': lớp "' . $s['className'] . '" không tồn tại';
                    continue;
                }
                if ($chophep !== null && !in_array((int) $lop['id'], $chophep, true)) {
                    $skipped++;
                    $errors[] = 'Dòng ' . ($i + 2) . ': bạn không phụ trách lớp "'
                              . $s['className'] . '"';
                    continue;
                }
                $had = db_one('SELECT id FROM students WHERE code = ?', [$s['code']]);
                upsert_student($s, $yid, (int) $me['id']);
                $had ? $updated++ : $added++;
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Nhập thất bại, đã hoàn tác toàn bộ: '), 500);
        }

        log_action('tao', 'students', 'Nhập danh sách từ file',
                   'thêm ' . $added . ', cập nhật ' . $updated . ', bỏ qua ' . $skipped);

        json_out(['ok' => true, 'added' => $added, 'updated' => $updated,
                  'skipped' => $skipped, 'errors' => array_slice($errors, 0, 10),
                  'scope' => allowed_class_names($chophep)]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
