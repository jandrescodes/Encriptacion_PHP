<?php

declare(strict_types=1);

namespace App\Policy;

// Reglas de autoproteccion del administrador: el último admin no debe poder quedarse sin acceso por error propio.
final class UserPolicy
{
    public function canDelete(int $actorId, int $targetId): bool
    {
        return $actorId !== $targetId;
    }

    public function canSetAdminFlag(int $actorId, int $targetId, bool $isAdmin): bool
    {
        return $isAdmin || $actorId !== $targetId;
    }
}
