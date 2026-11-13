<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Model\User;
use App\Model\ActivityLog;
use App\Model\LoginAttempt;

class HomeController extends Controller
{
    public function index(): void
    {
        $userModel    = new User($this->connection);
        $logModel     = new ActivityLog($this->connection);
        $attemptModel = new LoginAttempt($this->connection);

        $this->render('home/index.php', [
            'pageTitle'    => 'Dashboard — SecureAuth',
            'favicon'      => 'boton-de-inicio.png',
            'bodyClass'    => 'dashboard',
            'name'         => $_SESSION['name'],
            'isAdmin'      => $_SESSION['is_admin'],
            'totalUsers'   => $userModel->getTotalCount(),
            'loginsToday'  => $logModel->getCountTodayByEvent(ActivityLog::EVENT_LOGIN_SUCCESS),
            'failedToday'  => $logModel->getCountTodayByEvent(ActivityLog::EVENT_LOGIN_FAILED),
            'lockedNow'    => $attemptModel->getLockedCount(),
            'recentEvents' => $logModel->getRecentEvents(5),
        ], protected: true);
    }
}
