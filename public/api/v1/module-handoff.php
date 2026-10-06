<?php
declare(strict_types=1);
require_once dirname(__DIR__, 3) . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); header('Allow: POST'); echo json_encode(['ok' => false, 'error' => 'method_not_allowed']); exit;
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 4096) { http_response_code(413); exit; }
try {
    $body = file_get_contents('php://input', false, null, 0, 4097);
    if (strlen((string) $body) > 4096) throw new RuntimeException('Invalid request.');
    $input = json_decode((string) $body, true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($input)) throw new RuntimeException('Invalid request.');
    $identity = suite_handoff()->redeem((int) ($input['instance_id'] ?? 0), (string) ($input['code'] ?? ''),
        (string) ($input['state'] ?? ''), (string) ($_SERVER['HTTP_X_CONSTRUCTION_SUITE_KEY'] ?? ''));
    echo json_encode(['ok' => true, 'identity' => $identity], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(403); echo json_encode(['ok' => false, 'error' => 'access_denied']);
}
