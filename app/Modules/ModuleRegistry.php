<?php
declare(strict_types=1);

namespace Suite\Modules;

use Suite\Database\Connection;

final class ModuleRegistry
{
    public function __construct(private readonly array $modules)
    {
    }

    public function all(string $role): array
    {
        $result = [];
        foreach ($this->modules as $key => $module) {
            if (!($module['enabled'] ?? false)) {
                continue;
            }
            $roles = $module['roles'] ?? ['*'];
            if (!in_array('*', $roles, true) && !in_array($role, $roles, true)) {
                continue;
            }
            $module['key'] = $key;
            $result[] = $module;
        }

        usort($result, static fn(array $a, array $b): int =>
            ((int) ($a['sort'] ?? 999)) <=> ((int) ($b['sort'] ?? 999))
        );

        return $result;
    }

    public function allForProject(string $role, ?int $projectId, bool $enforceIsolation = true): array
    {
        $modules = $this->all($role);
        if (!$projectId) return [];
        if (!Connection::tableExists('project_modules')) {
            return $enforceIsolation && $role !== 'platform_admin' ? [] : $modules;
        }

        $stmt = Connection::pdo()->prepare(
            'SELECT module_key, enabled, external_project_ref, config_json
             FROM project_modules WHERE project_id = ?'
        );
        $stmt->execute([$projectId]);

        $settings = [];
        foreach ($stmt->fetchAll() as $row) {
            $settings[(string) $row['module_key']] = $row;
        }

        $result = [];
        $companyChoices = [];
        if (Connection::tableExists('company_project_modules')) {
            $choices = Connection::pdo()->prepare('SELECT module_key, enabled FROM company_project_modules WHERE project_id = ?');
            $choices->execute([$projectId]);
            foreach ($choices->fetchAll() as $choice) $companyChoices[(string) $choice['module_key']] = (bool) $choice['enabled'];
        }
        foreach ($modules as $module) {
            $key = (string) $module['key'];
            $setting = $settings[$key] ?? null;

            if (isset($companyChoices[$key]) && !$companyChoices[$key]) continue;

            if ($setting && !(bool) $setting['enabled']) {
                continue;
            }

            $module['external_project_ref'] = $setting['external_project_ref'] ?? null;
            $module['project_config'] = $setting && !empty($setting['config_json'])
                ? (json_decode((string) $setting['config_json'], true) ?: [])
                : [];
            $result[] = $module;
        }

        if (!$enforceIsolation) return $result;
        $project = Connection::pdo()->prepare('SELECT organization_id FROM projects WHERE id = ?');
        $project->execute([$projectId]);
        $organizationId = (int) ($project->fetchColumn() ?: 0);
        $instances = [];
        foreach (suite_instances()->forProject($projectId, $organizationId) as $instance) $instances[$instance['module_key']] = $instance;
        $scoped = [];
        foreach ($result as $module) {
            $instance = $instances[$module['key']] ?? null;
            if ($instance) {
                $module['url'] = $instance['origin'];
                $module['launch_url'] = '/launch.php?instance_id=' . $instance['id'];
                $module['tenant_isolated'] = true;
                $module['single_project_instance'] = true;
                $module['integration_key_env'] = $instance['key_env'];
                unset($module['summary_url'], $module['reference_url'], $module['health_url'], $module['external_project_ref']);
                if (($instance['summary_verified'] ?? false) === true) $module['summary_url'] = $instance['origin'] . '/api/suite-summary.php';
                $module['summary_requires_ref'] = false;
                $module['single_calendar'] = false;
            }
            if ($role === 'platform_admin' || !empty($module['tenant_isolated'])) $scoped[] = $module;
        }
        return $scoped;
    }

    public function count(string $role): int
    {
        return count($this->all($role));
    }

    public function definitions(): array
    {
        $result = [];
        foreach ($this->modules as $key => $module) {
            $module['key'] = $key;
            $result[] = $module;
        }

        usort($result, static fn(array $a, array $b): int =>
            ((int) ($a['sort'] ?? 999)) <=> ((int) ($b['sort'] ?? 999))
        );

        return $result;
    }
}
