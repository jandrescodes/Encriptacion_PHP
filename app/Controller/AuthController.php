<?php

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Model\ActivityLog;
use App\Model\UserSession;
use App\Service\MailerService;

class AuthController extends Controller
{
    private const REMEMBER_COOKIE = 'remember_me';

    private Auth         $auth;
    private MailerService $mailer;

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->auth   = new Auth($connection);
        $this->mailer = new MailerService();
    }

    private function rememberEnabled(): bool
    {
        return filter_var(env('REMEMBER_ME_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function cookieOptions(int $maxAge): array
    {
        return [
            'expires'  => time() + $maxAge,
            'path'     => '/',
            'secure'   => str_starts_with(env('APP_URL', ''), 'https'),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    public function login(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btningresar'])) {
            $this->verifyCsrf('/login');

            if (!empty($_POST['usuario']) && !empty($_POST['password'])) {
                $identifier = $_POST['usuario'];

                $remaining = $this->auth->lockedSecondsRemaining($identifier);
                if ($remaining > 0) {
                    $minutes = (int) ceil($remaining / 60);
                    $_SESSION['message'] = "Account temporarily locked. Try again in {$minutes} minute(s).";
                    $_SESSION['icon']    = 'warning';
                    $this->redirect('/login');
                }

                $user = $this->auth->verifyCredentials($identifier, $_POST['password']);

                if ($user) {
                    $this->auth->clearFailedAttempts($identifier);
                    session_regenerate_id(true);
                    $_SESSION['user_id']       = $user['id'];
                    $_SESSION['name']          = $user['first_name'];
                    $_SESSION['is_admin']      = $user['is_admin'];
                    $_SESSION['last_activity'] = time();
                    $_SESSION['message']       = "Welcome, " . $user['first_name'] . "!";
                    $_SESSION['icon']          = 'success';

                    $sessionToken = bin2hex(random_bytes(32));
                    $_SESSION['session_token'] = $sessionToken;
                    (new UserSession($this->connection))->create(
                        (int) $user['id'],
                        $sessionToken,
                        $_SERVER['REMOTE_ADDR'] ?? '',
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    );

                    ActivityLog::log(ActivityLog::EVENT_LOGIN_SUCCESS, "Login: {$user['username']}", $user['id']);

                    if ($this->rememberEnabled() && !empty($_POST['remember'])) {
                        $token = $this->auth->issueRememberToken($user['id']);
                        setcookie(self::REMEMBER_COOKIE, $token, $this->cookieOptions($this->auth->rememberTtl()));
                    }

                    $this->redirect('/');
                }

                if ($this->auth->userExists($identifier)) {
                    $this->auth->registerFailedAttempt($identifier);
                }
                ActivityLog::log(ActivityLog::EVENT_LOGIN_FAILED, "Failed login attempt: {$identifier}");
                $_SESSION['message'] = 'Incorrect username or password';
                $_SESSION['icon']    = 'error';
                $this->redirect('/login');
            }

            $_SESSION['message'] = 'Please fill in all fields';
            $_SESSION['icon']    = 'warning';
            $this->redirect('/login');
        }

        $this->render('auth/login.php');
    }

    public function logout(): void
    {
        $this->verifyCsrf('/');
        $userId = $_SESSION['user_id'] ?? null;

        ActivityLog::log(ActivityLog::EVENT_LOGOUT, 'User logged out', $userId ? (int) $userId : null);

        if (!empty($_SESSION['session_token'])) {
            (new UserSession($this->connection))->deleteByTokenHash(hash('sha256', $_SESSION['session_token']));
        }

        if ($userId && isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->auth->clearRememberToken((int) $userId);
            setcookie(self::REMEMBER_COOKIE, '', $this->cookieOptions(-3600));
        }

        session_destroy();
        session_start_secure();
        $_SESSION['message'] = 'Logged out successfully';
        $_SESSION['icon']    = 'success';
        $this->redirect('/login');
    }

    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnrecuperar'])) {
            $this->verifyCsrf('/forgot-password');
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $token = $this->auth->createPasswordResetToken($email);

            if ($token) {
                $resetUrl = APP_URL . '/reset-password?token=' . urlencode($token);
                $this->mailer->sendResetEmail($email, $resetUrl);
            }

            $_SESSION['message'] = 'If that email is registered, a recovery link has been sent';
            $_SESSION['icon']    = 'info';
            $this->redirect('/forgot-password');
        }

        $this->render('auth/forgot_password.php');
    }

    public function resetPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnactualizar'])) {
            $this->verifyCsrf('/reset-password?token=' . urlencode($_POST['token'] ?? ''));
            $token           = $_POST['token'];
            $newPassword     = $_POST['new_password'];
            $confirmPassword = $_POST['confirm_password'];

            if ($newPassword !== $confirmPassword) {
                $_SESSION['message'] = 'Passwords do not match';
                $_SESSION['icon']    = 'warning';
                $this->redirect('/reset-password?token=' . urlencode($token));
            }

            $email = $this->auth->consumeResetToken($token, $newPassword);

            if ($email) {
                $stmt = $this->connection->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $row    = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                $userId = $row ? (int) $row['id'] : null;
                ActivityLog::log(ActivityLog::EVENT_PASSWORD_RESET, "Password reset via email: {$email}", $userId);

                $_SESSION['message'] = 'Password updated successfully';
                $_SESSION['icon']    = 'success';
                $this->redirect('/login');
            }

            $_SESSION['message'] = 'Invalid or expired token';
            $_SESSION['icon']    = 'error';
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        $this->render('auth/reset_password.php');
    }
}
