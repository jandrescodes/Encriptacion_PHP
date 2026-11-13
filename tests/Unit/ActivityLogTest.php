<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Model\ActivityLog;
use App\Service\AuditLog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityLogTest extends TestCase
{
    private ActivityLog $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->model = new ActivityLog(self::$db);
    }

    // --- log() ---

    #[Test]
    public function it_inserts_a_row_with_user_id(): void
    {
        $user = $this->createUser();

        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'Login: testuser', $user['id']);

        $row = self::$db->query("SELECT * FROM activity_logs LIMIT 1")->fetch_assoc();
        $this->assertNotNull($row);
        $this->assertSame((string) $user['id'], (string) $row['user_id']);
        $this->assertSame(ActivityLog::EVENT_LOGIN_SUCCESS, $row['event']);
        $this->assertSame('Login: testuser', $row['description']);
    }

    #[Test]
    public function it_inserts_a_row_with_null_user_id(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'Failed login: ghost');

        $row = self::$db->query("SELECT * FROM activity_logs LIMIT 1")->fetch_assoc();
        $this->assertNotNull($row);
        $this->assertNull($row['user_id']);
        $this->assertSame(ActivityLog::EVENT_LOGIN_FAILED, $row['event']);
    }

    #[Test]
    public function it_records_ip_from_remote_addr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';

        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGOUT, 'User logged out');

        $row = self::$db->query("SELECT ip_address FROM activity_logs LIMIT 1")->fetch_assoc();
        $this->assertSame('192.168.1.1', $row['ip_address']);

        unset($_SERVER['REMOTE_ADDR']);
    }

    #[Test]
    public function it_stores_null_ip_when_remote_addr_is_absent(): void
    {
        unset($_SERVER['REMOTE_ADDR']);

        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGOUT, 'User logged out');

        $row = self::$db->query("SELECT ip_address FROM activity_logs LIMIT 1")->fetch_assoc();
        $this->assertNull($row['ip_address']);
    }

    #[Test]
    public function it_stores_description_with_special_characters_as_is(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'Failed login: <script>alert(1)</script>');

        $row = self::$db->query("SELECT description FROM activity_logs LIMIT 1")->fetch_assoc();
        $this->assertSame('Failed login: <script>alert(1)</script>', $row['description']);
    }

    #[Test]
    public function it_accumulates_multiple_rows(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'first');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGOUT, 'second');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'third');

        $count = self::$db->query("SELECT COUNT(*) AS c FROM activity_logs")->fetch_assoc();
        $this->assertSame(3, (int) $count['c']);
    }

    // --- getAll() ---

    #[Test]
    public function get_all_returns_empty_array_when_no_logs(): void
    {
        $this->assertSame([], $this->model->getAll());
    }

    #[Test]
    public function get_all_resolves_user_name_via_join(): void
    {
        $user = $this->createUser(['first_name' => 'Ana', 'last_name' => 'García']);
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'Login: testuser', $user['id']);

        $logs = $this->model->getAll();
        $this->assertCount(1, $logs);
        $this->assertSame('Ana García', $logs[0]['user_name']);
    }

    #[Test]
    public function get_all_shows_anonimo_when_user_id_is_null(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'Failed login: ghost');

        $logs = $this->model->getAll();
        $this->assertCount(1, $logs);
        $this->assertSame('Anónimo', $logs[0]['user_name']);
    }

    #[Test]
    public function get_all_orders_by_created_at_desc(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'first');
        // Force a slightly later timestamp for the second row
        self::$db->query("INSERT INTO activity_logs (event, description, created_at) VALUES ('logout', 'second', NOW() + INTERVAL 1 SECOND)");

        $logs = $this->model->getAll();
        $this->assertCount(2, $logs);
        $this->assertSame('second', $logs[0]['description']);
        $this->assertSame('first', $logs[1]['description']);
    }

    // --- getAll() con filtros ---

    #[Test]
    public function get_all_filters_by_event(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'ok');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'fail');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'fail2');

        $logs = $this->model->getAll(['event' => ActivityLog::EVENT_LOGIN_FAILED]);
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame(ActivityLog::EVENT_LOGIN_FAILED, $log['event']);
        }
    }

    #[Test]
    public function get_all_filters_by_user_id(): void
    {
        $user = $this->createUser();
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'user event', $user['id']);
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'anon event');

        $logs = $this->model->getAll(['user_id' => $user['id']]);
        $this->assertCount(1, $logs);
        $this->assertSame('user event', $logs[0]['description']);
    }

    #[Test]
    public function get_all_filters_by_date_range(): void
    {
        self::$db->query("INSERT INTO activity_logs (event, description, created_at) VALUES ('login_success', 'old', '2020-01-01 12:00:00')");
        self::$db->query("INSERT INTO activity_logs (event, description, created_at) VALUES ('login_success', 'in range', '2020-06-15 12:00:00')");
        self::$db->query("INSERT INTO activity_logs (event, description, created_at) VALUES ('login_success', 'future', '2030-01-01 12:00:00')");

        $logs = $this->model->getAll(['date_from' => '2020-01-01', 'date_to' => '2020-12-31']);
        $this->assertCount(2, $logs);
    }

    #[Test]
    public function get_all_respects_limit_and_offset(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, "event $i");
        }

        $page1 = $this->model->getAll([], 2, 0);
        $page2 = $this->model->getAll([], 2, 2);
        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotSame($page1[0]['description'], $page2[0]['description']);
    }

    #[Test]
    public function get_all_combines_event_and_user_id_with_and(): void
    {
        $user = $this->createUser();
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'user+success', $user['id']);
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'user+failed', $user['id']);
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'anon+success');

        $logs = $this->model->getAll(['event' => ActivityLog::EVENT_LOGIN_SUCCESS, 'user_id' => $user['id']]);
        $this->assertCount(1, $logs);
        $this->assertSame('user+success', $logs[0]['description']);
    }

    // --- getTotalCount() ---

    #[Test]
    public function get_total_count_returns_zero_on_empty_table(): void
    {
        $this->assertSame(0, $this->model->getTotalCount());
    }

    #[Test]
    public function get_total_count_returns_all_rows_without_filters(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'a');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGOUT, 'b');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_FAILED, 'c');

        $this->assertSame(3, $this->model->getTotalCount());
    }

    #[Test]
    public function get_total_count_filters_by_event(): void
    {
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'a');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGIN_SUCCESS, 'b');
        (new AuditLog(self::$db))->record(ActivityLog::EVENT_LOGOUT, 'c');

        $this->assertSame(2, $this->model->getTotalCount(['event' => ActivityLog::EVENT_LOGIN_SUCCESS]));
    }
}
