<?php
// Bootstrap file for PHPUnit tests
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session for tests
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load shared helpers (without sending HTTP headers — suppress them)
ob_start();
require_once __DIR__ . '/../public/api/_common.php';
require_once __DIR__ . '/../public/api/csrf.php';
ob_end_clean();

// Assignment helpers (Task 3: member_assignments)
require_once __DIR__ . '/../public/api/_bootstrap.php';
