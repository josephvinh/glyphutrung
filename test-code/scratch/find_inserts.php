<?php
$lines = file(__DIR__ . '/../ylcqukhi_glyphutrung_updated.sql');
foreach ($lines as $i => $line) {
    if (strpos($line, 'INSERT INTO') !== false) {
        echo "Line $i: " . substr($line, 0, 100) . "\n";
    }
}
