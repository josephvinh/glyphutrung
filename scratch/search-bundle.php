<?php
$c = file_get_contents(__DIR__ . '/../public/assets/js/bundle.min.js');

$patterns = ['openLibArticle', 'editArticle', 'openCompose'];
foreach ($patterns as $p) {
    $pos = strpos($c, $p);
    echo "$p: " . ($pos === false ? 'NOT FOUND' : "FOUND at offset $pos") . "\n";
}

// Check index.php openLibArticle call
echo "\nindex.php calls:\n";
$c2 = file_get_contents(__DIR__ . '/../public/index.php');
if (preg_match_all('/\b(editArticle|openLibArticle|openCompose)\b/', $c2, $m)) {
    foreach ($m[0] as $f) echo "  $f\n";
}
