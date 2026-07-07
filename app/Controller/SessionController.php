<?php

namespace App\Controller;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Model\ActivityLog;
use App\Model\UserSession;

class SessionController extends Controller
{
    private UserSession $sessionModel;

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->sessionModel = new UserSession($connection);
    }

    private function guard(): void
    {
        AuthMiddleware::timeout($this->connection);
        AuthMiddleware::session($this->connection);
        AuthMiddleware::auth();
    }

    private function currentTokenHash(): string
    {
        return hash('sha256', $_SESSION['session_token'] ?? '');
    }

    public function index(): void
    {
        $this->guard();

        $this->render('session/index.php', [
            'pageTitle' => 'Active Sessions — SecureAuth',
            'sessions'  => $this->sessionModel->getForUser((int) $_SESSION['user_id'], $this->currentTokenHash()),
        ], protected: true);
    }

    public function revoke(): void
    {
        $this->guard();
        $this->verifyCsrf('/sessions');

        if (isset($_POST['btnrevoke'])) {
            $sessionId  = (int) ($_POST['session_id'] ?? 0);
            $currentHash = $this->currentTokenHash();

            $revoked = $this->sessionModel->revoke($sessionId, (int) $_SESSION['user_id']);

            if ($revoked) {
                ActivityLog::log(ActivityLog::EVENT_SESSION_REVOKED, 'Session revoked', (int) $_SESSION['user_id']);
                $_SESSION['message'] = 'Session revoked successfully';
                $_SESSION['icon']    = 'success';
            } else {
                $_SESSION['message'] = 'Session not found';
                $_SESSION['icon']    = 'error';
            }
        }

        $this->redirect('/sessions');
    }

    public function revokeOthers(): void
    {
        $this->guard();
        $this->verifyCsrf('/sessions');

        if (isset($_POST['btnrevokeothers'])) {
            $count = $this->sessionModel->revokeAllExcept((int) $_SESSION['user_id'], $this->currentTokenHash());

            ActivityLog::log(ActivityLog::EVENT_SESSION_REVOKED, "Revoked {$count} other session(s)", (int) $_SESSION['user_id']);
            $_SESSION['message'] = 'All other sessions have been revoked';
            $_SESSION['icon']    = 'success';
        }

        $this->redirect('/sessions');
    }
}
