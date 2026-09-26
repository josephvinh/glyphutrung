<?php
$files = glob(__DIR__ . '/../views/*.php');
foreach ($files as $file) {
    $content = file_get_contents($file);
    // Find <i x-show="..." ...></i> and wrap with <span x-show="..." class="contents"><i>...</i></span>
    // Note: the original <i> should no longer have x-show or style="display: none;"
    $newContent = preg_replace_callback(
        '/<i\s+((?:x-[a-z:-]+="[^"]*"\s*|style="display:\s*none;?"\s*)+)(.*?)><\/i>/',
        function($matches) {
            $alpineAttrs = trim($matches[1]);
            // Remove style="display: none;" from the alpineAttrs if we want, or just move it
            $innerAttrs = trim($matches[2]);
            return "<span $alpineAttrs class=\"contents\"><i $innerAttrs></i></span>";
        },
        $content
    );
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Fixed: " . basename($file) . "\n";
    }
}
echo "Done.\n";
