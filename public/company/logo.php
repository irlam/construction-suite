<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
$user = suite_auth()->user();
if (!$user) { http_response_code(401); exit; }
try {
    $logo = suite_company_settings()->logoFor($user, (int) ($_GET['company_id'] ?? 0));
} catch (RuntimeException $e) { http_response_code(404); exit; }
if (!$logo) { http_response_code(404); exit; }
header('Content-Type: ' . $logo['mime']);
header('Content-Length: ' . strlen($logo['bytes']));
echo $logo['bytes'];
