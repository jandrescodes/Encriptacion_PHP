<?php

use App\Core\Auth;

date_default_timezone_set(env('APP_TIMEZONE', 'America/La_Paz'));

require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';

function session_start_secure(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => str_starts_with(env('APP_URL', ''), 'https'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

configure_session_storage();
session_start_secure();

(new Auth($connection))->restoreFromCookie();
