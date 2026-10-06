<?php
declare(strict_types=1);
// CLI-only fixture worker. No credentials or opaque session tokens are output.
if (PHP_SAPI !== 'cli' || getenv('SUITE_TEST_MYSQL') !== '1'
    || getenv('DB_DRIVER') !== 'mysql' || !str_starts_with((string) getenv('DB_DATABASE'), 'suite_test_')) {
    http_response_code(404); exit(1);
}
require_once dirname(__DIR__) . '/app/bootstrap.php';
$input = json_decode((string) fgets(STDIN), true, 8, JSON_THROW_ON_ERROR);
$catalog = new \Suite\Modules\InstanceCatalog($input['inventory']);
echo "READY\n"; flush();
if (trim((string) fgets(STDIN)) !== 'GO') exit(1);
try {
    $identity = (new \Suite\Auth\ModuleHandoff($catalog))->redeem(1, $input['code'], $input['state'], $input['key']);
    echo json_encode(['accepted' => isset($identity['session_token'])]) . "\n";
} catch (RuntimeException $e) {
    echo "{\"accepted\":false}\n";
}
