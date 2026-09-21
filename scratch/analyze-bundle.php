<?php
/**
 * Check if bundle contains actual source code (concatenated) or just old minified version.
 */
$c = file_get_contents(__DIR__ . '/../public/assets/js/bundle.min.js');

// Check for source code indicators (full names, readable patterns)
$sourceIndicators = [
    'libLoadCategories' => 'libLoadCategories',
    '_libLoad' => '_libLoad',
    'editFileItem' => 'editFileItem',
    'openCatManager' => 'openCatManager',
    'libMore' => 'libMore',
    'libSaveCategory' => 'libSaveCategory',
    'libToggleCategory' => 'libToggleCategory',
];

echo "=== Source code presence ===\n";
$found = 0;
$total = count($sourceIndicators);
foreach ($sourceIndicators as $label => $pattern) {
    $pos = strpos($c, $pattern);
    echo ($pos !== false ? '[YES] ' : '[NO]  ') . "$pattern (at " . ($pos ?: 'N/A') . ")\n";
    if ($pos !== false) $found++;
}

echo "\n=== Bundle size ===\n";
echo number_format(strlen($c)) . " bytes\n";
echo "Source modules: 282 KB\n";
echo "Ratio: " . round(strlen($c) / 282102 * 100) . "%\n";

echo "\n=== Checking if bundle is OLD minified ===\n";
// Old minified would have patterns like "return null" instead of "return!1"
$oldPatterns = ['return!1', 'return!0', 'open:!1', 'open:!0', 'loading:!1'];
$newPatterns = ['return!1' => false, 'return!0' => false, 'open:!1' => false, 'open:!0' => false, 'lib:{' => false];
foreach ($newPatterns as $k => $v) {
    $newPatterns[$k] = strpos($c, $k) !== false;
}
$allOld = $newPatterns['return!1'] || $newPatterns['return!0'];
$allNew = !$newPatterns['return!1'] && !$newPatterns['return!0'];
echo "Has 'return!1' (old minifier style): " . ($newPatterns['return!1'] ? 'YES' : 'NO') . "\n";
echo "Has 'lib:{' (new style): " . (strpos($c, 'lib:{') !== false ? 'YES' : 'NO') . "\n";

// Count unique function definitions
preg_match_all('/function\s+\w+/', $c, $funcMatches);
preg_match_all('/\w+\s*=\s*function/', $c, $arrowMatches);
echo "\nFunction definitions in bundle: " . (count($funcMatches[0]) + count($arrowMatches[0])) . "\n";

// Check what IS in the bundle that's the old library code
echo "\n=== Old vs new library code ===\n";
$oldLib = strpos($c, 'TNTT.library={lib:{categories:[],items:[],mineItems:[],pending:[],filter:0,q:"",loading:!1,tab:"all"}');
$newLib = strpos($c, 'TNTT.library={');
echo "Old single-line library init: " . ($oldLib !== false ? "YES (offset $oldLib)" : "NO") . "\n";
echo "General library init: " . ($newLib !== false ? "YES" : "NO") . "\n";

// Check if library object has all methods
$libraryMethods = ['loadLibrary', 'libRefresh', 'openCompose', 'editArticle', 'libApprove', 'libReject'];
$methodsFound = 0;
foreach ($libraryMethods as $m) {
    $pos = strpos($c, $m);
    echo "  $m: " . ($pos !== false ? "YES" : "NO") . "\n";
    if ($pos !== false) $methodsFound++;
}

echo "\n=== VERDICT ===\n";
echo "Functions found in bundle: $found / $total\n";
echo "Bundle appears to be: " . (strlen($c) < 150000 ? "OLD minified version" : "NEWER") . "\n";
