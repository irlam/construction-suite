<?php
declare(strict_types=1);

namespace Suite\Projects;

use Suite\Database\Connection;

final class ProjectRepository
{
    public function forUser(array $user): array
    {
        $pdo = Connection::pdo();

        if ((bool) ($user['is_platform_admin'] ?? false)) {
            return $pdo->query(
                'SELECT p.id, p.organization_id, p.name, p.code, p.location,
                        o.name AS organization_name, o.slug AS organization_slug
                 FROM projects p
                 JOIN organizations o ON o.id = p.organization_id
                 WHERE p.active = 1 AND o.active = 1
                 ORDER BY o.name, p.name'
            )->fetchAll();
        }

        $stmt = $pdo->prepare(
            'SELECT DISTINCT p.id, p.organization_id, p.name, p.code, p.location,
                    o.name AS organization_name, o.slug AS organization_slug
             FROM projects p
             JOIN organizations o ON o.id = p.organization_id
             JOIN memberships m ON m.user_id = ?
                AND m.organization_id = p.organization_id
                AND (m.project_id IS NULL OR m.project_id = p.id)
             WHERE p.active = 1 AND o.active = 1
             ORDER BY o.name, p.name'
        );
        $stmt->execute([(int) $user['id']]);
        return $stmt->fetchAll();
    }

    public function currentForUser(array $user): ?array
    {
        $projects = $this->forUser($user);
        if (!$projects) {
            unset($_SESSION['suite_project_id']);
            return null;
        }

        $selected = (int) ($_SESSION['suite_project_id'] ?? 0);
        foreach ($projects as $project) {
            if ((int) $project['id'] === $selected) {
                return $project;
            }
        }

        $_SESSION['suite_project_id'] = (int) $projects[0]['id'];
        return $projects[0];
    }

    public function selectForUser(array $user, int $projectId): bool
    {
        foreach ($this->forUser($user) as $project) {
            if ((int) $project['id'] === $projectId) {
                $_SESSION['suite_project_id'] = $projectId;
                return true;
            }
        }
        return false;
    }

    public function roleFor(array $user, ?array $project): string
    {
        if ((bool) ($user['is_platform_admin'] ?? false)) {
            return 'platform_admin';
        }
        if (!$project) {
            return 'user';
        }

        $stmt = Connection::pdo()->prepare(
            'SELECT role FROM memberships
             WHERE user_id = ? AND organization_id = ?
               AND (project_id = ? OR project_id IS NULL)
             ORDER BY CASE WHEN project_id = ? THEN 0 ELSE 1 END
             LIMIT 1'
        );
        $stmt->execute([
            (int) $user['id'],
            (int) $project['organization_id'],
            (int) $project['id'],
            (int) $project['id'],
        ]);

        return (string) ($stmt->fetchColumn() ?: 'user');
    }
}
