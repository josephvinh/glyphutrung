<?php
/**
 * VÁ LỖI DUYỆT THÀNH VIÊN — chạy 1 lần rồi XOÁ.
 *
 * CÁCH DÙNG:
 *   1. Vào cPanel → File Manager → thư mục public_html (nơi có index.php)
 *   2. Tạo tệp mới tên:  _va-loi.php   → dán toàn bộ tệp này → Lưu
 *   3. Mở trình duyệt:   https://ten-mien-cua-ban/_va-loi.php?run=1
 *   4. Đọc kết quả, rồi XOÁ NGAY tệp _va-loi.php
 *
 * Script tự sao lưu trước khi sửa, và tự bỏ qua nếu đã vá rồi.
 */

if (($_GET['run'] ?? '') !== '1') {
    header('Content-Type: text/plain; charset=utf-8');
    exit("Script vá lỗi duyệt thành viên.\n\nThêm ?run=1 vào cuối địa chỉ để chạy.\nXong thì XOÁ tệp này.");
}
header('Content-Type: text/plain; charset=utf-8');

$root = __DIR__;
$log  = [];
$ok   = true;

/* ------------------------------------------------------------------
   TỆP 1 — api/org.php
   ------------------------------------------------------------------ */
$f1 = $root . '/api/org.php';
if (!is_file($f1)) { $ok = false; $log[] = "KHÔNG THẤY: api/org.php"; }
else {
    $src = file_get_contents($f1);
    $eol = (strpos($src, "\r\n") !== false) ? "\r\n" : "\n";
    $w   = str_replace("\r\n", "\n", $src);

    /* --- Sửa 1: cổng phân quyền ------------------------------------ */
    $old1 = "\$me   = require_permission('org', 'edit');\n"
          . "\$year = current_year();\n"
          . "if (!\$year) json_fail('Chưa có niên khoá nào đang mở.', 409);\n"
          . "\n"
          . "\$yid    = (int) \$year['id'];\n"
          . "\$action = \$_GET['action'] ?? '';";
    $new1 = "\$action = \$_GET['action'] ?? '';\n"
          . "\n"
          . "// Duyệt tài khoản + quản lý nhân sự thuộc module Nhân sự (staff).\n"
          . "// Còn lại (khối, lớp, chủ nhiệm/trưởng khối) thuộc module Tổ chức (org).\n"
          . "\$STAFF_ACTIONS = ['saveMember', 'deleteMember', 'approveMember', 'rejectMember', 'resetPassword'];\n"
          . "\$me   = in_array(\$action, \$STAFF_ACTIONS, true)\n"
          . "    ? require_permission('staff', 'edit')\n"
          . "    : require_permission('org', 'edit');\n"
          . "\n"
          . "\$year = current_year();\n"
          . "if (!\$year) json_fail('Chưa có niên khoá nào đang mở.', 409);\n"
          . "\n"
          . "\$yid = (int) \$year['id'];";

    if (strpos($w, 'STAFF_ACTIONS') !== false) {
        $log[] = "[1/5] cổng phân quyền .......... ĐÃ VÁ RỒI (bỏ qua)";
    } elseif (strpos($w, $old1) !== false) {
        $w = str_replace($old1, $new1, $w);
        $log[] = "[1/5] cổng phân quyền .......... ĐÃ SỬA";
    } else {
        $ok = false;
        $log[] = "[1/5] cổng phân quyền .......... *** KHÔNG KHỚP — không sửa ***";
    }

    /* --- Sửa 2,3,4: SELECT trong 3 case ---------------------------- */
    $sel = "\$m  = db_one('SELECT id, role_code, full_name FROM members WHERE id=?', [\$id]);";
    $cases = [
        'approveMember' => "\$m  = db_one('SELECT id, role_code, full_name, status FROM members WHERE id=?', [\$id]);",
        'rejectMember'  => "\$m  = db_one('SELECT id, role_code, full_name, phone, status FROM members WHERE id=?', [\$id]);",
        'resetPassword' => "\$m  = db_one('SELECT id, role_code, full_name, phone FROM members WHERE id=?', [\$id]);",
    ];
    $n = 1;
    foreach ($cases as $case => $newSel) {
        $n++;
        $marker = "case '$case':";
        $p = strpos($w, $marker);
        if ($p === false) { $ok = false; $log[] = "[$n/5] $case ... *** KHÔNG THẤY CASE ***"; continue; }

        $nx = strpos($w, "\n    case '", $p + strlen($marker));
        $end = ($nx === false) ? strlen($w) : $nx;
        $seg = substr($w, $p, $end - $p);

        if (strpos($seg, $newSel) !== false) { $log[] = "[$n/5] $case ... ĐÃ VÁ RỒI (bỏ qua)"; continue; }
        if (strpos($seg, $sel) === false)    { $ok = false; $log[] = "[$n/5] $case ... *** KHÔNG THẤY SELECT ***"; continue; }

        $w = substr($w, 0, $p) . str_replace($sel, $newSel, $seg) . substr($w, $end);
        $log[] = "[$n/5] $case ... ĐÃ SỬA";
    }

    if ($w !== str_replace("\r\n", "\n", $src)) {
        $bak = $f1 . '.bak-' . date('Ymd-His');
        copy($f1, $bak);
        file_put_contents($f1, ($eol === "\r\n") ? str_replace("\n", "\r\n", $w) : $w);
        $log[] = "      → đã sao lưu: " . basename($bak);
    }
}

