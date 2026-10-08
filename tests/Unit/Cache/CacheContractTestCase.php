<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Lib\Cache\CacheInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

abstract class CacheContractTestCase extends TestCase
{
    abstract protected function makeCache(): CacheInterface;

    #[Test]
    public function it_returns_null_for_a_missing_key(): void
    {
        $this->assertNull($this->makeCache()->get('contract.missing'));
    }

    #[Test]
    public function it_stores_and_reads_a_value(): void
    {
        $cache = $this->makeCache();
        $cache->set('contract.value', ['a' => 1], 60);

        $this->assertSame(['a' => 1], $cache->get('contract.value'));
    }

    #[Test]
    public function it_forgets_a_key(): void
    {
        $cache = $this->makeCache();
        $cache->set('contract.forget', 'x', 60);
        $cache->forget('contract.forget');

        $this->assertNull($cache->get('contract.forget'));
    }

    #[Test]
    public function it_forgets_a_missing_key_without_error(): void
    {
        $this->makeCache()->forget('contract.never-set');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function remember_calls_the_resolver_only_once(): void
    {
        $cache = $this->makeCache();
        $calls = 0;
        $resolver = function () use (&$calls): array {
            $calls++;
            return ['id' => 7];
        };

        $first  = $cache->remember('contract.remember', 60, $resolver);
        $second = $cache->remember('contract.remember', 60, $resolver);

        $this->assertSame(['id' => 7], $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function an_empty_array_is_a_hit_not_a_miss(): void
    {
        $cache = $this->makeCache();
        $calls = 0;
        $resolver = function () use (&$calls): array {
            $calls++;
            return [];
        };

        $cache->remember('contract.empty', 60, $resolver);
        $this->assertSame([], $cache->remember('contract.empty', 60, $resolver));
        $this->assertSame(1, $calls);
    }

    #[Test]
    public function unserialize_never_instantiates_classes(): void
    {
        $cache = $this->makeCache();
        $cache->set('contract.object', new \ArrayObject([1]), 60);

        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $cache->get('contract.object'));
    }
}
