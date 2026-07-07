<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\UserSession;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserSessionTest extends TestCase
{
    private UserSession $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new UserSession(self::$db);
    }

    #[Test]
    public function create_stores_hash_not_raw_token(): void
    {
        $user = $this->createUser();
        $raw  = bin2hex(random_bytes(32));

        $this->model->create($user['id'], $raw, '127.0.0.1', 'PHPUnit-Agent');

        $expectedHash = hash('sha256', $raw);
        $row = self::$db->query("SELECT token_hash FROM user_sessions WHERE user_id={$user['id']}")->fetch_assoc();

        $this->assertSame($expectedHash, $row['token_hash']);
        $this->assertStringNotContainsString($raw, (string) $row['token_hash']);
    }

    #[Test]
    public function create_with_via_remember_marks_column(): void
    {
        $user = $this->createUser();

        $this->model->create($user['id'], bin2hex(random_bytes(32)), '127.0.0.1', 'PHPUnit-Agent', true);

        $row = self::$db->query("SELECT via_remember FROM user_sessions WHERE user_id={$user['id']}")->fetch_assoc();
        $this->assertSame(1, (int) $row['via_remember']);
    }

    #[Test]
    public function get_for_user_returns_only_sessions_of_that_user_ordered_by_last_activity(): void
    {
        $userA = $this->createUser(['username' => 'usera', 'email' => 'a@example.com']);
        $userB = $this->createUser(['username' => 'userb', 'email' => 'b@example.com']);

        $s1 = $this->createSession($userA['id']);
        $s2 = $this->createSession($userA['id']);
        $this->createSession($userB['id']);

        self::$db->query("UPDATE user_sessions SET last_activity = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id={$s1['id']}");

        $sessions = $this->model->getForUser($userA['id'], $s2['token_hash']);

        $this->assertCount(2, $sessions);
        $this->assertSame((string) $s2['id'], (string) $sessions[0]['id']);
    }

    #[Test]
    public function get_for_user_marks_is_current_only_for_matching_hash(): void
    {
        $user = $this->createUser();
        $s1   = $this->createSession($user['id']);
        $s2   = $this->createSession($user['id']);

        $sessions = $this->model->getForUser($user['id'], $s1['token_hash']);

        foreach ($sessions as $row) {
            $expected = ((int) $row['id'] === $s1['id']) ? 1 : 0;
            $this->assertSame($expected, (int) $row['is_current']);
        }
    }

    #[Test]
    public function touch_updates_last_activity(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        self::$db->query("UPDATE user_sessions SET last_activity = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id={$s['id']}");

        $this->model->touch($s['token_hash']);

        $row = self::$db->query(
            "SELECT (last_activity >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)) AS is_recent FROM user_sessions WHERE id={$s['id']}"
        )->fetch_assoc();
        $this->assertSame(1, (int) $row['is_recent']);
    }

    #[Test]
    public function exists_active_true_for_fresh_session(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        $this->assertTrue($this->model->existsActive($s['token_hash'], 1800));
    }

    #[Test]
    public function exists_active_false_after_expiration(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        self::$db->query("UPDATE user_sessions SET last_activity = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id={$s['id']}");

        $this->assertFalse($this->model->existsActive($s['token_hash'], 1800));
    }

    #[Test]
    public function revoke_deletes_row_and_returns_true(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        $result = $this->model->revoke($s['id'], $user['id']);

        $this->assertTrue($result);
        $row = self::$db->query("SELECT id FROM user_sessions WHERE id={$s['id']}")->fetch_assoc();
        $this->assertNull($row);
    }

    #[Test]
    public function revoke_with_other_user_id_does_not_delete_and_returns_false(): void
    {
        $owner   = $this->createUser(['username' => 'owner', 'email' => 'owner@example.com']);
        $attacker = $this->createUser(['username' => 'attacker', 'email' => 'attacker@example.com']);
        $s = $this->createSession($owner['id']);

        $result = $this->model->revoke($s['id'], $attacker['id']);

        $this->assertFalse($result);
        $row = self::$db->query("SELECT id FROM user_sessions WHERE id={$s['id']}")->fetch_assoc();
        $this->assertNotNull($row);
    }

    #[Test]
    public function revoke_all_except_deletes_all_but_current(): void
    {
        $user = $this->createUser();
        $current = $this->createSession($user['id']);
        $this->createSession($user['id']);
        $this->createSession($user['id']);

        $deleted = $this->model->revokeAllExcept($user['id'], $current['token_hash']);

        $this->assertSame(2, $deleted);
        $remaining = self::$db->query("SELECT id FROM user_sessions WHERE user_id={$user['id']}")->fetch_all(MYSQLI_ASSOC);
        $this->assertCount(1, $remaining);
        $this->assertSame((string) $current['id'], (string) $remaining[0]['id']);
    }

    #[Test]
    public function delete_by_token_hash_removes_row(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        $this->model->deleteByTokenHash($s['token_hash']);

        $row = self::$db->query("SELECT id FROM user_sessions WHERE id={$s['id']}")->fetch_assoc();
        $this->assertNull($row);
    }

    #[Test]
    public function purge_expired_removes_only_expired_rows(): void
    {
        $user   = $this->createUser();
        $fresh  = $this->createSession($user['id']);
        $stale  = $this->createSession($user['id']);

        self::$db->query("UPDATE user_sessions SET last_activity = DATE_SUB(NOW(), INTERVAL 2 HOUR) WHERE id={$stale['id']}");

        $purged = $this->model->purgeExpired(1800);

        $this->assertSame(1, $purged);
        $row = self::$db->query("SELECT id FROM user_sessions WHERE id={$fresh['id']}")->fetch_assoc();
        $this->assertNotNull($row);
    }

    #[Test]
    public function deleting_user_cascades_to_user_sessions(): void
    {
        $user = $this->createUser();
        $s    = $this->createSession($user['id']);

        self::$db->query("DELETE FROM users WHERE id={$user['id']}");

        $row = self::$db->query("SELECT id FROM user_sessions WHERE id={$s['id']}")->fetch_assoc();
        $this->assertNull($row);
    }
}
