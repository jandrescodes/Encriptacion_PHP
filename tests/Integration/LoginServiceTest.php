<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Model\User;
use App\Service\LoginService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LoginServiceTest extends TestCase
{
    /** @var array<string, ?string> */
    private array $envBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        // Un .env.testing local puede desactivar el bloqueo; el umbral se fija aquí para que el test sea determinista.
        foreach (['LOGIN_LOCKOUT_ENABLED' => 'true', 'LOGIN_MAX_ATTEMPTS' => '3', 'LOGIN_IP_THROTTLE_ENABLED' => 'false'] as $key => $value) {
            $this->envBackup[$key] = $_ENV[$key] ?? null;
            $_ENV[$key]            = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->envBackup as $key => $value) {
            $value === null ? $this->unsetEnv($key) : $_ENV[$key] = $value;
        }
        parent::tearDown();
    }

    private function unsetEnv(string $key): void
    {
        unset($_ENV[$key]);
    }

    private function service(): LoginService
    {
        return new LoginService(self::$db);
    }

    #[Test]
    public function valid_credentials_open_a_session_and_return_the_user(): void
    {
        $created = $this->createUser();

        $result = $this->service()->attempt('testuser', 'secret123', '203.0.113.1');

        $this->assertTrue($result->ok);
        $this->assertSame('Welcome, Test!', $result->message);
        $this->assertSame('success', $result->icon);
        $this->assertSame($created['id'], (int) $result->user['id']);
        $this->assertSame($created['id'], $_SESSION['user_id']);
    }

    #[Test]
    public function a_wrong_password_fails_without_opening_a_session(): void
    {
        $this->createUser();

        $result = $this->service()->attempt('testuser', 'incorrecta', '203.0.113.1');

        $this->assertFalse($result->ok);
        $this->assertSame('Incorrect username or password', $result->message);
        $this->assertSame('error', $result->icon);
        $this->assertNull($result->user);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function empty_fields_are_reported_before_touching_credentials(): void
    {
        $result = $this->service()->attempt('', 'x', '203.0.113.1');

        $this->assertFalse($result->ok);
        $this->assertSame('Please fill in all fields', $result->message);
        $this->assertSame('warning', $result->icon);
    }

    #[Test]
    public function the_account_locks_after_the_configured_failures_even_with_the_right_password(): void
    {
        $this->createUser();

        for ($i = 0; $i < 3; $i++) {
            $this->service()->attempt('testuser', 'incorrecta', '203.0.113.1');
        }
        $result = $this->service()->attempt('testuser', 'secret123', '203.0.113.1');

        $this->assertFalse($result->ok);
        $this->assertStringStartsWith('Account temporarily locked', $result->message);
        $this->assertArrayNotHasKey('user_id', $_SESSION);
    }

    #[Test]
    public function a_successful_login_clears_previous_failures(): void
    {
        $this->createUser();
        $this->service()->attempt('testuser', 'incorrecta', '203.0.113.1');

        $this->service()->attempt('testuser', 'secret123', '203.0.113.1');

        $this->assertSame(0, (int) self::$db->query("SELECT COUNT(*) FROM login_attempts WHERE identifier = 'testuser'")->fetch_row()[0]);
    }

    #[Test]
    public function google_only_accounts_are_refused_for_password_login(): void
    {
        (new User(self::$db))->createFromOAuth([
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'email'          => 'jane.doe@gmail.com',
            'username'       => 'janedoe',
            'oauth_provider' => User::OAUTH_PROVIDER_GOOGLE,
            'oauth_id'       => 'sub-service',
        ]);

        $result = $this->service()->attempt('janedoe', 'loquesea', '203.0.113.1');

        $this->assertFalse($result->ok);
        $this->assertSame('warning', $result->icon);
    }
}
