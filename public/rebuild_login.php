<?php
$passkey = file_get_contents(__DIR__ . '/assets/js/modules/passkey.js');
$login = file_get_contents(__DIR__ . '/assets/js/login.js');
// Simple concatenation for login.min.js to ensure the fix is applied there too
file_put_contents(__DIR__ . '/assets/js/login.min.js', $passkey . "\n;\n" . $login);
echo "Rebuilt login.min.js";
