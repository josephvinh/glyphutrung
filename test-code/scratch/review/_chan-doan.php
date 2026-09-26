<?php
/* CHẨN ĐOÁN — chỉ ĐỌC, không sửa. Dán vào public_html, mở ?run=1, xong XOÁ. */
if (($_GET['run'] ?? '') !== '1') exit("Them ?run=1 vao cuoi dia chi.");
header('Content-Type: text/plain; charset=utf-8');

$f1 = __DIR__ . '/api/org.php';
echo "===== api/org.php =====\n";
if (!is_file($f1)) { echo "KHONG THAY TEP\n"; }
else {
    $L = file($f1, true);
    echo count($L) . " dong | sua lan cuoi: " . date('Y-m-d H:i:s', filemtime($f1)) . "\n\n";
    foreach ($L as $i => $l) {
        if (preg_match("/require_permission|case '(approveMember|rejectMember|resetPassword)'|SELECT id, role_code, full_name|STAFF_ACTIONS/", $l)) {
            printf("%4d| %s", $i + 1, rtrim($l, "\r\n") . "\n");
        }
    }
}

$f2 = __DIR__ . '/../views/module_staff.php';
echo "\n===== views/module_staff.php =====\n";
if (!is_file($f2)) { echo "KHONG THAY TEP\n"; }
else {
    $L2 = file($f2, true);
    echo count($L2) . " dong | sua lan cuoi: " . date('Y-m-d H:i:s', filemtime($f2)) . "\n\n";
    foreach ($L2 as $i => $l) {
        if (strpos($l, 'pendingMembers.length') !== false) {
            printf("%4d| %s", $i + 1, rtrim($l, "\r\n") . "\n");
        }
    }
}
echo "\n===== XONG =====\nChup man hinh gui lai, roi XOA tep _chan-doan.php\n";
