<?php
/**
 * BACKFILL GIỜ CHO CHƯƠNG TRÌNH CŨ (tuỳ chọn)
 *
 *   php scripts/backfill_program_times.php                              # CHẠY THỬ (soát)
 *   php scripts/backfill_program_times.php --cutoff-after=15 --apply    # điền giờ đi trễ = start + 15'
 *   php scripts/backfill_program_times.php --absent-after=120 --apply   # điền giờ khoá sổ = start + 120'
 *   php scripts/backfill_program_times.php --program=3 --cutoff-after=15 --apply
 *
 * Điền các mốc giờ còn TRỐNG cho buổi cũ. KHÔNG có mặc định ngầm: chỉ điền
 * khi bạn TRUYỀN RÕ số phút (vì đặt giờ là quyết định của bạn):
 *   - --cutoff-after=N : giờ TÍNH ĐI TRỄ = start + N phút (nếu đang trống).
 *   - --absent-after=N : giờ KHOÁ SỔ   = start + N phút (nếu đang trống).
 * Không truyền cờ nào thì chỉ BÁO CÁO còn bao nhiêu buổi trống, không ghi.
 *
 * Mặc định CHẠY THỬ; --apply mới ghi. Idempotent. Nên sao lưu trước --apply.
 */

require __DIR__ . '/../config/db.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

$apply = in_array('--apply', $argv, true);
$cutoffAfter = null;
$absentAfter = null;
$progArg = null;
foreach ($argv as $a) {
    if (strpos($a, '--cutoff-after=') === 0) $cutoffAfter = (int) substr($a, 15);
    if (strpos($a, '--absent-after=') === 0) $absentAfter = (int) substr($a, 15);
    if (strpos($a, '--program=') === 0)      $progArg     = (int) substr($a, 10);
}
foreach (['--cutoff-after' => $cutoffAfter, '--absent-after' => $absentAfter] as $k => $v) {
    if ($v !== null && $v <= 0) { fwrite(STDERR, "$k phải là số phút > 0.\n"); exit(1); }
}

$addMin = fn(string $t, int $m): string => date('H:i:s', strtotime("1970-01-01 $t") + $m * 60);

$where = $progArg !== null ? 'WHERE id = ?' : '';
$rows  = db_all("SELECT id, name, start_time, cutoff_time, absent_time FROM programs $where ORDER BY id",
                $progArg !== null ? [$progArg] : []);

$trongCut = 0; $trongAbs = 0;          // đếm còn trống (để báo cáo)
$cutUpd = []; $absUpd = []; $samples = [];
foreach ($rows as $r) {
    if (empty($r['cutoff_time'])) $trongCut++;
    if (empty($r['absent_time'])) $trongAbs++;
    $newCut = ($cutoffAfter !== null && empty($r['cutoff_time'])) ? $addMin($r['start_time'], $cutoffAfter) : null;
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
   . " · đang trống giờ đi trễ: $trongCut · đang trống giờ khoá sổ: $trongAbs\n";
if ($cutoffAfter === null && $absentAfter === null) {
    echo "(Chỉ báo cáo. Thêm --cutoff-after=N và/hoặc --absent-after=N để điền = giờ bắt đầu + N phút.)\n";
    exit(0);
}
if ($samples) echo "Sẽ điền:\n" . implode("\n", $samples) . "\n";
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
