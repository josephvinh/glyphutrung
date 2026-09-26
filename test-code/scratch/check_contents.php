<?php
$files = glob(__DIR__ . '/../views/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    if (strpos($content, 'class="contents"') !== false) {
        echo basename($file) . " still has class='contents'\n";
    }
}
echo "Checked all.\n";
