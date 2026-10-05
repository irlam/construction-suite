<?php
declare(strict_types=1);

namespace Suite\Support;

use PDO;
use Suite\Database\Connection;

final class Notifications
{
    /**
     * User-owned rows only, and project-scoped or global messages.
     * Use parameter binding for both reads and writes.
     */
    public static function count(int $userId, ?int $projectId): int
    {
        if (!Connection::tableExists('notifications')) return 0;
        $stmt = Connection::pdo()->prepare(
            'SELECT COUNT(*) FROM notifications
             WHERE user_id = ? AND read_at IS NULL
               AND (project_id IS NULL OR project_id = ?)'
        );
        $stmt->execute([$userId, $projectId ?? -1]);
        return (int) $stmt->fetchColumn();
    }

    public static function recent(int $userId, ?int $projectId, int $limit = 30): array
    {
        if (!Connection::tableExists('notifications')) return [];
        $limit = max(1, min($limit, 100));
        $stmt = Connection::pdo()->prepare(
            'SELECT id, title, body, created_at, read_at
             FROM notifications
             WHERE user_id = ? AND (project_id IS NULL OR project_id = ?)
             ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute([$userId, $projectId ?? -1]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function acknowledge(int $notificationId, int $userId, ?int $projectId): bool
    {
        if (!Connection::tableExists('notifications')) return false;
        $stmt = Connection::pdo()->prepare(
            'UPDATE notifications SET read_at = CURRENT_TIMESTAMP
             WHERE id = ? AND user_id = ?
               AND (project_id IS NULL OR project_id = ?)
               AND read_at IS NULL'
        );
        $stmt->execute([$notificationId, $userId, $projectId ?? -1]);
        return $stmt->rowCount() === 1;
    }
}
