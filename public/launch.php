<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
$auth = suite_auth(); $user = $auth->requireUser();
$instanceId = (int) ($_GET['instance_id'] ?? $_POST['instance_id'] ?? 0);
try { $context = suite_handoff()->authorize($user, $instanceId); }
catch (Throwable $e) { http_response_code(403); exit('This project tool is not available to your account.'); }
$instance = $context['instance'];
$state = (string) ($_GET['state'] ?? $_POST['state'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $state === '') {
    header('Location: ' . $instance['origin'] . '/suite-login.php', true, 303); exit;
}
if (!preg_match('/^[a-f0-9]{64}$/D', $state)) { http_response_code(400); exit('Invalid sign-in state.'); }
$code = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) { http_response_code(419); exit('Session expired.'); }
    try { $code = suite_handoff()->issue($user, $instanceId, $state); }
    catch (Throwable $e) { http_response_code(503); exit('Project sign-in is not configured yet.'); }
}
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Open project tool · Construction Suite</title><link rel="stylesheet" href="/assets/css/app.css"></head><body class="app-page"><main class="app-shell admin-shell"><section class="admin-panel">
<h1>Open <?= suite_e((string) suite_config('modules')[$instance['module_key']]['name']) ?></h1>
<p><?= suite_e((string) $context['project']['organization_name']) ?> · <?= suite_e((string) $context['project']['name']) ?></p>
<?php if ($code): ?><form id="handoff" method="post" action="<?= suite_e($instance['origin']) ?>/suite-login.php"><input type="hidden" name="code" value="<?= suite_e($code) ?>"><input type="hidden" name="state" value="<?= suite_e($state) ?>"><button class="button primary">Continue to project tool</button></form><script>document.getElementById('handoff').submit();</script>
<?php else: ?><form method="post"><input type="hidden" name="instance_id" value="<?= $instanceId ?>"><input type="hidden" name="state" value="<?= suite_e($state) ?>"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><button class="button primary">Sign in to this project tool</button></form><?php endif; ?>
</section></main></body></html>
