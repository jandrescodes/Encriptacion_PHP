<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;
use Tests\Support\InteractsWithRedis;

require_once __DIR__ . '/../../app/Config/session.php';

final class SessionStorageTest extends TestCase
{
    use InteractsWithRedis;

    protected function setUp(): void
    {
        $this->setUpRedis();
    }

    protected function tearDown(): void
    {
        $this->tearDownRedis();
        unset($_ENV['SESSION_DRIVER']);
    }

    public function testSavePathIncludesDatabasePrefixAndEncodedCredentials(): void
    {
        $path = redis_session_save_path([
            'host' => '10.0.0.5', 'port' => 6380, 'username' => 'u s', 'password' => 'p&=#1',
            'db' => 3, 'prefix' => 'app:', 'connect_timeout' => 0.2, 'read_timeout' => 0.5,
        ]);

        $this->assertStringStartsWith('tcp://10.0.0.5:6380?', $path);
        $this->assertStringContainsString('database=3', $path);
        $this->assertStringContainsString('prefix=app%3Asess%3A', $path);
        $this->assertStringContainsString('auth[]=u%20s&auth[]=p%26%3D%231', $path);
    }

    public function testSavePathOmitsAuthWithoutPassword(): void
    {
        $path = redis_session_save_path([
            'host' => '127.0.0.1', 'port' => 6379, 'username' => '', 'password' => '',
            'db' => 0, 'prefix' => 'app:', 'connect_timeout' => 0.2, 'read_timeout' => 0.5,
        ]);

        $this->assertStringNotContainsString('auth', $path);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFileDriverLeavesIniUntouched(): void
    {
        $_ENV['SESSION_DRIVER'] = 'file';
        $before = ini_get('session.save_handler');

        $this->assertSame('files', configure_session_storage());
        $this->assertSame($before, ini_get('session.save_handler'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFallsBackToFilesWhenRedisIsDown(): void
    {
        $_ENV['SESSION_DRIVER'] = 'redis';
        $_ENV['REDIS_PORT']     = '1';
        ini_set('error_log', sys_get_temp_dir() . '/phpunit-session-fallback.log');
        $before = ini_get('session.save_handler');

        $this->assertSame('files', configure_session_storage());
        $this->assertSame($before, ini_get('session.save_handler'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionLivesInRedisWithTtlAndRegenerateDeletesOldKey(): void
    {
        $_ENV['SESSION_DRIVER']  = 'redis';
        $_ENV['SESSION_TIMEOUT'] = '600';
        $this->assertSame('redis', configure_session_storage());

        session_start();
        $id = session_id();
        $_SESSION['k'] = 'v';
        session_write_close();

        $key = $this->redisConfig['prefix'] . 'sess:' . $id;
        $this->assertSame(1, $this->redis->exists($key));
        $this->assertEqualsWithDelta(900, $this->redis->ttl($key), 5);

        session_id($id);
        session_start();
        session_regenerate_id(true);
        $newId = session_id();
        session_write_close();

        $this->assertNotSame($id, $newId);
        $this->assertSame(0, $this->redis->exists($key));
        $this->assertSame(1, $this->redis->exists($this->redisConfig['prefix'] . 'sess:' . $newId));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testStrictModeReplacesUnknownSessionId(): void
    {
        $_ENV['SESSION_DRIVER'] = 'redis';
        $this->assertSame('redis', configure_session_storage());

        $forged = 'forged' . bin2hex(random_bytes(8));
        session_id($forged);
        session_start();
        $issued = session_id();
        session_write_close();

        $this->assertNotSame($forged, $issued);
    }
}
