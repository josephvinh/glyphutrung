<?php
/**
 * STUDENT CONTROLLER
 *
 * Xu ly cac thao tac CRUD cho hoc sinh
 * Tang them mot layer giua Router va logic nghiep vu
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/students.php';

/**
 * Student Controller - proxy sang existing endpoint logic
 *
 * Vi he thong hien tai su dung endpoint files truc tiep,
 * Controller nay cung cap interface huong doi tuong
 * cho Router va backward compatibility
 */
class StudentController extends BaseController
{
    /**
     * Lay danh sach hoc sinh
     * GET /api/students
     */
    public function index(array $params = []): void
    {
        // Delegate to existing endpoint
        // Hien tai chi support action= parameter
        require_once __DIR__ . '/../../public/api/students.php';
        // Endpoint files su dung switch(action) nen can goi nhu cu
        $action = $_GET['action'] ?? '';
        // Neu la list, tra ve danh sach hoc sinh
        if ($action === 'list' || empty($action)) {
            // Forward sang endpoint hien tai
            $this->forwardToEndpoint('list');
        }
    }

    /**
     * Tao moi hoc sinh
     * POST /api/students
     */
    public function store(array $params = []): void
    {
        $_GET['action'] = 'save';
        $this->forwardToEndpoint('save');
    }

    /**
     * Chi tiet hoc sinh
     * GET /api/students/:id
     */
    public function show(array $params): void
    {
        $_GET['studentId'] = $params[0] ?? null;
        $this->forwardToEndpoint('detail');
    }

    /**
     * Cap nhat hoc sinh
     * PUT /api/students/:id
     */
    public function update(array $params): void
    {
        $_GET['action'] = 'save';
        $this->forwardToEndpoint('save');
    }

    /**
     * Xoa hoc sinh
     * DELETE /api/students/:id
     */
    public function destroy(array $params): void
    {
        $_GET['action'] = 'delete';
        $_GET['id'] = $params[0] ?? null;
        $this->forwardToEndpoint('delete');
    }

    /**
     * Ma hoc sinh tiep theo
     * GET /api/students/next-code
     */
    public function next_code(array $params = []): void
    {
        $_GET['action'] = 'next_code';
        $this->forwardToEndpoint('next_code');
    }

    /**
     * Nhap hoc sinh tu file
     * POST /api/students/import
     */
    public function import(array $params = []): void
    {
        $_GET['action'] = 'import';
        $this->forwardToEndpoint('import');
    }

    /**
     * Forward request sang existing endpoint file
     * Giu nguyen backward compatibility
     */
    private function forwardToEndpoint(string $action): never
    {
        $_GET['action'] = $action;
        // Include endpoint file - no se handle request nhu cu
        require __DIR__ . '/../../public/api/students.php';
        exit; // Khong bao gio chay toi day
    }
}
