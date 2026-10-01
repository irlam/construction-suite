<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$auth = suite_auth();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(405);
    exit('Method not allowed.');
}

$auth->logout();
session_regenerate_id(true);
header('Location: /login.php');
