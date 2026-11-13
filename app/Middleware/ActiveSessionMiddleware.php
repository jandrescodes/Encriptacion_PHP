<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Flash;
use App\Model\UserSession;

// Debe ir después de TimeoutMiddleware: touch() renueva last_activity de la fila y el timeout ya validó la sesión PHP.
class ActiveSessionMiddleware implements Middleware
{
    public function handle(\mysqli $connection): void
    {
        if (empty($_SESSION['user_id']) || empty($_SESSION['session_token'])) {
            return;
        }

        if (!filter_var(env('ACTIVE_SESSIONS_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $ttl   = (int) env('SESSION_TIMEOUT', 1800);
        $hash  = hash('sha256', $_SESSION['session_token']);
        $model = new UserSession($connection);

        if (!$model->existsActive($hash, $ttl)) {
            $model->deleteByTokenHash($hash);
            session_destroy();
            session_start_secure();
            Flash::warning('Your session has been revoked. Please log in again.');
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $model->touch($hash);
    }
}
