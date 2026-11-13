<?php

declare(strict_types=1);

namespace App\Middleware;

class AuthenticateMiddleware implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }
}
