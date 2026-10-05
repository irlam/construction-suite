<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');

$user = suite_auth()->user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'authentication_required']);
    exit;
}

$project = suite_projects()->currentForUser($user);
$role = suite_projects()->roleFor($user, $project);
$modules = suite_modules()->allForProject($role, $project ? (int) $project['id'] : null);
// Organisation boundaries are enforced before contacting external APIs.
// Global shared integration keys may span several organisations.
$projectCount = (int) suite_db()->query(
    'SELECT COUNT(*) FROM projects p
     JOIN organizations o ON o.id = p.organization_id
     WHERE p.active = 1 AND o.active = 1'
)->fetchColumn();
$modules = \Suite\Modules\ProjectScope::apply($modules, $projectCount);
$summary = suite_summary_client()->fetch($modules);

echo json_encode([
    'ok' => true,
    'project_id' => $project ? (int) $project['id'] : null,
    'configured' => (bool) ($summary['configured'] ?? false),
    'modules' => $summary['modules'] ?? [],
], JSON_UNESCAPED_SLASHES);
