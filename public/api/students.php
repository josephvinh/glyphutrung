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
    $holyName = preg_replace('/\s+/', ' ', trim((string) ($s['holyName'] ?? '')));
    $name = preg_replace('/\s+/', ' ', trim((string) ($s['name'] ?? '')));
    $fatherName = preg_replace('/\s+/', ' ', trim((string) ($s['fatherName'] ?? '')));
    $motherName = preg_replace('/\s+/', ' ', trim((string) ($s['motherName'] ?? '')));
    $address = preg_replace('/\s+/', ' ', trim((string) ($s['address'] ?? '')));

    $holyName = mb_strtoupper($holyName, 'UTF-8');
    $name = mb_strtoupper($name, 'UTF-8');
    $fatherName = mb_convert_case($fatherName, MB_CASE_TITLE, 'UTF-8');
    $motherName = mb_convert_case($motherName, MB_CASE_TITLE, 'UTF-8');

    $clean_phone = function($phone) {
        $p = preg_replace('/[^\d]/', '', $phone);
        if ($p === '') return '';
        if (strpos($p, '84') === 0 && strlen($p) >= 11) $p = '0' . substr($p, 2);
        if ($p[0] !== '0') $p = '0' . $p;
        return $p;
    };

    return [
        'code'        => trim((string) ($s['code'] ?? '')),
        'holyName'    => $holyName,
        'name'        => $name,
        'gender'      => (int) ($s['gender'] ?? 1) === 0 ? 0 : 1,
        'birthDate'   => ($s['birthDate'] ?? '') ?: null,
        'address'     => $address,
        'fatherName'  => $fatherName,
        'fatherPhone' => $clean_phone((string) ($s['fatherPhone'] ?? '')),
        'motherName'  => $motherName,
        'motherPhone' => $clean_phone((string) ($s['motherPhone'] ?? '')),
        'className'   => trim((string) ($s['className'] ?? '')),
        'status'      => trim((string) ($s['status'] ?? 'đang sinh hoạt')),
    ];
}

/**
 * Ghi một em: cập nhật nếu đã có mã số, chèn mới nếu chưa.
 * Trả về id của em.
 */
