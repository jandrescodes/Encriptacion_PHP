<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Lib\Redis\RedisConnector;

/** Redis real aislado para los tests: DB y prefijo propios, nunca FLUSHDB. */
trait InteractsWithRedis
{
    protected \Redis $redis;
    protected array $redisConfig;

    protected function setUpRedis(): void
    {
        $this->redisConfig = RedisConnector::configFromEnv();

        if ($this->redisConfig['db'] === 0 || !str_contains($this->redisConfig['prefix'], 'test')) {
            throw new \RuntimeException(
                'Refuso ejecutar tests contra datos reales de Redis. ' .
                    'Configura REDIS_DB distinto de 0 y un REDIS_PREFIX con "test" en .env.testing'
            );
        }

        if (!class_exists(\Redis::class)) {
            $this->redisUnavailable('ext-redis no está instalada');
        }

        $redis = RedisConnector::connect($this->redisConfig, false);
        if ($redis === null) {
            $this->redisUnavailable('no se pudo conectar al servidor Redis de test');
        }

        $this->redis = $redis;
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $this->flushTestKeys();
    }

    protected function tearDownRedis(): void
    {
        if (isset($this->redis)) {
            $this->flushTestKeys();
        }
    }

    protected function flushTestKeys(): void
    {
        $it = null;
        while (($keys = $this->redis->scan($it, $this->redisConfig['prefix'] . '*', 100)) !== false) {
            foreach ($keys as $key) {
                $this->redis->unlink($key);
            }
        }
    }

    private function redisUnavailable(string $reason): never
    {
        if (env('REDIS_REQUIRED', false)) {
            $this->fail('Redis es obligatorio en este entorno: ' . $reason);
        }

        $this->markTestSkipped($reason);
    }
}
