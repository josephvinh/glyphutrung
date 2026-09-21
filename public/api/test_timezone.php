<?php
/**
 * Debug timezone - xoá sau khi xong
 */
date_default_timezone_set('Asia/Ho_Chi_Minh');
echo json_encode([
    'ok' => true,
    'timezone' => date_default_timezone_get(),
    'time' => date('Y-m-d H:i:s'),
    'request_time' => date('H:i', $_SERVER['REQUEST_TIME'] ?? time()),
]);
