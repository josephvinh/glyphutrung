<?php
/**
 * KIỂM CHỨNG QUYỀN THẬT (scratch, chỉ đọc).
 *
 * Câu hỏi quyết định: vai trưởng khối có được SỬA khối/lớp không?
 *
 * Chuyên gia đọc code khẳng định "org: truong_khoi = view" (dựa vào bản đồ
 * mặc định trong core.js:589) => trưởng khối chỉ xem. Nhưng bản đồ đó chỉ là
 * giá trị MẶC ĐỊNH dùng khi CSDL trống. Quyền thật nằm ở bảng permissions và
 * được nạp vào BOOT.permissions. Script này tính lại đúng như
 * permission_of() trong _bootstrap.php:75-106.
 */
require __DIR__ . '/../config/db.php';

/** Bản sao logic permission_of() — HỢP quyền của vai gốc + mọi vai kiêm nhiệm */
function quyen_cua(array $me, string $moduleKey): string
{
    $assignments = db_all(
        "SELECT role_code FROM member_assignments WHERE member_id = ? AND to_date IS NULL",
        [$me['id']]
    );
    $roles = array_column($assignments, 'role_code');
    $roles[] = $me['role_code'];
    $roles = array_values(array_unique(array_filter($roles)));

    $ph = implode(',', array_fill(0, count($roles), '?'));
    $rows = db_all(
        "SELECT level FROM permissions WHERE module_key = ? AND role_code IN ($ph)",
        array_merge([$moduleKey], $roles)
    );
    $hang = ['none' => 0, 'view' => 1, 'edit' => 2];
    $tot = 'none';
    foreach ($rows as $r) {
        if (($hang[$r['level']] ?? 0) > ($hang[$tot] ?? 0)) $tot = $r['level'];
    }
    return $tot;
}

echo "=== QUYỀN THẬT của từng tài khoản trên module org (Khối lớp) & students (Danh sách) ===\n";
echo str_pad('Tài khoản', 22) . str_pad('Vai gốc', 16) . str_pad('org', 8) . "students\n";

foreach (db_all("SELECT id, full_name, role_code FROM members WHERE status <> 'đã nghỉ' ORDER BY id") as $m) {
    $org = quyen_cua($m, 'org');
    $stu = quyen_cua($m, 'students');
    printf("%-22s%-16s%-8s%s\n",
        mb_substr($m['full_name'], 0, 20), $m['role_code'], $org, $stu);
}

echo "\n=== Bản đồ MẶC ĐỊNH trong core.js (dùng khi CSDL trống) vs CSDL ===\n";
$mac_dinh = [
    'org'      => ['admin' => 'edit', 'bdh' => 'edit', 'truong_khoi' => 'view', 'glv_chu_nhiem' => 'view', 'glv' => 'view'],
    'students' => ['admin' => 'edit', 'bdh' => 'edit', 'truong_khoi' => 'view', 'glv_chu_nhiem' => 'view', 'glv' => 'view'],
    'staff'    => [],
];
foreach ($mac_dinh as $mk => $map) {
    foreach (['truong_khoi', 'bdh'] as $role) {
        $db = db_one('SELECT level FROM permissions WHERE module_key = ? AND role_code = ?', [$mk, $role]);
        $thuc = $db['level'] ?? '(không có dòng)';
        $md = $map[$role] ?? '(không khai)';
        $lech = ($thuc !== $md) ? '  <<< LỆCH' : '';
        printf("  %-9s %-13s mặc định=%-14s CSDL=%-14s%s\n", $mk, $role, $md, $thuc, $lech);
    }
}
