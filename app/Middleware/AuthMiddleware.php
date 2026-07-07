<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Model\UserSession;

class AuthMiddleware
{
    public static function auth(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/login');
            exit;
        }
    }

    public static function admin(): void
    {
        self::auth();
        if (empty($_SESSION['is_admin'])) {
            http_response_code(403);
            require __DIR__ . '/../../views/errors/403.php';
            exit;
        }
    }

    public static function timeout(\mysqli $connection): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $timeout = (int) env('SESSION_TIMEOUT', 1800);

        if ((time() - ($_SESSION['last_activity'] ?? time())) > $timeout) {
            (new Auth($connection))->clearRememberToken((int) $_SESSION['user_id']);
            if (isset($_COOKIE['remember_me'])) {
                setcookie('remember_me', '', [
                    'expires'  => time() - 3600,
                    'path'     => '/',
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]);
            }
            session_destroy();
            session_start_secure();
            $_SESSION['message'] = 'Your session has expired due to inactivity';
            $_SESSION['icon']    = 'warning';
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $_SESSION['last_activity'] = time();
    }

    public static function session(\mysqli $connection): void
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
            $_SESSION['message'] = 'Your session has been revoked. Please log in again.';
            $_SESSION['icon']    = 'warning';
            header('Location: ' . APP_URL . '/login');
            exit;
        }

        $model->touch($hash);
    }
}
