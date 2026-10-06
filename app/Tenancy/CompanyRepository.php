<?php
declare(strict_types=1);
namespace Suite\Tenancy;

use Suite\Database\Connection;
use Suite\Support\Audit;
use RuntimeException;

/** Company-level authority is separate from a project administrator role. */
final class CompanyRepository
{
    public function projectModules(array $user, int $companyId, int $projectId): array
    {
        $this->requireProject($user, $companyId, $projectId);
        $platform = [];
        if (Connection::tableExists('project_modules')) {
            $stmt = Connection::pdo()->prepare('SELECT module_key, enabled FROM project_modules WHERE project_id = ?');
            $stmt->execute([$projectId]);
            foreach ($stmt->fetchAll() as $row) $platform[$row['module_key']] = (bool) $row['enabled'];
        }
        $choices = [];
        if (Connection::tableExists('company_project_modules')) {
            $stmt = Connection::pdo()->prepare('SELECT module_key, enabled FROM company_project_modules WHERE project_id = ?');
            $stmt->execute([$projectId]);
            foreach ($stmt->fetchAll() as $row) $choices[$row['module_key']] = (bool) $row['enabled'];
        }
        $result = [];
        foreach (suite_modules()->definitions() as $module) {
            $key = (string) $module['key'];
            $module['permitted'] = !empty($module['enabled']) && ($platform[$key] ?? true);
            $module['selected'] = $choices[$key] ?? true;
            $result[] = $module;
        }
        return $result;
    }

