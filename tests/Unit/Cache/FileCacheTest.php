<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use App\Lib\Cache\CacheInterface;
use App\Lib\Cache\FileCache;
use PHPUnit\Framework\Attributes\Test;

final class FileCacheTest extends CacheContractTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/encphp_filecache_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*.cache') ?: []);
        rmdir($this->dir);
    }

    protected function makeCache(): CacheInterface
    {
        return new FileCache($this->dir);
    }

    #[Test]
    public function it_writes_a_cache_file_per_key(): void
    {
        $this->makeCache()->set('file.key', 1, 60);

        $this->assertCount(1, glob($this->dir . '/*.cache'));
    }

    #[Test]
    public function an_expired_entry_is_a_miss_and_is_removed(): void
    {
        $cache = $this->makeCache();
        $cache->set('file.expired', 'x', 60);
        $file = glob($this->dir . '/*.cache')[0];
        file_put_contents($file, base64_encode(serialize(['expires_at' => time() - 1, 'value' => 'x'])));

        $this->assertNull($cache->get('file.expired'));
        $this->assertFileDoesNotExist($file);
    }

    #[Test]
    public function a_disabled_cache_never_stores(): void
    {
        $cache = new FileCache($this->dir, false);
        $cache->set('file.off', 'x', 60);

        $this->assertNull($cache->get('file.off'));
        $this->assertCount(0, glob($this->dir . '/*.cache'));
    }
}
