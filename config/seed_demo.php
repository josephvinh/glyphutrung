<?php
/**
 * SINH DỮ LIỆU DEMO — ~600 em chia đều các lớp + điểm danh + điểm số.
 *
 * Dùng để DEMO và THỬ TẢI. Tên các em là NGẪU NHIÊN, không phải người thật.
 *
 * CÁCH CHẠY (trên DB DEMO, KHÔNG phải DB thật):
 *   1) Tạo DB demo + nạp schema:
 *        mysql -u root -e "CREATE DATABASE tntt_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
 *        TNTT_DB_NAME=tntt_demo php config/install.php
 *   2) Sinh dữ liệu:
 *        TNTT_DB_NAME=tntt_demo php config/seed_demo.php
 *   3) (tuỳ chọn) các migration mới:
 *        TNTT_DB_NAME=tntt_demo php config/migrate_roles_du_bi.php
 *        TNTT_DB_NAME=tntt_demo php config/migrate_modules_sync.php
 *
 * CHỐT AN TOÀN: chỉ chạy khi DB tên chứa 'demo' HOẶC đặt TNTT_ALLOW_SEED=1,
 * để không bao giờ lỡ tay làm hỏng dữ liệu thật.
 */

require __DIR__ . '/db.php';

$dbName = (string) (getenv('TNTT_DB_NAME') ?: (app_config('db')['name'] ?? ''));
$allow  = (getenv('TNTT_ALLOW_SEED') === '1') || (stripos($dbName, 'demo') !== false);
if (!$allow) {
    fwrite(STDERR, "TỪ CHỐI: seed_demo chỉ chạy trên DB có tên chứa 'demo' hoặc khi đặt TNTT_ALLOW_SEED=1.\n"
        . "DB hiện tại: '$dbName'. Tránh làm hỏng dữ liệu thật.\n"
        . "Ví dụ: TNTT_DB_NAME=tntt_demo php config/seed_demo.php\n");
    exit(1);
}

$t0  = microtime(true);
$pdo = db();

/* ---------- Cấu hình lượng dữ liệu ---------- */
$SO_KHOI      = 5;
$LOP_MOI_KHOI = 4;      // 5 x 4 = 20 lớp
$EM_MOI_LOP   = 30;     // 20 x 30 = 600 em
$TL_DI_TRE    = 10;     // % đi trễ
$TL_VANG      = 12;     // % vắng (không tạo dòng điểm danh)

