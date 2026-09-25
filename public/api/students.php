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

/**
 * Lớp hiện tại của em trong niên khoá đang mở (null nếu chưa ghi danh).
 * Dùng để chặn IDOR: người dùng phải phủ được LỚP NGUỒN thì mới sửa/chuyển
 * được em — không chỉ lớp đích.
 */
function current_enrollment_class(int $studentId, int $yid): ?int
{
    $r = db_one(
        'SELECT class_id FROM enrollments WHERE year_id = ? AND student_id = ?',
        [$yid, $studentId]
    );
    return $r ? (int) $r['class_id'] : null;
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

        $existing = db_one('SELECT id FROM students WHERE code = ?', [$s['code']]);
        // Chống IDOR: sửa một em ĐÃ TỒN TẠI (không phải thêm mới) thì người phạm
        // vi hẹp phải phủ được lớp NGUỒN của em. Chỉ kiểm lớp đích là chưa đủ —
        // nếu không, người phụ trách lớp A có thể "bắt" em bất kỳ (mã tuần tự dễ
        // đoán) sang lớp mình và ghi đè hồ sơ. FAIL-CLOSED: em chưa ghi danh
        // niên khoá này (curClass = null) → người phạm vi hẹp cũng KHÔNG chứng
        // minh được mình phụ trách, nên từ chối; chỉ toàn đoàn (admin/BĐH) mới
        // re-enroll/sửa được. Người tạo em MỚI dùng isNew → mã do máy chủ cấp,
        // $existing = null nên không vướng nhánh này.
        if ($existing && $chophep !== null) {   // $chophep null = toàn đoàn
            $curClass = current_enrollment_class((int) $existing['id'], $yid);
            if ($curClass === null || !can_access_class($me, 'students', $curClass, 'edit')) {
                json_fail('Em này không thuộc lớp bạn phụ trách (hoặc chưa ghi danh '
                        . 'niên khoá hiện tại). Bạn không thể sửa hồ sơ hay chuyển em. '
                        . 'Vui lòng liên hệ Ban Điều Hành để xếp lớp.', 403);
            }
        }

        db()->beginTransaction();
        try {
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

        // Lấy ánh xạ lớp: tên → id
        $classMap = [];
        foreach (db_all('SELECT id, name FROM classes') as $r) {
            $classMap[$r['name']] = (int) $r['id'];
        }

        // Chỉ lấy mã học sinh ĐÃ CÓ trong CSDL để phân biệt thêm mới / cập nhật.
        // KHÔNG lấy toàn bộ học sinh — chỉ cần code → id, và chỉ cần từ niên khoá
        // hiện tại (vì mã số gắn với năm nhập học).
        // LEFT JOIN (không phải JOIN): phải bắt được CẢ em đã tồn tại nhưng CHƯA
        // ghi danh niên khoá này — nếu không, em đó bị coi như "mới", và
        // INSERT ... ON DUPLICATE KEY UPDATE (mã là UNIQUE) sẽ ghi đè hồ sơ của
        // em ngoài phạm vi. class_id = null nghĩa là chưa ghi danh năm nay.
        $existingCodes = [];
        $existingClass = [];   // code → class_id hiện tại (null = chưa ghi danh năm nay)
        foreach (db_all(
            "SELECT s.code, s.id, e.class_id FROM students s
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?",
            [$yid]
        ) as $r) {
            $existingCodes[$r['code']] = (int) $r['id'];
            $existingClass[$r['code']] = $r['class_id'] !== null ? (int) $r['class_id'] : null;
        }

        $year2 = year_two_digit($year);
        $prefix = STUDENT_CODE_PREFIX . sprintf('%02d', $year2);
        $row = db_one(
            "SELECT MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS mx
               FROM students WHERE code LIKE ?",
            [strlen($prefix) + 1, $prefix . '%']
        );
        $nextNum = ((int) ($row['mx'] ?? 0)) + 1;

        // Chuẩn bị dữ liệu trước transaction để không giữ lock quá lâu
        $upsert_students  = [];
        $upsert_params    = [];
        $enrollment_batch = [];

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

            // Chống IDOR (như action save): với em ĐÃ TỒN TẠI, người phạm vi hẹp
            // ($chophep !== null) phải phủ được lớp NGUỒN mới được cập nhật/chuyển.
            // FAIL-CLOSED: chưa ghi danh năm nay (curClass = null) cũng bị bỏ qua
            // cho người phạm vi hẹp — chỉ toàn đoàn mới re-enroll qua file.
            if ($sid && $chophep !== null) {
                $curClass = $existingClass[$s['code']] ?? null;
                if ($curClass === null || !can_access_class($me, 'students', $curClass, 'edit')) {
                    $skipped++;
                    $errors[] = 'Dòng ' . ($i + 2) . ': em "' . $s['code']
                              . '" không thuộc lớp bạn phụ trách (hoặc chưa ghi danh năm nay), đã bỏ qua';
                    continue;
                }
            }

            $upsert_students[] = '(?,?,?,?,?,?,?,?,?,?,?)';
            array_push($upsert_params,
                $sid, $s['code'], $s['holyName'], $s['name'], $s['gender'], $s['birthDate'], $s['address'],
                $s['fatherName'], $s['fatherPhone'], $s['motherName'], $s['motherPhone']
            );

            // enrollment_batch dùng mã để tra sau INSERT thành công
            $enrollment_batch[] = [
                'code'    => $s['code'],
                'class_id'=> $lopId,
                'status'  => $s['status'],
            ];

            if (!$sid) {
                $existingCodes[$s['code']] = -1; // đánh dấu tạm để tránh đếm trùng trong cùng lô
                $added++;
            } else {
                $updated++;
            }
        }

        if (!$upsert_students) {
            json_out(['ok' => true, 'added' => 0, 'updated' => 0,
                      'skipped' => $skipped, 'errors' => array_slice($errors, 0, 10),
                      'scope' => allowed_class_names($chophep)]);
            break;
        }

        db()->beginTransaction();
        try {
            // Chèn / cập nhật học sinh theo lô 500 dòng mỗi lần
            $chunk_size = 500;
            $chunks = array_chunk($upsert_students, $chunk_size);
            $param_chunks = [];
            for ($ci = 0; $ci < count($upsert_students); $ci += $chunk_size) {
                $param_chunks[] = array_slice($upsert_params, $ci * 11, $chunk_size * 11);
            }
            foreach ($chunks as $ki => $chunk) {
                $sql = 'INSERT INTO students (id, code, holy_name, full_name, gender, birth_date, address, father_name, father_phone, mother_name, mother_phone) VALUES '
                     . implode(',', $chunk)
                     . ' ON DUPLICATE KEY UPDATE holy_name=VALUES(holy_name), full_name=VALUES(full_name), gender=VALUES(gender), birth_date=VALUES(birth_date), address=VALUES(address), father_name=VALUES(father_name), father_phone=VALUES(father_phone), mother_name=VALUES(mother_name), mother_phone=VALUES(mother_phone)';
                db_run($sql, $param_chunks[$ki]);
            }

            // Sau khi students đã lưu: lấy lại id mới sinh cho các em mới
            // (chỉ cần cho các em thêm mới — em cũ đã có trong $existingCodes)
            $new_codes = [];
            foreach ($enrollment_batch as $e) {
                if (($existingCodes[$e['code']] ?? -1) === -1) {
                    $new_codes[] = $e['code'];
                }
            }

            $new_ids = [];
            if ($new_codes) {
                $ph = implode(',', array_fill(0, count($new_codes), '?'));
                foreach (db_all(
                    "SELECT id, code FROM students WHERE code IN ($ph)",
                    $new_codes
                ) as $r) {
                    $new_ids[$r['code']] = (int) $r['id'];
                    $existingCodes[$r['code']] = (int) $r['id'];
                }
            }

            // Chèn enrollments
            $enroll_rows = [];
            $enroll_params = [];
            foreach ($enrollment_batch as $e) {
                $sid = $existingCodes[$e['code']] ?? null;
                if ($sid && $sid > 0) {
                    $enroll_rows[] = '(?,?,?,?)';
                    array_push($enroll_params, $yid, $sid, $e['class_id'], $e['status']);
                }
            }

            if ($enroll_rows) {
                $ec = count($enroll_rows);
                for ($ci = 0; $ci < $ec; $ci += $chunk_size) {
                    $sql_e = 'INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES '
                           . implode(',', array_slice($enroll_rows, $ci, $chunk_size))
                           . ' ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), status=VALUES(status)';
                    $e_params = array_slice($enroll_params, $ci * 4, $chunk_size * 4);
                    db_run($sql_e, $e_params);
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
    // Bulk move students to another class
    case 'bulk_move':
        require_write();
        $ids = $in['student_ids'] ?? [];
        $targetClass = trim($in['target_class'] ?? '');

        if (!is_array($ids) || count($ids) === 0) {
            json_fail('Không có em nào được chọn.', 400);
        }
        if ($targetClass === '') {
            json_fail('Vui lòng chọn lớp đích.', 400);
        }

        // Validate target class exists
        $targetClassId = class_id_by_name($targetClass);

        // Check write permission on target class
        $chophep = allowed_class_ids($me);
        if ($chophep !== null && !in_array($targetClassId, $chophep, true)) {
            json_fail('Bạn không phụ trách lớp "' . $targetClass . '".', 403);
        }

        // Validate each student's current class (IDOR prevention)
        $validIds = [];
        foreach ($ids as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) continue;
            $curClass = current_enrollment_class($sid, $yid);
            // If user has narrow scope, must cover source class; null = not enrolled this year
            if ($chophep !== null) {
                if ($curClass === null || !can_access_class($me, 'students', $curClass, 'edit')) {
                    continue; // skip unauthorized students
                }
            }
            $validIds[] = $sid;
        }

        if (count($validIds) === 0) {
            json_fail('Không có em nào bạn được phép chuyển. Có thể các em không thuộc lớp bạn phụ trách.', 403);
        }

        db()->beginTransaction();
        try {
            $ph = implode(',', array_fill(0, count($validIds), '(?, ?, ?, ?)'));
            $params = [];
            foreach ($validIds as $sid) {
                $params[] = $yid;
                $params[] = $sid;
                $params[] = $targetClassId;
                $params[] = 'đang sinh hoạt';
            }
            $sql = "INSERT INTO enrollments (year_id, student_id, class_id, status)
                    VALUES $ph
                    ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), status = 'đang sinh hoạt'";
            db_run($sql, $params);

            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Chuyển lớp thất bại: '), 500);
        }

        log_action('chuyen', 'students', 'Bulk chuyển ' . count($validIds) . ' em sang lớp ' . $targetClass);
        Cache::flush();
        json_out(['ok' => true, 'moved' => count($validIds)]);
        break;

    // -------------------------------------------------------------
    // Bulk delete students
    case 'bulk_delete':
        require_write();
        $ids = $in['student_ids'] ?? [];

        if (!is_array($ids) || count($ids) === 0) {
            json_fail('Không có em nào được chọn.', 400);
        }

        // Permission check for delete (same scope as bulk_move)
        $chophepDel = allowed_class_ids($me);

        // Validate each student's current class (IDOR prevention)
        $validIds = [];
        foreach ($ids as $sid) {
            $sid = (int) $sid;
            if ($sid <= 0) continue;
            $curClass = current_enrollment_class($sid, $yid);
            if ($chophepDel !== null) {
                if ($curClass === null || !can_access_class($me, 'students', $curClass, 'edit')) {
                    continue;
                }
            }
            $validIds[] = $sid;
        }

        if (count($validIds) === 0) {
            json_fail('Không có em nào bạn được phép xóa. Có thể các em không thuộc lớp bạn phụ trách.', 403);
        }

        db()->beginTransaction();
        try {
            // Soft delete: mark as 'dừng sinh hoạt' in enrollments (keep student records, just inactive)
            $ph = implode(',', array_fill(0, count($validIds), '?'));
            $params = [];
            foreach ($validIds as $sid) {
                $params[] = $sid;
            }
            db_run(
                "UPDATE enrollments SET status = 'dừng sinh hoạt' WHERE year_id = ? AND student_id IN ($ph)",
                array_merge([$yid], $params)
            );

            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            json_fail(safe_error($e, 'Xóa không thành công: '), 500);
        }

        log_action('xoa', 'students', 'Bulk xóa ' . count($validIds) . ' em');
        Cache::flush();
        json_out(['ok' => true, 'deleted' => count($validIds)]);
        break;

    // -------------------------------------------------------------
    // FAVORITES — Lấy danh sách favorites của user hiện tại
    case 'favorites_list':
        $uid = (int) $me['id'];
        $favs = db_all(
            'SELECT sf.student_id, sf.created_at,
                    s.code, s.holy_name, s.full_name, s.gender, s.birth_date,
                    c.name AS class_name, b.name AS block_name
               FROM student_favorites sf
               JOIN students s ON s.id = sf.student_id
               LEFT JOIN enrollments e ON e.student_id = s.id AND e.year_id = ?
               LEFT JOIN classes c ON c.id = e.class_id
               LEFT JOIN blocks b ON b.id = c.block_id
              WHERE sf.user_id = ?
           ORDER BY sf.created_at DESC',
            [$yid, $uid]
        );
        json_out(['ok' => true, 'favorites' => $favs]);
        break;

    // -------------------------------------------------------------
    // FAVORITES — Toggle favorite (add hoặc remove)
    case 'favorites_toggle':
        $inFav = $in ?? [];
        $studentId = isset($inFav['student_id']) ? (int) $inFav['student_id'] : 0;
        if ($studentId <= 0) json_fail('Thiếu student_id.', 400);

        // Kiểm tra student tồn tại
        $student = db_one('SELECT id, full_name FROM students WHERE id = ?', [$studentId]);
        if (!$student) json_fail('Không tìm thấy thiếu nhi.', 404);

        $uid = (int) $me['id'];

        // Kiểm tra đã favorite chưa
        $existing = db_one(
            'SELECT id FROM student_favorites WHERE student_id = ? AND user_id = ?',
            [$studentId, $uid]
        );

        if ($existing) {
            // Xóa favorite
            db_run('DELETE FROM student_favorites WHERE student_id = ? AND user_id = ?', [$studentId, $uid]);
            log_action('unfav', 'students', 'Bỏ yêu thích ' . $student['full_name']);
            json_out(['ok' => true, 'favorited' => false]);
        } else {
            // Thêm favorite
            db_insert('INSERT INTO student_favorites (student_id, user_id) VALUES (?, ?)', [$studentId, $uid]);
            log_action('fav', 'students', 'Đánh dấu yêu thích ' . $student['full_name']);
            json_out(['ok' => true, 'favorited' => true]);
        }
        break;

    // -------------------------------------------------------------
    default:
        json_fail('Hành động không hợp lệ.', 404);
}
