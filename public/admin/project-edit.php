<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to edit projects.');
}

$pdo = suite_db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT p.id, p.organization_id, p.name, p.code, p.location, p.active,
            o.name AS organization_name
     FROM projects p JOIN organizations o ON o.id = p.organization_id
     WHERE p.id = ?'
);
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    http_response_code(404);
    exit('Project not found.');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $name = trim((string) ($_POST['name'] ?? ''));
        $code = trim((string) ($_POST['code'] ?? ''));
        $location = trim((string) ($_POST['location'] ?? ''));
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Enter a project/site name.';
        } else {
            $update = $pdo->prepare(
                'UPDATE projects SET name = ?, code = ?, location = ?, active = ? WHERE id = ?'
            );
            $update->execute([
                $name,
                $code !== '' ? $code : null,
                $location !== '' ? $location : null,
                $active,
                $id,
            ]);
            Audit::record('project.updated', (int) $user['id'], (int) $project['organization_id'], $id, [
                'name' => $name,
                'code' => $code,
                'location' => $location,
                'active' => (bool) $active,
            ]);
            $message = 'Project updated.';
            $stmt->execute([$id]);
            $project = $stmt->fetch();
        }
    }
}

$memberStmt = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.active, m.role_key
     FROM memberships m JOIN users u ON u.id = m.user_id
     WHERE m.project_id = ? ORDER BY u.name'
);
$memberStmt->execute([$id]);
$members = $memberStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>Edit project · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/projects.php">Back to projects</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow"><?= suite_e((string) $project['organization_name']) ?></p><h1><?= suite_e((string) $project['name']) ?></h1></div><span><?= count($members) ?> member<?= count($members) === 1 ? '' : 's' ?></span></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <section class="admin-panel">
    <form method="post" class="admin-form-grid">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
      <label>Project / site name<input type="text" name="name" value="<?= suite_e((string) $project['name']) ?>" required></label>
      <label>Project code<input type="text" name="code" value="<?= suite_e((string) ($project['code'] ?? '')) ?>" placeholder="Optional"></label>
      <label>Location<input type="text" name="location" value="<?= suite_e((string) ($project['location'] ?? '')) ?>" placeholder="Optional"></label>
      <label class="check-label"><input type="checkbox" name="active" value="1" <?= (bool) $project['active'] ? 'checked' : '' ?>> Project active</label>
      <div class="form-action"><button class="button primary" type="submit">Save changes</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">ACCESS</p><h2>Project members</h2></div></div>
    <div class="admin-table">
      <?php foreach ($members as $member): ?>
        <a class="admin-row admin-row-link" href="/admin/user-edit.php?id=<?= (int) $member['id'] ?>">
          <div class="avatar-small"><?= suite_e(strtoupper(substr((string) $member['name'], 0, 1))) ?></div>
          <div><strong><?= suite_e((string) $member['name']) ?></strong><span><?= suite_e((string) $member['email']) ?></span></div>
          <span class="role-pill"><?= suite_e(ucwords(str_replace('_', ' ', (string) $member['role_key']))) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body></html>
