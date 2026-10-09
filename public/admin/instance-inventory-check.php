<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) { http_response_code(403); exit; }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
try {
    $file = \Suite\Modules\InstanceCatalog::inventoryFile();
    $items = suite_instances()->all();
    $anyReady = false;
    foreach ($items as $item) $anyReady = $anyReady || $item['ready'];
    echo json_encode(['web_inventory_loaded' => $file !== null, 'instances' => count($items),
        'application_private_file' => $file === realpath(SUITE_ROOT) . '/private/programme-staging-instances.json',
        'cli_path_alias' => $file !== \Suite\Support\Env::get('SUITE_INSTANCES_FILE', ''),
        'tenant_ready' => $anyReady], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(503);
    echo json_encode(['web_inventory_loaded' => false, 'error' => 'private_inventory_unavailable']);
}
