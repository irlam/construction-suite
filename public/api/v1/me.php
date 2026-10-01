<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = suite_auth()->user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'authentication_required']);
    exit;
}

$project = suite_projects()->currentForUser($user);
$role = suite_projects()->roleFor($user, $project);

echo json_encode([
    'ok' => true,
    'data' => [
        'user' => [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ],
        'project' => $project ? [
            'id' => (int) $project['id'],
            'name' => (string) $project['name'],
            'organisation' => (string) $project['organization_name'],
        ] : null,
        'role' => $role,
        'modules' => array_map(
            static fn(array $module): array => [
                'key' => (string) $module['key'],
                'name' => (string) $module['name'],
                'url' => (string) $module['url'],
                'offline' => (string) $module['offline'],
            ],
            suite_modules()->all($role)
        ),
    ],
], JSON_UNESCAPED_SLASHES);
