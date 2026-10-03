<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$failures = [];
$check = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$modules = suite_modules()->all('platform_admin');
$check(count($modules) === 8, 'Expected eight initial Suite modules.');
$check(class_exists(\Suite\Modules\ModuleHealth::class), 'Module health service is not wired.');
$check(class_exists(\Suite\Support\Audit::class), 'Audit service is not wired.');

foreach ($modules as $module) {
    $check(isset($module['key'], $module['name'], $module['url']), 'Module registry entry is incomplete.');
    $check(str_starts_with((string) $module['url'], 'https://'), 'Module URLs must use HTTPS.');
}

$setupSource = (string) file_get_contents(SUITE_ROOT . '/public/setup.php');
$check(str_contains($setupSource, 'role_key'), 'First-run setup must insert memberships using role_key.');
$check(!str_contains($setupSource, 'project_id, role)'), 'Legacy memberships role column remains in setup.');

foreach ([
    'public/index.php',
    'public/login.php',
    'public/offline.html',
    'public/service-worker.js',
    'public/manifest.webmanifest',
    'public/assets/css/app.css',
    'public/assets/js/app.js',
    'public/profile.php',
    'public/api/v1/module-health.php',
    'public/admin/organisation-edit.php',
    'public/admin/project-edit.php',
    'public/admin/user-edit.php',
    'public/admin/audit.php',
] as $required) {
    $check(is_file(SUITE_ROOT . '/' . $required), 'Missing required Hub file: ' . $required);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS: Construction Suite bootstrap, registry and PWA shell checks.\n";
