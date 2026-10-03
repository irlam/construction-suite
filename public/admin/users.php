<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$auth = suite_auth();
$currentUser = $auth->requireUser();
if (!$auth->isPlatformAdmin($currentUser)) {
    http_response_code(403);
    exit('You do not have permission to manage users.');
}

$pdo = suite_db();
$message = null;
$error = null;
$allowedRoles = ['admin', 'manager', 'site_manager', 'user', 'contractor'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'add_user') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['password'] ?? '');
            $projectId = (int) ($_POST['project_id'] ?? 0);
            $role = (string) ($_POST['role_key'] ?? 'user');

            $projectStmt = $pdo->prepare('SELECT id, organization_id FROM projects WHERE id = ? AND active = 1');
            $projectStmt->execute([$projectId]);
            $project = $projectStmt->fetch();

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter a name and valid email address.';
            } elseif (strlen($password) < 12) {
                $error = 'New user passwords must contain at least 12 characters.';
            } elseif (!$project) {
                $error = 'Choose a valid project.';
            } elseif (!in_array($role, $allowedRoles, true)) {
                $error = 'Choose a valid role.';
            } else {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $exists->execute([$email]);
                if ((int) $exists->fetchColumn() > 0) {
                    $error = 'That email address is already in use.';
                } else {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare(
                            'INSERT INTO users (email, name, password_hash, active, is_platform_admin)
                             VALUES (?, ?, ?, 1, 0)'
                        );
                        $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
                        $userId = (int) $pdo->lastInsertId();

                        $stmt = $pdo->prepare(
                            'INSERT INTO memberships (user_id, organization_id, project_id, role_key)
                             VALUES (?, ?, ?, ?)'
                        );
                        $stmt->execute([$userId, (int) $project['organization_id'], $projectId, $role]);
                        $pdo->commit();
                        $message = 'User created and assigned to the project.';
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $error = 'The user could not be created.';
                    }
                }
            }
        }

        if ($action === 'toggle_user') {
            $userId = (int) ($_POST['user_id'] ?? 0);
            if ($userId === (int) $currentUser['id']) {
                $error = 'You cannot deactivate your own platform administrator account.';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE users SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = ? AND is_platform_admin = 0'
                );
                $stmt->execute([$userId]);
                $message = 'User status updated.';
            }
        }
    }
}

$projects = $pdo->query(
    'SELECT p.id, p.name, o.name AS organization_name
     FROM projects p JOIN organizations o ON o.id = p.organization_id
     WHERE p.active = 1 AND o.active = 1 ORDER BY o.name, p.name'
)->fetchAll();

$users = $pdo->query(
    'SELECT u.id, u.name, u.email, u.active, u.is_platform_admin,
            GROUP_CONCAT(DISTINCT p.name) AS project_names,
            GROUP_CONCAT(DISTINCT m.role_key) AS roles
     FROM users u
     LEFT JOIN memberships m ON m.user_id = u.id
     LEFT JOIN projects p ON p.id = m.project_id
     GROUP BY u.id, u.name, u.email, u.active, u.is_platform_admin
     ORDER BY u.name'
)->fetchAll();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#111827">
  <title>Users · Construction Suite</title>
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page">
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a>
  <a class="button small secondary" href="/admin/">Admin home</a>
</header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">ADMINISTRATION</p><h1>Users & access</h1></div></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">NEW USER</p><h2>Create project access</h2></div></div>
    <form method="post" class="admin-form-grid">
      <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
      <input type="hidden" name="action" value="add_user">
      <label>Name<input type="text" name="name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Temporary password<input type="password" name="password" minlength="12" required></label>
      <label>Project
        <select name="project_id" required>
          <option value="">Choose project</option>
          <?php foreach ($projects as $project): ?>
            <option value="<?= (int) $project['id'] ?>"><?= suite_e((string) $project['organization_name']) ?> · <?= suite_e((string) $project['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Role
        <select name="role_key" required>
          <?php foreach ($allowedRoles as $role): ?><option value="<?= suite_e($role) ?>"><?= suite_e(ucwords(str_replace('_', ' ', $role))) ?></option><?php endforeach; ?>
        </select>
      </label>
      <div class="form-action"><button class="button primary" type="submit">Create user</button></div>
    </form>
  </section>

  <section class="admin-panel">
    <div class="section-heading compact"><div><p class="eyebrow">USER REGISTER</p><h2><?= count($users) ?> accounts</h2></div></div>
    <div class="admin-table">
      <?php foreach ($users as $account): ?>
        <div class="admin-row admin-row-actions admin-row-five">
          <div class="avatar-small"><?= suite_e(strtoupper(substr((string) $account['name'], 0, 1))) ?></div>
          <div>
            <strong><?= suite_e((string) $account['name']) ?><?= (bool) $account['is_platform_admin'] ? ' · Platform admin' : '' ?></strong>
            <span><?= suite_e((string) $account['email']) ?> · <?= suite_e((string) ($account['project_names'] ?: 'No project')) ?></span>
          </div>
          <span class="role-pill"><?= suite_e((string) ($account['roles'] ?: ((bool) $account['is_platform_admin'] ? 'platform_admin' : 'user'))) ?></span>
          <a class="button small secondary" href="/admin/user-edit.php?id=<?= (int) $account['id'] ?>">Edit</a>
          <?php if (!(bool) $account['is_platform_admin']): ?>
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
              <input type="hidden" name="action" value="toggle_user">
              <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
              <button class="button small secondary" type="submit"><?= (bool) $account['active'] ? 'Deactivate' : 'Activate' ?></button>
            </form>
          <?php else: ?><span class="muted"><?= (bool) $account['active'] ? 'Active' : 'Inactive' ?></span><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body>
</html>
