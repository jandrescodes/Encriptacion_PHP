<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Router;
use App\Middleware\ActiveSessionMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\Middleware;
use App\Middleware\TimeoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RouterTest extends TestCase
{
    /** Rutas que el usuario alcanza sin sesión. */
    private const PUBLIC_ROUTES = [
        '/login', '/logout', '/forgot-password', '/reset-password', '/auth/google', '/auth/google/callback',
    ];

    private function loadRoutes(): Router
    {
        $router = new Router(self::$db);
        require __DIR__ . '/../../routes/web.php';

        return $router;
    }

    #[Test]
    public function public_routes_have_no_middleware(): void
    {
        foreach ($this->loadRoutes()->routes() as $route) {
            if (in_array($route['uri'], self::PUBLIC_ROUTES, true)) {
                $this->assertSame([], $route['middleware'], "{$route['method']} {$route['uri']}");
            }
        }
    }

    #[Test]
    public function every_other_route_checks_timeout_then_revocation_then_authentication(): void
    {
        $protected = array_filter(
            $this->loadRoutes()->routes(),
            fn (array $route): bool => !in_array($route['uri'], self::PUBLIC_ROUTES, true)
        );

        $this->assertNotEmpty($protected);
        foreach ($protected as $route) {
            $this->assertSame(
                [TimeoutMiddleware::class, ActiveSessionMiddleware::class, AuthenticateMiddleware::class],
                array_slice($route['middleware'], 0, 3),
                "{$route['method']} {$route['uri']}"
            );
        }
    }

    #[Test]
    public function user_management_and_audit_routes_require_admin_last(): void
    {
        $router = $this->loadRoutes();

        foreach (['GET /users', 'POST /users/delete', 'GET /activity-logs', 'GET /activity-logs/data'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $middleware = $router->middlewareFor($method, $uri);

            $this->assertSame(AdminMiddleware::class, end($middleware), $route);
        }
        $this->assertNotContains(AdminMiddleware::class, $router->middlewareFor('GET', '/sessions'));
    }

    #[Test]
    public function it_returns_null_for_unknown_routes(): void
    {
        $this->assertNull($this->loadRoutes()->middlewareFor('GET', '/no-existe'));
    }

    #[Test]
    public function group_middleware_does_not_leak_to_later_routes(): void
    {
        $router = new Router(self::$db);
        $router->group([TimeoutMiddleware::class], function (Router $router): void {
            $router->get('/dentro', [RouterRecorder::class, 'run']);
        });
        $router->get('/fuera', [RouterRecorder::class, 'run']);

        $this->assertSame([TimeoutMiddleware::class], $router->middlewareFor('GET', '/dentro'));
        $this->assertSame([], $router->middlewareFor('GET', '/fuera'));
    }

    #[Test]
    public function dispatch_runs_middleware_in_order_before_the_controller(): void
    {
        RouterRecorder::$calls = [];
        $router = new Router(self::$db);
        $router->group([RouterFirst::class, RouterSecond::class], function (Router $router): void {
            $router->get('/ruta', [RouterRecorder::class, 'run']);
        });

        $router->dispatch('GET', '/ruta');

        $this->assertSame(['first', 'second', 'controller'], RouterRecorder::$calls);
    }
}

final class RouterRecorder
{
    /** @var list<string> */
    public static array $calls = [];

    public function __construct(\mysqli $connection)
    {
    }

    public function run(): void
    {
        self::$calls[] = 'controller';
    }
}

final class RouterFirst implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        RouterRecorder::$calls[] = 'first';
    }
}

final class RouterSecond implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        RouterRecorder::$calls[] = 'second';
    }
}
