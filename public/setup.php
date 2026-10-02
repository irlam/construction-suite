<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use Suite\Database\Connection;
use Suite\Support\Env;

$error = null;
$success = false;
$configuredKey = (string) Env::get('SUITE_SETUP_KEY', '');

try {
    $pdo = Connection::pdo();
    $alreadyInstalled = Connection::tableExists('users')
        && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0;
} catch (Throwable $e) {
    $alreadyInstalled = false;
    $error = 'Database connection failed. Check the values in your private .env file.';
}

if ($alreadyInstalled) {
    http_response_code(404);
    exit('Setup is locked because Construction Suite already has an administrator.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
    $key = (string) ($_POST['setup_key'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $organization = trim((string) ($_POST['organization'] ?? ''));
    $project = trim((string) ($_POST['project'] ?? ''));

    if ($configuredKey === '' || !hash_equals($configuredKey, $key)) {
        $error = 'The setup key is not correct.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter your name and a valid administrator email address.';
    } elseif (strlen($password) < 12) {
        $error = 'Use an administrator password of at least 12 characters.';
    } elseif ($organization === '' || $project === '') {
        $error = 'Enter the first organisation and project/site names.';
    } else {
        try {
            $driver = Connection::driver();
            $schema = SUITE_ROOT . '/database/schema.' . ($driver === 'sqlite' ? 'sqlite' : 'mysql') . '.sql';
            $sql = (string) file_get_contents($schema);
            $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    $pdo->exec($statement);
                }
            }

            $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $organization), '-'));
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO organizations (name, slug, active) VALUES (?, ?, 1)');
            $stmt->execute([$organization, $slug ?: 'first-organisation']);
            $organizationId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO projects (organization_id, name, code, location, active) VALUES (?, ?, NULL, NULL, 1)'
            );
            $stmt->execute([$organizationId, $project]);
            $projectId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO users (email, name, password_hash, active, is_platform_admin)
                 VALUES (?, ?, ?, 1, 1)'
            );
            $stmt->execute([$email, $name, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO memberships (user_id, organization_id, project_id, role_key)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $organizationId, $projectId, 'admin']);

            $pdo->commit();
            $success = true;
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Setup could not be completed. Check the database configuration and server error log.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#111827">
  <title>Set up Construction Suite</title>
  <link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-page">
  <main class="auth-shell">
    <section class="auth-brand">
      <img src="/assets/brand/logo.svg" class="auth-logo" alt="">
      <p class="eyebrow">FIRST RUN</p>
      <h1>Build the hub around your first site.</h1>
      <p>Create the first organisation, project and platform administrator. This installer locks itself after the first account exists.</p>
    </section>
    <section class="auth-card">
      <?php if ($success): ?>
        <div class="notice success"><strong>Construction Suite is ready.</strong><br>Remove <code>SUITE_SETUP_KEY</code> from your private environment after you sign in.</div>
        <a class="button primary wide" href="/login.php">Continue to sign in</a>
      <?php else: ?>
        <h2>Initial setup</h2>
        <p class="muted">Your setup key comes from the private <code>.env</code> file and is never stored in the database.</p>
        <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>
        <form method="post" class="auth-form" autocomplete="off">
          <label>Setup key<input type="password" name="setup_key" required></label>
          <label>Your name<input type="text" name="name" value="<?= suite_e((string) ($_POST['name'] ?? '')) ?>" required></label>
          <label>Admin email<input type="email" name="email" value="<?= suite_e((string) ($_POST['email'] ?? '')) ?>" required></label>
          <label>Admin password<input type="password" name="password" minlength="12" required></label>
          <label>Organisation<input type="text" name="organization" value="<?= suite_e((string) ($_POST['organization'] ?? '')) ?>" placeholder="Example Construction Ltd" required></label>
          <label>First project / site<input type="text" name="project" value="<?= suite_e((string) ($_POST['project'] ?? '')) ?>" placeholder="Manchester Project" required></label>
          <button class="button primary wide" type="submit">Create Construction Suite</button>
        </form>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
