<?php

declare(strict_types=1);

namespace App\Core;

// Las claves message/icon son el contrato con views/layouts/messages.php y con los tests que leen $_SESSION.
final class Flash
{
    private const MESSAGE = 'message';
    private const ICON    = 'icon';

    public static function set(string $message, string $icon): void
    {
        $_SESSION[self::MESSAGE] = $message;
        $_SESSION[self::ICON]    = $icon;
    }

    public static function success(string $message): void
    {
        self::set($message, 'success');
    }

    public static function error(string $message): void
    {
        self::set($message, 'error');
    }

    public static function warning(string $message): void
    {
        self::set($message, 'warning');
    }

    public static function info(string $message): void
    {
        self::set($message, 'info');
    }

    /**
     * Devuelve el aviso pendiente y lo consume; null si falta el mensaje o el icono.
     *
     * @return array{message: string, icon: string}|null
     */
    public static function pull(): ?array
    {
        if (!isset($_SESSION[self::MESSAGE], $_SESSION[self::ICON])) {
            return null;
        }

        $flash = ['message' => (string) $_SESSION[self::MESSAGE], 'icon' => (string) $_SESSION[self::ICON]];
        unset($_SESSION[self::MESSAGE], $_SESSION[self::ICON]);

        return $flash;
    }
}
