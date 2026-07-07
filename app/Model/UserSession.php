<?php

namespace App\Model;

use App\Config\Database;
use App\Core\Model;

class UserSession extends Model
{
    public function __construct(\mysqli $connection)
    {
        parent::__construct($connection);
    }

    public function create(int $userId, string $rawToken, string $ip, string $userAgent, bool $viaRemember = false): int
    {
        return static::createTo($this->db, $userId, $rawToken, $ip, $userAgent, $viaRemember);
    }

    public static function createTo(\mysqli $db, int $userId, string $rawToken, string $ip, string $userAgent, bool $viaRemember = false): int
    {
        $tokenHash   = hash('sha256', $rawToken);
        $viaRemember = (int) $viaRemember;

        $stmt = $db->prepare(
            "INSERT INTO user_sessions (user_id, token_hash, ip_address, user_agent, via_remember)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('isssi', $userId, $tokenHash, $ip, $userAgent, $viaRemember);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        return $id;
    }

    public function touch(string $tokenHash): void
    {
        $stmt = $this->db->prepare(
            "UPDATE user_sessions SET last_activity = NOW() WHERE token_hash = ?"
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $stmt->close();
    }

    public function existsActive(string $tokenHash, int $ttlSeconds): bool
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM user_sessions
             WHERE token_hash = ? AND last_activity >= DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->bind_param('si', $tokenHash, $ttlSeconds);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();

        return $exists;
    }

    public function getForUser(int $userId, string $currentTokenHash): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, ip_address, user_agent, via_remember, created_at, last_activity,
                    (token_hash = ?) AS is_current
             FROM user_sessions
             WHERE user_id = ?
             ORDER BY last_activity DESC"
        );
        $stmt->bind_param('si', $currentTokenHash, $userId);
        $stmt->execute();
        $result   = $stmt->get_result();
        $sessions = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $sessions;
    }

    public function revoke(int $sessionId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_sessions WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param('ii', $sessionId, $userId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected > 0;
    }

    public function revokeAllExcept(int $userId, string $currentTokenHash): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_sessions WHERE user_id = ? AND token_hash != ?"
        );
        $stmt->bind_param('is', $userId, $currentTokenHash);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }

    public function deleteByTokenHash(string $tokenHash): void
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_sessions WHERE token_hash = ?"
        );
        $stmt->bind_param('s', $tokenHash);
        $stmt->execute();
        $stmt->close();
    }

    public function purgeExpired(int $ttlSeconds): int
    {
        $stmt = $this->db->prepare(
            "DELETE FROM user_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->bind_param('i', $ttlSeconds);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return $affected;
    }
}
