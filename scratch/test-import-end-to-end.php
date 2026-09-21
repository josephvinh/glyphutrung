<?php
/**
 * TEST END-TO-END: thực sự gọi action=import với 1 dòng dữ liệu thật.
 * Tạo một em "TEST_IMPORT_XXX" → xác nhận logic mới hoạt động.
 *
 * Chạy: php scratch/test-import-end-to-end.php
 */
require __DIR__ . '/../config/db.php';

// --- 1. Lấy context: niên khoá, lớp đầu tiên ---
$year = db_one("SELECT * FROM school_years WHERE is_current = 1 LIMIT 1");
if (!$year) { echo "Không có niên khoá.\n"; exit(1); }
$yid = (int) $year['id'];

$firstClass = db_one("SELECT id, name FROM classes ORDER BY id LIMIT 1");
if (!$firstClass) { echo "Không có lớp nào.\n"; exit(1); }
$classId = (int) $firstClass['id'];
$className = $firstClass['name'];
echo "Niên khoá: id=$yid | Lớp thử: $className (id=$classId)\n";

// --- 2. Tạo tài khoản test với quyền admin ---
// Lấy admin đầu tiên
$admin = db_one("SELECT id FROM members WHERE role_code = 'admin' AND status <> 'đã nghỉ' LIMIT 1");
if (!$admin) { echo "Không tìm thấy tài khoản admin.\n"; exit(1); }
session_destroy();
session_start();
$_SESSION['member_id'] = $admin['id'];
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));
echo "Đăng nhập với tài khoản admin (id={$admin['id']})\n";

// --- 3. Tạo file CSV test 1 em mới ---
$testName = 'TEST_IMPORT_' . date('His');
$csv = "Họ và Tên,Giới tính,Ngày sinh,Khối,Lớp,Địa chỉ,Cha,Mẹ,SĐT Cha,SĐT Mẹ\n";
$csv .= "$testName,1,01/01/2015,$className,$className,Test address,Test Father,Test Mother,0900000001,0900000002\n";

echo "Tên test: $testName\n";

// --- 4. Parse CSV (tái tạo logic students.js) ---
function parse_csv(string $text): array {
    $rows = [];
    $lines = preg_split('/\r\n|\n|\r/', $text);
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $fields = [];
        $inQuotes = false;
        $field = '';
        for ($i = 0; $i < strlen($line); $i++) {
            $ch = $line[$i];
            if ($ch === '"') {
                $inQuotes = !$inQuotes;
            } elseif ($ch === ',' && !$inQuotes) {
                $fields[] = $field;
                $field = '';
            } else {
                $field .= $ch;
            }
        }
        $fields[] = $field;
        $rows[] = $fields;
    }
    return $rows;
}

function normalize_text(string $t): string {
    return mb_strtolower(preg_replace('/\s+/', ' ', trim($t)), 'UTF-8');
}

$parsed = parse_csv($csv);
$header = array_map(normalize_text(...), $parsed[0]);
$cols = [
    'name' => array_search('họ và tên', $header),
    'gender' => array_search('giới tính', $header),
    'birthDate' => array_search('ngày sinh', $header),
    'className' => array_search('lớp', $header),
    'address' => array_search('địa chỉ', $header),
    'fatherName' => array_search('cha', $header),
    'motherName' => array_search('mẹ', $header),
    'fatherPhone' => array_search('sđt cha', $header),
    'motherPhone' => array_search('sđt mẹ', $header),
];

// Tạo row object
$raw = [];
foreach ($cols as $key => $idx) {
    if ($idx !== false && isset($parsed[1][$idx])) {
        $raw[$key] = $parsed[1][$idx];
    }
}
echo "Row parse: " . json_encode($raw, JSON_UNESCAPED_UNICODE) . "\n";

$rows_payload = [[
    'name' => $raw['name'],
    'className' => $raw['className'],
    'gender' => (int)($raw['gender'] ?? 1),
    'birthDate' => $raw['birthDate'] ?? '',
    'address' => $raw['address'] ?? '',
    'fatherName' => $raw['fatherName'] ?? '',
    'motherName' => $raw['motherName'] ?? '',
    'fatherPhone' => $raw['fatherPhone'] ?? '',
    'motherPhone' => $raw['motherPhone'] ?? '',
]];

// --- 5. Gọi API ---
$ch = curl_init("http://127.0.0.1:8888/tntt/api/students.php?action=import");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['rows' => $rows_payload]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-CSRF-Token: ' . $_SESSION['csrf_token'],
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "\n=== KẾT QUẢ IMPORT ===\n";
echo "HTTP: $code\n";
if ($err) echo "Lỗi CURL: $err\n";
echo "Body: $res\n";

$data = json_decode($res, true);
if ($data && ($data['ok'] ?? false)) {
    echo "✓ IMPORT THÀNH CÔNG\n";
    echo "  Thêm: {$data['added']} | Cập nhật: {$data['updated']} | Bỏ qua: {$data['skipped']}\n";
    if (!empty($data['errors'])) {
        echo "  Lỗi: " . implode(', ', $data['errors']) . "\n";
    }

    // Cleanup: xóa em test
    $testSid = db_one("SELECT id FROM students WHERE full_name = ?", [$testName]);
    if ($testSid) {
        db_run("DELETE FROM enrollments WHERE student_id = ?", [(int)$testSid['id']]);
        db_run("DELETE FROM students WHERE id = ?", [(int)$testSid['id']]);
        echo "  Đã dọn dẹp em test (id={$testSid['id']})\n";
    }
} else {
    echo "✗ IMPORT THẤT BẠI\n";
    if ($data) {
        echo "  Lỗi: " . ($data['error'] ?? '?') . "\n";
    }
    exit(1);
}
