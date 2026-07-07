<?php

namespace App\Model;

use App\Config\Database;
use App\Core\Model;

class ActivityLog extends Model
{
    public const EVENT_LOGIN_SUCCESS    = 'login_success';
    public const EVENT_LOGIN_FAILED     = 'login_failed';
    public const EVENT_LOGOUT           = 'logout';
    public const EVENT_PASSWORD_CHANGED = 'password_changed';
    public const EVENT_PASSWORD_RESET   = 'password_reset';
    public const EVENT_USER_CREATED     = 'user_created';
    public const EVENT_USER_UPDATED     = 'user_updated';
    public const EVENT_USER_DELETED     = 'user_deleted';
    public const EVENT_SESSION_REVOKED  = 'session_revoked';

    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
    }

    public static function log(string $event, string $description, ?int $userId = null): void
    {
        static::logTo(Database::getConnection(), $event, $description, $userId);
    }

    // Separated for test injection without loading autoload.php or using the singleton
    public static function logTo(\mysqli $db, string $event, string $description, ?int $userId = null): void
    {
        try {
            $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $db->prepare(
                "INSERT INTO activity_logs (user_id, event, description, ip_address) VALUES (?, ?, ?, ?)"
            );
            $stmt->bind_param("isss", $userId, $event, $description, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log("ActivityLog::log failed: " . $e->getMessage());
        }
    }

    public function getCountTodayByEvent(string $event): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total
             FROM activity_logs
             WHERE event = ? AND DATE(created_at) = CURDATE()"
        );
        $stmt->bind_param('s', $event);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    public function getRecentEvents(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            "SELECT al.id, al.created_at, al.event, al.description, al.ip_address,
                    COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Anónimo') AS user_name
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             ORDER BY al.created_at DESC
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $events = [];
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
        $stmt->close();
        return $events;
    }

    public function getAll(array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        $where = $this->buildWhere($filters);

        $sql = "SELECT al.id, al.created_at, al.event, al.description, al.ip_address,
                       COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'Anónimo') AS user_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                {$where['sql']}
                ORDER BY al.created_at DESC";

        $types  = $where['types'];
        $params = $where['params'];

        if ($limit !== null) {
            $sql     .= " LIMIT ? OFFSET ?";
            $types   .= 'ii';
            $params[] = $limit;
            $params[] = $offset ?? 0;
        }

        if ($types === '') {
            $result = $this->db->query($sql);
            $logs   = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            return $logs;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs   = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
        return $logs;
    }

    public function getTotalCount(array $filters = []): int
    {
        $where = $this->buildWhere($filters);

        $sql = "SELECT COUNT(*) AS total
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id
                {$where['sql']}";

        if ($where['types'] === '') {
            $result = $this->db->query($sql);
            return (int) $result->fetch_assoc()['total'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($where['types'], ...$where['params']);
        $stmt->execute();
        $total = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        return $total;
    }

    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $types   = '';
        $params  = [];

        if (!empty($filters['event'])) {
            $clauses[] = 'al.event = ?';
            $types    .= 's';
            $params[]  = $filters['event'];
        }

        if (!empty($filters['user_id'])) {
            $clauses[] = 'al.user_id = ?';
            $types    .= 'i';
            $params[]  = (int) $filters['user_id'];
        }

        if (!empty($filters['date_from'])) {
            $clauses[] = 'al.created_at >= ?';
            $types    .= 's';
            $params[]  = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $clauses[] = 'al.created_at <= ?';
            $types    .= 's';
            $params[]  = $filters['date_to'] . ' 23:59:59';
        }

        $sql = $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';

        return ['sql' => $sql, 'types' => $types, 'params' => $params];
    }
}
