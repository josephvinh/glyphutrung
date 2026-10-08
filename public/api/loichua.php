<?php
/**
 * LỜI CHÚA HÔM NAY - API Endpoint
 *
 * GET /api/loichua.php?date=YYYY-MM-DD
 *
 * Trả về dữ liệu Lời Chúa theo lịch phụng vụ Việt Nam.
 * Không ghi database, không lưu IP.
 *
 * @see config/loichua.php
 */

require __DIR__ . '/_bootstrap.php';

// Chỉ cho phép GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use GET.']);
    exit;
}

// Lấy date parameter
$date = $_GET['date'] ?? '';

// Validate date
if (empty($date)) {
    // Default to today
    $date = date('Y-m-d');
}

// Validate date format và range
if (!loi_chua_validate_date($date)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'success' => false,
        'error' => 'Invalid date. Use format YYYY-MM-DD within allowed range (30 days back to 7 days forward).',
    ]);
    exit;
}

// Load service
require __DIR__ . '/../../config/loichua.php';

// Get data
$result = loi_chua_get($date);

if ($result === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode([
        'success' => false,
        'error' => 'Không thể lấy dữ liệu lời Chúa cho ngày này.',
    ]);
    exit;
}

// Success response
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300'); // Cache 5 phút

echo json_encode([
    'success' => true,
    ...$result,
]);
