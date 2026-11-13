<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string, 2: list<class-string>}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    /** @var list<class-string> */
    private array $groupMiddleware = [];

    public function __construct(private \mysqli $connection)
    {
    }

    /** @param array{0: class-string, 1: string} $action */
    public function get(string $uri, array $action): void
    {
        $this->add('GET', $uri, $action);
    }

    /** @param array{0: class-string, 1: string} $action */
    public function post(string $uri, array $action): void
    {
        $this->add('POST', $uri, $action);
    }

    /**
     * Las rutas registradas dentro del callback heredan el middleware, en el orden dado.
     *
     * @param list<class-string> $middleware
     */
    public function group(array $middleware, callable $routes): void
    {
        $previous             = $this->groupMiddleware;
        $this->groupMiddleware = [...$previous, ...$middleware];
        try {
            $routes($this);
        } finally {
            $this->groupMiddleware = $previous;
        }
    }

    /** @return list<class-string>|null null si la ruta no existe */
    public function middlewareFor(string $method, string $uri): ?array
    {
        return $this->routes[$method][$this->normalize($uri)][2] ?? null;
    }

    /** @return list<array{method: string, uri: string, middleware: list<class-string>}> */
    public function routes(): array
    {
        $list = [];
        foreach ($this->routes as $method => $byUri) {
            foreach ($byUri as $uri => [,, $middleware]) {
                $list[] = ['method' => $method, 'uri' => $uri, 'middleware' => $middleware];
            }
        }

        return $list;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = $this->normalize(parse_url($uri, PHP_URL_PATH) ?? '/');
        $base = $this->normalize(parse_url(APP_URL, PHP_URL_PATH) ?? '');

        if ($base !== '/' && str_starts_with($path, $base)) {
            $path = $this->normalize(substr($path, strlen($base)));
        }

        $action = $this->routes[$method][$path] ?? null;

        if ($action === null) {
            http_response_code(404);
            require __DIR__ . '/../../views/errors/404.php';
            return;
        }

        [$class, $methodName, $middleware] = $action;

        foreach ($middleware as $middlewareClass) {
            (new $middlewareClass())->handle($this->connection);
        }

        (new $class($this->connection))->{$methodName}();
    }

    /** @param array{0: class-string, 1: string} $action */
    private function add(string $method, string $uri, array $action): void
    {
        $this->routes[$method][$this->normalize($uri)] = [...$action, $this->groupMiddleware];
    }

    private function normalize(string $uri): string
    {
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }
}
