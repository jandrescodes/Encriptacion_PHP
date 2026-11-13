<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();
$dotenv->required(['DB_HOST', 'DB_USERNAME', 'DB_DATABASE', 'APP_URL'])->notEmpty();

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    return match (strtolower((string) $value)) {
        'true',  '(true)'  => true,
        'false', '(false)' => false,
        'null',  '(null)'  => null,
        'empty', '(empty)' => '',
        default            => $value,
    };
}

define('APP_URL', rtrim(env('APP_URL', 'http://localhost/Encriptacion_PHP'), '/'));

$url = APP_URL . '/';
