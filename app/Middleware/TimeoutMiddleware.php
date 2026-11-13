<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Flash;
use App\Service\RememberCookie;

class TimeoutMiddleware implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $timeout = (int) env('SESSION_TIMEOUT', 1800);

        if ((time() - ($_SESSION['last_activity'] ?? time())) > $timeout) {
            (new Auth($connection))->clearRememberToken((int) $_SESSION['user_id']);
            if (isset($_COOKIE[RememberCookie::NAME])) {
                RememberCookie::clear();
            }
            session_destroy();
            session_start_secure();
            Flash::warning('Your session has expired due to inactivity');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $_SESSION['last_activity'] = time();
    }
}