function upsert_student(array $s, int $yid, int $classId, ?int $sid = null): int
{
    if ($sid) {
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
    // Xem trước mã kế tiếp cho em mới (client điền sẵn vào ô mã, chỉ đọc).
    case 'next_code':
        json_out(['ok' => true, 'code' => next_student_code(year_two_digit($year))]);
        break;

    // -------------------------------------------------------------
    case 'save':
        require_write();
        $s = clean_student($in);
        if ($s['name'] === '') json_fail('Thiếu họ tên.');
        if ($s['className'] === '') json_fail('Vui lòng chọn lớp cho em.');

        // Thêm mới: MÁY CHỦ tự cấp mã (GDGLPT + năm nhập + số thứ tự) — không
        // tin mã do client gửi, để chắc chắn duy nhất toàn đoàn, hết đụng độ.
        // Sửa hồ sơ thì giữ nguyên mã cũ (mã bền theo em, đổi là hỏng thẻ QR).
        if (!empty($in['isNew'])) {
            $s['code'] = next_student_code(year_two_digit($year));
        } elseif ($s['code'] === '') {
            json_fail('Thiếu mã số.');
        }

        $classId = class_id_by_name($s['className']);
        // Chỉ được ghi vào lớp thuộc phạm vi mình phụ trách
        $chophep = allowed_class_ids($me);
        if ($chophep !== null && !in_array($classId, $chophep, true)) {
            json_fail('Bạn không phụ trách lớp "' . $s['className'] . '".'
                    . ' Bạn chỉ ghi được vào: ' . allowed_class_names($chophep) . '.', 403);
        }

        db()->beginTransaction();
        try {
            $existing = db_one('SELECT id FROM students WHERE code = ?', [$s['code']]);
            $sid = upsert_student($s, $yid, $classId, $existing ? (int) $existing['id'] : null);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Không lưu được: '), 500);
        }

        log_action('sua', 'students', 'Sửa hồ sơ ' . $s['name'], $s['code'] . ' · ' . $s['className']);
        Cache::flush();
        json_out(['ok' => true, 'id' => $sid]);
        break;

    // -------------------------------------------------------------
    case 'import':
        require_write();
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

        $classMap = [];
        $res = db_run('SELECT id, name FROM classes')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) $classMap[$r['name']] = (int) $r['id'];

        $existingCodes = [];
        $res = db_run('SELECT code, id FROM students')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($res as $r) $existingCodes[$r['code']] = (int) $r['id'];

        $year2 = year_two_digit($year);
        $prefix = STUDENT_CODE_PREFIX . sprintf('%02d', $year2);
        $row = db_one(
            "SELECT MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS mx
               FROM students WHERE code LIKE ?",
            [strlen($prefix) + 1, $prefix . '%']
        );
        $nextNum = ((int) ($row['mx'] ?? 0)) + 1;

        $upsert_students = [];
        $upsert_params = [];
        $enrollment_data = [];

        db()->beginTransaction();
        try {
            foreach ($rows as $i => $raw) {
                $s = clean_student($raw);
                if ($s['name'] === '') { $skipped++; continue; }
                if ($s['code'] === '') {
                    $s['code'] = $prefix . sprintf('%04d', $nextNum);
                    $nextNum++;
                }

                $lopId = $classMap[$s['className']] ?? null;
                if (!$lopId) {
                    $skipped++;
                    $errors[] = 'Dòng ' . ($i + 2) . ': lớp "' . $s['className'] . '" không tồn tại';
                    continue;
                }
                if ($chophep !== null && !in_array($lopId, $chophep, true)) {
                    $skipped++;
                    $errors[] = 'Dòng ' . ($i + 2) . ': bạn không phụ trách lớp "' . $s['className'] . '"';
                    continue;
                }

                $sid = $existingCodes[$s['code']] ?? null;
                $upsert_students[] = '(?,?,?,?,?,?,?,?,?,?,?)';
                array_push($upsert_params,
                    $sid, $s['code'], $s['holyName'], $s['name'], $s['gender'], $s['birthDate'], $s['address'],
                    $s['fatherName'], $s['fatherPhone'], $s['motherName'], $s['motherPhone']
                );
                
                $enrollment_data[] = [
                    'code' => $s['code'],
                    'class_id' => $lopId,
                    'status' => $s['status']
                ];

                if (!$sid) {
                    $existingCodes[$s['code']] = -1; // temp mark to avoid duplicate counts if duplicate in same batch
                    $added++;
                } else {
                    $updated++;
                }
            }
            
            if ($upsert_students) {
                // Break into chunks of 500 to avoid packet size issues if file is huge
                $chunk_size = 500;
                $total_students = count($upsert_students);
                for ($ci = 0; $ci < $total_students; $ci += $chunk_size) {
                    $chunk_upsert = array_slice($upsert_students, $ci, $chunk_size);
                    $chunk_params = array_slice($upsert_params, $ci * 11, $chunk_size * 11);
                    $sql = 'INSERT INTO students (id, code, holy_name, full_name, gender, birth_date, address, father_name, father_phone, mother_name, mother_phone) VALUES ' 
                         . implode(',', $chunk_upsert) 
                         . ' ON DUPLICATE KEY UPDATE holy_name=VALUES(holy_name), full_name=VALUES(full_name), gender=VALUES(gender), birth_date=VALUES(birth_date), address=VALUES(address), father_name=VALUES(father_name), father_phone=VALUES(father_phone), mother_name=VALUES(mother_name), mother_phone=VALUES(mother_phone)';
                    db_run($sql, $chunk_params);
                }

                $res = db_run('SELECT code, id FROM students')->fetchAll(PDO::FETCH_ASSOC);
                $finalCodes = [];
                foreach ($res as $r) $finalCodes[$r['code']] = (int) $r['id'];

                $upsert_enrollments = [];
                $enroll_params = [];
                foreach ($enrollment_data as $e) {
                    $sid = $finalCodes[$e['code']] ?? null;
                    if ($sid) {
                        $upsert_enrollments[] = '(?,?,?,?)';
                        array_push($enroll_params, $yid, $sid, $e['class_id'], $e['status']);
                    }
                }
                
                $total_enrolls = count($upsert_enrollments);
                for ($ci = 0; $ci < $total_enrolls; $ci += $chunk_size) {
                    $chunk_upsert = array_slice($upsert_enrollments, $ci, $chunk_size);
                    $chunk_params = array_slice($enroll_params, $ci * 4, $chunk_size * 4);
                    $sql_e = 'INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES '
                           . implode(',', $chunk_upsert)
                           . ' ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), status=VALUES(status)';
                    db_run($sql_e, $chunk_params);
                }
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Nhập thất bại, đã hoàn tác toàn bộ: '), 500);
        }

        log_action('tao', 'students', 'Nhập danh sách từ file',
                   'thêm ' . $added . ', cập nhật ' . $updated . ', bỏ qua ' . $skipped);
        Cache::flush();

        json_out(['ok' => true, 'added' => $added, 'updated' => $updated,
                  'skipped' => $skipped, 'errors' => array_slice($errors, 0, 10),
                  'scope' => allowed_class_names($chophep)]);

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
