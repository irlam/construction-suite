<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$auth = suite_auth();
if ($auth->user()) {
    header('Location: /');
    exit;
}

$error = null;
$setupRequired = false;

try {
    $setupRequired = !\Suite\Database\Connection::tableExists('users')
        || (int) suite_db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
} catch (Throwable $e) {
    $error = 'Construction Suite is not connected to its database yet.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupRequired && $error === null) {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your sign-in session expired. Refresh the page and try again.';
    } else {
        $result = $auth->attempt(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        );

        if ($result['ok']) {
            $next = (string) ($_POST['next'] ?? '/');
            if ($next === '' || str_starts_with($next, '//') || !str_starts_with($next, '/')) {
                $next = '/';
            }
            header('Location: ' . $next);
            exit;
        }
        $error = (string) $result['message'];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Sign in · Construction Suite</title>
  <link rel="manifest" href="/manifest.webmanifest">
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-brand">
      <img src="/assets/brand/logo.svg" class="auth-logo" alt="">
      <p class="eyebrow">CONSTRUCTION SUITE</p>
      <h1>One place for the work that keeps a site moving.</h1>
      <p>Defects, deliveries, safety, permits, documents, handover and programme — built around the project you are working on.</p>
      <div class="auth-points">
        <span>Mobile first</span><span>Offline capable</span><span>Project controlled</span>
      </div>
    </section>
    <section class="auth-card">
      <p class="eyebrow">WELCOME BACK</p>
      <h2>Sign in</h2>
      <p class="muted">Use your Construction Suite account. Existing modules may still ask for their own login during the transition.</p>
      <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>
      <?php if ($setupRequired): ?>
        <div class="notice warning">The Suite has not been initialised yet.</div>
        <a class="button primary wide" href="/setup.php">Run first-time setup</a>
      <?php else: ?>
        <form method="post" class="auth-form">
          <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
          <input type="hidden" name="next" value="<?= suite_e((string) ($_GET['next'] ?? '/')) ?>">
          <label>Email<input type="email" name="email" autocomplete="email" required autofocus></label>
          <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
          <button class="button primary wide" type="submit">Sign in securely</button>
        </form>
      <?php endif; ?>
      <div class="connection-line"><span class="status-dot" data-connection-dot></span><span data-connection-text>Checking connection…</span></div>
    </section>
  </main>
  <script src="/assets/js/app.js" defer></script>
</body>
</html>
