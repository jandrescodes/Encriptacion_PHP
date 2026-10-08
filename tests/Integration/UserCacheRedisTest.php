<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Lib\Cache\RedisCache;
use App\Model\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithRedis;
use Tests\TestCase;

final class UserCacheRedisTest extends TestCase
{
    use InteractsWithRedis;

    private const KEY = 'encphp_test:v1:cache:users.all';

    private User $users;
    private array $existing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRedis();

        $this->users    = new User(self::$db, new RedisCache($this->redis, 'encphp_test:v1:cache:'));
        $this->existing = $this->createUser(['username' => 'cached', 'email' => 'cached@example.com']);
        $this->users->getAll();
    }

    protected function tearDown(): void
    {
        $this->tearDownRedis();
        parent::tearDown();
    }

    #[Test]
    public function get_all_fills_the_cache_key(): void
    {
        $this->assertSame(1, $this->redis->exists(self::KEY));
        $this->assertGreaterThan(0, $this->redis->pttl(self::KEY));
    }

    #[Test]
    public function get_all_serves_the_cached_list_until_invalidated(): void
    {
        $this->createUser(['username' => 'late', 'email' => 'late@example.com']);

        $this->assertCount(1, $this->users->getAll());
    }

    /** @return array<string, array{string}> */
    public static function invalidatingWrites(): array
    {
        return [
            'create'                => ['create'],
            'update'                => ['update'],
            'delete'                => ['delete'],
            'updatePassword'        => ['updatePassword'],
            'updateProfile'         => ['updateProfile'],
            'updatePasswordProfile' => ['updatePasswordProfile'],
            'createFromOAuth'       => ['createFromOAuth'],
            'linkOAuthProvider'     => ['linkOAuthProvider'],
        ];
    }

    #[Test]
    #[DataProvider('invalidatingWrites')]
    public function every_write_drops_the_cached_list(string $write): void
    {
        $id   = $this->existing['id'];
        $data = [
            'first_name' => 'Nuevo', 'last_name' => 'Nombre', 'email' => 'nuevo@example.com',
            'username' => 'nuevo', 'password' => 'otraclave123', 'is_admin' => 0,
        ];

        match ($write) {
            'create'                => $this->users->create($data),
            'update'                => $this->users->update($id, ['email' => 'cached@example.com', 'username' => 'cached'] + $data),
            'delete'                => $this->users->delete($id),
            'updatePassword'        => $this->users->updatePassword('cached@example.com', 'otraclave123'),
            'updateProfile'         => $this->users->updateProfile($id, $data),
            'updatePasswordProfile' => $this->users->updatePasswordProfile($id, 'otraclave123'),
            'createFromOAuth'       => $this->users->createFromOAuth([
                'first_name' => 'G', 'last_name' => 'User', 'email' => 'g@example.com',
                'username' => 'guser', 'oauth_provider' => 'google', 'oauth_id' => 'g-1',
            ]),
            'linkOAuthProvider'     => $this->users->linkOAuthProvider($id, 'google', 'g-2'),
        };

        $this->assertSame(0, $this->redis->exists(self::KEY), "{$write} no invalidó la caché");
    }

    #[Test]
    public function the_next_read_after_a_write_shows_the_new_data(): void
    {
        $this->users->create([
            'first_name' => 'Nuevo', 'last_name' => 'Nombre', 'email' => 'nuevo@example.com',
            'username' => 'nuevo', 'password' => 'otraclave123', 'is_admin' => 0,
        ]);

        $this->assertCount(2, $this->users->getAll());
    }
}
