<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=20');

$user = suite_auth()->user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'authentication_required']);
    exit;
}

$project = suite_projects()->currentForUser($user);
$role = suite_projects()->roleFor($user, $project);
$modules = suite_modules()->allForProject($role, $project ? (int) $project['id'] : null);

$health = suite_module_health()->checkMany($modules);
$available = count(array_filter($health, static fn(array $item): bool => $item['status'] === 'available'));

echo json_encode([
    'ok' => true,
    'checked_at' => date(DATE_ATOM),
    'summary' => [
        'available' => $available,
        'total' => count($health),
    ],
    'modules' => $health,
], JSON_UNESCAPED_SLASHES);
