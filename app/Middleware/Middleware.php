<?php

declare(strict_types=1);

namespace App\Middleware;

interface Middleware
{
    /** Corta la petición (redirect + exit) o la deja continuar al controller. */
    public function handle(\mysqli $connection): void;
}
