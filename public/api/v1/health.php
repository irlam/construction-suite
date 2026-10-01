<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$database = false;
try {
    suite_db()->query('SELECT 1');
    $database = true;
} catch (Throwable $e) {
    $database = false;
}

http_response_code($database ? 200 : 503);
echo json_encode([
    'status' => $database ? 'ok' : 'degraded',
    'version' => (string) suite_config('version'),
    'database' => $database ? 'ok' : 'unavailable',
], JSON_UNESCAPED_SLASHES);
