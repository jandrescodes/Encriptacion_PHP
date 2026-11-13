<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Controller\AuthController;

final class CapturingAuthController extends AuthController
{
    public ?string $redirectedTo = null;

    protected function redirect(string $path): void
    {
        $this->redirectedTo = $path;
        throw new RedirectCaptured();
    }
}
