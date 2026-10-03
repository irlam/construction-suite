<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to access Suite administration.');
}

$projects = suite_projects()->forUser($user);
$modules = suite_modules()->all('platform_admin');
$dbStatus = 'Connected';
try {
    suite_db()->query('SELECT 1');
} catch (Throwable $e) {
    $dbStatus = 'Unavailable';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#111827">
  <title>Administration · Construction Suite</title>
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page">
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a>
  <a class="button small secondary" href="/">Back to hub</a>
</header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">PLATFORM ADMINISTRATION</p><h1>Suite control centre</h1></div><span>v<?= suite_e((string) suite_config('version')) ?></span></section>
  <section class="admin-metrics">
    <article><strong><?= count($projects) ?></strong><span>Projects</span></article>
    <article><strong><?= count($modules) ?></strong><span>Enabled modules</span></article>
    <article><strong><?= suite_e($dbStatus) ?></strong><span>Database</span></article>
    <article><strong><?= suite_e((string) \Suite\Database\Connection::driver()) ?></strong><span>DB driver</span></article>
  </section>
  <section class="admin-shortcuts">
    <a class="admin-shortcut" href="/admin/projects.php"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span><div><strong>Organisations & projects</strong><span>Create sites and control active projects</span></div></a>
    <a class="admin-shortcut" href="/admin/users.php"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#settings"></use></svg></span><div><strong>Users & access</strong><span>Create accounts and assign project roles</span></div></a>
    <a class="admin-shortcut" href="/admin/audit.php"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#pulse"></use></svg></span><div><strong>Audit activity</strong><span>Review recent administrative changes</span></div></a>
    <a class="admin-shortcut" href="/profile.php"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#check"></use></svg></span><div><strong>My profile</strong><span>Update your name, email and password</span></div></a>
  </section>
  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">MODULE REGISTRY</p><h2>Connected applications</h2></div></div>
    <div class="admin-table">
      <?php foreach ($modules as $module): ?>
        <div class="admin-row">
          <span class="module-icon tiny"><svg><use href="/assets/icons.svg#<?= suite_e((string) $module['icon']) ?>"></use></svg></span>
          <div><strong><?= suite_e((string) $module['name']) ?></strong><span><?= suite_e((string) $module['url']) ?></span></div>
          <span class="role-pill"><?= suite_e((string) $module['offline']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">NEXT CONTROL LAYER</p><h2>Coming through Hub V1</h2></div></div>
    <p class="muted">Organisation management, project creation, users, memberships, module entitlements and audit activity will be added here without changing the existing live apps.</p>
  </section>
</main>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