    public function saveProjectModules(array $user, int $companyId, int $projectId, array $enabled): void
    {
        $project = $this->requireProject($user, $companyId, $projectId);
        $company = $this->requireCompany($user, $companyId);
        if (empty($company['active']) || empty($project['active'])) throw new RuntimeException('Activate the company and project before changing tools.');
        if (!Connection::tableExists('company_project_modules')) throw new RuntimeException('The platform owner must apply the company settings migration first.');
        $modules = $this->projectModules($user, $companyId, $projectId);
        $known = array_column($modules, 'key');
        foreach ($enabled as $key) {
            if (!is_string($key) || !in_array($key, $known, true)) throw new RuntimeException('Choose a valid project tool.');
        }
        $pdo = Connection::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($modules as $module) {
                $key = $module['key'];
                // A company preference can only narrow platform access. It never
                // changes integration mappings, credentials or readiness flags.
                if (!$module['permitted'] && in_array($key, $enabled, true)) throw new RuntimeException('This tool is not enabled by the platform owner.');
                $selected = in_array($key, $enabled, true) ? 1 : 0;
                $find = $pdo->prepare('SELECT module_key FROM company_project_modules WHERE project_id = ? AND module_key = ?');
                $find->execute([$projectId, $key]);
                if ($find->fetchColumn()) {
                    $stmt = $pdo->prepare('UPDATE company_project_modules SET enabled = ?, updated_at = CURRENT_TIMESTAMP WHERE project_id = ? AND module_key = ?');
                    $stmt->execute([$selected, $projectId, $key]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO company_project_modules (project_id, module_key, enabled) VALUES (?, ?, ?)');
                    $stmt->execute([$projectId, $key, $selected]);
                }
            }
            Audit::record('company.project_tools_saved', (int) $user['id'], $companyId, $projectId);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    private function requireProject(array $user, int $companyId, int $projectId): array
    {
        $this->requireCompany($user, $companyId);
        $stmt = Connection::pdo()->prepare('SELECT id, active FROM projects WHERE id = ? AND organization_id = ?');
        $stmt->execute([$projectId, $companyId]);
        $project = $stmt->fetch();
        if (!$project) throw new RuntimeException('Project access denied.');
        return $project;
    }

    public function managedBy(array $user): array
    {
        if (empty($user['active'])) return [];
        $pdo = Connection::pdo();
        if (!empty($user['is_platform_admin'])) {
            return $pdo->query('SELECT id, name, slug, active FROM organizations ORDER BY name')->fetchAll();
        }
        $stmt = $pdo->prepare("SELECT DISTINCT o.id, o.name, o.slug, o.active
            FROM organizations o JOIN memberships m ON m.organization_id = o.id
            WHERE m.user_id = ? AND m.project_id IS NULL
              AND m.role_key IN ('company_admin', 'admin') AND o.active = 1 ORDER BY o.name");
        $stmt->execute([(int) $user['id']]);
        return $stmt->fetchAll();
    }

    public function requireCompany(array $user, int $id): array
    {
        foreach ($this->managedBy($user) as $company) {
            if ((int) $company['id'] === $id) return $company;
        }
        throw new RuntimeException('Company access denied.');
    }

    public function projects(array $user, int $companyId): array
    {
        $this->requireCompany($user, $companyId);
        $stmt = Connection::pdo()->prepare('SELECT id, organization_id, name, code, location, active FROM projects WHERE organization_id = ? ORDER BY name');
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public function members(array $user, int $companyId): array
    {
        $this->requireCompany($user, $companyId);
        $stmt = Connection::pdo()->prepare('SELECT m.id, m.user_id, m.project_id, m.role_key, u.name, u.email, u.active, p.name AS project_name
            FROM memberships m JOIN users u ON u.id = m.user_id
            LEFT JOIN projects p ON p.id = m.project_id AND p.organization_id = m.organization_id
            WHERE m.organization_id = ? ORDER BY u.name, p.name');
        $stmt->execute([$companyId]);
        return $stmt->fetchAll();
    }

    public function saveProject(array $user, int $companyId, int $projectId, array $input): int
    {
        $company = $this->requireCompany($user, $companyId);
        if (empty($company['active'])) throw new RuntimeException('Activate the company before changing projects.');
        $name = trim((string) ($input['name'] ?? ''));
        $code = trim((string) ($input['code'] ?? ''));
        $location = trim((string) ($input['location'] ?? ''));
        if ($name === '' || strlen($name) > 180 || strlen($code) > 80 || strlen($location) > 190) {
            throw new RuntimeException('Enter a project name up to 180 characters, code up to 80 and location up to 190.');
        }
        $pdo = Connection::pdo();
        $active = !empty($input['active']) ? 1 : 0;
        if ($projectId > 0) {
            $check = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ?');
            $check->execute([$projectId, $companyId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Project access denied.');
            $stmt = $pdo->prepare('UPDATE projects SET name = ?, code = ?, location = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND organization_id = ?');
            $stmt->execute([$name, $code ?: null, $location ?: null, $active, $projectId, $companyId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO projects (organization_id, name, code, location, active) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$companyId, $name, $code ?: null, $location ?: null, $active]);
            $projectId = (int) $pdo->lastInsertId();
        }
        Audit::record('company.project_saved', (int) $user['id'], $companyId, $projectId);
        return $projectId;
    }

    public function saveMembership(array $user, int $companyId, int $targetUserId, ?int $projectId, string $role): void
    {
        $company = $this->requireCompany($user, $companyId);
        if (empty($company['active'])) throw new RuntimeException('Activate the company before changing access.');
        $pdo = Connection::pdo();
        $roles = $projectId === null ? ['company_admin', 'user'] : ['admin', 'manager', 'site_manager', 'user', 'contractor'];
        if (!in_array($role, $roles, true)) throw new RuntimeException('Choose a valid role for this scope.');
        // Company admins can only manage people already in their company. The
        // platform owner assigns the first company admin and cross-company users.
        $target = $pdo->prepare('SELECT id, active, is_platform_admin FROM users WHERE id = ?');
        $target->execute([$targetUserId]);
        $account = $target->fetch();
        if (!$account || empty($account['active']) || !empty($account['is_platform_admin'])) throw new RuntimeException('Choose an active company user.');
        if (empty($user['is_platform_admin'])) {
            if ($targetUserId === (int) $user['id']) throw new RuntimeException('Ask the platform owner to change your own access.');
            $member = $pdo->prepare('SELECT id FROM memberships WHERE user_id = ? AND organization_id = ? LIMIT 1');
            $member->execute([$targetUserId, $companyId]);
            if (!$member->fetchColumn()) throw new RuntimeException('User access denied.');
        }
        if ($projectId !== null) {
            $check = $pdo->prepare('SELECT id FROM projects WHERE id = ? AND organization_id = ? AND active = 1');
            $check->execute([$projectId, $companyId]);
            if (!$check->fetchColumn()) throw new RuntimeException('Project access denied.');
        }
        $scope = $projectId === null ? 'project_id IS NULL' : 'project_id = ?';
        $args = [$targetUserId, $companyId];
        if ($projectId !== null) $args[] = $projectId;
        $find = $pdo->prepare('SELECT id FROM memberships WHERE user_id = ? AND organization_id = ? AND ' . $scope);
        $find->execute($args);
        $ids = $find->fetchAll();
        if (empty($user['is_platform_admin']) && $projectId === null) {
            $admin = $pdo->prepare("SELECT id FROM memberships WHERE user_id = ? AND organization_id = ? AND project_id IS NULL AND role_key IN ('company_admin','admin') LIMIT 1");
            $admin->execute([$targetUserId, $companyId]);
            if ($role === 'company_admin' || $admin->fetchColumn()) throw new RuntimeException('Only the platform owner can change company administrator access.');
        }
        if ($ids) {
            $stmt = $pdo->prepare('UPDATE memberships SET role_key = ? WHERE user_id = ? AND organization_id = ? AND ' . $scope);
            $stmt->execute(array_merge([$role], $args));
        } else {
            $stmt = $pdo->prepare('INSERT INTO memberships (user_id, organization_id, project_id, role_key) VALUES (?, ?, ?, ?)');
            $stmt->execute([$targetUserId, $companyId, $projectId, $role]);
        }
        Audit::record('company.membership_saved', (int) $user['id'], $companyId, $projectId, ['target_user_id' => $targetUserId, 'role' => $role]);
    }
    public function createUser(array $user, int $companyId, array $input): int
    {
        $company = $this->requireCompany($user, $companyId);
        if (empty($company['active'])) throw new RuntimeException('Activate the company before adding people.');
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        if ($name === '' || strlen($name) > 160 || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new RuntimeException('Enter a name, valid email and password of at least 12 characters.');
        }
        $pdo = Connection::pdo();
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetchColumn()) throw new RuntimeException('This account cannot be created. Contact the platform owner to assign an existing account.');
        $projectId = (int) ($input['scope_project_id'] ?? 0);
        $role = (string) ($input['role_key'] ?? 'user');
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, active, is_platform_admin) VALUES (?, ?, ?, 1, 0)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $id = (int) $pdo->lastInsertId();
            // Bootstrap only a harmless company membership. The same scoped
            // policy validates the final requested role and project below.
            $stmt = $pdo->prepare("INSERT INTO memberships (user_id, organization_id, project_id, role_key) VALUES (?, ?, NULL, 'user')");
            $stmt->execute([$id, $companyId]);
            $this->saveMembership($user, $companyId, $id, $projectId ?: null, $role);
            if ($projectId > 0) {
                $stmt = $pdo->prepare('DELETE FROM memberships WHERE user_id = ? AND organization_id = ? AND project_id IS NULL');
                $stmt->execute([$id, $companyId]);
            }
            $pdo->commit();
            Audit::record('company.user_created', (int) $user['id'], $companyId, $projectId ?: null, ['target_user_id' => $id]);
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function removeMembership(array $user, int $companyId, int $membershipId): void
    {
        $company = $this->requireCompany($user, $companyId);
        if (empty($company['active'])) throw new RuntimeException('Activate the company before changing access.');
        $pdo = Connection::pdo();
        $lookup = $pdo->prepare('SELECT m.* FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.id=? AND m.organization_id=? AND u.is_platform_admin=0');
        $lookup->execute([$membershipId, $companyId]);
        $member = $lookup->fetch();
        if (!$member || (int) $member['user_id'] === (int) $user['id']) throw new RuntimeException('Ask the platform owner to change your own access.');
        // Retain at least one company administrator; access can be restored
        // through Save access, and the global user account is never deleted.
        if ($member['project_id'] === null && in_array($member['role_key'], ['company_admin', 'admin'], true)) {
            if (empty($user['is_platform_admin'])) throw new RuntimeException('Only the platform owner can change company administrator access.');
            $check = $pdo->prepare("SELECT COUNT(DISTINCT m.user_id) FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.organization_id=? AND m.project_id IS NULL AND m.role_key IN ('company_admin','admin') AND u.active=1 AND m.user_id<>?");
            $check->execute([$companyId, (int) $member['user_id']]);
            if ((int) $check->fetchColumn() === 0) throw new RuntimeException('Assign another company administrator first.');
        }
        $stmt = $pdo->prepare('DELETE FROM memberships WHERE id=? AND organization_id=?');
        $stmt->execute([$membershipId, $companyId]);
        Audit::record('company.membership_removed', (int) $user['id'], $companyId, $member['project_id'] === null ? null : (int) $member['project_id'], ['target_user_id' => (int) $member['user_id']]);
    }

}
