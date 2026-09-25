<?php
/**
 * AUTH CONTROLLER
 *
 * Xu ly dang nhap, dang xuat, thong tin nguoi dung
 */

namespace TNTT\Controllers;

require_once __DIR__ . '/../../public/api/auth.php';

/**
 * Auth Controller
 */
class AuthController extends BaseController
{
    /**
     * Dang nhap
     * POST /api/auth/login
     */
    public function login(array $params = []): never
    {
        $_GET['action'] = 'login';
        require __DIR__ . '/../../public/api/auth.php';
        exit;
    }

    /**
     * Dang xuat
     * POST /api/auth/logout
     */
    public function logout(array $params = []): never
    {
        $_GET['action'] = 'logout';
        require __DIR__ . '/../../public/api/auth.php';
        exit;
    }

    /**
     * Thong tin nguoi dung hien tai
     * GET /api/auth/me
     */
    public function me(array $params = []): never
    {
        $_GET['action'] = 'me';
        require __DIR__ . '/../../public/api/auth.php';
        exit;
    }
}
