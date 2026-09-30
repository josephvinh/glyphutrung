<?php
/**
 * FIXTURE CỐ ĐỊNH CHO CI (#82)
 *
 * Chạy SAU `php config/install.php` (+ các migrate), TRƯỚC PHPUnit:
 *
 *   php tests/fixtures/ci_seed.php
 *
 * Các test trong tests/unit/ được viết để chạy trên DB đã có dữ liệu mẫu:
 *   - năm học id = 1 (đang mở, is_current = 1)   <- install.php tạo
 *   - lớp id = 1                                 <- install.php tạo (Khai Tâm 1A)
 *   - quản trị (admin) id = 1                    <- install.php tạo
 *   - thiếu nhi id 1..3, mã HS001..HS003, ghi danh năm 1 vào lớp 1  <- file này
 *   - admin có một dòng member_assignments đang hiệu lực            <- file này
 *     (cài mới không tạo dòng này — xem issue #94; fixture tạo hộ để
 *      PermissionTest không phụ thuộc vào việc #94 được sửa thế nào)
 *
 * Idempotent: chạy lại vô hại. Nếu các tiền đề của install.php không còn đúng
 * (id năm học/lớp/admin khác 1) thì DỪNG với mã ≠ 0 và nói rõ, thay vì để test
 * fail khó hiểu.
 *
 * CHỈ dùng cho DB CI/dev trống. KHÔNG chạy trên DB thật.
 */

require __DIR__ . '/../../config/db.php';

function seed_fail(string $msg): never
{
    fwrite(STDERR, "ci_seed: LỖI: $msg\n");
    exit(1);
}

$yearId  = 1;
$classId = 1;
$adminId = 1;

// --- Tiền đề do install.php tạo -------------------------------------------
$year = db_one('SELECT id, is_current FROM school_years WHERE id = ?', [$yearId]);
if (!$year || (int) $year['is_current'] !== 1) {
    seed_fail('không có năm học id=1 đang mở — đã chạy config/install.php trên DB trống chưa?');
}
if (!db_one('SELECT id FROM classes WHERE id = ?', [$classId])) {
    seed_fail('không có lớp id=1.');
}
$admin = db_one("SELECT id FROM members WHERE id = ? AND role_code = 'admin'", [$adminId]);
if (!$admin) {
    seed_fail('thành viên id=1 không phải admin.');
}

// --- Thiếu nhi HS001..HS003 (id 1..3) + ghi danh năm 1 / lớp 1 -------------
$students = [
    [1, 'HS001', 'Phêrô', 'Nguyễn Văn Test Một', 1, '2016-03-01'],
    [2, 'HS002', 'Maria', 'Trần Thị Test Hai',   0, '2016-05-02'],
    [3, 'HS003', 'Giuse', 'Lê Văn Test Ba',      1, '2016-07-03'],
];
foreach ($students as [$id, $code, $holy, $name, $gender, $birth]) {
    db_run('INSERT IGNORE INTO students (id, code, holy_name, full_name, gender, birth_date)
            VALUES (?,?,?,?,?,?)', [$id, $code, $holy, $name, $gender, $birth]);
    $row = db_one('SELECT code FROM students WHERE id = ?', [$id]);
    if (!$row || $row['code'] !== $code) {
        seed_fail("students.id=$id đang giữ mã khác ($code mong đợi) — DB không trống?");
    }
    db_run("INSERT IGNORE INTO enrollments (year_id, student_id, class_id, status)
            VALUES (?,?,?, 'đang sinh hoạt')", [$yearId, $id, $classId]);
}

// --- Admin có phân công đang hiệu lực (workaround #94) ---------------------
$has = db_one("SELECT id FROM member_assignments
                WHERE member_id = ? AND role_code = 'admin' AND to_date IS NULL", [$adminId]);
if (!$has) {
    db_run("INSERT INTO member_assignments (member_id, role_code, is_primary, from_date, assigned_by)
            VALUES (?, 'admin', 1, CURDATE(), ?)", [$adminId, $adminId]);
}

echo "ci_seed: OK — 3 thiếu nhi (HS001..HS003), ghi danh năm 1/lớp 1, admin có assignment.\n";
