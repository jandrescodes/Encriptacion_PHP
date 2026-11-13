<?php

declare(strict_types=1);

namespace App\Lib\Cache;

/** Caché apagada o no disponible: toda lectura es un miss y cada remember() consulta la fuente. */
class NullCache implements CacheInterface
{
    public function get(string $key): mixed
    {
        return null;
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
    }

    public function forget(string $key): void
    {
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        return $resolver();
    }
}
