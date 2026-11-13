<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Auth;
use App\Model\ActivityLog;

// Reglas del login con contraseña. El controller conserva CSRF, flash, cookie remember y redirect.
final class LoginService
{
    private Auth $auth;
    private SessionIssuer $issuer;
    private AuditLog $audit;

    public function __construct(
        \mysqli $connection,
        ?Auth $auth = null,
        ?SessionIssuer $issuer = null,
        private ?LoginThrottle $throttle = null,
        ?AuditLog $audit = null,
    ) {
        $this->auth   = $auth ?? new Auth($connection);
        $this->issuer = $issuer ?? new SessionIssuer($connection);
        $this->audit  = $audit ?? new AuditLog($connection);
    }

    public function attempt(string $identifier, string $password, string $ip): LoginResult
    {
        // El límite por IP cuenta cualquier envío, incluso con campos vacíos.
        $retryAfter = ($this->throttle ?? LoginThrottle::fromEnv())?->hit($ip) ?? 0;
        if ($retryAfter > 0) {
            $minutes = (int) ceil($retryAfter / 60);
            return LoginResult::failure("Too many login attempts. Try again in {$minutes} minute(s).", 'warning');
        }

        if (empty($identifier) || empty($password)) {
            return LoginResult::failure('Please fill in all fields', 'warning');
        }

        $remaining = $this->auth->lockedSecondsRemaining($identifier);
        if ($remaining > 0) {
            $minutes = (int) ceil($remaining / 60);
            return LoginResult::failure("Account temporarily locked. Try again in {$minutes} minute(s).", 'warning');
        }

        $rejectMessage = $this->auth->passwordLoginErrorMessage($identifier);
        if ($rejectMessage) {
            return LoginResult::failure($rejectMessage, 'warning');
        }

        $user = $this->auth->verifyCredentials($identifier, $password);

        if ($user === null) {
            // Solo se cuentan intentos de usuarios que existen: evita llenar login_attempts con nombres inventados.
            if ($this->auth->userExists($identifier)) {
                $this->auth->registerFailedAttempt($identifier);
            }
            $this->audit->record(ActivityLog::EVENT_LOGIN_FAILED, "Failed login attempt: {$identifier}");

            return LoginResult::failure('Incorrect username or password', 'error');
        }

        $this->auth->clearFailedAttempts($identifier);
        $this->issuer->issue($user);
        $this->audit->record(ActivityLog::EVENT_LOGIN_SUCCESS, "Login: {$user['username']}", (int) $user['id']);

        return LoginResult::success('Welcome, ' . $user['first_name'] . '!', $user);
    }
}
