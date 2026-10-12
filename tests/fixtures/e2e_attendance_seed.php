<?php
/**
 * FIXTURE CHO TEST TRÌNH DUYỆT ĐIỂM DANH (tests/e2e/attendance_regress.js)
 *
 * Chạy SAU `php config/install.php` + `php tests/fixtures/ci_seed.php`:
 *
 *   php tests/fixtures/e2e_attendance_seed.php
 *
 * Tạo (idempotent, chạy lại vô hại):
 *   - buổi "E2E Điểm Danh" diễn ra HÔM NAY, bắt đầu 23:59 (chưa quá giờ chốt),
 *     cho phép quét QR, không tính Mộc;
 *   - 6 em E2E001..E2E006 ở lớp A và 2 em E2E101..E2E102 ở lớp A2 (cùng khối);
 *   - GLV 0901000101 (chỉ lớp A) và trưởng khối 0901000102 (cả khối của A);
 *   - bỏ cờ must_change_pw của admin 0901000001 để đăng nhập thẳng vào app.
 *
 * Mật khẩu các tài khoản: tntt@2026. CHỈ dùng cho DB test/CI, từ chối chạy nếu
 * tên DB không phải tntt_test.
 */

require __DIR__ . '/../../config/db.php';

function e2e_fail(string $msg): never
{
    fwrite(STDERR, "e2e_attendance_seed: LỖI: $msg\n");
    exit(1);
}

$dbName = db_one('SELECT DATABASE() AS d')['d'] ?? '';
if ($dbName !== 'tntt_test') e2e_fail("chỉ chạy trên DB tntt_test (đang là '$dbName').");

$year = db_one('SELECT id FROM school_years WHERE is_current = 1 ORDER BY id LIMIT 1');
if (!$year) e2e_fail('không có năm học đang mở — đã chạy config/install.php chưa?');
$yearId = (int) $year['id'];

$a = db_one('SELECT id, block_id FROM classes WHERE block_id IS NOT NULL ORDER BY id LIMIT 1');
$a2 = $a ? db_one('SELECT id FROM classes WHERE block_id = ? AND id <> ? ORDER BY id LIMIT 1', [$a['block_id'], $a['id']]) : null;
if (!$a || !$a2) e2e_fail('cần một khối có ít nhất 2 lớp.');
$admin = db_one("SELECT id FROM members WHERE role_code = 'admin' ORDER BY id LIMIT 1");
if (!$admin) e2e_fail('không có admin.');
$adminId = (int) $admin['id'];

// --- Dọn lần chạy trước ------------------------------------------------------
$old = db_all("SELECT id FROM programs WHERE name = 'E2E Điểm Danh'");
foreach ($old as $p) {
    db_run('DELETE FROM attendances WHERE program_id = ?', [$p['id']]);
    db_run('DELETE FROM program_classes WHERE program_id = ?', [$p['id']]);
    db_run('DELETE FROM programs WHERE id = ?', [$p['id']]);
}
foreach (db_all("SELECT id FROM students WHERE code LIKE 'E2E%'") as $s) {
    db_run('DELETE FROM attendances WHERE student_id = ?', [$s['id']]);
    db_run('DELETE FROM enrollments WHERE student_id = ?', [$s['id']]);
    db_run('DELETE FROM students WHERE id = ?', [$s['id']]);
}
foreach (db_all("SELECT id FROM members WHERE phone IN ('0901000101','0901000102')") as $m) {
    db_run('DELETE FROM member_assignments WHERE member_id = ?', [$m['id']]);
    db_run('DELETE FROM members WHERE id = ?', [$m['id']]);
}

// --- Buổi điểm danh hôm nay ----------------------------------------------------
db_insert(
    "INSERT INTO programs (year_id, name, type, status, day_of_week, start_time, count_for_attendance, count_for_emulation, allow_qr)
     VALUES (?, 'E2E Điểm Danh', 'bắt buộc', 'kích hoạt', ?, '23:59:00', 1, 0, 1)",
    [$yearId, (int) date('w')]
);

// --- Thiếu nhi -----------------------------------------------------------------
$mk = function (string $code, int $classId) use ($yearId): void {
    $sid = db_insert("INSERT INTO students (code, full_name, gender) VALUES (?, ?, 1)", [$code, 'Em ' . $code]);
    db_run("INSERT INTO enrollments (year_id, student_id, class_id, status) VALUES (?,?,?,'đang sinh hoạt')",
           [$yearId, $sid, $classId]);
};
for ($i = 1; $i <= 6; $i++) $mk(sprintf('E2E%03d', $i), (int) $a['id']);
for ($i = 1; $i <= 2; $i++) $mk(sprintf('E2E%03d', 100 + $i), (int) $a2['id']);

// --- Tài khoản -----------------------------------------------------------------
$hash = password_hash('tntt@2026', PASSWORD_BCRYPT, ['cost' => 4]);
$mkMember = function (string $phone, string $role, string $name, ?int $blockId, ?int $classId) use ($hash, $adminId): void {
    $id = db_insert(
        "INSERT INTO members (code, full_name, phone, password_hash, role_code, must_change_pw)
         VALUES (?, ?, ?, ?, ?, 0)",
        ['E2EM' . substr($phone, -3), $name, $phone, $hash, $role]
    );
    db_run(
        "INSERT INTO member_assignments (member_id, role_code, block_id, class_id, is_primary, from_date, assigned_by)
         VALUES (?, ?, ?, ?, 1, CURDATE(), ?)",
        [$id, $role, $blockId, $classId, $adminId]
    );
};
$mkMember('0901000101', 'glv', 'E2E GLV lớp A', null, (int) $a['id']);
$mkMember('0901000102', 'truong_khoi', 'E2E Trưởng khối A', (int) $a['block_id'], null);

db_run("UPDATE members SET must_change_pw = 0 WHERE phone = '0901000001'");
db_run('DELETE FROM login_attempts');

echo "e2e_attendance_seed: OK — buổi 'E2E Điểm Danh' hôm nay, em E2E001..E2E006 (lớp {$a['id']}) và E2E101..E2E102 (lớp {$a2['id']}), "
   . "GLV 0901000101, trưởng khối 0901000102.\n";