/* ---------- Niên khoá + học kỳ ---------- */
$year = db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) {
    db_run("INSERT INTO school_years (name, start_date, end_date, is_current, status)
            VALUES ('2026 - 2027','2026-08-01','2027-05-31',1,'đang mở')");
    $year = db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
    db_run("INSERT INTO terms (year_id,name,start_date,end_date,sort_order)
            VALUES (?, 'Học kỳ I','2026-08-01','2026-12-31',1)", [$year['id']]);
    db_run("INSERT INTO terms (year_id,name,start_date,end_date,sort_order)
            VALUES (?, 'Học kỳ II','2027-01-01','2027-05-31',2)", [$year['id']]);
}
$yearId = (int) $year['id'];
$term = db_one("SELECT * FROM terms WHERE year_id = ? ORDER BY sort_order LIMIT 1", [$yearId]);
if (!$term) { fwrite(STDERR, "Chưa có học kỳ. Chạy install.php trước.\n"); exit(1); }
$termId    = (int) $term['id'];
$termStart = $term['start_date'];
$termEnd   = $term['end_date'];

/* ---------- Chương trình (buổi) để điểm danh ---------- */
$progs = array_column(db_all("SELECT id FROM programs WHERE year_id = ? AND status = 'kích hoạt'", [$yearId]), 'id');
if (!$progs) {
    db_run("INSERT INTO programs (year_id,name,type,status,count_for_attendance,start_time,day_of_week)
            VALUES (?, 'Học Giáo Lý','bắt buộc','kích hoạt',1,'07:30',0)", [$yearId]);
    $progs = array_column(db_all("SELECT id FROM programs WHERE year_id = ? AND status = 'kích hoạt'", [$yearId]), 'id');
}

/* ---------- Admin để gán marked_by ---------- */
$adminId = (int) (db_one("SELECT id FROM members WHERE role_code = 'admin' LIMIT 1")['id'] ?? 0) ?: null;

/* ---------- Dọn dữ liệu cũ (chỉ trên DB demo) ---------- */
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
foreach (['attendances', 'scores', 'enrollments', 'students', 'member_assignments', 'classes', 'blocks'] as $t) {
    $pdo->exec("DELETE FROM `$t`");
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

/* ---------- Khối + Lớp ---------- */
$tenKhoi = ['Khai Tâm', 'Ấu Nhi', 'Thiếu Nhi', 'Nghĩa Sĩ', 'Hiệp Sĩ'];
$tuoiKhoi = [[6, 7], [8, 9], [10, 12], [13, 15], [16, 17]]; // khoảng tuổi ~ để sinh ngày sinh
$classIds = []; // [ [classId, tuoiMin, tuoiMax], ... ]
for ($k = 0; $k < $SO_KHOI; $k++) {
    db_run("INSERT INTO blocks (name, sort_order) VALUES (?, ?)", [$tenKhoi[$k] ?? ('Khối ' . ($k + 1)), $k + 1]);
    $blockId = (int) $pdo->lastInsertId();
    for ($l = 1; $l <= $LOP_MOI_KHOI; $l++) {
        $tenLop = ($tenKhoi[$k] ?? ('K' . ($k + 1))) . ' ' . $l;
        db_run("INSERT INTO classes (block_id, name, sort_order) VALUES (?, ?, ?)", [$blockId, $tenLop, $l]);
        $classIds[] = [(int) $pdo->lastInsertId(), $tuoiKhoi[$k][0], $tuoiKhoi[$k][1]];
    }
}

/* ---------- Kho tên tiếng Việt (ngẫu nhiên) ---------- */
$thanh = ['Giuse', 'Maria', 'Phêrô', 'Phaolô', 'Gioan', 'Anna', 'Têrêsa', 'Tôma', 'Martinô', 'Cecilia',
          'Antôn', 'Đaminh', 'Micae', 'Giacôbê', 'Luca', 'Matthêu', 'Vincentê', 'Anrê', 'Catarina', 'Agata'];
$ho = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý'];
$dem = ['Văn', 'Thị', 'Minh', 'Hoàng', 'Ngọc', 'Gia', 'Đức', 'Anh', 'Quang', 'Thanh', 'Bảo', 'Khánh', 'Hữu', 'Thành', 'Tuấn', 'Xuân'];
$ten = ['An', 'Bình', 'Cường', 'Dũng', 'Duy', 'Đạt', 'Giang', 'Hà', 'Hải', 'Hân', 'Hiếu', 'Hoa', 'Huy', 'Khoa', 'Lan', 'Linh',
        'Long', 'Mai', 'Nam', 'Nga', 'Nhi', 'Phúc', 'Quân', 'Quyên', 'Sơn', 'Tâm', 'Thảo', 'Trang', 'Trí', 'Uyên', 'Vy', 'Yến'];

/* ---------- Sinh 600 em + enrollment ---------- */
function chen_nhieu(PDO $pdo, string $sql, array $rows, int $colsPerRow, int $chunk = 400): int
{
    if (!$rows) return 0;
    $n = 0;
    foreach (array_chunk($rows, $chunk) as $lo) {
        $ph = implode(',', array_fill(0, count($lo), '(' . implode(',', array_fill(0, $colsPerRow, '?')) . ')'));
        $st = $pdo->prepare($sql . ' VALUES ' . $ph);
        $flat = [];
        foreach ($lo as $r) foreach ($r as $v) $flat[] = $v;
        $st->execute($flat);
        $n += count($lo);
    }
    return $n;
}

$yy = date('y', strtotime($termStart)); // 26
$seq = 0;
$studentRows = [];
$plan = []; // [studentId sẽ điền sau khi có id] -> classId, tuoiMin/Max
$stuMeta = [];
foreach ($classIds as $ci) {
    [$classId, $tMin, $tMax] = $ci;
    for ($i = 0; $i < $EM_MOI_LOP; $i++) {
        $seq++;
        $code = 'GDGLPT' . $yy . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
        $hoTen = $ho[array_rand($ho)] . ' ' . $dem[array_rand($dem)] . ' ' . $ten[array_rand($ten)];
        $tuoi = random_int($tMin, $tMax);
        $namSinh = (int) date('Y') - $tuoi;
        $birth = sprintf('%04d-%02d-%02d', $namSinh, random_int(1, 12), random_int(1, 28));
        $studentRows[] = [$code, $thanh[array_rand($thanh)], $hoTen, $birth];
        $stuMeta[] = ['classId' => $classId, 'code' => $code];
    }
}
chen_nhieu($pdo, "INSERT INTO students (code, holy_name, full_name, birth_date)", $studentRows, 4);

// Lấy id theo code để enroll + gán dữ liệu
$idByCode = [];
foreach (db_all("SELECT id, code FROM students") as $r) $idByCode[$r['code']] = (int) $r['id'];

$enrollRows = [];
$studentsFlat = []; // [id, classId]
foreach ($stuMeta as $m) {
    $sid = $idByCode[$m['code']];
    $enrollRows[] = [$yearId, $sid, $m['classId'], 'đang sinh hoạt'];
    $studentsFlat[] = [$sid, $m['classId']];
}
chen_nhieu($pdo, "INSERT INTO enrollments (year_id, student_id, class_id, status)", $enrollRows, 4);

/* ---------- Các buổi (Chúa Nhật) trong học kỳ ---------- */
$sundays = [];
$d = strtotime($termStart);
// nhảy tới Chủ Nhật đầu tiên
while ((int) date('w', $d) !== 0) $d = strtotime('+1 day', $d);
$endTs = strtotime($termEnd);
while ($d <= $endTs) {
    $sundays[] = date('Y-m-d', $d);
    $d = strtotime('+7 days', $d);
}

/* ---------- Điểm danh: mỗi em × mỗi buổi × mỗi chương trình ---------- */
$attRows = [];
$now = date('Y-m-d H:i:s');
foreach ($studentsFlat as [$sid, $cid]) {
    foreach ($sundays as $day) {
        foreach ($progs as $pid) {
            $r = random_int(1, 100);
            if ($r <= $TL_VANG) continue;                 // vắng: không tạo dòng
            $status = ($r <= $TL_VANG + $TL_DI_TRE) ? 'đi trễ' : 'có mặt';
            $attRows[] = [$yearId, (int) $pid, $day, $sid, $status, 'tay', $adminId, $now];
        }
    }
}
$nAtt = chen_nhieu($pdo, "INSERT INTO attendances (year_id, program_id, session_date, student_id, status, method, marked_by, marked_at)", $attRows, 8, 300);

/* ---------- Điểm số học kỳ hiện tại ---------- */
$types = array_column(db_all("SELECT code FROM score_types"), 'code');
$scoreRows = [];
foreach ($studentsFlat as [$sid, $cid]) {
    foreach ($types as $tc) {
        $val = round(random_int(40, 100) / 10, 1); // 4.0 .. 10.0
        $scoreRows[] = [$termId, $sid, $tc, $val, $adminId, $now];
    }
}
$nScore = chen_nhieu($pdo, "INSERT INTO scores (term_id, student_id, type_code, value, updated_by, updated_at)", $scoreRows, 6, 400);

/* ---------- Tổng kết ---------- */
$giay = round(microtime(true) - $t0, 1);
echo "XONG trong {$giay}s\n";
echo " - Khối: $SO_KHOI · Lớp: " . count($classIds) . " · Em: " . count($studentsFlat) . "\n";
echo " - Buổi (Chúa Nhật) trong kỳ: " . count($sundays) . " · Chương trình: " . count($progs) . "\n";
echo " - Dòng điểm danh: $nAtt · Dòng điểm số: $nScore\n";
echo " - Vắng ~{$TL_VANG}% · Đi trễ ~{$TL_DI_TRE}% (còn lại có mặt)\n";
echo "Đăng nhập bằng tài khoản admin sẵn có để xem toàn đoàn.\n";
