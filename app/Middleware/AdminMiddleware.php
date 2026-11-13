<?php

declare(strict_types=1);

namespace App\Middleware;

// Asume AuthenticateMiddleware antes en la cadena: sin sesión también responde 403.
class AdminMiddleware implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            require __DIR__ . '/../../views/errors/403.php';
            exit;
        }
    }
}