/* ------------------------------------------------------------------
   TỆP 2 — views/module_staff.php
   ------------------------------------------------------------------ */
$f2 = $root . '/../views/module_staff.php';
if (!is_file($f2)) { $ok = false; $log[] = "[5/5] KHÔNG THẤY: ../views/module_staff.php"; }
else {
    $s2 = file_get_contents($f2);
    $o2 = '<div x-show="pendingMembers.length > 0" style="display: none;"';
    $n2 = '<div x-show="canManageOrg && pendingMembers.length > 0" style="display: none;"';
    if (strpos($s2, 'canManageOrg && pendingMembers.length') !== false) {
        $log[] = "[5/5] hàng chờ duyệt .......... ĐÃ VÁ RỒI (bỏ qua)";
    } elseif (strpos($s2, $o2) !== false) {
        copy($f2, $f2 . '.bak-' . date('Ymd-His'));
        file_put_contents($f2, str_replace($o2, $n2, $s2));
        $log[] = "[5/5] hàng chờ duyệt .......... ĐÃ SỬA";
    } else {
        $ok = false;
        $log[] = "[5/5] hàng chờ duyệt .......... *** KHÔNG KHỚP — không sửa ***";
    }
}

/* ------------------------------------------------------------------
   KIỂM TRA CÚ PHÁP
   ------------------------------------------------------------------ */
$syntax = 'không kiểm tra được (host tắt shell_exec)';
if (function_exists('shell_exec')) {
    $out = @shell_exec('php -l ' . escapeshellarg($f1) . ' 2>&1');
    if ($out) $syntax = trim($out);
}

echo "===== KẾT QUẢ VÁ LỖI =====\n\n";
echo implode("\n", $log) . "\n\n";
echo "Kiểm tra cú pháp: $syntax\n\n";

if ($ok) {
    echo "==> XONG CA 5 CHO.\n\n";
    echo "KIỂM TRA LẠI:\n";
    echo "  1. Đăng nhập Ban Điều Hành -> Nhân sự -> bấm Duyệt  => phải THÀNH CÔNG\n";
    echo "  2. Đăng nhập Trưởng khối   -> Nhân sự               => KHÔNG còn nút Duyệt\n";
} else {
    echo "==> CÓ CHỖ KHÔNG KHỚP (xem dòng *** ở trên).\n";
    echo "    Tệp trên host khác bản gốc, nên script chỉ sửa được phần khớp.\n";
    echo "    Phần *** PHẢI SỬA TAY. Xem tệp CHI-4-CHO-CAN-SUA.txt để biết cách.\n";
}

$coSua = false;
foreach ($log as $l) { if (strpos($l, '→ đã sao lưu') !== false) { $coSua = true; } }
echo $coSua
    ? "\nCÓ tệp được sửa. Tệp sao lưu (.bak-...) nằm cạnh tệp gốc — giữ lại để khôi phục nếu cần.\n"
    : "\nKHÔNG tệp nào bị sửa (mọi thứ đã vá sẵn hoặc không khớp).\n";
echo "\n!!! XOÁ NGAY tệp _va-loi.php khỏi máy chủ !!!\n";
