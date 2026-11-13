<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Service\SessionIssuer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SessionIssuerTest extends TestCase
{
    private ?string $activeSessionsBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activeSessionsBackup = $_ENV['ACTIVE_SESSIONS_ENABLED'] ?? null;
        $_ENV['ACTIVE_SESSIONS_ENABLED'] = 'true';
        $_SERVER['REMOTE_ADDR']     = '203.0.113.9';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';
    }

    protected function tearDown(): void
    {
        if ($this->activeSessionsBackup === null) {
            unset($_ENV['ACTIVE_SESSIONS_ENABLED']);
        } else {
            $_ENV['ACTIVE_SESSIONS_ENABLED'] = $this->activeSessionsBackup;
        }
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tearDown();
    }

    /** @return array<string, mixed>|null */
    private function sessionRow(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = self::$db->prepare('SELECT user_id, ip_address, via_remember FROM user_sessions WHERE token_hash = ?');
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row;
    }

    #[Test]
    public function it_fills_the_session_with_normalized_types(): void
    {
        $user  = $this->createUser(['is_admin' => 1]);
        $token = (new SessionIssuer(self::$db))->issue(['id' => (string) $user['id'], 'first_name' => 'Test', 'is_admin' => '1']);

        $this->assertSame($user['id'], $_SESSION['user_id']);
        $this->assertSame('Test', $_SESSION['name']);
        $this->assertTrue($_SESSION['is_admin']);
        $this->assertSame($token, $_SESSION['session_token']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertEqualsWithDelta(time(), $_SESSION['last_activity'], 2);
    }

    #[Test]
    public function it_stores_only_the_hash_of_the_token(): void
    {
        $user  = $this->createUser();
        $token = (new SessionIssuer(self::$db))->issue(['id' => $user['id'], 'first_name' => 'Test', 'is_admin' => '0']);

        $row = $this->sessionRow($token);
        $this->assertSame($user['id'], (int) $row['user_id']);
        $this->assertSame('203.0.113.9', $row['ip_address']);
        $this->assertSame(0, (int) $row['via_remember']);
        $this->assertFalse($_SESSION['is_admin']);
        $this->assertSame(0, (int) self::$db->query("SELECT COUNT(*) FROM user_sessions WHERE token_hash = '{$token}'")->fetch_row()[0]);
    }

    #[Test]
    public function it_flags_sessions_opened_by_remember_me(): void
    {
        $user  = $this->createUser();
        $token = (new SessionIssuer(self::$db))->issue(['id' => $user['id'], 'first_name' => 'Test', 'is_admin' => '0'], viaRemember: true);

        $this->assertSame(1, (int) $this->sessionRow($token)['via_remember']);
    }

    #[Test]
    public function it_skips_the_database_row_when_active_sessions_are_disabled(): void
    {
        $_ENV['ACTIVE_SESSIONS_ENABLED'] = 'false';
        $user  = $this->createUser();
        $token = (new SessionIssuer(self::$db))->issue(['id' => $user['id'], 'first_name' => 'Test', 'is_admin' => '0']);

        $this->assertSame($token, $_SESSION['session_token']);
        $this->assertNull($this->sessionRow($token));
    }
}
