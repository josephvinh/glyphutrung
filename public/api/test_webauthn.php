<?php
require_once __DIR__ . '/webauthn/WebAuthn.php';
$WebAuthn = new \lbuchs\WebAuthn\WebAuthn('TNTT Super App', 'localhost');
$createArgs = $WebAuthn->getCreateArgs('123', '0987654321', 'Test Name', 60*4, true);
echo json_encode($createArgs, JSON_PRETTY_PRINT);
