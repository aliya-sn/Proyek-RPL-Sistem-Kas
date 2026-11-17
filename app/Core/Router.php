<?php

namespace App\Core;

use RuntimeException;

class Router
{
    /**
     * Registered routes
     */
    protected static array $routes = [];

    /**
     * Named route registry
     */
    protected static array $namedRoutes = [];

    /**
     * Middleware alias mapping
     */
    protected static array $middlewareAliases = [
        'auth' => \App\Middleware\AuthMiddleware::class,
        'guest' => \App\Middleware\GuestMiddleware::class,
        'role' => \App\Middleware\RoleMiddleware::class,
        'csrf' => \App\Middleware\CsrfMiddleware::class,
    ];

    /**
     * Current route group attributes
     */
    protected static array $groupStack = [];

    /**
     * Register a GET route.
     */
    public static function get(string $path, array|callable $handler, array $middlewares = []): static
    {
        return static::addRoute('GET', $path, $handler, $middlewares);
    }

    /**
     * Register a POST route.
     */
    public static function post(string $path, array|callable $handler, array $middlewares = []): static
    {
        return static::addRoute('POST', $path, $handler, $middlewares);
    }

    /**
     * Register a PUT route.
     */
    public static function put(string $path, array|callable $handler, array $middlewares = []): static
    {
        return static::addRoute('PUT', $path, $handler, $middlewares);
    }

    /**
     * Register a DELETE route.
     */
    public static function delete(string $path, array|callable $handler, array $middlewares = []): static
    {
        return static::addRoute('DELETE', $path, $handler, $middlewares);
    }

    /**
     * Register a route group with shared attributes.
     */
    public static function group(array $attributes, callable $callback): void
    {
        static::$groupStack[] = $attributes;
        $callback();
        array_pop(static::$groupStack);
    }

    /**
     * Internal method to add route.
     */
    protected static function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): static
    {
        $prefix = '';
        $groupMiddlewares = [];

        foreach (static::$groupStack as $group) {
            if (isset($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (isset($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $groupMiddlewares = array_merge($groupMiddlewares, $m);
            }
        }

        $fullPath = $prefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');
        if ($fullPath === '') {
            $fullPath = '/';
        }

        $combinedMiddlewares = array_merge($groupMiddlewares, $middlewares);

        $route = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => $combinedMiddlewares,
            'pattern' => static::compilePattern($fullPath),
            'name' => null,
        ];

        $instance = new static();
        static::$routes[] = &$route;

        return $instance;
    }

    /**
     * Name the last registered route.
     */
    public function name(string $name): static
    {
        $lastIdx = count(static::$routes) - 1;
        if ($lastIdx >= 0) {
            static::$routes[$lastIdx]['name'] = $name;
            static::$namedRoutes[$name] = static::$routes[$lastIdx]['path'];
        }
        return $this;
    }

    /**
     * Compile URL pattern into regex for matching parameters {param}.
     */
    protected static function compilePattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Dispatch the current request against registered routes.
     */
    public static function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach (static::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                // Extract named parameter values
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                // Execute middlewares
                static::runMiddlewares($route['middlewares'], $request);

                // Call route handler
                static::executeHandler($route['handler'], $request, $params);
                return;
            }
        }

        // Route not found
        static::handleNotFound($request);
    }

    /**
     * Run middleware chain.
     */
    protected static function runMiddlewares(array $middlewares, Request $request): void
    {
        foreach ($middlewares as $middleware) {
            $params = [];
            if (str_contains($middleware, ':')) {
                [$middlewareName, $argString] = explode(':', $middleware, 2);
                $params = explode(',', $argString);
            } else {
                $middlewareName = $middleware;
            }

            $middlewareClass = static::$middlewareAliases[$middlewareName] ?? $middlewareName;

            if (class_exists($middlewareClass)) {
                $instance = new $middlewareClass();
                if (method_exists($instance, 'handle')) {
                    $instance->handle($request, ...$params);
                }
            }
        }
    }

    /**
     * Execute controller action or closure.
     */
    protected static function executeHandler(array|callable $handler, Request $request, array $params): void
    {
        if (is_callable($handler)) {
            echo call_user_func_array($handler, array_merge([$request], array_values($params)));
            return;
        }

        if (is_array($handler)) {
            [$controllerClass, $method] = $handler;

            if (!class_exists($controllerClass)) {
                throw new RuntimeException("Controller class '{$controllerClass}' not found.");
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new RuntimeException("Method '{$method}' not found in controller '{$controllerClass}'.");
            }

            $controller->$method($request, ...array_values($params));
            return;
        }

        throw new RuntimeException("Invalid route handler specified.");
    }

    /**
     * Handle 404 Not Found response.
     */
    protected static function handleNotFound(Request $request): void
    {
        Response::status(404);

        if ($request->isAjax()) {
            Response::json(['error' => '404 Not Found', 'message' => 'Endpoint tidak ditemukan.'], 404);
            return;
        }

        $errorView = base_path('app/Views/errors/404.php');
        if (file_exists($errorView)) {
            echo View::render('errors/404', ['title' => '404 - Halaman Tidak Ditemukan'], false);
            return;
        }

        echo "<h1>404 Not Found</h1><p>Halaman yang Anda cari tidak ditemukan.</p>";
    }
}
