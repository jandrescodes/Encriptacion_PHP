<?php

declare(strict_types=1);

namespace App\Service;

// Única definición de los atributos de la cookie remember_me: antes había cuatro setcookie y dos sin Secure.
final class RememberCookie
{
    public const NAME = 'remember_me';

    public static function set(string $token, int $ttl): void
    {
        setcookie(self::NAME, $token, self::options(time() + $ttl));
    }

    public static function clear(): void
    {
        setcookie(self::NAME, '', self::options(time() - 3600));
    }

    /** @return array{expires: int, path: string, secure: bool, httponly: bool, samesite: string} */
    private static function options(int $expires): array
    {
        return [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => str_starts_with((string) env('APP_URL', ''), 'https'),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }
}
