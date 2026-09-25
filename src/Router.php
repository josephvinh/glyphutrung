<?php
/**
 * ROUTER - Lightweight REST Router cho TNTT API
 *
 * Cung cap:
 * - GET, POST, PUT, DELETE methods
 * - Middleware support
 * - Route parameters (/students/:id)
 * - Backward compatibility voi existing endpoints
 */

namespace TNTT;

class Router
{
    /** @var array<string, array{pattern: string, handler: callable, method: string, middleware: array}> */
    private static array $routes = [];

    /** @var array<callable> Global middleware */
    private static array $globalMiddleware = [];

    /** Khoi dong router voi cac routes mac dinh */
    public static function init(): void
    {
        require_once __DIR__ . '/Controllers/StudentController.php';
        require_once __DIR__ . '/Controllers/AttendanceController.php';
        require_once __DIR__ . '/Controllers/ProgramController.php';
        require_once __DIR__ . '/Controllers/ClassController.php';
        require_once __DIR__ . '/Controllers/ReportController.php';
        require_once __DIR__ . '/Controllers/DataController.php';
        require_once __DIR__ . '/Controllers/AuthController.php';

        self::registerApiRoutes();
    }

    /**
     * Dang ky tat ca API routes
     */
    private static function registerApiRoutes(): void
    {
        $ctrl = '\\TNTT\\Controllers\\';

        // Students routes
        self::get('/api/students', [$ctrl . 'StudentController', 'index']);
        self::post('/api/students', [$ctrl . 'StudentController', 'store']);
        self::get('/api/students/next-code', [$ctrl . 'StudentController', 'next_code']);
        self::post('/api/students/import', [$ctrl . 'StudentController', 'import']);

        // Attendance routes
        self::post('/api/attendance/toggle', [$ctrl . 'AttendanceController', 'toggle']);
        self::get('/api/attendance/lookup', [$ctrl . 'AttendanceController', 'lookup']);
        self::post('/api/attendance/scan', [$ctrl . 'AttendanceController', 'scan']);

        // Programs routes
        self::get('/api/programs', [$ctrl . 'ProgramController', 'index']);
        self::post('/api/programs', [$ctrl . 'ProgramController', 'store']);

        // Classes routes
        self::get('/api/classes', [$ctrl . 'ClassController', 'index']);
        self::post('/api/classes', [$ctrl . 'ClassController', 'store']);

        // Reports routes
        self::get('/api/reports/attendance', [$ctrl . 'ReportController', 'attendance']);
        self::get('/api/reports/students', [$ctrl . 'ReportController', 'students']);

        // Data routes
        self::get('/api/data', [$ctrl . 'DataController', 'index']);

        // Auth routes
        self::post('/api/auth/login', [$ctrl . 'AuthController', 'login']);
        self::post('/api/auth/logout', [$ctrl . 'AuthController', 'logout']);
        self::get('/api/auth/me', [$ctrl . 'AuthController', 'me']);
    }

    /** Dang ky global middleware */
    public static function use(callable $middleware): void
    {
        self::$globalMiddleware[] = $middleware;
    }

    /**
     * Dang ky mot route
     *
     * @param string   $method   HTTP method (GET, POST, PUT, DELETE)
     * @param string   $path     Route path (vd: '/students', '/students/:id')
     * @param callable $handler  Callback xu ly (Controller@method hoac closure)
     * @param array    $middleware Middleware cho route nay
     */
    public static function register(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $pattern = self::pathToRegex($path);
        self::$routes[] = [
            'method'    => strtoupper($method),
            'path'      => $path,
            'pattern'   => $pattern,
            'handler'   => $handler,
            'middleware'=> $middleware,
        ];
    }

    /** Shorthand methods */
    public static function get(string $path, callable $handler, array $middleware = []): void
    {
        self::register('GET', $path, $handler, $middleware);
    }

    public static function post(string $path, callable $handler, array $middleware = []): void
    {
        self::register('POST', $path, $handler, $middleware);
    }

    public static function put(string $path, callable $handler, array $middleware = []): void
    {
        self::register('PUT', $path, $handler, $middleware);
    }

    public static function delete(string $path, callable $handler, array $middleware = []): void
    {
        self::register('DELETE', $path, $handler, $middleware);
    }

    /**
     * Dispatch request toi route phu hop
     *
     * @param string|null $method  Override method (cho backward compatibility)
     * @param string|null $path    Override path
     * @return mixed
     */
    public static function dispatch(?string $method = null, ?string $path = null): mixed
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = $path ?? parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Chay global middleware truoc
        foreach (self::$globalMiddleware as $mw) {
            $result = $mw();
            if ($result === false) return false;
        }

        // Tim route phu hop
        foreach (self::$routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;

            $params = self::matchPath($route['pattern'], $path);
            if ($params !== null) {
                // Chay route middleware
                foreach ($route['middleware'] as $mw) {
                    $result = $mw();
                    if ($result === false) return false;
                }

                // Goi handler
                return ($route['handler'])($params);
            }
        }

        // Khong tim thay route
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Route not found: ' . $method . ' ' . $path], JSON_UNESCAPED_UNICODE);
        return false;
    }

    /**
     * Chuyen path thanh regex
     * /students/:id -> /^\\/students\\/([^\\/]+)$/
     */
    private static function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/:[a-zA-Z_][a-zA-Z0-9_]*/', '([^\\/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Kiem tra path co khop voi pattern khong
     *
     * @return array|null Tra ve params neu khop, null neu khong
     */
    private static function matchPath(string $pattern, string $path): ?array
    {
        if (preg_match($pattern, $path, $matches)) {
            array_shift($matches); // Loai bo full match
            return $matches;
        }
        return null;
    }

    /**
     * Tao route cho mot Controller
     *
     * @param string $basePath   Base path (vd: '/api/students')
     * @param string $controller Controller class name
     * @param array  $routes    Map action -> HTTP method
     *                          VD: ['index' => 'GET', 'store' => 'POST', 'show' => 'GET/:id']
     */
    public static function controller(string $basePath, string $controller, array $routes): void
    {
        foreach ($routes as $action => $methodSpec) {
            if (is_int($action)) {
                // Chi co method, action = method name (vi du: 'index' => 'GET')
                $action = $methodSpec;
                $method = $action === 'index' ? 'GET' : 'POST';
            } else {
                // MethodSpec co the la 'GET', 'POST', hoac 'GET/:id'
                if (strpos($methodSpec, '/') !== false) {
                    [$method, $suffix] = explode('/', $methodSpec, 2);
                    $path = $basePath . '/' . $suffix;
                } else {
                    $method = $methodSpec;
                    $path = $basePath;
                }
            }

            $handler = [$controller, $action];
            self::register($method, $path, $handler);
        }
    }

    /** Lay danh sach routes (cho debugging) */
    public static function getRoutes(): array
    {
        return self::$routes;
    }
}
