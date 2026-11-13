<?php

declare(strict_types=1);

namespace App\Core;

use App\Service\AuditLog;

abstract class Controller
{
    protected AuditLog $audit;

    public function __construct(protected \mysqli $connection, ?AuditLog $audit = null)
    {
        $this->audit = $audit ?? new AuditLog($connection);
    }

    protected function verifyCsrf(string $redirectPath): void
    {
        if (!Csrf::verify()) {
            Flash::error('Invalid request. Please try again.');
            $this->redirect($redirectPath);
        }
    }

    protected function redirect(string $path): void
    {
        $url = str_starts_with($path, 'http') ? $path : APP_URL . '/' . ltrim($path, '/');
        header("Location: {$url}");
        exit;
    }

    protected function render(string $view, array $data = [], bool $protected = false): void
    {
        $data['year'] ??= date('Y');
        extract($data, EXTR_SKIP);
        $viewsPath = __DIR__ . '/../../views/';

        if ($protected) {
            require $viewsPath . 'layouts/header.php';
            require $viewsPath . ltrim($view, '/');
            require $viewsPath . 'layouts/footer.php';
        } else {
            require $viewsPath . ltrim($view, '/');
        }
    }
}
