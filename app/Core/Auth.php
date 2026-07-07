<?php

namespace App\Core;

use App\Model\LoginAttempt;
use App\Model\User;
use App\Model\UserSession;

class Auth
{
    private User         $userModel;
    private LoginAttempt $attempts;

    public function __construct(private \mysqli $connection)
    {
        $this->userModel = new User($connection);
        $this->attempts  = new LoginAttempt($connection);
    }

    public function lockoutEnabled(): bool
    {
        return filter_var(env('LOGIN_LOCKOUT_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
    }

    private function maxAttempts(): int
    {
        return (int) env('LOGIN_MAX_ATTEMPTS', 5);
    }

    private function lockMinutes(): int
    {
        return (int) env('LOGIN_LOCKOUT_MINUTES', 15);
    }

    public function lockedSecondsRemaining(string $identifier): int
    {
        if (!$this->lockoutEnabled()) {
            return 0;
        }
        return $this->attempts->lockedSecondsRemaining($identifier);
    }

    public function userExists(string $identifier): bool
    {
        return $this->userModel->getByUsername($identifier) !== null;
    }

    public function registerFailedAttempt(string $identifier): void
    {
        if ($this->lockoutEnabled()) {
            $this->attempts->registerFailure($identifier, $this->maxAttempts(), $this->lockMinutes());
        }
    }

    public function clearFailedAttempts(string $identifier): void
    {
        $this->attempts->clear($identifier);
    }

    public function verifyCredentials(string $username, string $password): ?array
    {
        $user = $this->userModel->getByUsername($username);

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }

    public function issueRememberToken(int $userId): string
    {
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + $this->rememberTtl());
        $this->userModel->setRememberToken($userId, $this->hashToken($token), $expires);
        return $token;
    }

    public function consumeRememberToken(string $rawToken): ?array
    {
        return $this->userModel->getByRememberToken($this->hashToken($rawToken)) ?: null;
    }

    public function clearRememberToken(int $userId): void
    {
        $this->userModel->clearRememberToken($userId);
    }

    public function createPasswordResetToken(string $email): ?string
    {
        if (!$this->userModel->getByEmail($email)) {
            return null;
        }

        $token     = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expires   = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->connection->prepare(
            "INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $email, $tokenHash, $expires);
        $stmt->execute();
        $stmt->close();

        return $token;
    }

    public function consumeResetToken(string $token, string $newPassword): ?string
    {
        $tokenHash = hash('sha256', $token);
        $stmt      = $this->connection->prepare(
            "SELECT email FROM password_resets
             WHERE token = ? AND expires_at > NOW() AND used = 0
             ORDER BY created_at DESC LIMIT 1"
        );
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $reset = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$reset) {
            return null;
        }

        $this->userModel->updatePassword($reset['email'], $newPassword);

        $stmt = $this->connection->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->bind_param("s", $tokenHash);
        $stmt->execute();
        $stmt->close();

        // Clear lockout for both email and username (either could have been used at login).
        $this->clearFailedAttempts($reset['email']);
        $user = $this->userModel->getByEmail($reset['email']);
        if ($user) {
            $this->clearFailedAttempts($user['username']);
        }

        return $reset['email'];
    }

    public function restoreFromCookie(): void
    {
        if (!empty($_SESSION['user_id'])) {
            return;
        }

        $enabled = filter_var(env('REMEMBER_ME_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled || empty($_COOKIE['remember_me'])) {
            return;
        }

        $user = $this->consumeRememberToken($_COOKIE['remember_me']);

        if (!$user) {
            setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['name']          = $user['first_name'];
        $_SESSION['is_admin']      = $user['is_admin'];
        $_SESSION['last_activity'] = time();

        $sessionToken = bin2hex(random_bytes(32));
        $_SESSION['session_token'] = $sessionToken;
        UserSession::createTo(
            $this->connection,
            (int) $user['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            viaRemember: true
        );
    }

    public function rememberTtl(): int
    {
        return (int) env('REMEMBER_ME_TTL', 2592000);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
