<?php

namespace App\Controller;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Model\ActivityLog;
use App\Model\User;

class ProfileController extends Controller
{
    private User $userModel;

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->userModel = new User($connection);
    }

    private function guard(): void
    {
        AuthMiddleware::timeout($this->connection);
        AuthMiddleware::auth();
    }

    public function profile(): void
    {
        $this->guard();

        $id = (int) $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
            $this->verifyCsrf('/profile');

            $data = [
                'first_name' => $_POST['first_name'] ?? '',
                'last_name'  => $_POST['last_name']  ?? '',
                'email'      => $_POST['email']       ?? '',
                'username'   => $_POST['username']    ?? '',
            ];

            $error = $this->validateProfile($data, $id);

            if ($error) {
                $_SESSION['message'] = $error;
                $_SESSION['icon']    = 'error';
                $this->redirect('/profile');
            }

            if ($this->userModel->updateProfile($id, $data)) {
                $_SESSION['name']    = $data['first_name'];
                $_SESSION['message'] = 'Profile updated successfully';
                $_SESSION['icon']    = 'success';
            } else {
                $_SESSION['message'] = 'Failed to update profile';
                $_SESSION['icon']    = 'error';
            }

            $this->redirect('/profile');
        }

        $user = $this->userModel->getById($id);

        $this->render('profile/index.php', [
            'pageTitle' => 'My Profile — SecureAuth',
            'user'      => $user,
        ], protected: true);
    }

    public function changePassword(): void
    {
        $this->guard();

        $id             = (int) $_SESSION['user_id'];
        $current        = $_POST['current_password'] ?? '';
        $new            = $_POST['new_password']     ?? '';
        $confirm        = $_POST['confirm_password'] ?? '';

        if (!isset($_POST['change_password'])) {
            $this->redirect('/profile');
        }

        $this->verifyCsrf('/profile');

        if (strlen($new) < 8) {
            $_SESSION['message'] = 'New password must be at least 8 characters';
            $_SESSION['icon']    = 'error';
            $this->redirect('/profile');
        }

        if ($new !== $confirm) {
            $_SESSION['message'] = 'New password and confirmation do not match';
            $_SESSION['icon']    = 'error';
            $this->redirect('/profile');
        }

        $user = $this->userModel->getById($id);

        if (!$user) {
            $_SESSION['message'] = 'User not found';
            $_SESSION['icon']    = 'error';
            $this->redirect('/profile');
        }

        if (!User::isOAuthAccount($user)) {
            $hash = $this->userModel->getPasswordById($id);

            if (!$hash || !password_verify($current, $hash)) {
                $_SESSION['message'] = 'Current password is incorrect';
                $_SESSION['icon']    = 'error';
                $this->redirect('/profile');
            }
        }

        if ($this->userModel->updatePasswordProfile($id, $new)) {
            ActivityLog::log(ActivityLog::EVENT_PASSWORD_CHANGED, 'Password set via profile', $id);
            $_SESSION['message'] = 'Password updated successfully';
            $_SESSION['icon']    = 'success';
        } else {
            $_SESSION['message'] = 'Failed to update password';
            $_SESSION['icon']    = 'error';
        }

        $this->redirect('/profile');
    }

    private function validateProfile(array $data, int $id): ?string
    {
        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['username'])) {
            return 'First name, last name and username are required';
        }

        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return 'A valid email address is required';
        }

        $byEmail = $this->userModel->getByEmail($data['email']);
        if ($byEmail && (int) $byEmail['id'] !== $id) {
            return 'Email is already registered';
        }

        $byUsername = $this->userModel->getByUsername($data['username']);
        if ($byUsername && (int) $byUsername['id'] !== $id) {
            return 'Username is already taken';
        }

        return null;
    }
}
