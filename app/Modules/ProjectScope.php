<?php
declare(strict_types=1);

namespace Suite\Modules;

/**
 * A global third-party summary is safe only while the organisation has a
 * single project. Mapping must become explicit before multi-site rollout.
 * A single-calendar module cannot provide project-isolated aggregates.
 */
final class ProjectScope
{
    public static function apply(array $modules, int $activeProjects): array
    {
        if ($activeProjects <= 1) {
            return $modules;
        }

        foreach ($modules as &$module) {
            if (!empty($module['single_project_instance']) || empty($module['summary_url'])) {
                continue;
            }

            $reference = trim((string) ($module['external_project_ref'] ?? ''));
            $isSingleCalendar = !empty($module['single_calendar']);
            $canFilter = trim((string) ($module['summary_param'] ?? '')) !== '';

            if ($isSingleCalendar || !$canFilter || $reference === '' || $reference === '__all__') {
                $module['summary_scope_blocked'] = true;
            }
        }
        unset($module);

        return $modules;
    }
}
