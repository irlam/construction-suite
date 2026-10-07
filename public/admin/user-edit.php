<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$currentUser = $auth->requireUser();
if (!$auth->isPlatformAdmin($currentUser)) {
    http_response_code(403);
    exit('You do not have permission to edit users.');
}

$pdo = suite_db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$allowedRoles = ['admin', 'manager', 'site_manager', 'user', 'contractor', 'viewer'];

$userStmt = $pdo->prepare(
    'SELECT id, name, email, active, is_platform_admin, last_login_at FROM users WHERE id = ?'
);
$userStmt->execute([$id]);
$account = $userStmt->fetch();

if (!$account) {
    http_response_code(404);
    exit('User not found.');
}

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'account') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $active = isset($_POST['active']) ? 1 : 0;

            if ($id === (int) $currentUser['id']) {
                $active = 1;
            }

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a name and valid email address.';
            } else {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
                $exists->execute([$email, $id]);
                if ((int) $exists->fetchColumn() > 0) {
                    $error = 'That email address is already in use.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ?, active = ? WHERE id = ?');
                    $stmt->execute([$name, $email, $active, $id]);
                    Audit::record('user.updated', (int) $currentUser['id'], null, null, [
                        'target_user_id' => $id,
                        'email' => $email,
                        'active' => (bool) $active,
                    ]);
                    $message = 'User details updated.';
                }
            }
        }

        if ($action === 'reset_password' && $error === null) {
            $password = (string) ($_POST['new_password'] ?? '');
            if (strlen($password) < 12) {
                $error = 'Use a temporary password of at least 12 characters.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
                Audit::record('user.password_reset', (int) $currentUser['id'], null, null, [
                    'target_user_id' => $id,
                ]);
                $message = 'Password reset.';
            }
        }

        if ($action === 'add_membership' && $error === null) {
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $role = (string) ($_POST['role_key'] ?? 'user');
            $projectStmt = $pdo->prepare('SELECT id, organization_id FROM projects WHERE id = ? AND active = 1');
            $projectStmt->execute([$projectId]);
            $project = $projectStmt->fetch();

            if (!$project || !in_array($role, $allowedRoles, true)) {
                $error = 'Choose a valid project and role.';
            } else {
                $exists = $pdo->prepare('SELECT id FROM memberships WHERE user_id = ? AND project_id = ? LIMIT 1');
                $exists->execute([$id, $projectId]);
                $membershipId = (int) ($exists->fetchColumn() ?: 0);

                if ($membershipId > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE memberships SET organization_id = ?, role_key = ? WHERE id = ?'
                    );
                    $stmt->execute([(int) $project['organization_id'], $role, $membershipId]);
                    $message = 'Project role updated.';
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO memberships (user_id, organization_id, project_id, role_key)
                         VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$id, (int) $project['organization_id'], $projectId, $role]);
                    $message = 'Project access added.';
                }

                Audit::record('membership.saved', (int) $currentUser['id'], (int) $project['organization_id'], $projectId, [
                    'target_user_id' => $id,
                    'role' => $role,
                ]);
            }
        }

        if ($action === 'remove_membership' && $error === null) {
            $membershipId = (int) ($_POST['membership_id'] ?? 0);
            $lookup = $pdo->prepare(
                'SELECT id, organization_id, project_id FROM memberships WHERE id = ? AND user_id = ?'
            );
            $lookup->execute([$membershipId, $id]);
            $membership = $lookup->fetch();

            if ($membership) {
                $stmt = $pdo->prepare('DELETE FROM memberships WHERE id = ? AND user_id = ?');
                $stmt->execute([$membershipId, $id]);
                Audit::record(
                    'membership.removed',
                    (int) $currentUser['id'],
                    (int) $membership['organization_id'],
                    (int) $membership['project_id'],
                    ['target_user_id' => $id]
                );
                if ($id === (int) $currentUser['id']) {
                    unset($_SESSION['suite_project_id']);
                }
                $message = 'Project access removed.';
            }
        }

        $userStmt->execute([$id]);
        $account = $userStmt->fetch();
    }
}

$projects = $pdo->query(
    'SELECT p.id, p.name, p.organization_id, o.name AS organization_name
     FROM projects p JOIN organizations o ON o.id = p.organization_id
     WHERE p.active = 1 AND o.active = 1 ORDER BY o.name, p.name'
)->fetchAll();

$membershipStmt = $pdo->prepare(
    'SELECT m.id, m.project_id, m.role_key, p.name AS project_name, o.name AS organization_name
     FROM memberships m
     LEFT JOIN projects p ON p.id = m.project_id
     JOIN organizations o ON o.id = m.organization_id
     WHERE m.user_id = ? ORDER BY o.name, p.name'
);
$membershipStmt->execute([$id]);
$memberships = $membershipStmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>Edit user · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/users.php">Back to users</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow"><?= (bool) $account['is_platform_admin'] ? 'PLATFORM ADMINISTRATOR' : 'USER ACCOUNT' ?></p><h1><?= suite_e((string) $account['name']) ?></h1></div><span><?= suite_e((string) $account['email']) ?></span></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <div class="admin-two-col">
    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">ACCOUNT</p><h2>User details</h2></div></div>
      <form method="post" class="auth-form">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="account">
        <label>Name<input type="text" name="name" value="<?= suite_e((string) $account['name']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= suite_e((string) $account['email']) ?>" required></label>
        <label class="check-label"><input type="checkbox" name="active" value="1" <?= (bool) $account['active'] ? 'checked' : '' ?> <?= $id === (int) $currentUser['id'] ? 'disabled' : '' ?>> Account active</label>
        <button class="button primary" type="submit">Save user</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">SECURITY</p><h2>Reset password</h2></div></div>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="reset_password">
        <label>New temporary password<input type="password" name="new_password" minlength="12" required></label>
        <button class="button secondary" type="submit">Reset password</button>
      </form>
    </section>
  </div>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">PROJECT ACCESS</p><h2>Memberships</h2></div></div>
    <div class="admin-table">
      <?php if (!$memberships): ?><p class="muted">No project access assigned.</p><?php endif; ?>
      <?php foreach ($memberships as $membership): ?>
        <div class="admin-row admin-row-actions">
          <span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span>
          <div><strong><?= suite_e((string) ($membership['project_name'] ?: 'Organisation-wide')) ?></strong><span><?= suite_e((string) $membership['organization_name']) ?></span></div>
          <span class="role-pill"><?= suite_e(ucwords(str_replace('_', ' ', (string) $membership['role_key']))) ?></span>
          <?php if ($membership['project_id'] !== null): ?>
          <form method="post">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="remove_membership">
            <input type="hidden" name="membership_id" value="<?= (int) $membership['id'] ?>">
            <button class="button small secondary" type="submit">Remove</button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <form method="post" class="admin-form-grid access-form">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
      <input type="hidden" name="action" value="add_membership">
      <label>Project
        <select name="project_id" required><option value="">Choose project</option><?php foreach ($projects as $project): ?><option value="<?= (int) $project['id'] ?>"><?= suite_e((string) $project['organization_name']) ?> · <?= suite_e((string) $project['name']) ?></option><?php endforeach; ?></select>
      </label>
      <label>Role
        <select name="role_key" required><?php foreach ($allowedRoles as $role): ?><option value="<?= suite_e($role) ?>"><?= suite_e(ucwords(str_replace('_', ' ', $role))) ?></option><?php endforeach; ?></select>
      </label>
      <div class="form-action"><button class="button primary" type="submit">Add / update access</button></div>
    </form>
  </section>
</main>
</body></html>
