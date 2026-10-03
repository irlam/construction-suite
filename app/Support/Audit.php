<?php
declare(strict_types=1);

namespace Suite\Support;

use Suite\Database\Connection;

final class Audit
{
    public static function record(
        string $eventType,
        ?int $userId = null,
        ?int $organizationId = null,
        ?int $projectId = null,
        array $details = []
    ): void {
        try {
            if (!Connection::tableExists('audit_events')) {
                return;
            }

            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
            $ipHash = $ip !== '' ? hash('sha256', $ip) : null;

            $stmt = Connection::pdo()->prepare(
                'INSERT INTO audit_events
                 (user_id, organization_id, project_id, event_type, details_json, ip_hash)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $organizationId,
                $projectId,
                $eventType,
                $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null,
                $ipHash,
            ]);
        } catch (\Throwable $e) {
            // Auditing must never break the primary user action.
        }
    }
}
