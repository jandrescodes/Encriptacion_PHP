<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserOAuthTest extends TestCase
{
    private User $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new User(self::$db);
    }

    // --- generateUniqueUsername ---

    #[Test]
    public function it_generates_a_slug_from_given_and_family_name(): void
    {
        $username = $this->users->generateUniqueUsername('Juan Carlos Perez-Gomez');
        $this->assertSame('juancarlosperezgomez', $username);
    }

    #[Test]
    public function it_appends_a_numeric_suffix_when_the_username_is_taken(): void
    {
        $this->createUser(['username' => 'johnsmith']);
        $this->assertSame('johnsmith1', $this->users->generateUniqueUsername('John Smith'));
    }

    #[Test]
    public function it_appends_incremental_suffixes_on_consecutive_collisions(): void
    {
        $this->createUser(['username' => 'annasmith', 'email' => 'anna1@example.com']);
        $this->assertSame('annasmith1', $this->users->generateUniqueUsername('Anna Smith'));
        $this->createUser(['username' => 'annasmith1', 'email' => 'anna2@example.com']);
        $this->assertSame('annasmith2', $this->users->generateUniqueUsername('Anna Smith'));
    }

    #[Test]
    public function it_falls_back_to_the_email_local_part_when_the_base_is_empty(): void
    {
        $username = $this->users->generateUniqueUsername('!!!', 'alice.smith@example.com');
        $this->assertSame('alicesmith', $username);
    }

    #[Test]
    public function it_uses_a_default_username_when_nothing_derivable_exists(): void
    {
        $this->assertSame('user', $this->users->generateUniqueUsername('!!!'));
    }

    // --- createFromOAuth / findByOAuth ---

    #[Test]
    public function it_creates_an_oauth_user_with_null_password(): void
    {
        $id = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $this->assertGreaterThan(0, $id);
        $row = $this->users->getById($id);
        $this->assertSame('Jane', $row['first_name']);
        $this->assertNull($row['password']);
        $this->assertSame('google', $row['oauth_provider']);
        $this->assertSame('sub-123456', $row['oauth_id']);
        $this->assertSame(0, (int) $row['is_admin']);
    }

    #[Test]
    public function it_exposes_oauth_created_users_through_get_all(): void
    {
        $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $this->assertCount(1, $this->users->getAll());
    }

    #[Test]
    public function it_finds_a_user_by_oauth_provider_and_id(): void
    {
        $id = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $found = $this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, 'sub-123456');
        $this->assertNotNull($found);
        $this->assertSame($id, (int) $found['id']);

        $this->assertNull($this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, 'sub-unknown'));
        $this->assertNull($this->users->findByOAuth('github', 'sub-123456'));
    }

    // --- linkOAuthProvider ---

    #[Test]
    public function it_links_an_existing_user_to_an_oauth_provider(): void
    {
        $u = $this->createUser(['email' => 'bob@example.com']);
        $ok = $this->users->linkOAuthProvider($u['id'], User::OAUTH_PROVIDER_GOOGLE, 'sub-bob');

        $this->assertTrue($ok);
        $row = $this->users->getById($u['id']);
        $this->assertSame('google', $row['oauth_provider']);
        $this->assertSame('sub-bob', $row['oauth_id']);
        $this->assertNotNull($this->users->findByOAuth(User::OAUTH_PROVIDER_GOOGLE, 'sub-bob'));
    }

    #[Test]
    public function it_returns_false_when_linking_an_unknown_user(): void
    {
        $this->assertFalse($this->users->linkOAuthProvider(999999, User::OAUTH_PROVIDER_GOOGLE, 'sub-x'));
    }

    // --- isOAuthAccount ---

    #[Test]
    public function it_distinguishes_oauth_accounts_from_regular_ones(): void
    {
        $normal = $this->createUser();
        $oauthId = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $this->assertFalse(User::isOAuthAccount($this->users->getById($normal['id'])));
        $this->assertTrue(User::isOAuthAccount($this->users->getById($oauthId)));
    }

    // --- rejectPasswordLogin ---

    #[Test]
    public function reject_password_login_blocks_oauth_accounts_without_password(): void
    {
        $oauthId = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $this->assertSame(
            'This account uses Google sign-in. Please use "Continue with Google".',
            User::rejectPasswordLogin($this->users->getById($oauthId))
        );
    }

    #[Test]
    public function reject_password_login_allows_regular_accounts(): void
    {
        $u = $this->createUser();
        $this->assertNull(User::rejectPasswordLogin($this->users->getById($u['id'])));
    }

    #[Test]
    public function reject_password_login_allows_hybrid_accounts_with_a_password_set(): void
    {
        $oauthId = $this->users->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@example.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-123456',
        ]);

        $this->users->updatePasswordProfile($oauthId, 'hybridpass123');
        $this->assertNull(User::rejectPasswordLogin($this->users->getById($oauthId)));
    }
}
