<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$auth = suite_auth();
$user = $auth->requireUser();
$projectsRepo = suite_projects();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'select_project') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Session expired.');
    }
    $projectsRepo->selectForUser($user, (int) ($_POST['project_id'] ?? 0));
    header('Location: /');
    exit;
}

$projectList = $projectsRepo->forUser($user);
$project = $projectsRepo->currentForUser($user);
$role = $projectsRepo->roleFor($user, $project);
$modules = suite_modules()->allForProject($role, $project ? (int) $project['id'] : null);
$summaryConfigured = suite_summary_client()->configured();

$notificationCount = \Suite\Support\Notifications::count(
    (int) $user['id'],
    $project ? (int) $project['id'] : null
);
$notificationPreview = \Suite\Support\Notifications::recent(
    (int) $user['id'],
    $project ? (int) $project['id'] : null,
    3
);

$firstName = trim(explode(' ', trim((string) $user['name']))[0] ?? 'there');
$projectName = $project['name'] ?? 'No project assigned';
$organizationName = $project['organization_name'] ?? 'Construction Suite';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <meta name="description" content="Construction Suite — mobile-first site management hub.">
  <title><?= suite_e((string) $projectName) ?> · Construction Suite</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page"
      data-suite-user="<?= suite_e((string) $user['name']) ?>"
      data-suite-project="<?= suite_e((string) $projectName) ?>"
      data-suite-organisation="<?= suite_e((string) $organizationName) ?>">
  <header class="topbar">
    <a class="brand" href="/" aria-label="Construction Suite home">
      <img src="/assets/brand/logo.svg" alt="">
      <span><strong>Construction</strong><small>Suite</small></span>
    </a>
    <div class="topbar-actions">
      <button class="icon-button install-button" type="button" data-install hidden aria-label="Install Construction Suite">
        <svg><use href="/assets/icons.svg#download"></use></svg>
      </button>
      <a class="icon-button notification-button" href="/notifications.php" aria-label="Notifications">
        <svg><use href="/assets/icons.svg#bell"></use></svg>
        <?php if ($notificationCount > 0): ?><span class="notification-count"><?= $notificationCount ?></span><?php endif; ?>
      </a>
      <details class="profile-menu">
        <summary><?= suite_e(strtoupper(substr($firstName, 0, 1))) ?></summary>
        <div class="profile-panel">
          <strong><?= suite_e((string) $user['name']) ?></strong>
          <span><?= suite_e((string) $user['email']) ?></span>
          <span class="role-pill"><?= suite_e(str_replace('_', ' ', $role)) ?></span>
          <a href="/profile.php">My profile & password</a>
          <?php if ($auth->isPlatformAdmin($user)): ?><a href="/admin/">Suite administration</a><?php endif; ?>
          <form method="post" action="/logout.php">
            <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
            <button type="submit">Sign out</button>
          </form>
        </div>
      </details>
    </div>
  </header>

  <main class="app-shell">
    <section class="project-strip">
      <div>
        <p class="eyebrow"><?= suite_e((string) $organizationName) ?></p>
        <h1>Good <?= date('G') < 12 ? 'morning' : (date('G') < 18 ? 'afternoon' : 'evening') ?>, <?= suite_e($firstName) ?></h1>
        <p class="project-location"><?= suite_e((string) ($project['location'] ?? '')) ?></p>
      </div>
      <?php if (count($projectList) > 1): ?>
        <form method="post" class="project-switcher">
          <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
          <input type="hidden" name="action" value="select_project">
          <label for="project_id">Current project</label>
          <select id="project_id" name="project_id" onchange="this.form.submit()">
            <?php foreach ($projectList as $item): ?>
              <option value="<?= (int) $item['id'] ?>" <?= (int) ($project['id'] ?? 0) === (int) $item['id'] ? 'selected' : '' ?>>
                <?= suite_e((string) $item['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      <?php else: ?>
        <div class="project-chip"><svg><use href="/assets/icons.svg#site"></use></svg><span><?= suite_e((string) $projectName) ?></span></div>
      <?php endif; ?>
    </section>

    <section class="status-grid" aria-label="Suite status">
      <article><span class="status-icon blue"><svg><use href="/assets/icons.svg#grid"></use></svg></span><div><strong data-module-health-summary>Checking…</strong><span>Module availability</span></div></article>
      <article><span class="status-icon green"><svg><use href="/assets/icons.svg#sync"></use></svg></span><div><strong data-sync-label>Up to date</strong><span>Device sync</span></div></article>
      <article><span class="status-icon amber"><svg><use href="/assets/icons.svg#bell"></use></svg></span><div><strong><?= $notificationCount ?></strong><span>Unread alerts</span></div></article>
      <article><span class="status-icon slate"><span class="status-dot" data-connection-dot></span></span><div><strong data-connection-text>Checking…</strong><span>Connection</span></div></article>
    </section>

    <?php if ($summaryConfigured): ?>
    <section class="snapshot-shell" aria-label="Live site snapshot">
      <div class="section-heading compact">
        <div><p class="eyebrow">LIVE SITE SNAPSHOT</p><h2>What needs attention now</h2></div>
        <span data-summary-updated>Connecting…</span>
      </div>
      <div class="snapshot-grid">
        <article class="snapshot-card">
          <span class="snapshot-icon blue"><svg><use href="/assets/icons.svg#defects"></use></svg></span>
          <div><strong data-summary-module="defects" data-summary-metric="active">—</strong><span>Active defects</span><small data-summary-status="defects">Connecting…</small></div>
        </article>
        <article class="snapshot-card">
          <span class="snapshot-icon purple"><svg><use href="/assets/icons.svg#permit"></use></svg></span>
          <div><strong data-summary-module="permits" data-summary-metric="pending_approval">—</strong><span>Permits awaiting approval</span><small data-summary-status="permits">Connecting…</small></div>
        </article>
        <article class="snapshot-card">
          <span class="snapshot-icon green"><svg><use href="/assets/icons.svg#shield"></use></svg></span>
          <div><strong data-summary-module="safety" data-summary-metric="open_actions">—</strong><span>Open safety actions</span><small data-summary-status="safety">Connecting…</small></div>
        </article>
        <article class="snapshot-card">
          <span class="snapshot-icon orange"><svg><use href="/assets/icons.svg#truck"></use></svg></span>
          <div><strong data-summary-module="deliveries" data-summary-metric="today">—</strong><span>Deliveries today</span><small data-summary-status="deliveries">Connecting…</small></div>
        </article>
        <article class="snapshot-card">
          <span class="snapshot-icon purple"><svg><use href="/assets/icons.svg#document"></use></svg></span>
          <div><strong data-summary-module="documents" data-summary-metric="open_count">—</strong><span>Open site notices</span><small data-summary-status="documents">Connecting…</small></div>
        </article>
      </div>
    </section>
    <?php endif; ?>

    <section class="section-heading">
      <div><p class="eyebrow">YOUR SITE TOOLKIT</p><h2><?= suite_e((string) $projectName) ?></h2></div>
      <span><?= count($modules) ?> modules</span>
    </section>

    <section class="module-grid" id="modules">
      <?php foreach ($modules as $module): ?>
        <a class="module-card accent-<?= suite_e((string) $module['accent']) ?>"
           href="<?= suite_e((string) $module['url']) ?>"
           target="_blank" rel="noopener">
          <span class="module-icon"><svg><use href="/assets/icons.svg#<?= suite_e((string) $module['icon']) ?>"></use></svg></span>
          <span class="module-arrow"><svg><use href="/assets/icons.svg#arrow"></use></svg></span>
          <strong><?= suite_e((string) $module['short_name']) ?></strong>
          <span class="module-name"><?= suite_e((string) $module['name']) ?></span>
          <p><?= suite_e((string) $module['description']) ?></p>
          <span class="module-footer">
            <span class="module-meta"><span class="mini-dot"></span><?= suite_e((string) $module['offline']) ?></span>
            <span class="module-health checking" data-module-health="<?= suite_e((string) $module['key']) ?>"><span></span>Checking</span>
          </span>
        </a>
      <?php endforeach; ?>
    </section>

    <section class="work-offline">
      <div class="offline-mark"><svg><use href="/assets/icons.svg#offline"></use></svg></div>
      <div>
        <p class="eyebrow">BUILT FOR THE SITE</p>
        <h2>Keep working when the signal disappears.</h2>
        <p>The Suite shell stays available offline. Individual modules will progressively use the shared durable queue so site work is stored safely on the device and synchronised when connection returns.</p>
      </div>
      <button class="button secondary" type="button" data-install>Install app</button>
    </section>

    <section class="notification-shell" id="notifications">
      <div class="section-heading compact">
        <div><p class="eyebrow">NOTIFICATIONS</p><h2>What needs attention</h2></div>
        <a class="button small secondary" href="/notifications.php">View notifications</a>
      </div>
      <?php if ($notificationCount === 0): ?>
        <div class="empty-panel"><svg><use href="/assets/icons.svg#check"></use></svg><strong>Nothing waiting</strong><span>You have no unread Suite notifications for this project.</span></div>
      <?php else: ?>
        <div class="empty-panel"><svg><use href="/assets/icons.svg#bell"></use></svg><strong><?= $notificationCount ?> unread notification<?= $notificationCount === 1 ? '' : 's' ?></strong><span>Open your inbox to review and acknowledge messages.</span></div>
      <?php endif; ?>
    </section>
  </main>

  <nav class="mobile-nav" aria-label="Main navigation">
    <a class="active" href="/"><svg><use href="/assets/icons.svg#home"></use></svg><span>Home</span></a>
    <a href="#project_id"><svg><use href="/assets/icons.svg#site"></use></svg><span>Projects</span></a>
    <a href="#modules"><svg><use href="/assets/icons.svg#grid"></use></svg><span>Modules</span></a>
    <a href="#notifications"><svg><use href="/assets/icons.svg#bell"></use></svg><span>Alerts</span></a>
    <?php if ($auth->isPlatformAdmin($user)): ?><a href="/admin/"><svg><use href="/assets/icons.svg#settings"></use></svg><span>Admin</span></a><?php endif; ?>
  </nav>

  <div class="ios-install-hint" data-ios-install hidden>
    <strong>Install Construction Suite</strong>
    <span>On iPhone/iPad, tap Share then “Add to Home Screen”.</span>
    <button type="button" aria-label="Close" data-ios-close>×</button>
  </div>

  <script src="/assets/js/app.js" defer></script>
</body>
</html>
