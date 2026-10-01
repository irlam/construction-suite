<?php
declare(strict_types=1);

namespace Suite\Modules;

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

    public function count(string $role): int
    {
        return count($this->all($role));
    }
}
