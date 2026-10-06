<?php
/**
 * SỬA LẠI TRẠNG THÁI "đi trễ" / "có mặt" ĐÃ GHI SAI
 *
 *   php scripts/fix_attendance_status.php                 # CHẠY THỬ (không ghi)
 *   php scripts/fix_attendance_status.php --apply         # ghi thật
 *   php scripts/fix_attendance_status.php --from=2026-09-01 --to=2026-10-05
 *   php scripts/fix_attendance_status.php --program=3 --apply
 *
 * VÌ SAO: trước đây máy chủ tính giờ chốt = giờ bắt đầu + 30' và BỎ QUA
 * giờ chốt riêng (cutoff_time) của buổi. Buổi đặt giờ chốt muộn hơn (VD
 * bắt đầu 06:00, chốt 08:00) khiến em điểm danh lúc 06:36 — VẪN trong giờ
 * — bị ghi "đi trễ". Bản vá đã sửa cho lần ghi MỚI; script này sửa các
 * bản ghi CŨ đã lỡ ghi sai.
 *
 * CÁCH TÍNH: dựng lại đúng điều máy chủ (đã vá) sẽ quyết định — so
 * `marked_at` (thời điểm bấm/quét) với GIỜ CHỐT THẬT của buổi hôm đó:
 *   marked_at >= giờ chốt  -> "đi trễ"
 *   marked_at <  giờ chốt  -> "có mặt"
 * Giờ chốt = cutoff_time nếu có, ngược lại start_time + cutoff_minutes.
 * (Khớp public/api/_common.php: program_cutoff_ts.)
 *
 * AN TOÀN:
 *   - Mặc định CHẠY THỬ: chỉ liệt kê, KHÔNG đổi gì. Thêm --apply mới ghi.
 *   - Nên SAO LƯU DB trước khi --apply.
 *   - Đổi status xong có TÍNH LẠI SỔ MỘC cho các em bị ảnh hưởng (mốc
 *     thưởng phân biệt có mặt / đi trễ).
 *   - Chạy lại vô hại (idempotent): lần sau không còn gì để sửa.
 */

require __DIR__ . '/../public/api/_common.php';        // program_cutoff_ts, attendance_expected_status, db
require __DIR__ . '/../public/api/StampService.php';   // program_earns_stamps, recalc_stamps_safe

date_default_timezone_set('Asia/Ho_Chi_Minh');          // như public/api/_bootstrap.php

// ----- Tham số dòng lệnh -----
$apply   = in_array('--apply', $argv, true);
$opt = function (string $name) use ($argv): ?string {
    foreach ($argv as $a) {
        if (strpos($a, "--$name=") === 0) return substr($a, strlen($name) + 3);
    }
    return null;
};
$from    = $opt('from');        // YYYY-MM-DD (lọc theo session_date >=)
$to      = $opt('to');          // YYYY-MM-DD (lọc theo session_date <=)
$progArg = $opt('program');     // chỉ một chương trình

foreach (['from' => $from, 'to' => $to] as $k => $v) {
    if ($v !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        fwrite(STDERR, "Tham số --$k phải dạng YYYY-MM-DD.\n");
        exit(1);
    }
}

// ----- Lấy bản ghi cần soát -----
$where  = [];
$params = [];
if ($from !== null)    { $where[] = 'a.session_date >= ?'; $params[] = $from; }
if ($to !== null)      { $where[] = 'a.session_date <= ?'; $params[] = $to; }
if ($progArg !== null) { $where[] = 'a.program_id = ?';    $params[] = (int) $progArg; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = db_all(
    "SELECT a.id, a.student_id, a.year_id, a.program_id, a.session_date,
            a.status, a.marked_at, p.start_time, p.cutoff_time, p.name AS prog_name
       FROM attendances a
       JOIN programs p ON p.id = a.program_id
       $whereSql
      ORDER BY a.session_date, a.id", $params);

echo "Soát " . count($rows) . " bản ghi điểm danh"
   . ($from || $to ? " (từ " . ($from ?: '…') . " đến " . ($to ?: '…') . ")" : '')
   . ($progArg !== null ? " · chương trình #$progArg" : '') . "\n";

$toPresent = [];   // id: đang 'đi trễ' nhưng đúng ra 'có mặt'
$toLate    = [];   // id: đang 'có mặt' nhưng đúng ra 'đi trễ'
$affected  = [];   // "studentId|yearId" => [studentId, yearId]
$samples   = [];

foreach ($rows as $r) {
    // Giờ chốt thật của buổi (cutoff_time, trống mới start+30') — khớp server.
    $correct = attendance_expected_status($r['marked_at'], $r, $r['session_date']);
    if ($correct === $r['status']) continue;

    if ($correct === 'có mặt') $toPresent[] = (int) $r['id'];
    else                       $toLate[]    = (int) $r['id'];

    $affected[$r['student_id'] . '|' . $r['year_id']] = [(int) $r['student_id'], (int) $r['year_id']];
    if (count($samples) < 15) {
        $samples[] = sprintf('  #%-6d %s · %s · bấm %s · %s → %s',
            $r['id'], $r['session_date'], $r['prog_name'],
            substr($r['marked_at'], 11, 5), $r['status'], $correct);
    }
}

$total = count($toPresent) + count($toLate);
echo "Sai cần sửa: $total  (đi trễ→có mặt: " . count($toPresent)
   . ", có mặt→đi trễ: " . count($toLate) . ")\n";
echo "Số em bị ảnh hưởng: " . count($affected) . "\n";
if ($samples) echo "Ví dụ:\n" . implode("\n", $samples) . "\n";

if ($total === 0) { echo "✓ Không có gì để sửa.\n"; exit(0); }

if (!$apply) {
    echo "\n(CHẠY THỬ — chưa ghi gì.) Thêm --apply để sửa thật. Nên sao lưu DB trước.\n";
    exit(0);
}

// ----- GHI THẬT -----
echo "\nĐang sửa...\n";
db()->beginTransaction();
try {
    foreach ([['có mặt', $toPresent], ['đi trễ', $toLate]] as [$st, $ids]) {
        foreach (array_chunk($ids, 500) as $lo) {
            $ph = implode(',', array_fill(0, count($lo), '?'));
            db_run("UPDATE attendances SET status = ? WHERE id IN ($ph)", array_merge([$st], $lo));
        }
    }
    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    fwrite(STDERR, "LỖI, đã hoàn tác: " . $e->getMessage() . "\n");
    exit(1);
}
echo "  ✓ Đã cập nhật $total bản ghi.\n";

// ----- Tính lại Sổ Mộc cho các em bị ảnh hưởng -----
echo "Tính lại Sổ Mộc cho " . count($affected) . " lượt em-năm...\n";
$okCnt = 0;
foreach ($affected as [$sid, $yid]) {
    if (recalc_stamps_safe($sid, $yid)) $okCnt++;
}
echo "  ✓ Tính lại xong ($okCnt thành công).\n";
echo "✓ HOÀN TẤT.\n";
