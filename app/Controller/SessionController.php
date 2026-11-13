<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Flash;
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

    private function currentTokenHash(): string
    {
        return hash('sha256', $_SESSION['session_token'] ?? '');
    }

    public function index(): void
    {
        $this->render('session/index.php', [
            'pageTitle'    => 'Active Sessions — SecureAuth',
            'sessions'     => $this->sessionModel->getForUser((int) $_SESSION['user_id'], $this->currentTokenHash()),
            'pageScripts'  => ['js/sessions-revoke.js'],
        ], protected: true);
    }

    public function revoke(): void
    {
        $this->verifyCsrf('/sessions');

        if (isset($_POST['btnrevoke'])) {
            $sessionId  = (int) ($_POST['session_id'] ?? 0);
            $currentHash = $this->currentTokenHash();

            $revoked = $this->sessionModel->revoke($sessionId, (int) $_SESSION['user_id']);

            if ($revoked) {
                $this->audit->record(ActivityLog::EVENT_SESSION_REVOKED, 'Session revoked', (int) $_SESSION['user_id']);
                Flash::success('Session revoked successfully');
            } else {
                Flash::error('Session not found');
            }
        }

        $this->redirect('/sessions');
    }

    public function revokeOthers(): void
    {
        $this->verifyCsrf('/sessions');

        if (isset($_POST['btnrevokeothers'])) {
            $count = $this->sessionModel->revokeAllExcept((int) $_SESSION['user_id'], $this->currentTokenHash());

            $this->audit->record(ActivityLog::EVENT_SESSION_REVOKED, "Revoked {$count} other session(s)", (int) $_SESSION['user_id']);
            Flash::success('All other sessions have been revoked');
        }

        $this->redirect('/sessions');
    }
}
