<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
$pdo = suite_db();
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'profile') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Enter your name and a valid email address.';
            } else {
                $exists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?');
                $exists->execute([$email, (int) $user['id']]);
                if ((int) $exists->fetchColumn() > 0) {
                    $error = 'That email address is already in use.';
                } else {
                    $stmt = $pdo->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
                    $stmt->execute([$name, $email, (int) $user['id']]);
                    Audit::record('profile.updated', (int) $user['id'], null, null, ['email' => $email]);
                    $message = 'Profile updated.';
                    $user = $auth->user() ?? $user;
                }
            }
        }

        if ($action === 'password' && $error === null) {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');

            $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
            $hash = (string) $stmt->fetchColumn();

            if (!password_verify($current, $hash)) {
                $error = 'Your current password is incorrect.';
            } elseif (strlen($new) < 12) {
                $error = 'Use a new password of at least 12 characters.';
            } elseif ($new !== $confirm) {
                $error = 'The new passwords do not match.';
            } else {
                $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
                Audit::record('password.changed', (int) $user['id']);
                session_regenerate_id(true);
                $message = 'Password changed.';
            }
        }
    }
}

$project = suite_projects()->currentForUser($user);
$role = suite_projects()->roleFor($user, $project);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#111827">
  <title>My profile · Construction Suite</title>
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page">
<header class="topbar">
  <a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a>
  <a class="button small secondary" href="/">Back to hub</a>
</header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">ACCOUNT</p><h1>My profile</h1></div><span><?= suite_e(ucwords(str_replace('_', ' ', $role))) ?></span></section>
  <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

  <div class="admin-two-col">
    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">DETAILS</p><h2>Profile information</h2></div></div>
      <form method="post" class="auth-form">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="profile">
        <label>Name<input type="text" name="name" value="<?= suite_e((string) $user['name']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= suite_e((string) $user['email']) ?>" required></label>
        <label>Current project<input type="text" value="<?= suite_e((string) ($project['name'] ?? 'No project assigned')) ?>" disabled></label>
        <button class="button primary" type="submit">Save profile</button>
      </form>
    </section>

    <section class="admin-panel">
      <div class="section-heading compact"><div><p class="eyebrow">SECURITY</p><h2>Change password</h2></div></div>
      <form method="post" class="auth-form" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
        <input type="hidden" name="action" value="password">
        <label>Current password<input type="password" name="current_password" autocomplete="current-password" required></label>
        <label>New password<input type="password" name="new_password" minlength="12" autocomplete="new-password" required></label>
        <label>Confirm new password<input type="password" name="confirm_password" minlength="12" autocomplete="new-password" required></label>
        <button class="button primary" type="submit">Change password</button>
      </form>
    </section>
  </div>
</main>
</body>
</html>
