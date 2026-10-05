<?php
declare(strict_types=1);

/**
 * Read-only production diagnostics; run via Plesk's PHP CLI.
 * No secrets, tenant records or individual names are written to output.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

use Suite\Database\Connection;
use Suite\Modules\ProjectScope;

$errors = [];
$warnings = [];

$check = static function (bool $ok, string $label, bool $critical = true)
    use (&$errors, &$warnings): void {
    echo ($ok ? '[OK] ' : ($critical ? '[ERROR] ' : '[CHECK] ')) . $label . PHP_EOL;
    if (!$ok) {
        if ($critical) $errors[] = $label;
        else $warnings[] = $label;
    }
};

$check(PHP_VERSION_ID >= 80200, 'PHP 8.2 or newer');
$check(extension_loaded('pdo_mysql'), 'PDO MySQL extension');
$check(extension_loaded('curl'), 'cURL extension');
$check(is_dir(SUITE_ROOT . '/public'), 'Dedicated public document directory');
$check(
    suite_summary_client()->configured(),
    'Shared integration key is configured (value hidden)'
);

try {
    $db = suite_db();
    $db->query('SELECT 1');
    $check(true, 'Database connection');
    foreach (['organizations', 'projects', 'users', 'memberships',
              'project_modules', 'notifications', 'audit_events'] as $table) {
        $check(Connection::tableExists($table), 'Table: ' . $table);
    }

    if (Connection::tableExists('projects') && Connection::tableExists('organizations')) {
        $count = (int) $db->query(
            'SELECT COUNT(*) FROM projects p
             JOIN organizations o ON o.id = p.organization_id
             WHERE p.active = 1 AND o.active = 1'
        )->fetchColumn();
        echo '[INFO] Active projects: ' . $count . PHP_EOL;

        if ($count > 1 && Connection::tableExists('project_modules')) {
            $rows = $db->query(
                'SELECT p.id AS project_id, p.name,
                        pm.module_key, pm.external_project_ref
                 FROM projects p
                 JOIN organizations o ON o.id = p.organization_id
                 LEFT JOIN project_modules pm ON pm.project_id = p.id
                 WHERE p.active = 1 AND o.active = 1'
            )->fetchAll(PDO::FETCH_ASSOC);

            $byProject = [];
            foreach ($rows as $row) {
                $byProject[(int) $row['project_id']][(string) ($row['module_key'] ?? '')] = $row;
            }
            foreach ($byProject as $projectId => $mapped) {
                $modules = [];
                foreach (suite_modules()->definitions() as $module) {
                    $module['external_project_ref'] = $mapped[$module['key']]['external_project_ref'] ?? null;
                    $modules[] = $module;
                }
                foreach (ProjectScope::apply($modules, $count) as $module) {
                    if (!empty($module['summary_scope_blocked'])) {
                        $warnings[] = 'Project #' . $projectId . ' requires ' .
                            $module['key'] . ' source-site mapping';
                    }
                }
            }
        }
    }
} catch (Throwable $e) {
    $check(false, 'Database could not be inspected');
}

$check(
    trim((string) \Suite\Support\Env::get('SUITE_SETUP_KEY', '')) === '',
    'One-time setup key cleared after installation',
    false
);
$warnings[] = 'Plesk offsite backup and restore test not independently verified';

if ($warnings) {
    foreach (array_unique($warnings) as $warning) {
        echo '[CHECK] ' . $warning . PHP_EOL;
    }
}
echo '[INFO] Private passwords and API keys are never printed.' . PHP_EOL;
echo '[RESULT] ' . ($errors ? 'Critical checks failed.' :
    ($warnings ? 'Core checks passed; manual actions remain.' : 'Core checks passed.')) . PHP_EOL;

exit($errors ? 2 : 0);
