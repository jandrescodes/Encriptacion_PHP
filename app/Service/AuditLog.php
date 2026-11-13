<?php

declare(strict_types=1);

namespace App\Service;

// La auditoría nunca interrumpe la petición: si el INSERT falla se registra en error_log y el flujo sigue.
final class AuditLog
{
    public function __construct(private \mysqli $connection)
    {
    }

    public function record(string $event, string $description, ?int $userId = null): void
    {
        try {
            $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
            $stmt = $this->connection->prepare(
                'INSERT INTO activity_logs (user_id, event, description, ip_address) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('isss', $userId, $event, $description, $ip);
            $stmt->execute();
            $stmt->close();
        } catch (\Throwable $e) {
            error_log('AuditLog::record failed: ' . $e->getMessage());
        }
    }
}
