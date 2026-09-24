<?php
$c = file_get_contents(__DIR__ . '/../public/assets/js/bundle.min.js');

// Find editArticle
$p = strpos($c, 'editArticle');
echo "editArticle at offset $p:\n";
echo substr($c, $p, 300);
echo "\n\n";

// Find openCompose  
$p2 = strpos($c, 'openCompose');
echo "openCompose at offset $p2:\n";
echo substr($c, $p2, 200);
echo "\n\n";

// Check if libViewer.open=false appears before openCompose in editArticle
$chunk = substr($c, $p, 1000);
if (strpos($chunk, 'libViewer') !== false) {
    echo "editArticle DOES reference libViewer\n";
} else {
    echo "editArticle does NOT reference libViewer\n";
}
