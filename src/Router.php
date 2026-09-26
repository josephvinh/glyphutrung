<?php
/**
 * ROUTER — Lightweight API Router
 *
 * Features:
 * - RESTful-style routing (GET, POST, PUT, DELETE)
 * - Route parameters (/students/:id)
 * - Grouped routes
 * - Backward compatible with existing action-based endpoints
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/api/v1/students', [StudentController::class, 'index']);
 *   $router->dispatch();
 */
class Router
{
    /** @var array<string, array{callback, methods}>[] */
    private array $routes = [];

    /** @var string Current base path */
    private string $basePath = '';

    /**
     * Set base path for all routes
     */
    public function setBasePath(string $path): self
    {
        $this->basePath = rtrim($path, '/');
        return $this;
    }

    /**
     * Register a GET route
     */
    public function get(string $path, callable|array $callback): self
    {
        return $this->addRoute('GET', $path, $callback);
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable|array $callback): self
    {
        return $this->addRoute('POST', $path, $callback);
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable|array $callback): self
    {
        return $this->addRoute('PUT', $path, $callback);
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable|array $callback): self
    {
        return $this->addRoute('DELETE', $path, $callback);
    }

    /**
     * Register a route for multiple methods
     */
    public function match(array $methods, string $path, callable|array $callback): self
    {
        foreach ($methods as $method) {
            $this->addRoute(strtoupper($method), $path, $callback);
        }
        return $this;
    }

    /**
     * Register a route for all methods
     */
    public function any(string $path, callable|array $callback): self
    {
        return $this->match(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], $path, $callback);
    }

    /**
     * Group routes with common prefix/middleware
     */
    public function group(string $prefix, callable $callback, array $options = []): self
    {
        $originalBasePath = $this->basePath;

        $this->basePath .= rtrim($prefix, '/');

        // Apply middleware if provided
        if (isset($options['middleware']) && is_array($options['middleware'])) {
            // Middleware will be applied to routes in this group
        }

        $callback($this);

        $this->basePath = $originalBasePath;

        return $this;
    }

    /**
     * Add a route
     */
    private function addRoute(string $method, string $path, callable|array $callback): self
    {
        $fullPath = $this->basePath . $path;

        $this->routes[$method][$fullPath] = [
            'callback' => $callback,
            'pattern'  => $this->pathToRegex($fullPath),
        ];

        return $this;
    }

    /**
     * Convert path pattern to regex
     * /students/:id -> /^\\/students\\/([^\\/]+)$/
     */
    private function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/\/(:([a-zA-Z_][a-zA-Z0-9_]*))/', '/(?<$2>[^/]+)', $path);
        $pattern = str_replace('/', '\/', $pattern);
        return '/^' . $pattern . '$/';
    }

    /**
     * Dispatch the current request
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        // Find matching route
        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $path => $route) {
                if (preg_match($route['pattern'], $uri, $matches)) {
                    // Extract named parameters
                    $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                    // Get body for non-GET requests
                    $body = in_array($method, ['POST', 'PUT', 'PATCH'])
                        ? json_decode(file_get_contents('php://input'), true) ?? []
                        : [];

                    // Call the callback
                    $callback = $route['callback'];

                    if (is_array($callback) && is_string($callback[0])) {
                        // Class method: [Controller::class, 'method']
                        $controller = new $callback[0]();
                        $method = $callback[1];
                        echo $controller->$method($params, $body);
                    } elseif (is_callable($callback)) {
                        // Closure
                        echo $callback($params, $body);
                    }

                    return;
                }
            }
        }

        // No route found
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'ok'    => false,
            'error' => 'Route not found: ' . $method . ' ' . $uri,
        ]);
    }

    /**
     * Get all registered routes (for documentation/debugging)
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Generate URL from route name and parameters
     */
    public function generate(string $name, array $params = []): string
    {
        // For now, return simple path (can be extended with named routes)
        return $name;
    }
}

/**
 * Base Controller class
 */
abstract class Controller
{
    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function success(array $data = [], int $code = 200): never
    {
        $this->json(array_merge(['ok' => true], $data), $code);
    }

    protected function error(string $message, int $code = 400): never
    {
        $this->json(['ok' => false, 'error' => $message], $code);
    }
}
