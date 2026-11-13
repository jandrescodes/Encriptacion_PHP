<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Controller;
use App\Core\Flash;
use App\Model\ActivityLog;
use App\Model\User;
use App\Policy\UserPolicy;
use App\Service\PasswordPolicy;

class UserController extends Controller
{
    private User $userModel;
    private UserPolicy $policy;

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
        $this->userModel = new User($connection);
        $this->policy    = new UserPolicy();
    }

    public function index(): void
    {
        $this->render('user/index.php', [
            'pageTitle'     => 'Users — SecureAuth',
            'useDataTables' => true,
            'pageScripts'   => ['js/users-table.js', 'js/users-delete.js'],
            'users'         => $this->userModel->getAll(),
        ], protected: true);
    }

    public function create(): void
    {
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
                Flash::error($error);
                $_SESSION['old']     = $data;
                unset($_SESSION['old']['password']);
                $this->redirect('/users/create');
            }

            if ($this->userModel->create($data)) {
                $this->audit->record(ActivityLog::EVENT_USER_CREATED, "User created: {$data['username']}", (int) $_SESSION['user_id']);
                Flash::success('User added successfully');
                $this->redirect('/users');
            }

            Flash::error('Failed to create user');
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
            $this->verifyCsrf('/users/edit?id=' . (int) ($_POST['id'] ?? 0));
            $id   = (int) $_POST['id'];

            if (!$this->policy->canSetAdminFlag((int) $_SESSION['user_id'], $id, isset($_POST['is_admin']))) {
                Flash::error('You cannot remove your own admin privileges');
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
                Flash::error($error);
                $_SESSION['old']     = $data;
                unset($_SESSION['old']['password']);
                $this->redirect('/users/edit?id=' . $id);
            }

            if ($this->userModel->update($id, $data)) {
                $this->audit->record(ActivityLog::EVENT_USER_UPDATED, "User updated: {$data['username']} (id:{$id})", (int) $_SESSION['user_id']);
                Flash::success('User updated successfully');
                $this->redirect('/users');
            }

            Flash::error('Failed to update user');
            $this->redirect('/users/edit?id=' . $id);
        }

        $id   = (int) ($_GET['id'] ?? 0);
        $user = $this->userModel->getById($id);

        if (!$user) {
            Flash::error('User not found');
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
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
            $this->verifyCsrf('/users');
            $id = (int) $_POST['id'];

            if (!$this->policy->canDelete((int) $_SESSION['user_id'], $id)) {
                Flash::error('You cannot delete your own account');
                $this->redirect('/users');
            }

            $target  = $this->userModel->getById($id);
            $deleted = $this->userModel->delete($id);

            if ($deleted && $target) {
                $this->audit->record(ActivityLog::EVENT_USER_DELETED, "User deleted: {$target['username']} (id:{$id})", (int) $_SESSION['user_id']);
            }

            $deleted ? Flash::success('User deleted successfully') : Flash::error('Failed to delete user');
        }

        $this->redirect('/users');
    }

    private function validateUser(array $data, ?int $excludeId = null): ?string
    {
        if (!empty($data['password']) && ($policyError = PasswordPolicy::validate($data['password']))) {
            return $policyError;
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
