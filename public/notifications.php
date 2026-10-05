<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$auth = suite_auth();
$user = $auth->requireUser();
$project = suite_projects()->currentForUser($user);
$projectId = $project ? (int)$project['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('Session expired.');
    }
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        \Suite\Support\Notifications::acknowledge(
            $id, (int)$user['id'], $projectId
        );
    }
    header('Location: /notifications.php', true, 303);
    exit;
}

$items = \Suite\Support\Notifications::recent((int)$user['id'], $projectId);
$unread = \Suite\Support\Notifications::count((int)$user['id'], $projectId);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Notifications · Construction Suite</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="app-page">
<header class="topbar">
<a class="brand" href="/">Construction Suite</a>
<a class="button small secondary" href="/">Back to hub</a>
</header>
<main class="app-shell admin-shell">
<section class="section-heading">
<div><p class="eyebrow">YOUR PROJECT</p><h1>Notifications</h1></div>
<span><?= $unread ?> unread</span>
</section>
<section class="admin-panel">
<?php if (!$items): ?>
<div class="empty-panel"><strong>Nothing waiting</strong>
<span>Only your own notifications for this project appear here.</span></div>
<?php else: ?>
<div class="admin-table">
<?php foreach ($items as $item): ?>
<article class="admin-row">
<div>
<strong><?= suite_e((string)$item['title']) ?></strong>
<span><?= suite_e((string)($item['body'] ?? '')) ?></span>
<small><?= suite_e((string)$item['created_at']) ?></small>
</div>
<?php if (empty($item['read_at'])): ?>
<form method="post">
<input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
<input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
<button type="submit" class="button small secondary">Mark read</button>
</form>
<?php else: ?>
<span class="role-pill">Read</span>
<?php endif; ?>
</article>
<?php endforeach; ?>
</div>
<?php endif; ?>
</section>
</main>
</body>
</html>
