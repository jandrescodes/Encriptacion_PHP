<?php

declare(strict_types=1);

namespace App\Lib\Redis;

final class RedisConnector
{
    private static bool $attempted = false;
    private static ?\Redis $shared = null;

    /** @return array{host:string,port:int,username:string,password:string,db:int,prefix:string,connect_timeout:float,read_timeout:float} */
    public static function configFromEnv(): array
    {
        return [
            'host'            => (string) env('REDIS_HOST', '127.0.0.1'),
            'port'            => (int) env('REDIS_PORT', 6379),
            'username'        => (string) env('REDIS_USERNAME', ''),
            'password'        => (string) env('REDIS_PASSWORD', ''),
            'db'              => (int) env('REDIS_DB', 0),
            'prefix'          => (string) env('REDIS_PREFIX', 'encphp:'),
            'connect_timeout' => (float) env('REDIS_CONNECT_TIMEOUT', 0.2),
            'read_timeout'    => (float) env('REDIS_READ_TIMEOUT', 0.5),
        ];
    }

    /** Una sola conexión (o un solo fallo) por petición: así un Redis caído cuesta un timeout, no uno por consulta. */
    public static function fromEnv(): ?\Redis
    {
        if (!self::$attempted) {
            self::$attempted = true;
            self::$shared    = self::connect(self::configFromEnv());
        }

        return self::$shared;
    }

    public static function reset(): void
    {
        self::$attempted = false;
        self::$shared    = null;
    }

    public static function isValidNamespace(string $value): bool
    {
        return preg_match('/^[a-z0-9_:-]+$/i', $value) === 1;
    }

    public static function connect(array $config, bool $log = true): ?\Redis
    {
        if (!class_exists(\Redis::class)) {
            return self::fail('ext-redis no está instalada', $log);
        }

        if (!self::isValidNamespace($config['prefix'])) {
            return self::fail('REDIS_PREFIX contiene caracteres no permitidos', $log);
        }

        try {
            $redis = new \Redis();
            $redis->connect($config['host'], $config['port'], $config['connect_timeout']);
            $redis->setOption(\Redis::OPT_READ_TIMEOUT, $config['read_timeout']);

            if ($config['username'] !== '' && $config['password'] !== '') {
                $redis->auth([$config['username'], $config['password']]);
            } elseif ($config['password'] !== '') {
                $redis->auth($config['password']);
            }

            if ($config['db'] !== 0) {
                $redis->select($config['db']);
            }
        } catch (\RedisException $e) {
            // El mensaje de phpredis no incluye credenciales; igualmente no se loguea el DSN completo.
            return self::fail($e->getMessage(), $log);
        }

        return $redis;
    }

    private static function fail(string $reason, bool $log): null
    {
        if ($log) {
            error_log('Redis no disponible, se continúa sin él: ' . $reason);
        }

        return null;
    }
}
