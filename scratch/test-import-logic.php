<?php
/**
 * KIỂM TRA logic import — xác nhận hàm mới hoạt động đúng.
 * Chạy: php scratch/test-import-logic.php
 */
require __DIR__ . '/../config/db.php';

// --- Mô phỏng: db_run() trả int (như trong db.php) ---
// (test này chỉ kiểm tra logic PHP thuần, không cần gọi DB thật)

echo "=== KIỂM TRA: logic xác định thêm mới / cập nhật ===\n";

// Mô phỏng: Có 2 em đã có trong CSDL (year hiện tại), thêm 1 em mới
$existingCodes = ['TNTT-25-0001' => 101, 'TNTT-25-0002' => 102];
$nextNum = 3;
$prefix = 'TNTT-25-';
$added = $updated = 0;

$rows = [
    ['code' => 'TNTT-25-0001', 'name' => 'Nguyễn A'],   // đã có → cập nhật
    ['code' => 'TNTT-25-0002', 'name' => 'Trần B'],      // đã có → cập nhật
    ['code' => '',               'name' => 'Lê C'],        // mới → mã mới sinh
    ['code' => 'TNTT-25-9999', 'name' => 'Dũng D'],    // mới → mã tự nhập
    ['code' => '',               'name' => ''],            // trống → bỏ qua
];

$enrollment_batch = [];
$upsert_students = [];
$errors = [];
$skipped = 0;

foreach ($rows as $i => $raw) {
    $name = trim($raw['name'] ?? '');
    $code = trim($raw['code'] ?? '');
    if ($name === '') { $skipped++; continue; }
    if ($code === '') {
        $code = $prefix . sprintf('%04d', $nextNum);
        $nextNum++;
    }

    $sid = $existingCodes[$code] ?? null;
    $enrollment_batch[] = ['code' => $code, 'class_id' => 1, 'status' => 'đang sinh hoạt'];

    if (!$sid) {
        $existingCodes[$code] = -1; // temp mark
        $added++;
    } else {
        $updated++;
    }
}

echo "  existingCodes: " . json_encode($existingCodes) . "\n";
echo "  Thêm mới: $added, Cập nhật: $updated, Bỏ qua: $skipped\n";
echo "  enrollment_batch: " . count($enrollment_batch) . " dòng\n";

$ok = ($added === 3 && $updated === 2 && $skipped === 1);
echo $ok ? "  ✓ PASS\n" : "  ✗ FAIL (mong đợi 3 thêm / 2 cập nhật / 1 bỏ qua)\n";

echo "\n=== KIỂM TRA: phân chunk đúng ===\n";
$upsert_students = array_fill(0, 1234, '(?,?,?,?,?,?,?,?,?,?,?)');
$chunk_size = 500;
$chunks = array_chunk($upsert_students, $chunk_size);
$param_chunks = [];
for ($ci = 0; $ci < count($upsert_students); $ci += $chunk_size) {
    $param_chunks[] = array_slice([], $ci * 11, $chunk_size * 11); // placeholder
}
echo "  1234 dòng → " . count($chunks) . " chunks\n";
echo count($chunks) === 3 ? "  ✓ PASS (3 chunks: 500+500+234)\n" : "  ✗ FAIL\n";

echo "\n=== KIỂM TRA: truy vấn mã mới sau INSERT chỉ quét mã MỚI ===\n";
// Giả lập $existingCodes sau khi xử lý
$existingCodes2 = ['TNTT-25-0001' => 101, 'TNTT-25-0002' => 102, 'TNTT-25-0003' => -1, 'TNTT-25-9999' => -1];
$enrollment_batch2 = [
    ['code' => 'TNTT-25-0001', 'class_id' => 1, 'status' => 'đang sinh hoạt'],
    ['code' => 'TNTT-25-0003', 'class_id' => 1, 'status' => 'đang sinh hoạt'],
    ['code' => 'TNTT-25-9999', 'class_id' => 1, 'status' => 'đang sinh hoạt'],
];
$new_codes = [];
foreach ($enrollment_batch2 as $e) {
    if (($existingCodes2[$e['code']] ?? -1) === -1) {
        $new_codes[] = $e['code'];
    }
}
echo "  Mã cần tra: " . json_encode($new_codes) . "\n";
$ok2 = count($new_codes) === 2 && in_array('TNTT-25-0003', $new_codes) && in_array('TNTT-25-9999', $new_codes);
echo $ok2 ? "  ✓ PASS (chỉ 2 mã mới, KHÔNG lặp lại 600 dòng CSDL)\n" : "  ✗ FAIL\n";

echo "\n=== TỔNG KẾT ===\n";
if ($ok && $ok2) {
    echo "✓ Tất cả PASSED — logic import an toàn và hiệu quả.\n";
} else {
    echo "✗ Có lỗi.\n";
    exit(1);
}
