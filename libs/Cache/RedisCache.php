<?php

declare(strict_types=1);

namespace App\Lib\Cache;

class RedisCache implements CacheInterface
{
    private bool $broken = false;

    public function __construct(
        private \Redis $redis,
        private string $namespace
    ) {
    }

    public function get(string $key): mixed
    {
        if ($this->broken) {
            return null;
        }

        try {
            $raw = $this->redis->get($this->namespace . $key);
        } catch (\RedisException) {
            $this->broken = true;
            return null;
        }

        if (!is_string($raw)) {
            return null;
        }

        // allowed_classes=false: lo que haya en Redis nunca debe poder instanciar objetos.
        $payload = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('value', $payload)) {
            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        if ($this->broken) {
            return;
        }

        try {
            $this->redis->set(
                $this->namespace . $key,
                serialize(['value' => $value]),
                ['EX' => max(1, $ttlSeconds)]
            );
        } catch (\RedisException) {
            $this->broken = true;
        }
    }

    public function forget(string $key): void
    {
        if ($this->broken) {
            return;
        }

        try {
            $this->redis->unlink($this->namespace . $key);
        } catch (\RedisException) {
            $this->broken = true;
        }
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }
}
