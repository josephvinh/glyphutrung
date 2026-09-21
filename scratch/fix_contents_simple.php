<?php
$files = glob(__DIR__ . '/../views/*.php');
$count = 0;
foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = str_replace('class="contents"', 'class="inline-flex items-center justify-center"', $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Fixed contents in: " . basename($file) . "\n";
        $count++;
    }
}
echo "Done. Fixed $count files.\n";
