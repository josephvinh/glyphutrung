<?php
/** Sửa nháy cong trong _bootstrap.php */
$f = __DIR__ . '/../public/api/_bootstrap.php';
$c = file_get_contents($f);

// U+2018 LEFT SINGLE QUOTATION MARK
// U+2019 RIGHT SINGLE QUOTATION MARK
$bad = ["\xE2\x80\x98", "\xE2\x80\x99"];
$good = "'";
$c2 = str_replace($bad, $good, $c);

$changed = $c2 !== $c;
if ($changed) {
    file_put_contents($f, $c2);
    echo "Đã sửa. Tổng ký tự: " . strlen($c2) . "\n";
} else {
    echo "Không có nháy cong để sửa.\n";
}

// Kiểm tra syntax PHP
$out = shell_exec('"G:\xampp\php\php.exe" -l "' . $f . '" 2>&1');
echo $out;
