<?php
// Bootstrap file for PHPUnit tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load application config
require_once __DIR__ . '/../config/db.php';
