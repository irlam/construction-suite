<?php
declare(strict_types=1);
$root = sys_get_temp_dir() . '/suite-test-enable-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
$copy = static function(string $from, string $to) use (&$copy): void {
    mkdir($to, 0700); foreach (scandir($from) as $name) {
        if ($name === '.' || $name === '..') continue;
        if (is_dir($from . '/' . $name)) $copy($from . '/' . $name, $to . '/' . $name);
        else copy($from . '/' . $name, $to . '/' . $name);
    }
};
$remove = static function(string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) { foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') $remove($path . '/' . $name); rmdir($path); }
    else unlink($path);
};
$checks = 0;
$check = static function(bool $ok, string $label) use (&$checks): void { if (!$ok) throw new RuntimeException($label); $checks++; };
try {
    foreach (['app', 'config', 'database'] as $directory) $copy(dirname(__DIR__) . '/' . $directory, $root . '/' . $directory);
    foreach (['public', 'private', 'bin'] as $directory) mkdir($root . '/' . $directory, 0700);
    copy(dirname(__DIR__) . '/bin/complete-test-connections.php', $root . '/bin/complete-test-connections.php');
    putenv('DB_DRIVER=sqlite'); putenv('DB_DATABASE=' . $root . '/fixture.sqlite');
    $inventory = $root . '/private/programme-staging-instances.json'; putenv('SUITE_INSTANCES_FILE=' . $inventory);
    for ($id = 1; $id <= 14; $id++) putenv('SUITE_INSTANCE_KEY_' . $id . '=' . hash('sha256', 'synthetic-test-key-' . $id));
    require $root . '/app/bootstrap.php'; $db = suite_db(); $db->exec(file_get_contents($root . '/database/schema.sqlite.sql'));
    foreach (['001_project_modules', '002_module_handoffs', '003_module_sessions', '004_company_settings'] as $migration) $db->exec(file_get_contents($root . '/database/migrations/' . $migration . '.sqlite.sql'));
    $db->exec("INSERT INTO organizations(id,name,slug) VALUES(7,'Alpha','programme-alpha-staging'),(8,'Beta','programme-beta-staging'); INSERT INTO projects(id,organization_id,name) VALUES(7,7,'Alpha'),(8,8,'Beta')");
    $id = 7;
    foreach (['alpha' => 7, 'beta' => 8] as $side => $org) foreach (['admin' => 'company_admin', 'manager' => 'manager', 'viewer' => 'viewer'] as $label => $role) {
        $q = $db->prepare('INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES(?,?,?,?,0)'); $q->execute([$id, "suite-$side-$label@example.invalid", $label, 'unused']);
        $q = $db->prepare('INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(?,?,?,?)'); $q->execute([$id++, $org, $label === 'admin' ? null : $org, $role]);
    }
    $items = []; $fixtures = ['alpha' => ['site_id' => 19, 'company_id' => 31], 'beta' => ['site_id' => 19, 'company_id' => 44]];
    foreach (['programme' => 'programme.defecttracker.uk', 'defects' => 'defectnotice.site', 'deliveries' => 'sitedeliveries.site', 'qa' => 'qa.defecttracker.uk', 'safety' => 'sitesafety.site', 'permits' => 'sitepermits.site', 'documents' => 'sitenotices.site'] as $module => $host) {
        foreach (['alpha' => 7, 'beta' => 8] as $side => $org) {
            $entry = ['id' => count($items) + 1, 'organization_id' => $org, 'project_id' => $org, 'module_key' => $module,
                'origin' => 'https://' . ($module === 'deliveries' ? '' : $side . '.') . $host, 'isolation_verified' => false, 'gateway_verified' => false, 'summary_verified' => false];
            if ($module === 'deliveries') $entry += ['scope_mode' => 'company_site', 'local_site_id' => 19, 'local_company_id' => $fixtures[$side]['company_id']];
            $items[] = $entry;
        }
    }
    file_put_contents($inventory, json_encode(array_reverse($items))); chmod($inventory, 0600);
    $fixtureFile = $root . '/private/delivery-test-fixtures.json'; file_put_contents($fixtureFile, json_encode($fixtures)); chmod($fixtureFile, 0600);
    $run = static function(string $mode) use ($root): array {
        $process = proc_open([PHP_BINARY, $root . '/bin/complete-test-connections.php', $mode], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), json_decode($output, true, 16, JSON_THROW_ON_ERROR), $error];
    };
    $before = hash_file('sha256', $inventory);
    [$status, $result] = $run('--check'); $check($status === 0 && !$result['test_connections_enabled'] && $result['connections'] === 14, 'Exact plan accepts non-sequential inventory and fixture-owned local IDs');
    $check(hash_file('sha256', $inventory) === $before, 'Preflight is read-only');
    file_put_contents($root . '/private/staging-validation.json', '{}'); [$status] = $run('--enable');
    $check($status !== 0 && hash_file('sha256', $inventory) === $before, 'Open validation window prevents activation'); unlink($root . '/private/staging-validation.json');
    $db->exec("UPDATE memberships SET role_key='admin' WHERE user_id=9"); [$status] = $run('--enable'); $check($status !== 0, 'Unexpected Viewer escalation prevents activation'); $db->exec("UPDATE memberships SET role_key='viewer' WHERE user_id=9");
    $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(7,8,8,'viewer')"); [$status] = $run('--enable'); $check($status !== 0, 'Cross-company assignment prevents activation'); $db->exec('DELETE FROM memberships WHERE user_id=7 AND organization_id=8');
    $q = $db->prepare('INSERT INTO module_sessions VALUES(?,?,?,?,?,NULL)'); $q->execute([hash('sha256', 'test'), hash('sha256', 'audience'), 7, 8, time() + 300]);
    [$status] = $run('--enable'); $check($status !== 0, 'Active fixture session prevents activation'); $db->exec('DELETE FROM module_sessions');
    $bad = $fixtures; $bad['alpha']['company_id'] = 44; file_put_contents($fixtureFile, json_encode($bad)); [$status] = $run('--enable'); $check($status !== 0, 'Wrong delivery company prevents activation'); file_put_contents($fixtureFile, json_encode($fixtures));
    [$status, $result] = $run('--enable'); $check($status === 0 && $result['test_connections_enabled'] && !$result['summary_reporting_enabled'] && $result['handover_excluded'], 'Enable only prepared test access');
    clearstatcache(); $check((fileperms($inventory) & 0777) === 0600, 'Private inventory remains private');
    $check(count(suite_instances()->forProject(7, 7)) === 7 && count(suite_instances()->forProject(8, 8)) === 7, 'Both test sites gain exactly seven connections');
    $check(suite_instances()->forProject(9, 9) === [], 'Unrelated customer site gains no access');
    [$status] = $run('--enable'); $check($status !== 0, 'Repeated activation cannot silently overwrite verified state');
    echo "$checks test connection activation checks passed.\n";
} finally { $remove($root); }
