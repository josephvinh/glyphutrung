<?php
/**
 * TEST TRỰC TIẾP: gọi logic import không qua HTTP.
 * Bao gồm toàn bộ code import vào một hàm, gọi với dữ liệu thật.
 * Chạy: php scratch/test-import-direct.php
 */
require __DIR__ . '/../config/db.php';
require __DIR__ . '/../public/api/_bootstrap.php';

// ---- GIẢ LẬP require_permission('students', 'edit') ----
function mock_require_permission(): array {
    $me = db_one("SELECT * FROM members WHERE role_code = 'admin' AND status <> 'đã nghỉ' LIMIT 1");
    if (!$me) die("Không có tài khoản admin\n");
    return $me;
}

function mock_json_input(): array {
    // Dữ liệu test: 1 em mới
    return [
        'rows' => [[
            'name'        => 'TEST_DIRECT_' . date('His'),
            'gender'      => 1,
            'birthDate'   => '01/01/2015',
            'className'   => 'Khai Tâm 1',  // lớp đầu tiên trong CSDL
            'address'     => 'Test address',
            'fatherName'  => 'Test Father',
            'motherName'  => 'Test Mother',
            'fatherPhone' => '0900000001',
            'motherPhone' => '0900000002',
        ]]
    ];
}

// ---- CÁC HÀM TỪ students.php ----
function clean_student(array $s): array {
    $holyName   = preg_replace('/\s+/', ' ', trim((string) ($s['holyName'] ?? '')));
    $name       = preg_replace('/\s+/', ' ', trim((string) ($s['name'] ?? '')));
    $fatherName = preg_replace('/\s+/', ' ', trim((string) ($s['fatherName'] ?? '')));
    $motherName = preg_replace('/\s+/', ' ', trim((string) ($s['motherName'] ?? '')));
    $address    = preg_replace('/\s+/', ' ', trim((string) ($s['address'] ?? '')));

    $holyName   = mb_strtoupper($holyName, 'UTF-8');
    $name       = mb_strtoupper($name, 'UTF-8');
    $fatherName = mb_convert_case($fatherName, MB_CASE_TITLE, 'UTF-8');
    $motherName = mb_convert_case($motherName, MB_CASE_TITLE, 'UTF-8');

    $clean_phone = function($phone) {
        $p = preg_replace('/[^\d]/', '', $phone);
        if ($p === '') return '';
        if (strpos($p, '84') === 0 && strlen($p) >= 11) $p = '0' . substr($p, 2);
        if (($p[0] ?? '') !== '0') $p = '0' . $p;
        return $p;
    };

    return [
        'code'        => trim((string) ($s['code'] ?? '')),
        'holyName'    => $holyName,
        'name'        => $name,
        'gender'      => ((int) ($s['gender'] ?? 1)) === 0 ? 0 : 1,
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

// ---- LOGIC IMPORT MỚI ----
function run_import(array $me, array $in, int $yid): array {
    $rows = $in['rows'] ?? [];
    if (!is_array($rows) || count($rows) === 0) {
        return ['ok' => false, 'error' => 'Không có dòng nào.'];
    }

    $added = $updated = $skipped = 0;
    $errors = [];

    // Phạm vi
    $chophep = allowed_class_ids($me);
    if ($chophep !== null && !$chophep) {
        return ['ok' => false, 'error' => 'Không có quyền nhập.'];
    }

    // Ánh xạ lớp
    $classMap = [];
    foreach (db_all('SELECT id, name FROM classes') as $r) {
        $classMap[$r['name']] = (int) $r['id'];
    }

    // Mã học sinh đã có trong niên khoá hiện tại
    $existingCodes = [];
    foreach (db_all(
        "SELECT s.code, s.id FROM students s JOIN enrollments e ON e.student_id = s.id WHERE e.year_id = ?",
        [$yid]
    ) as $r) {
        $existingCodes[$r['code']] = (int) $r['id'];
    }

    // year_two_digit() nhận mảng niên khoá
    $schoolYear = db_one("SELECT * FROM school_years WHERE id = ?", [$yid]);
    $year2 = year_two_digit($schoolYear);
    $prefix = 'TNTT-' . sprintf('%02d', $year2) . '-';
    $row = db_one(
        "SELECT MAX(CAST(SUBSTRING(code, ?) AS UNSIGNED)) AS mx FROM students WHERE code LIKE ?",
        [strlen($prefix) + 1, $prefix . '%']
    );
    $nextNum = ((int) ($row['mx'] ?? 0)) + 1;

    $upsert_students  = [];
    $upsert_params   = [];
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
            $errors[] = "Dòng " . ($i + 2) . ": lớp '{$s['className']}' không tồn tại";
            continue;
        }
        if ($chophep !== null && !in_array($lopId, $chophep, true)) {
            $skipped++;
            $errors[] = "Dòng " . ($i + 2) . ": không phụ trách lớp '{$s['className']}'";
            continue;
        }

        $sid = $existingCodes[$s['code']] ?? null;
        $upsert_students[] = '(?,?,?,?,?,?,?,?,?,?,?)';
        array_push($upsert_params,
            $sid, $s['code'], $s['holyName'], $s['name'], $s['gender'], $s['birthDate'], $s['address'],
            $s['fatherName'], $s['fatherPhone'], $s['motherName'], $s['motherPhone']
        );
        $enrollment_batch[] = ['code' => $s['code'], 'class_id' => $lopId, 'status' => $s['status']];

        if (!$sid) {
            $existingCodes[$s['code']] = -1;
            $added++;
        } else {
            $updated++;
        }
    }

    if (!$upsert_students) {
        return ['ok' => true, 'added' => 0, 'updated' => 0,
                'skipped' => $skipped, 'errors' => $errors];
    }

    db()->beginTransaction();
    try {
        $chunk_size = 500;
        $total = count($upsert_students);
        for ($ci = 0; $ci < $total; $ci += $chunk_size) {
            $chunk = array_slice($upsert_students, $ci, $chunk_size);
            $params = array_slice($upsert_params, $ci * 11, $chunk_size * 11);
            $sql = 'INSERT INTO students (id, code, holy_name, full_name, gender, birth_date, address, father_name, father_phone, mother_name, mother_phone) VALUES '
                 . implode(',', $chunk)
                 . ' ON DUPLICATE KEY UPDATE holy_name=VALUES(holy_name), full_name=VALUES(full_name), gender=VALUES(gender), birth_date=VALUES(birth_date), address=VALUES(address), father_name=VALUES(father_name), father_phone=VALUES(father_phone), mother_name=VALUES(mother_name), mother_phone=VALUES(mother_phone)';
            db_run($sql, $params);
        }

        // Lấy id mới cho các em mới
        $new_codes = [];
        foreach ($enrollment_batch as $e) {
            if (($existingCodes[$e['code']] ?? -1) === -1) {
                $new_codes[] = $e['code'];
            }
        }
        if ($new_codes) {
            $ph = implode(',', array_fill(0, count($new_codes), '?'));
            foreach (db_all("SELECT id, code FROM students WHERE code IN ($ph)", $new_codes) as $r) {
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
        $ec = count($enroll_rows);
        for ($ci = 0; $ci < $ec; $ci += $chunk_size) {
            $sql_e = 'INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES '
                   . implode(',', array_slice($enroll_rows, $ci, $chunk_size))
                   . ' ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), status=VALUES(status)';
            $e_params = array_slice($enroll_params, $ci * 4, $chunk_size * 4);
            db_run($sql_e, $e_params);
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        return ['ok' => false, 'error' => 'Lỗi: ' . $e->getMessage()];
    }

    return ['ok' => true, 'added' => $added, 'updated' => $updated,
            'skipped' => $skipped, 'errors' => $errors];
}

// ---- CHẠY TEST ----
echo "=== TEST IMPORT TRỰC TIẾP ===\n";

$me = mock_require_permission();
$year = db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) { echo "Không có niên khoá.\n"; exit(1); }
$yid = (int) $year['id'];
echo "Niên khoá: id=$yid\n";
echo "Admin: {$me['full_name']} (id={$me['id']})\n";

// Đếm trước
$before = db_one("SELECT COUNT(*) n FROM students");
$beforeN = (int) $before['n'];
echo "Học sinh trước: $beforeN\n";

// Lấy lớp đầu tiên
$cls = db_one("SELECT id, name FROM classes ORDER BY id LIMIT 1");
$clsId = (int) $cls['id'];
$clsName = $cls['name'];
echo "Lớp test: $clsName (id=$clsId)\n";

$testName = 'TEST_DIRECT_' . date('His');
$in = mock_json_input();
$in['rows'][0]['name'] = $testName;
$in['rows'][0]['className'] = $clsName;
echo "Tên test: $testName\n";

$result = run_import($me, $in, $yid);
echo "\nKết quả: " . json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";

// Kiểm tra
$after = db_one("SELECT COUNT(*) n FROM students");
$afterN = (int) $after['n'];
echo "Học sinh sau: $afterN\n";

$testSid = db_one("SELECT id, code FROM students WHERE full_name = ?", [$testName]);
echo "Em test: " . ($testSid ? "CÓ (id={$testSid['id']}, mã={$testSid['code']})" : "KHÔNG TÌM THẤY") . "\n";

$enrollCheck = db_one(
    "SELECT e.* FROM enrollments e JOIN students s ON s.id=e.student_id WHERE s.full_name = ? AND e.year_id = ?",
    [$testName, $yid]
);
echo "Ghi danh: " . ($enrollCheck ? "CÓ (lớp {$enrollCheck['class_id']})" : "KHÔNG") . "\n";

if ($result['ok'] && $result['added'] === 1 && $afterN === $beforeN + 1 && $testSid && $enrollCheck) {
    echo "\n✓ TEST PASSED — import hoạt động đúng!\n";

    // Cleanup
    db_run("DELETE FROM enrollments WHERE student_id = ?", [(int) $testSid['id']]);
    db_run("DELETE FROM students WHERE id = ?", [(int) $testSid['id']]);
    $after2 = db_one("SELECT COUNT(*) n FROM students");
    echo "  Đã dọn dẹp. Học sinh sau cleanup: {$after2['n']}\n";
} else {
    echo "\n✗ TEST FAILED\n";
    exit(1);
}
