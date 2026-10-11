<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Modules/ProjectScope.php';
require_once dirname(__DIR__) . '/app/Modules/ModuleLinks.php';

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

check_scope(
    \Suite\Modules\ModuleLinks::url($multi[1]) === null,
    'Unscoped KPI link must be blocked'
);
check_scope(
    \Suite\Modules\ModuleLinks::url($multi[3]) === null,
    'Shared Deliveries calendar must never link as site-isolated'
);
check_scope(
    str_contains(
        (string) \Suite\Modules\ModuleLinks::url($multi[0]),
        'project=123'
    ),
    'Mapped Defects link must preserve project filter'
);
check_scope(
    str_contains(
        (string) \Suite\Modules\ModuleLinks::url($multi[4]),
        'site_exact=West%20Site'
    ),
    'Mapped Notices URL must use exact, encoded site filter'
);

$mappedDeliveries=ProjectScope::apply([['key'=>'deliveries','summary_url'=>'https://example.test/api','summary_param'=>'site','external_project_ref'=>'1']],2)[0];
$instanceDeliveries=ProjectScope::apply([['key'=>'deliveries','summary_url'=>'https://sitedeliveries.site/api/suite-instance-summary.php?instance_id=5','company_site_instance'=>true,'tenant_isolated'=>true,'launch_url'=>'/launch.php?instance_id=5']],2)[0];
check_scope(empty($instanceDeliveries['summary_scope_blocked']),'Verified company/site reporting does not require a separate user-supplied site mapping');
check_scope(\Suite\Modules\ModuleLinks::url($instanceDeliveries)==='/launch.php?instance_id=5','Company/site KPI link uses the scoped Suite handoff');
$unverified=ProjectScope::apply([['key'=>'deliveries','summary_url'=>'https://example.test/api','company_site_instance'=>true]],2)[0];
check_scope(!empty($unverified['summary_scope_blocked']),'A company/site flag alone does not bypass fail-closed scoping');
check_scope(empty($mappedDeliveries['summary_scope_blocked']),'Explicitly mapped Deliveries remains available across multiple projects');
check_scope(\Suite\Modules\ModuleLinks::url($mappedDeliveries)==='https://sitedeliveries.site/schedule.php?site=1','Deliveries calendar link preserves its site reference');
check_scope(\Suite\Modules\ModuleLinks::url(['key'=>'deliveries','external_project_ref'=>'__all__'])===null,'Deliveries cannot launch an unscoped all-data calendar');
echo "PASS: Single-project backward compatibility and multi-project fail-closed scoping.\n";

check_scope(\Suite\Modules\ModuleLinks::url(['key'=>'qa','external_project_ref'=>'2'])==='https://qa.defecttracker.uk/?project=2','QA link preserves its mapped project');
check_scope(\Suite\Modules\ModuleLinks::url(['key'=>'qa','external_project_ref'=>'__all__'])===null,'QA cannot launch an unscoped all-project report');
