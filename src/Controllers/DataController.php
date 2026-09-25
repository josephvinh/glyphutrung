<?php
/**
 * DATA CONTROLLER
 *
 * Cung cap du lieu cho frontend: hoc sinh, lop, chuong trinh
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/data.php';

/**
 * Data Controller
 */
class DataController extends BaseController
{
    /**
     * Lay du lieu cho frontend
     * GET /api/data
     */
    public function index(array $params = []): never
    {
        require __DIR__ . '/../../public/api/data.php';
        exit;
    }
}
