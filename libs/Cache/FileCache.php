<?php

declare(strict_types=1);

namespace App\Lib\Cache;

class FileCache implements CacheInterface
{
    public function __construct(
        private string $directory,
        private bool $enabled = true
    ) {
    }

    public function get(string $key): mixed
    {
        if (!$this->enabled) {
            return null;
        }

        $file = $this->filePath($key);
        if (!is_file($file)) {
            return null;
        }

        $raw = file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded === false) {
            $this->forget($key);
            return null;
        }

        $payload = unserialize($decoded, ['allowed_classes' => false]);
        if (!is_array($payload) || !array_key_exists('expires_at', $payload) || !array_key_exists('value', $payload)) {
            $this->forget($key);
            return null;
        }

        if ($payload['expires_at'] < time()) {
            $this->forget($key);
            return null;
        }

        return $payload['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds): void
    {
        if (!$this->enabled) {
            return;
        }

        $payload = [
            'expires_at' => time() + max(1, $ttlSeconds),
            'value'      => $value,
        ];

        $result = file_put_contents(
            $this->filePath($key),
            base64_encode(serialize($payload)),
            LOCK_EX
        );

        if ($result === false) {
            trigger_error('Unable to write cache file for key: ' . $key, E_USER_WARNING);
        }
    }

    public function forget(string $key): void
    {
        if (!$this->enabled) {
            return;
        }

        $file = $this->filePath($key);
        if (is_file($file)) {
            unlink($file);
        }
    }

    public function remember(string $key, int $ttlSeconds, callable $resolver): mixed
    {
        if ($this->enabled && is_file($this->filePath($key))) {
            $cached = $this->get($key);
            if ($cached !== null) {
                return $cached;
            }
        }

        $value = $resolver();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    private function filePath(string $key): string
    {
        return rtrim($this->directory, '/') . '/' . sha1($key) . '.cache';
    }
}
