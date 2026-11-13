<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Flash;
use App\Model\ActivityLog;
use App\Model\User;
use App\Model\UserSession;
use App\Service\GoogleOAuthService;
use App\Service\LoginService;
use App\Service\MailerService;
use App\Service\OAuthAccountResolver;
use App\Service\PasswordPolicy;
use App\Service\RememberCookie;
use App\Service\SessionIssuer;

class AuthController extends Controller
{
    private Auth               $auth;
    private LoginService       $loginService;
    private MailerService      $mailer;
    private GoogleOAuthService $google;

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->auth         = new Auth($connection);
        $this->loginService = new LoginService($connection, $this->auth);
        $this->mailer       = new MailerService();
        $this->google       = new GoogleOAuthService();
    }

    private function rememberEnabled(): bool
    {
        return filter_var(env('REMEMBER_ME_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function login(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btningresar'])) {
            $this->verifyCsrf('/login');

            $result = $this->loginService->attempt(
                is_string($_POST['usuario'] ?? null) ? $_POST['usuario'] : '',
                is_string($_POST['password'] ?? null) ? $_POST['password'] : '',
                $_SERVER['REMOTE_ADDR'] ?? ''
            );
            Flash::set($result->message, $result->icon);

            if (!$result->ok) {
                $this->redirect('/login');
            }

            if ($this->rememberEnabled() && !empty($_POST['remember'])) {
                RememberCookie::set($this->auth->issueRememberToken((int) $result->user['id']), $this->auth->rememberTtl());
            }

            $this->redirect('/');
        }

        $this->render('auth/login.php');
    }

    public function logout(): void
    {
        $this->verifyCsrf('/');
        $userId = $_SESSION['user_id'] ?? null;

        $this->audit->record(ActivityLog::EVENT_LOGOUT, 'User logged out', $userId ? (int) $userId : null);

        if (!empty($_SESSION['session_token'])) {
            (new UserSession($this->connection))->deleteByTokenHash(hash('sha256', $_SESSION['session_token']));
        }

        if ($userId && isset($_COOKIE[RememberCookie::NAME])) {
            $this->auth->clearRememberToken((int) $userId);
            RememberCookie::clear();
        }

        session_destroy();
        session_start_secure();
        Flash::success('Logged out successfully');
        $this->redirect('/login');
    }

    public function forgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btnrecuperar'])) {
            $this->verifyCsrf('/forgot-password');
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

            $user = (new User($this->connection))->getByEmail($email);
            if ($user && User::isOAuthAccount($user)) {
                Flash::info('This account uses Google sign-in. No password reset needed.');
                $this->redirect('/forgot-password');
            }

            $token = $this->auth->createPasswordResetToken($email);

            if ($token) {
                $resetUrl = APP_URL . '/reset-password?token=' . urlencode($token);
                $this->mailer->sendResetEmail($email, $resetUrl);
            }

            Flash::info('If that email is registered, a recovery link has been sent');
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
                Flash::warning('Passwords do not match');
                $this->redirect('/reset-password?token=' . urlencode($token));
            }

            if ($policyError = PasswordPolicy::validate($newPassword)) {
                Flash::warning($policyError);
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
                $this->audit->record(ActivityLog::EVENT_PASSWORD_RESET, "Password reset via email: {$email}", $userId);

                Flash::success('Password updated successfully');
                $this->redirect('/login');
            }

            Flash::error('Invalid or expired token');
            $this->redirect('/reset-password?token=' . urlencode($token));
        }

        $this->render('auth/reset_password.php');
    }

    public function googleLogin(): void
    {
        if (!empty($_SESSION['user_id'])) {
            $this->redirect('/');
        }

        if (!GoogleOAuthService::isEnabled()) {
            Flash::info('Google sign-in is currently disabled.');
            $this->redirect('/login');
        }

        $state                            = bin2hex(random_bytes(32));
        $_SESSION['oauth_google_state']   = $state;

        $this->redirect($this->google->buildAuthorizationUrl($state));
    }

    public function googleCallback(): void
    {
        if (!GoogleOAuthService::isEnabled()) {
            Flash::info('Google sign-in is currently disabled.');
            $this->redirect('/login');
        }

        if (!$this->validateOAuthState($_GET['state'] ?? '')) {
            $this->redirect('/login');
        }

        try {
            $tokens     = $this->google->exchangeCodeForTokens($_GET['code'] ?? '');
            $googleUser = $this->google->fetchUserInfo($tokens['access_token']);
        } catch (\RuntimeException) {
            Flash::warning('Google sign-in failed. Please try again.');
            $this->redirect('/login');
        }

        $user = (new OAuthAccountResolver(new User($this->connection)))->resolve($googleUser);

        if ($user === null) {
            Flash::warning('Google sign-in failed. Please try again.');
            $this->redirect('/login');
        }

        $this->completeOAuthLogin($user);
    }

    private function validateOAuthState(string $received): bool
    {
        $saved = $_SESSION['oauth_google_state'] ?? null;
        unset($_SESSION['oauth_google_state']);

        if ($saved === null || !hash_equals($saved, $received)) {
            Flash::error('Invalid OAuth state. Please try again.');
            return false;
        }

        return true;
    }

    private function completeOAuthLogin(array $user): void
    {
        (new SessionIssuer($this->connection))->issue($user);

        $this->audit->record(ActivityLog::EVENT_LOGIN_SUCCESS, "Google login: {$user['username']}", (int) $user['id']);

        Flash::success('Welcome, ' . $user['first_name'] . '!');
        $this->redirect('/');
    }
}
