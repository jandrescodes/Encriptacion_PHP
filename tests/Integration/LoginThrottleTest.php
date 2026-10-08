<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\LoginThrottle;
use PHPUnit\Framework\TestCase;
use Tests\Support\InteractsWithRedis;

final class LoginThrottleTest extends TestCase
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

    private function throttle(int $max = 3, int $window = 60): LoginThrottle
    {
        return new LoginThrottle($this->redis, $this->redisConfig['prefix'] . 'rl:login:', $max, $window);
    }

    public function testAllowsUpToMaxAttemptsAndBlocksTheNext(): void
    {
        $throttle = $this->throttle(3);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(0, $throttle->hit('203.0.113.7'));
        }

        $retry = $throttle->hit('203.0.113.7');
        $this->assertGreaterThan(0, $retry);
        $this->assertLessThanOrEqual(60, $retry);
    }

    public function testIpsAreCountedIndependently(): void
    {
        $throttle = $this->throttle(1);

        $this->assertSame(0, $throttle->hit('203.0.113.7'));
        $this->assertGreaterThan(0, $throttle->hit('203.0.113.7'));
        $this->assertSame(0, $throttle->hit('203.0.113.8'));
    }

    public function testStoresHashedIpWithTtlAndWindowIsNotExtended(): void
    {
        $throttle = $this->throttle(5, 60);
        $throttle->hit('203.0.113.7');

        $key = $this->redisConfig['prefix'] . 'rl:login:' . hash('sha256', '203.0.113.7');
        $this->assertSame(1, $this->redis->exists($key));
        $this->assertSame(0, $this->redis->exists($this->redisConfig['prefix'] . 'rl:login:203.0.113.7'));

        $this->redis->expire($key, 30);
        $throttle->hit('203.0.113.7');
        $this->assertLessThanOrEqual(30, $this->redis->ttl($key));
    }

    public function testFailsOpenWhenRedisIsDown(): void
    {
        $dead = new \Redis();
        $throttle = new LoginThrottle($dead, 'x:', 1, 60);

        ini_set('error_log', sys_get_temp_dir() . '/phpunit-throttle.log');
        $this->assertSame(0, $throttle->hit('203.0.113.7'));
        $this->assertSame(0, $throttle->hit('203.0.113.7'));
    }
}
