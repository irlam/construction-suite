<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to edit organisations.');
}

$pdo = suite_db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare('SELECT id, name, slug, active FROM organizations WHERE id = ?');
$stmt->execute([$id]);
$organisation = $stmt->fetch();

if (!$organisation) {
    http_response_code(404);
    exit('Organisation not found.');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Enter an organisation name.';
        } else {
            $update = $pdo->prepare('UPDATE organizations SET name = ?, active = ? WHERE id = ?');
            $update->execute([$name, $active, $id]);
            Audit::record('organisation.updated', (int) $user['id'], $id, null, [
                'name' => $name,
                'active' => (bool) $active,
            ]);
            $message = 'Organisation updated.';
            $stmt->execute([$id]);
            $organisation = $stmt->fetch();
        }
    }
}

$projectStmt = $pdo->prepare(
    'SELECT id, name, code, location, active FROM projects WHERE organization_id = ? ORDER BY name'
);
$projectStmt->execute([$id]);
$projects = $projectStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>Edit organisation · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/projects.php">Back to projects</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">ORGANISATION</p><h1><?= suite_e((string) $organisation['name']) ?></h1></div><span><?= count($projects) ?> project<?= count($projects) === 1 ? '' : 's' ?></span></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <section class="admin-panel">
    <form method="post" class="admin-form-grid">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
      <label>Organisation name<input type="text" name="name" value="<?= suite_e((string) $organisation['name']) ?>" required></label>
      <label>Internal slug<input type="text" value="<?= suite_e((string) $organisation['slug']) ?>" disabled></label>
      <label class="check-label"><input type="checkbox" name="active" value="1" <?= (bool) $organisation['active'] ? 'checked' : '' ?>> Organisation active</label>
      <div class="form-action"><button class="button primary" type="submit">Save changes</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">PROJECTS</p><h2>Sites in this organisation</h2></div></div>
    <div class="admin-table">
      <?php foreach ($projects as $project): ?>
        <a class="admin-row admin-row-link" href="/admin/project-edit.php?id=<?= (int) $project['id'] ?>">
          <span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span>
          <div><strong><?= suite_e((string) $project['name']) ?></strong><span><?= suite_e((string) ($project['location'] ?: 'No location set')) ?></span></div>
          <span class="role-pill"><?= (bool) $project['active'] ? 'Active' : 'Inactive' ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body></html>
