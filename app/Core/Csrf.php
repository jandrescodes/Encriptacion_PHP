<?php

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(): bool
    {
        $submitted = $_POST['_csrf'] ?? '';
        $stored    = $_SESSION[self::SESSION_KEY] ?? '';
        $valid     = $submitted !== '' && $stored !== '' && hash_equals($stored, $submitted);

        if ($valid) {
            unset($_SESSION[self::SESSION_KEY]);
        }

        return $valid;
    }
}
