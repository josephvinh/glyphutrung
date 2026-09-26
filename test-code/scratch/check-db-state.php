<?php
require __DIR__ . '/../config/db.php';

$s = db_one('SELECT COUNT(*) n FROM students');
$e = db_one('SELECT COUNT(*) n FROM enrollments');
$c = db_one('SELECT COUNT(*) n FROM classes');
$b = db_one('SELECT COUNT(*) n FROM blocks');
printf("Học sinh: %d | Ghi danh: %d | Lớp: %d | Khối: %d\n",
    $s['n'], $e['n'], $c['n'], $b['n']);

// Mã mẫu
$r = db_all("SELECT code FROM students WHERE code LIKE 'TNTT-25-%' LIMIT 3");
echo "Mã mẫu: ";
foreach ($r as $x) echo $x['code'] . ' ';
echo "\n";

// Niên khoá hiện tại
$cur = db_one("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
if ($cur) {
    printf("Niên khoá hiện tại: id=%d\n", $cur['id']);
    $n = db_one(
        "SELECT COUNT(DISTINCT student_id) n FROM enrollments WHERE year_id = ?",
        [$cur['id']]
    );
    printf("Học sinh có ghi danh trong năm hiện tại: %d\n", $n['n']);
}
