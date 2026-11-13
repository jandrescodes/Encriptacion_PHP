<?php

declare(strict_types=1);

namespace App\Model;

use App\Core\Model;

class LoginAttempt extends Model
{
    private function normalize(string $identifier): string
    {
        return mb_strtolower(trim($identifier));
    }

    public function lockedSecondsRemaining(string $identifier): int
    {
        $id   = $this->normalize($identifier);
        $stmt = $this->db->prepare(
            "SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS secs
             FROM login_attempts
             WHERE identifier = ? AND locked_until IS NOT NULL AND locked_until > NOW()"
        );
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['secs'] : 0;
    }

    // maxAttempts and lockMinutes are passed in so tests can inject values directly
    // without relying on env().
    public function registerFailure(string $identifier, int $maxAttempts, int $lockMinutes): void
    {
        $id   = $this->normalize($identifier);
        $stmt = $this->db->prepare(
            "INSERT INTO login_attempts (identifier, attempts, last_attempt)
             VALUES (?, 1, NOW())
             ON DUPLICATE KEY UPDATE
               attempts     = attempts + 1,
               last_attempt = NOW(),
               locked_until = IF(attempts >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), locked_until)"
        );
        $stmt->bind_param('sii', $id, $maxAttempts, $lockMinutes);
        $stmt->execute();
        $stmt->close();
    }

    public function getLockedCount(): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) AS total
             FROM login_attempts
             WHERE locked_until IS NOT NULL AND locked_until > NOW()"
        );
        return (int) $result->fetch_assoc()['total'];
    }

    public function clear(string $identifier): void
    {
        $id   = $this->normalize($identifier);
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE identifier = ?");
        $stmt->bind_param('s', $id);
        $stmt->execute();
        $stmt->close();
    }
}
