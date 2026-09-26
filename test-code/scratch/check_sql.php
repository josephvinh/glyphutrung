<?php
$file = 'g:/xampp/htdocs/tntt/ylcqukhi_glyphutrung_updated.sql';
$content = file_get_contents($file);
if (strpos($content, 'du_bi') !== false) {
    $lines = explode("\n", $content);
    foreach ($lines as $line) {
        if (strpos($line, 'INSERT INTO `roles`') !== false || strpos($line, 'INSERT INTO `titles`') !== false) {
            echo $line . "\n";
        }
    }
}
