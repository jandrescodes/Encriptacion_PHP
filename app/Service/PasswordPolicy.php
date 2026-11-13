<?php

declare(strict_types=1);

namespace App\Service;

final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    /** Devuelve el motivo del rechazo o null si la contraseña cumple. */
    public static function validate(string $password): ?string
    {
        return strlen($password) < self::MIN_LENGTH
            ? 'Password must be at least ' . self::MIN_LENGTH . ' characters long'
            : null;
    }
}
