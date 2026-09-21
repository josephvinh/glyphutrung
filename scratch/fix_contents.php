<?php
$files = glob(__DIR__ . '/../views/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Replace class="contents" with class="inline-flex" for all span tags that have x-show
    $newContent = preg_replace('/<span([^>]*x-show="[^"]*"[^>]*)class="contents"([^>]*)>/', '<span$1class="inline-flex items-center justify-center"$2>', $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Fixed contents class in: " . basename($file) . "\n";
    }
}
echo "Done.\n";
