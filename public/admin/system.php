<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to manage the Suite system.');
}

$migrator = suite_migrator();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'migrate') {
        try {
            $applied = $migrator->migrateAll();
            if ($applied) {
                Audit::record('system.migrations_applied', (int) $user['id'], null, null, ['versions' => $applied]);
                $message = count($applied) . ' database update' . (count($applied) === 1 ? '' : 's') . ' applied.';
            } else {
                $message = 'Database is already up to date.';
            }
        } catch (Throwable $e) {
            $error = 'Database update failed. Check the server error log before retrying.';
        }
    }
}

$pending = $migrator->pending();
$applied = $migrator->appliedVersions();

$health = [
    'database' => false,
    'curl' => function_exists('curl_multi_init'),
    'https' => (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off'),
];
try {
    suite_db()->query('SELECT 1');
    $health['database'] = true;
} catch (Throwable $e) {
    $health['database'] = false;
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>System · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/">Admin home</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">SYSTEM</p><h1>Platform health & updates</h1></div><span>v<?= suite_e((string) suite_config('version')) ?></span></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <section class="admin-metrics">
    <article><strong><?= $health['database'] ? 'OK' : 'Problem' ?></strong><span>Database</span></article>
    <article><strong><?= $health['https'] ? 'HTTPS' : 'HTTP' ?></strong><span>Connection security</span></article>
    <article><strong><?= $health['curl'] ? 'Ready' : 'Missing' ?></strong><span>Module health checks</span></article>
    <article><strong><?= count($pending) ?></strong><span>Pending DB updates</span></article>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">DATABASE</p><h2>Schema updates</h2></div></div>
    <?php if ($pending): ?>
      <div class="notice warning"><strong><?= count($pending) ?> update<?= count($pending) === 1 ? '' : 's' ?> ready.</strong><br><?= suite_e(implode(', ', array_keys($pending))) ?></div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="migrate">
        <button class="button primary" type="submit">Apply database updates</button>
      </form>
    <?php else: ?>
      <div class="empty-panel"><svg><use href="/assets/icons.svg#check"></use></svg><strong>Database up to date</strong><span>No migrations are waiting.</span></div>
    <?php endif; ?>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">HISTORY</p><h2>Applied updates</h2></div></div>
    <div class="admin-table">
      <?php if (!$applied): ?><p class="muted">No versioned migrations have been applied yet.</p><?php endif; ?>
      <?php foreach (array_reverse($applied, true) as $version => $date): ?>
        <div class="admin-row"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#check"></use></svg></span><div><strong><?= suite_e($version) ?></strong><span><?= suite_e($date) ?></span></div><span class="role-pill">Applied</span></div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body></html>
