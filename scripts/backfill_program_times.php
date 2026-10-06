<?php
/**
 * BACKFILL GIỜ CHO CHƯƠNG TRÌNH CŨ
 *
 *   php scripts/backfill_program_times.php                       # CHẠY THỬ
 *   php scripts/backfill_program_times.php --apply               # điền giờ chốt
 *   php scripts/backfill_program_times.php --absent-after=120 --apply
 *   php scripts/backfill_program_times.php --program=3 --apply
 *
 * Điền các mốc giờ còn TRỐNG để chương trình cũ hợp quy định "bắt buộc nhập":
 *
 *   - cutoff_time (giờ tính đi trễ) NULL -> start_time + cutoff_minutes
 *     (mặc định 30'). KHÔNG đổi hành vi: server vốn đã dùng đúng mặc định
 *     này khi để trống, nên chỉ là "ghi rõ ra".
 *
 *   - absent_time (giờ khoá sổ) NULL -> start_time + N phút, CHỈ khi truyền
 *     --absent-after=N. Mặc định KHÔNG đụng vì đặt giờ khoá sổ là THAY ĐỔI
 *     HÀNH VI (bắt đầu khoá cứng buổi quá khứ) — phải do bạn chọn N.
 *
 * Mặc định CHẠY THỬ; --apply mới ghi. Idempotent. Nên sao lưu trước --apply.
 */

require __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$apply = in_array('--apply', $argv, true);
$absentAfter = null;
$progArg = null;
foreach ($argv as $a) {
    if (strpos($a, '--absent-after=') === 0) $absentAfter = (int) substr($a, 15);
    if (strpos($a, '--program=') === 0)      $progArg     = (int) substr($a, 10);
}
if ($absentAfter !== null && $absentAfter <= 0) {
    fwrite(STDERR, "--absent-after phải là số phút > 0.\n");
    exit(1);
}

$cutoffMin = (int) app_config('cutoff_minutes');
$addMin = fn(string $t, int $m): string => date('H:i:s', strtotime("1970-01-01 $t") + $m * 60);

$where = $progArg !== null ? 'WHERE id = ?' : '';
$rows  = db_all("SELECT id, name, start_time, cutoff_time, absent_time FROM programs $where ORDER BY id",
                $progArg !== null ? [$progArg] : []);

$cutUpd = []; $absUpd = []; $samples = [];
foreach ($rows as $r) {
    $newCut = empty($r['cutoff_time']) ? $addMin($r['start_time'], $cutoffMin) : null;
    $newAbs = ($absentAfter !== null && empty($r['absent_time'])) ? $addMin($r['start_time'], $absentAfter) : null;
    if ($newCut === null && $newAbs === null) continue;
    if ($newCut !== null) $cutUpd[(int) $r['id']] = $newCut;
    if ($newAbs !== null) $absUpd[(int) $r['id']] = $newAbs;
    if (count($samples) < 20) {
        $samples[] = sprintf('  #%d %s · bắt đầu %s%s%s', $r['id'], $r['name'],
            substr($r['start_time'], 0, 5),
            $newCut !== null ? " · +đi trễ " . substr($newCut, 0, 5) : '',
            $newAbs !== null ? " · +khoá sổ " . substr($newAbs, 0, 5) : '');
    }
}

echo "Chương trình soát: " . count($rows)
   . " · cần điền giờ đi trễ: " . count($cutUpd)
   . " · cần điền giờ khoá sổ: " . count($absUpd) . "\n";
if ($absentAfter === null) {
    echo "(Không đụng giờ khoá sổ — thêm --absent-after=N để điền = giờ bắt đầu + N phút.)\n";
}
if ($samples) echo "Ví dụ:\n" . implode("\n", $samples) . "\n";
if (!$cutUpd && !$absUpd) { echo "✓ Không có gì để điền.\n"; exit(0); }

if (!$apply) {
    echo "\n(CHẠY THỬ — chưa ghi gì.) Thêm --apply để điền thật. Nên sao lưu DB trước.\n";
    exit(0);
}

db()->beginTransaction();
try {
    foreach ($cutUpd as $id => $v) db_run("UPDATE programs SET cutoff_time = ? WHERE id = ?", [$v, $id]);
    foreach ($absUpd as $id => $v) db_run("UPDATE programs SET absent_time = ? WHERE id = ?", [$v, $id]);
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    fwrite(STDERR, "LỖI, đã hoàn tác: " . $e->getMessage() . "\n");
    exit(1);
}
echo "  ✓ Đã điền giờ đi trễ " . count($cutUpd) . " buổi, giờ khoá sổ " . count($absUpd) . " buổi.\n✓ HOÀN TẤT.\n";
