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

    public function allForProject(string $role, ?int $projectId): array
    {
        $modules = $this->all($role);
        // Legacy modules have independent account/data boundaries. Until an
        // adapter is verified, only the platform owner may launch them here.
        if ($role !== 'platform_admin') {
            $modules = array_values(array_filter($modules, static fn(array $module): bool =>
                ($module['tenant_isolated'] ?? false) === true
            ));
        }
        if (!$projectId) return [];
        if (!Connection::tableExists('project_modules')) {
            return $modules;
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
        foreach ($modules as $module) {
            $key = (string) $module['key'];
            $setting = $settings[$key] ?? null;

            if ($setting && !(bool) $setting['enabled']) {
                continue;
            }

            $module['external_project_ref'] = $setting['external_project_ref'] ?? null;
            $module['project_config'] = $setting && !empty($setting['config_json'])
                ? (json_decode((string) $setting['config_json'], true) ?: [])
                : [];
            $result[] = $module;
        }

        return $result;
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
