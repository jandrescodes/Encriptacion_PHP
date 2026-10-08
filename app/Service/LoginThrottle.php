<?php

namespace App\Service;

use App\Lib\Redis\RedisConnector;

/**
 * Límite de intentos de login por IP en una ventana fija. Complementa el
 * bloqueo por usuario de MySQL; si Redis falla deja pasar (fail-open) porque
 * ese bloqueo sigue protegiendo las cuentas.
 */
final class LoginThrottle
{
    public function __construct(
        private \Redis $redis,
        private string $namespace,
        private int $maxAttempts,
        private int $windowSeconds
    ) {
    }

    public static function fromEnv(): ?self
    {
        if (!filter_var(env('LOGIN_IP_THROTTLE_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $redis = RedisConnector::fromEnv();
        if ($redis === null) {
            return null;
        }

        $version = (string) env('CACHE_VERSION', 'v1');
        if (!RedisConnector::isValidNamespace($version)) {
            $version = 'v1';
        }

        return new self(
            $redis,
            RedisConnector::configFromEnv()['prefix'] . $version . ':rl:login:',
            max(1, (int) env('LOGIN_IP_MAX_ATTEMPTS', 20)),
            max(1, (int) env('LOGIN_IP_WINDOW', 900))
        );
    }

    /** Registra un intento. Devuelve 0 si puede continuar o los segundos que faltan para que se libere la IP. */
    public function hit(string $ip): int
    {
        $key = $this->namespace . hash('sha256', $ip);

        try {
            // La IP se guarda como hash; INCR y EXPIRE NX en una transacción para que la ventana no se reinicie.
            [$count] = $this->redis->multi()
                ->incr($key)
                ->expire($key, $this->windowSeconds, 'NX')
                ->exec();

            if ($count <= $this->maxAttempts) {
                return 0;
            }

            return max(1, (int) $this->redis->ttl($key));
        } catch (\RedisException $e) {
            error_log('Redis no disponible para el límite por IP, se deja pasar: ' . $e->getMessage());

            return 0;
        }
    }
}
