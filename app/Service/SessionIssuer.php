<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\UserSession;

// Punto único para abrir sesión (contraseña, Google y remember-me): sin él cada camino guardaba tipos
// y banderas distintas, y alguno ignoraba ACTIVE_SESSIONS_ENABLED.
final class SessionIssuer
{
    public function __construct(private \mysqli $connection)
    {
    }

    /** @param array<string, mixed> $user fila de users; devuelve el token de sesión en claro */
    public function issue(array $user, bool $viaRemember = false): string
    {
        // Sin sesión activa (CLI/tests sin session_start) regenerar el id lanzaría un warning.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $token = bin2hex(random_bytes(32));

        $_SESSION['user_id']       = (int) $user['id'];
        $_SESSION['name']          = $user['first_name'];
        $_SESSION['is_admin']      = (bool) $user['is_admin'];
        $_SESSION['last_activity'] = time();
        $_SESSION['session_token'] = $token;

        if (filter_var(env('ACTIVE_SESSIONS_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            (new UserSession($this->connection))->create(
                (int) $user['id'],
                $token,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $viaRemember
            );
        }

        return $token;
    }
}
