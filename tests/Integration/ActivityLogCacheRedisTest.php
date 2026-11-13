<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Lib\Cache\RedisCache;
use App\Model\ActivityLog;
use App\Service\AuditLog;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithRedis;
use Tests\TestCase;

final class ActivityLogCacheRedisTest extends TestCase
{
    use InteractsWithRedis;

    private ActivityLog $logs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();
        $this->logs = new ActivityLog(self::$db, new RedisCache($this->redis, 'encphp_test:v1:cache:'));

        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'first');
    }

    protected function tearDown(): void
    {
        $this->tearDownRedis();
        parent::tearDown();
    }

    #[Test]
    public function activity_log_queries_fill_redis_with_a_ttl(): void
    {
        $this->logs->getAll([], 25, 0);
        $this->logs->getTotalCount([]);
        $this->logs->getRecentEvents(5);
        $this->logs->getCountTodayByEvent(ActivityLog::EVENT_LOGIN_SUCCESS);

        $keys = [];
        $iterator = null;
        while (($batch = $this->redis->scan($iterator, 'encphp_test:v1:cache:activity_log.*', 100)) !== false) {
            $keys = array_merge($keys, $batch);
        }

        $this->assertCount(4, $keys);
        foreach ($keys as $key) {
            $this->assertGreaterThan(0, $this->redis->pttl($key));
        }
    }

    #[Test]
    public function cached_activity_log_result_is_reused_until_expiration(): void
    {
        $this->assertCount(1, $this->logs->getAll([], 25, 0));

        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'second');

        $this->assertCount(1, $this->logs->getAll([], 25, 0));
    }
}
