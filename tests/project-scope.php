<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Modules/ProjectScope.php';

use Suite\Modules\ProjectScope;

function check_scope(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
}

$modules = [
    ['key' => 'defects', 'summary_url' => 'https://example.test/api', 'summary_param' => 'project', 'external_project_ref' => '123'],
    ['key' => 'permits', 'summary_url' => 'https://example.test/api', 'summary_param' => 'site', 'external_project_ref' => '__all__'],
    ['key' => 'safety', 'summary_url' => 'https://example.test/api', 'summary_param' => 'site', 'external_project_ref' => null],
    ['key' => 'deliveries', 'summary_url' => 'https://example.test/api', 'single_calendar' => true],
    ['key' => 'documents', 'summary_url' => 'https://example.test/api', 'summary_param' => 'site', 'external_project_ref' => 'West Site'],
    ['key' => 'handover'],
];

$single = ProjectScope::apply($modules, 1);
foreach ($single as $module) {
    check_scope(empty($module['summary_scope_blocked']), 'Single-site use should not be blocked');
}

$multi = ProjectScope::apply($modules, 2);
$blocked = [];
foreach ($multi as $module) {
    if (!empty($module['summary_scope_blocked'])) $blocked[] = $module['key'];
}
sort($blocked);
check_scope(
    $blocked === ['deliveries', 'permits', 'safety'],
    'Multi-site unscoped or single-calendar integrations must fail closed'
);
check_scope(
    ($multi[0]['external_project_ref'] ?? null) === '123'
    && ($multi[4]['external_project_ref'] ?? null) === 'West Site',
    'Explicit mapped references must be preserved'
);
check_scope(
    ($modules[1]['summary_scope_blocked'] ?? null) === null,
    'Policy must not mutate its input'
);

echo "PASS: Single-project backward compatibility and multi-project fail-closed scoping.\n";
