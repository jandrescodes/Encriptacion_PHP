<?php

namespace App\Lib\Cache;

interface CacheInterface
{
    public function get(string $key): mixed;

    public function set(string $key, mixed $value, int $ttlSeconds): void;

    public function forget(string $key): void;

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed;
}
