<?php
/**
 * ATTENDANCE CONTROLLER
 *
 * Xu ly cac thao tac diem danh
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/attendance.php';

/**
 * Attendance Controller - proxy sang existing endpoint logic
 */
class AttendanceController extends BaseController
{
    /**
     * Toggle diem danh mot em
     * POST /api/attendance/toggle
     */
    public function toggle(array $params = []): never
    {
        // Endpoint attendance.php xu ly chinh
        require __DIR__ . '/../../public/api/attendance.php';
        exit;
    }

    /**
     * Tra cuu danh sach hoc sinh de diem danh
     * GET /api/attendance/lookup
     */
    public function lookup(array $params = []): never
    {
        $_GET['action'] = 'lookup';
        require __DIR__ . '/../../public/api/attendance.php';
        exit;
    }

    /**
     * Quet QR nhieu em
     * POST /api/attendance/scan
     */
    public function scan(array $params = []): never
    {
        $_GET['action'] = 'scan';
        require __DIR__ . '/../../public/api/attendance.php';
        exit;
    }
}
