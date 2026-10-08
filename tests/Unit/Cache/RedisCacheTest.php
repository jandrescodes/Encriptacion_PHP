<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Lib\Cache\CacheInterface;
use App\Lib\Cache\RedisCache;
use App\Lib\Redis\RedisConnector;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithRedis;

final class RedisCacheTest extends CacheContractTestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        $this->setUpRedis();
    }

    protected function tearDown(): void
    {
        $this->tearDownRedis();
    }

    protected function makeCache(string $version = 'v1'): CacheInterface
    {
        return new RedisCache($this->redis, $this->redisConfig['prefix'] . $version . ':cache:');
    }

    #[Test]
    public function it_stores_under_a_prefixed_versioned_key(): void
    {
        $this->makeCache()->set('users.all', [1], 60);

        $this->assertSame(1, $this->redis->exists('encphp_test:v1:cache:users.all'));
    }

    #[Test]
    public function it_uses_a_native_ttl(): void
    {
        $this->makeCache()->set('ttl.key', 'x', 60);

        $pttl = $this->redis->pttl('encphp_test:v1:cache:ttl.key');
        $this->assertGreaterThan(0, $pttl);
        $this->assertLessThanOrEqual(60000, $pttl);
    }

    #[Test]
    public function a_non_positive_ttl_still_expires(): void
    {
        $this->makeCache()->set('ttl.zero', 'x', 0);

        $this->assertGreaterThan(0, $this->redis->pttl('encphp_test:v1:cache:ttl.zero'));
    }

    #[Test]
    public function changing_the_version_isolates_keys(): void
    {
        $this->makeCache('v1')->set('shared', 'old', 60);

        $this->assertNull($this->makeCache('v2')->get('shared'));
    }

    #[Test]
    public function a_corrupt_payload_is_a_miss(): void
    {
        $this->redis->set('encphp_test:v1:cache:corrupt', 'no-es-serialize');
        $cache = $this->makeCache();

        $this->assertNull($cache->get('corrupt'));
        $this->assertSame('fresh', $cache->remember('corrupt', 60, fn() => 'fresh'));
    }

    #[Test]
    public function a_dead_connection_degrades_to_no_cache_without_throwing(): void
    {
        $cache  = new RedisCache(new \Redis(), 'encphp_test:v1:cache:');
        $calls  = 0;
        $start  = microtime(true);

        $cache->set('dead', 'x', 60);
        $this->assertNull($cache->get('dead'));
        $cache->forget('dead');
        $value = $cache->remember('dead', 60, function () use (&$calls) {
            $calls++;
            return 'from-source';
        });

        $this->assertSame('from-source', $value);
        $this->assertSame(1, $calls);
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    #[Test]
    public function an_unreachable_server_yields_no_connection_quickly(): void
    {
        $start = microtime(true);

        $redis = RedisConnector::connect(['port' => 1] + $this->redisConfig, false);

        $this->assertNull($redis);
        $this->assertLessThan(1.0, microtime(true) - $start);
    }

    #[Test]
    public function a_wrong_password_yields_no_connection(): void
    {
        $this->assertNull(RedisConnector::connect(['password' => 'incorrecta'] + $this->redisConfig, false));
    }

    #[Test]
    public function an_invalid_prefix_yields_no_connection(): void
    {
        $this->assertNull(RedisConnector::connect(['prefix' => 'bad prefix*'] + $this->redisConfig, false));
    }

    #[Test]
    public function the_failure_log_never_contains_the_password(): void
    {
        $logFile  = tempnam(sys_get_temp_dir(), 'redislog');
        $previous = ini_set('error_log', $logFile);

        try {
            RedisConnector::connect(['password' => 'clave-secreta-xyz'] + $this->redisConfig);
        } finally {
            ini_set('error_log', $previous === false ? '' : $previous);
        }

        $log = (string) file_get_contents($logFile);
        unlink($logFile);

        $this->assertStringContainsString('Redis no disponible', $log);
        $this->assertStringNotContainsString('clave-secreta-xyz', $log);
    }
}
