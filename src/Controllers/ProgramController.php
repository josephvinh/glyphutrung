<?php
/**
 * PROGRAM CONTROLLER
 *
 * Xu ly cac thao tac CRUD cho chuong trinh sinh hoat
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/programs.php';

/**
 * Program Controller
 */
class ProgramController extends BaseController
{
    /**
     * Lay danh sach chuong trinh
     * GET /api/programs
     */
    public function index(array $params = []): void
    {
        $action = $_GET['action'] ?? '';
        if (empty($action)) {
            $_GET['action'] = 'list';
        }
        require __DIR__ . '/../../public/api/programs.php';
        exit;
    }

    /**
     * Tao moi chuong trinh
     * POST /api/programs
     */
    public function store(array $params = []): void
    {
        $_GET['action'] = 'save';
        require __DIR__ . '/../../public/api/programs.php';
        exit;
    }

    /**
     * Chi tiet chuong trinh
     * GET /api/programs/:id
     */
    public function show(array $params): void
    {
        $_GET['id'] = $params[0] ?? null;
        $_GET['action'] = 'detail';
        require __DIR__ . '/../../public/api/programs.php';
        exit;
    }

    /**
     * Cap nhat chuong trinh
     * PUT /api/programs/:id
     */
    public function update(array $params): void
    {
        $_GET['id'] = $params[0] ?? null;
        $_GET['action'] = 'save';
        require __DIR__ . '/../../public/api/programs.php';
        exit;
    }

    /**
     * Xoa chuong trinh
     * DELETE /api/programs/:id
     */
    public function destroy(array $params): void
    {
        $_GET['id'] = $params[0] ?? null;
        $_GET['action'] = 'delete';
        require __DIR__ . '/../../public/api/programs.php';
        exit;
    }
}
