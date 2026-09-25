<?php
/**
 * REPORT CONTROLLER
 *
 * Xu ly cac bao cao: diem danh, hoc sinh, thong ke
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/reports.php';

/**
 * Report Controller
 */
class ReportController extends BaseController
{
    /**
     * Bao cao diem danh
     * GET /api/reports/attendance
     */
    public function attendance(array $params = []): void
    {
        $_GET['action'] = 'attendance';
        require __DIR__ . '/../../public/api/reports.php';
        exit;
    }

    /**
     * Bao cao hoc sinh
     * GET /api/reports/students
     */
    public function students(array $params = []): void
    {
        $_GET['action'] = 'students';
        require __DIR__ . '/../../public/api/reports.php';
        exit;
    }
}
