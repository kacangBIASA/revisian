<?php
// app/core/Router.php

class Router
{
    private array $routes = [
        'GET'  => [],
        'POST' => [],
    ];

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['GET'][$this->normalize($path)] = [$handler, $middleware];
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->routes['POST'][$this->normalize($path)] = [$handler, $middleware];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = parse_url($uri, PHP_URL_PATH) ?: '/';

        $basePath = parse_url(config('app.base_url', ''), PHP_URL_PATH) ?: '';
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
            if ($uri === '') $uri = '/';
        }

        $path = $this->normalize($uri);

        $route = $this->routes[$method][$path] ?? null;
        if (!$route) {
            http_response_code(404);
            echo "404 Not Found: " . htmlspecialchars($path);
            return;
        }

        [$handler, $middleware] = $route;

        // run middleware
        foreach ($middleware as $mwClass) {
            $mw = new $mwClass();
            if (method_exists($mw, 'handle')) {
                $mw->handle();
            }
        }

        // handler [Class, method]
        if (is_array($handler) && count($handler) === 2) {
            [$class, $action] = $handler;
            $controller = new $class();
            $result = $controller->$action();
            echo is_string($result) ? $result : '';
            return;
        }

        $result = call_user_func($handler);
        echo is_string($result) ? $result : '';
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : $path;
    }
}
