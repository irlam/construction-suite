<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to manage projects.');
}

$pdo = suite_db();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_organisation') {
            $name = trim((string) ($_POST['name'] ?? ''));
            if ($name === '') {
                $error = 'Enter an organisation name.';
            } else {
                $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
                $slug = $slugBase ?: 'organisation';
                $candidate = $slug;
                $suffix = 2;
                $check = $pdo->prepare('SELECT COUNT(*) FROM organizations WHERE slug = ?');
                do {
                    $check->execute([$candidate]);
                    if ((int) $check->fetchColumn() === 0) break;
                    $candidate = $slug . '-' . $suffix++;
                } while ($suffix < 1000);

                $stmt = $pdo->prepare('INSERT INTO organizations (name, slug, active) VALUES (?, ?, 1)');
                $stmt->execute([$name, $candidate]);
                $organisationId = (int) $pdo->lastInsertId();
                Audit::record('organisation.created', (int) $user['id'], $organisationId, null, ['name' => $name]);
                $message = 'Organisation added.';
            }
        }

        if ($action === 'add_project') {
            $organizationId = (int) ($_POST['organization_id'] ?? 0);
            $name = trim((string) ($_POST['name'] ?? ''));
            $code = trim((string) ($_POST['code'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));

            $check = $pdo->prepare('SELECT COUNT(*) FROM organizations WHERE id = ? AND active = 1');
            $check->execute([$organizationId]);

            if ($organizationId < 1 || (int) $check->fetchColumn() !== 1) {
                $error = 'Choose a valid organisation.';
            } elseif ($name === '') {
                $error = 'Enter a project/site name.';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO projects (organization_id, name, code, location, active)
                     VALUES (?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $organizationId,
                    $name,
                    $code !== '' ? $code : null,
                    $location !== '' ? $location : null,
                ]);
                $projectId = (int) $pdo->lastInsertId();
                Audit::record('project.created', (int) $user['id'], $organizationId, $projectId, ['name' => $name]);
                $message = 'Project added.';
            }
        }

        if ($action === 'toggle_project') {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $stmt = $pdo->prepare(
                'UPDATE projects SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ?'
            );
            $stmt->execute([$projectId]);
            $lookup = $pdo->prepare('SELECT organization_id, active FROM projects WHERE id = ?');
            $lookup->execute([$projectId]);
            $changedProject = $lookup->fetch();
            if ($changedProject) {
                Audit::record('project.status_changed', (int) $user['id'], (int) $changedProject['organization_id'], $projectId, ['active' => (bool) $changedProject['active']]);
            }
            $message = 'Project status updated.';
        }
    }
}

$organisations = $pdo->query(
    'SELECT id, name, slug, active FROM organizations ORDER BY name'
)->fetchAll();

$projects = $pdo->query(
    'SELECT p.id, p.name, p.code, p.location, p.active, o.name AS organization_name
     FROM projects p JOIN organizations o ON o.id = p.organization_id
     ORDER BY o.name, p.name'
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#111827">
  <title>Projects · Construction Suite</title>
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page">
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a>
  <a class="button small secondary" href="/admin/">Admin home</a>
</header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>Organisations & projects</h1></div></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <div class="admin-two-col">
    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">NEW ORGANISATION</p><h2>Add a company</h2></div></div>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="add_organisation">
        <label>Organisation name<input type="text" name="name" required placeholder="Example Construction Ltd"></label>
        <button class="button primary" type="submit">Add organisation</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">NEW PROJECT</p><h2>Add a site</h2></div></div>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="add_project">
        <label>Organisation
          <select name="organization_id" required>
            <option value="">Choose organisation</option>
            <?php foreach ($organisations as $org): if (!(bool) $org['active']) continue; ?>
              <option value="<?= (int) $org['id'] ?>"><?= suite_e((string) $org['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Project / site name<input type="text" name="name" required></label>
        <label>Project code<input type="text" name="code" placeholder="Optional"></label>
        <label>Location<input type="text" name="location" placeholder="Optional"></label>
        <button class="button primary" type="submit">Add project</button>
      </form>
    </section>
  </div>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">ORGANISATIONS</p><h2><?= count($organisations) ?> organisations</h2></div></div>
    <div class="admin-table">
      <?php foreach ($organisations as $org): ?>
        <a class="admin-row admin-row-link" href="/admin/organisation-edit.php?id=<?= (int) $org['id'] ?>">
          <span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span>
          <div><strong><?= suite_e((string) $org['name']) ?></strong><span><?= suite_e((string) $org['slug']) ?></span></div>
          <span class="role-pill"><?= (bool) $org['active'] ? 'Active' : 'Inactive' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">PROJECT REGISTER</p><h2><?= count($projects) ?> projects</h2></div></div>
    <div class="admin-table">
      <?php foreach ($projects as $project): ?>
        <div class="admin-row admin-row-actions admin-row-five">
          <span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span>
          <div>
            <strong><?= suite_e((string) $project['name']) ?></strong>
            <span><?= suite_e((string) $project['organization_name']) ?><?= $project['location'] ? ' · ' . suite_e((string) $project['location']) : '' ?></span>
          </div>
          <span class="role-pill"><?= (bool) $project['active'] ? 'Active' : 'Inactive' ?></span>
          <a class="button small secondary" href="/admin/project-edit.php?id=<?= (int) $project['id'] ?>">Edit</a>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="toggle_project">
            <input type="hidden" name="project_id" value="<?= (int) $project['id'] ?>">
            <button class="button small secondary" type="submit"><?= (bool) $project['active'] ? 'Deactivate' : 'Activate' ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body>
</html>
