<?php
/**
 * Check for property conflicts between modules.
 */
$manifest = require __DIR__ . '/../public/assets/asset_manifest.php';
$base = __DIR__ . '/../public/assets/js/modules/';

$props = [];
$conflicts = [];

foreach ($manifest['js_modules'] as $m) {
    $f = $base . $m . '.js';
    if (!is_file($f)) continue;
    $c = file_get_contents($f);

    // Extract property/function names (simple regex)
    preg_match_all('/^\s+(?:get\s+)?([\w]+)\s*[:(]/m', $c, $matches);
    foreach ($matches[1] as $name) {
        if (in_array($name, ['async', 'function', 'const', 'let', 'var', 'if', 'for', 'return', 'new', 'this'])) continue;
        if (!isset($props[$name])) $props[$name] = [];
        $props[$name][] = $m;
    }
}

echo "=== Property conflicts between modules ===\n";
foreach ($props as $name => $modules) {
    if (count($modules) > 1) {
        echo "CONFLICT: '$name' defined in " . implode(', ', $modules) . "\n";
        $conflicts[] = $name;
    }
}

if (!$conflicts) {
    echo "No conflicts found.\n";
}

echo "\n=== All module properties ===\n";
foreach ($props as $name => $modules) {
    $count = count($modules);
    echo "$name: " . ($count > 1 ? "CONFLICT($count)" : $modules[0]) . "\n";
}
