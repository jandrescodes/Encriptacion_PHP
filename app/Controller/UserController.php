<?php

namespace App\Controller;

use App\Core\Controller;
use App\Middleware\AuthMiddleware;
use App\Model\ActivityLog;
use App\Model\User;

class UserController extends Controller
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
        AuthMiddleware::admin();
    }

    public function index(): void
    {
        $this->guard();

        $this->render('user/index.php', [
            'pageTitle'     => 'Users — SecureAuth',
            'useDataTables' => true,
            'pageScripts'   => ['js/users-table.js', 'js/users-delete.js'],
            'users'         => $this->userModel->getAll(),
        ], protected: true);
    }

    public function create(): void
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
            $this->verifyCsrf('/users/create');
            $data = [
                'first_name' => $_POST['first_name'],
                'last_name'  => $_POST['last_name'],
                'email'      => $_POST['email'] ?? '',
                'username'   => $_POST['username'],
                'password'   => $_POST['password'],
                'is_admin'   => isset($_POST['is_admin']) ? 1 : 0,
            ];

            $error = $this->validateUser($data);

            if ($error) {
                $_SESSION['message'] = $error;
                $_SESSION['icon']    = 'error';
                $_SESSION['old']     = $data;
                unset($_SESSION['old']['password']);
                $this->redirect('/users/create');
            }

            if ($this->userModel->create($data)) {
                ActivityLog::log(ActivityLog::EVENT_USER_CREATED, "User created: {$data['username']}", (int) $_SESSION['user_id']);
                $_SESSION['message'] = 'User added successfully';
                $_SESSION['icon']    = 'success';
                $this->redirect('/users');
            }

            $_SESSION['message'] = 'Failed to create user';
            $_SESSION['icon']    = 'error';
            $this->redirect('/users/create');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $this->render('user/create.php', [
            'pageTitle' => 'Create User — SecureAuth',
            'old'       => $old,
        ], protected: true);
    }

    public function edit(): void
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
            $this->verifyCsrf('/users/edit?id=' . (int) ($_POST['id'] ?? 0));
            $id   = (int) $_POST['id'];

            if ($id === (int) $_SESSION['user_id'] && !isset($_POST['is_admin'])) {
                $_SESSION['message'] = 'You cannot remove your own admin privileges';
                $_SESSION['icon']    = 'error';
                $this->redirect('/users/edit?id=' . $id);
            }

            $data = [
                'first_name' => $_POST['first_name'],
                'last_name'  => $_POST['last_name'],
                'email'      => $_POST['email'] ?? '',
                'username'   => $_POST['username'],
                'password'   => $_POST['password'] ?? '',
                'is_admin'   => isset($_POST['is_admin']) ? 1 : 0,
            ];

            $error = $this->validateUser($data, $id);

            if ($error) {
                $_SESSION['message'] = $error;
                $_SESSION['icon']    = 'error';
                $_SESSION['old']     = $data;
                unset($_SESSION['old']['password']);
                $this->redirect('/users/edit?id=' . $id);
            }

            if ($this->userModel->update($id, $data)) {
                ActivityLog::log(ActivityLog::EVENT_USER_UPDATED, "User updated: {$data['username']} (id:{$id})", (int) $_SESSION['user_id']);
                $_SESSION['message'] = 'User updated successfully';
                $_SESSION['icon']    = 'success';
                $this->redirect('/users');
            }

            $_SESSION['message'] = 'Failed to update user';
            $_SESSION['icon']    = 'error';
            $this->redirect('/users/edit?id=' . $id);
        }

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->userModel->getById($id);

        if (!$user) {
            $_SESSION['message'] = 'User not found';
            $_SESSION['icon']    = 'error';
            $this->redirect('/users');
        }

        $old = $_SESSION['old'] ?? [];
        unset($_SESSION['old']);

        $this->render('user/edit.php', [
            'pageTitle' => 'Edit User — SecureAuth',
            'user'      => $old ? array_merge($user, $old) : $user,
        ], protected: true);
    }

    public function delete(): void
    {
        $this->guard();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $this->verifyCsrf('/users');
            $id = (int) $_POST['id'];

            if ($id === (int) $_SESSION['user_id']) {
                $_SESSION['message'] = 'You cannot delete your own account';
                $_SESSION['icon']    = 'error';
                $this->redirect('/users');
            }

            $target  = $this->userModel->getById($id);
            $deleted = $this->userModel->delete($id);

            if ($deleted && $target) {
                ActivityLog::log(ActivityLog::EVENT_USER_DELETED, "User deleted: {$target['username']} (id:{$id})", (int) $_SESSION['user_id']);
            }

            $_SESSION['message'] = $deleted ? 'User deleted successfully' : 'Failed to delete user';
            $_SESSION['icon']    = $deleted ? 'success' : 'error';
        }

        $this->redirect('/users');
    }

    private function validateUser(array $data, ?int $excludeId = null): ?string
    {
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            return 'Password must be at least 8 characters';
        }

        $byEmail = $this->userModel->getByEmail($data['email']);
        if ($byEmail && (int) $byEmail['id'] !== $excludeId) {
            return 'Email is already registered';
        }

        $byUsername = $this->userModel->getByUsername($data['username']);
        if ($byUsername && (int) $byUsername['id'] !== $excludeId) {
            return 'Username is already taken';
        }

        return null;
    }
}
