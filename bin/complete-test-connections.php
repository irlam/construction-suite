<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0');
$phase = 'arguments';
try {
    $enable = ($argv[1] ?? '') === '--enable';
    if ($argc !== 2 || !in_array($argv[1], ['--check', '--enable'], true)) throw new RuntimeException();
    require dirname(__DIR__) . '/app/bootstrap.php';
    $private = realpath(SUITE_ROOT) . '/private';
    $file = \Suite\Modules\InstanceCatalog::inventoryFile();
    if ($file !== $private . '/programme-staging-instances.json' || file_exists($private . '/staging-validation.json')) throw new RuntimeException();
    $items = suite_instances()->all();
    ksort($items);
    if (array_keys($items) !== range(1, 14) || !empty(suite_config('modules')['handover']['enabled'])) throw new RuntimeException();
    $plans = [1 => ['programme', 'programme.defecttracker.uk'], 3 => ['defects', 'defectnotice.site'],
        5 => ['deliveries', 'sitedeliveries.site'], 7 => ['qa', 'qa.defecttracker.uk'],
        9 => ['safety', 'sitesafety.site'], 11 => ['permits', 'sitepermits.site'], 13 => ['documents', 'sitenotices.site']];
    $db = suite_db();
    $deliveryFile = $private . '/delivery-test-fixtures.json';
    if (is_link($deliveryFile) || !is_file($deliveryFile) || (fileperms($deliveryFile) & 0777) !== 0600) throw new RuntimeException();
    $deliveryFixtures = json_decode((string) file_get_contents($deliveryFile), true, 16, JSON_THROW_ON_ERROR);
    foreach (['alpha' => [7, [7, 8, 9]], 'beta' => [8, [10, 11, 12]]] as $side => [$org, $ids]) {
        $phase = $side . ' company and users';
        $q = $db->prepare('SELECT COUNT(*) FROM organizations o JOIN projects p ON p.organization_id=o.id WHERE o.id=? AND p.id=? AND o.slug=? AND o.active=1 AND p.active=1');
        $q->execute([$org, $org, 'programme-' . $side . '-staging']);
        if ((int) $q->fetchColumn() !== 1) throw new RuntimeException();
        $q = $db->prepare('SELECT DISTINCT user_id FROM memberships WHERE organization_id=? ORDER BY user_id'); $q->execute([$org]);
        if (array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN)) !== $ids) throw new RuntimeException();
        foreach (['admin' => 'company_admin', 'manager' => 'manager', 'viewer' => 'viewer'] as $label => $role) {
            $uid = array_shift($ids);
            $q = $db->prepare('SELECT email,active,is_platform_admin FROM users WHERE id=?'); $q->execute([$uid]); $u = $q->fetch();
            if (!$u || $u['email'] !== "suite-$side-$label@example.invalid" || !(int) $u['active'] || (int) $u['is_platform_admin']) throw new RuntimeException();
            $q = $db->prepare('SELECT organization_id,project_id,role_key FROM memberships WHERE user_id=?'); $q->execute([$uid]); $m = $q->fetchAll();
            if (count($m) !== 1 || (int) $m[0]['organization_id'] !== $org || $m[0]['role_key'] !== $role
                || ($label === 'admin' ? $m[0]['project_id'] !== null : (int) $m[0]['project_id'] !== $org)) throw new RuntimeException();
        }
        foreach ($plans as $first => [$module, $host]) {
            $id = $first + ($side === 'beta' ? 1 : 0); $i = $items[$id]; $phase = $side . ' ' . $module . ' connection';
            if ($i['organization_id'] !== $org || $i['project_id'] !== $org || $i['module_key'] !== $module
                || $i['origin'] !== 'https://' . ($module === 'deliveries' ? '' : $side . '.') . $host
                || $i['ready'] || ($i['summary_verified'] ?? false) !== false
                || !preg_match('/^[a-f0-9]{64}$/D', (string) \Suite\Support\Env::get($i['key_env'], ''))) throw new RuntimeException();
            $q = $db->prepare('SELECT COUNT(*) FROM module_sessions WHERE instance_id=? AND revoked_at IS NULL AND expires_at>=?');
            $q->execute([$id, time()]); if ((int) $q->fetchColumn()) throw new RuntimeException();
            $q = $db->prepare('SELECT COUNT(*) FROM module_handoffs WHERE instance_id=? AND used_at IS NULL AND expires_at>=?');
            $q->execute([$id, time()]); if ((int) $q->fetchColumn()) throw new RuntimeException();
            if ($module === 'deliveries') {
                $phase = $side . ' delivery fixture scope';
                $fixture = $deliveryFixtures[$side] ?? null;
                if (!is_array($fixture) || !is_int($fixture['site_id'] ?? null) || !is_int($fixture['company_id'] ?? null)
                    || $i['scope_mode'] !== 'company_site' || $i['local_site_id'] !== $fixture['site_id']
                    || $i['local_company_id'] !== $fixture['company_id']) throw new RuntimeException();
            }
        }
    }
    $phase = 'prepared inventory';
    $prepared = json_decode((string) file_get_contents($file), true, 32, JSON_THROW_ON_ERROR);
    foreach ($prepared as &$entry) { $entry['isolation_verified'] = true; $entry['gateway_verified'] = true; }
    unset($entry);
    (new \Suite\Modules\InstanceCatalog($prepared))->all();
    if ($enable) {
        $phase = 'enable test company connections';
        $text = json_encode($prepared, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $temp = $file . '.' . bin2hex(random_bytes(8)) . '.tmp'; $mask = umask(0077);
        try { $h = fopen($temp, 'xb'); } finally { umask($mask); }
        if (!$h) throw new RuntimeException();
        try { if (fwrite($h, $text) !== strlen($text) || !fflush($h)) throw new RuntimeException(); } finally { fclose($h); }
        if (!rename($temp, $file)) throw new RuntimeException();
        \Suite\Support\Audit::record('test_connections.enabled', null, null, null, ['instance_ids' => range(1, 14), 'test_organizations' => [7, 8]]);
    }
    echo json_encode(['ok' => true, 'test_connections_enabled' => $enable, 'companies' => 2, 'sites' => 2,
        'existing_test_accounts' => 6, 'connections' => 14, 'modules' => 7, 'handover_excluded' => true,
        'standalone_customer_connections_changed' => false, 'summary_reporting_enabled' => false]) . "\n";
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'failed_phase' => $phase, 'test_connections_enabled' => false]) . "\n"; exit(1);
}
