<?php
// path: core/Router.php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array|callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, array|callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    private function normalize(string $path): string
    {
        $path = trim($path);
        $path = rtrim($path, '/');
        return $path === '' ? '/' : $path;
    }

    public function dispatch(string $method, string $uri): void
    {
        $parsedPath = parse_url($uri, PHP_URL_PATH) ?? '/';
        $normalizedUri = $this->normalize($parsedPath);
        $method = strtoupper($method);

        $handler = $this->routes[$method][$normalizedUri] ?? null;

        if (!$handler) {
            http_response_code(404);
            echo "404 Not Found: [{$method}] {$normalizedUri}";
            return;
        }

        if (is_callable($handler)) {
            call_user_func($handler);
            return;
        }

        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    }
}