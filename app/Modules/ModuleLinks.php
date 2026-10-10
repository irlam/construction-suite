<?php
declare(strict_types=1);

namespace Suite\Modules;

final class ModuleLinks
{
    public static function url(array $module): ?string
    {
        if (!empty($module['summary_scope_blocked'])) {
            return null;
        }
        if (!empty($module['single_project_instance'])) return $module['launch_url'] ?? null;
        $key = (string) ($module['key'] ?? '');
        $ref = (string) ($module['external_project_ref'] ?? '');
        if ($ref === '__all__') $ref = '';

        $links = [
            'defects' => 'https://defectnotice.site/defects.php',
            'permits' => 'https://sitepermits.site/manager-approvals.php',
            'safety' => 'https://sitesafety.site/actions.php',
            'deliveries' => 'https://sitedeliveries.site/schedule.php',
            'documents' => 'https://sitenotices.site/forms/clean-up/list.php',
        ];
        if (!isset($links[$key])) return null;
        $query = [];
        if ($key === 'defects') {
            $query = ['status' => 'active'];
            if ($ref !== '' && ctype_digit($ref)) $query['project'] = $ref;
        }
        if ($key === 'permits' && $ref !== '') $query['site'] = $ref;
        if ($key === 'deliveries') {
            if ($ref === '' || !ctype_digit($ref)) return null;
            $query['site'] = $ref;
        }
        if ($key === 'safety') {
            $query = ['status' => 'Open'];
            if ($ref !== '') $query['site'] = $ref;
        }
        if ($key === 'documents') {
            $query = ['status' => 'open'];
            if ($ref !== '') $query['site_exact'] = $ref;
        }
        return $links[$key] . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }
}
