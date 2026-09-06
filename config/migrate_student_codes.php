<?php
/**
 * CHUYỂN ĐỔI MÃ THIẾU NHI SANG GDGLPT (chạy một lần)
 *
 *   php config/migrate_student_codes.php          # xem trước (dry-run)
 *   php config/migrate_student_codes.php --apply  # thực thi
 *
 * Gán lại mã mọi em theo: GDGLPT + <2 số năm nhập đoàn> + <số thứ tự 4 chữ số>.
 *   - Năm nhập = năm bắt đầu của niên khoá em GHI DANH sớm nhất; không có ghi
 *     danh nào thì lấy niên khoá đang mở.
 *   - Số thứ tự đếm theo từng năm, thứ tự ổn định theo mã cũ.
 *   - Lưu bảng đối chiếu cũ→mới ra config/backup/ trước khi ghi (để in lại thẻ,
 *     tra cứu, hoặc hoàn tác nếu cần).
 *
 * An toàn: mã mới (GDGLPT…) khác hẳn mã cũ (TN…), quan hệ dữ liệu dùng
 * student_id chứ không dùng mã, nên đổi mã không gãy ràng buộc nào.
 */

require __DIR__ . '/db.php';
require __DIR__ . '/student_code.php';

$apply = in_array('--apply', $argv, true);

$open = db_one("SELECT name FROM school_years WHERE status = 'đang mở' ORDER BY id DESC LIMIT 1");

// Mỗi em kèm năm ghi danh sớm nhất. Sắp theo mã cũ để thứ tự tái lập được.
$rows = db_all(
    "SELECT s.id, s.code, s.full_name, MIN(y.name) AS first_year
       FROM students s
       LEFT JOIN enrollments e ON e.student_id = s.id
       LEFT JOIN school_years y ON y.id = e.year_id
      GROUP BY s.id, s.code, s.full_name
      ORDER BY s.code ASC"
);

if (!$rows) { echo "Không có thiếu nhi nào.\n"; exit; }

$counter = [];      // yy => số đã cấp
$mapping = [];
foreach ($rows as $r) {
    $yearName = $r['first_year'] ?: ($open['name'] ?? null);
    $yy = year_two_digit($yearName ? ['name' => $yearName] : null);
    $counter[$yy] = ($counter[$yy] ?? 0) + 1;
    $new = STUDENT_CODE_PREFIX . sprintf('%02d', $yy) . sprintf('%04d', $counter[$yy]);
    $mapping[] = [
        'id'   => (int) $r['id'],
        'name' => $r['full_name'],
        'old'  => $r['code'],
        'new'  => $new,
        'year' => $yearName,
    ];
}

echo "== Bảng đối chiếu (" . count($mapping) . " em) ==\n";
foreach ($mapping as $m) {
    printf("  #%d  %-8s -> %-14s  %s  (nhập %s)\n",
        $m['id'], $m['old'], $m['new'], $m['name'], $m['year'] ?? '?');
}

// Cảnh báo trùng (không nên xảy ra)
$news = array_column($mapping, 'new');
if (count($news) !== count(array_unique($news))) {
    echo "\n[LỖI] Mã mới bị trùng — dừng lại, không ghi.\n";
    exit(1);
}

if (!$apply) {
    echo "\n(dry-run) Thêm --apply để thực thi.\n";
    exit;
}

// Lưu backup trước khi ghi
$dir = __DIR__ . '/backup';
if (!is_dir($dir)) @mkdir($dir, 0775, true);
$file = $dir . '/student_code_migration_' . date('Ymd_His') . '.json';
file_put_contents($file, json_encode($mapping, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nĐã lưu đối chiếu: $file\n";

$pdo = db();
$pdo->beginTransaction();
try {
    foreach ($mapping as $m) {
        db_run("UPDATE students SET code = ? WHERE id = ?", [$m['new'], $m['id']]);
    }
    $pdo->commit();
    echo "Đã đổi mã cho " . count($mapping) . " em.\n";
} catch (Throwable $e) {
    $pdo->rollBack();
    echo "[LỖI] " . $e->getMessage() . " — đã hoàn tác, không đổi gì.\n";
    exit(1);
}
