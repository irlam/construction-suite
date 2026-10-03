<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to view audit activity.');
}

$stmt = suite_db()->query(
    'SELECT a.id, a.event_type, a.details_json, a.created_at,
            u.name AS user_name, o.name AS organization_name, p.name AS project_name
     FROM audit_events a
     LEFT JOIN users u ON u.id = a.user_id
     LEFT JOIN organizations o ON o.id = a.organization_id
     LEFT JOIN projects p ON p.id = a.project_id
     ORDER BY a.id DESC LIMIT 100'
);
$events = $stmt->fetchAll();
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>Audit activity · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/">Admin home</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">AUDIT TRAIL</p><h1>Recent platform activity</h1></div><span>Latest <?= count($events) ?></span></section>
  <section class="admin-panel">
    <div class="audit-list">
      <?php if (!$events): ?><div class="empty-panel"><strong>No audit events yet</strong><span>Administrative changes will appear here.</span></div><?php endif; ?>
      <?php foreach ($events as $event): $details = json_decode((string) ($event['details_json'] ?? ''), true) ?: []; ?>
        <article class="audit-event">
          <span class="audit-dot"></span>
          <div>
            <strong><?= suite_e(str_replace('.', ' · ', (string) $event['event_type'])) ?></strong>
            <span><?= suite_e((string) ($event['user_name'] ?: 'System')) ?><?= $event['project_name'] ? ' · ' . suite_e((string) $event['project_name']) : '' ?><?= $event['organization_name'] && !$event['project_name'] ? ' · ' . suite_e((string) $event['organization_name']) : '' ?></span>
            <?php if ($details): ?><small><?= suite_e(json_encode($details, JSON_UNESCAPED_SLASHES) ?: '') ?></small><?php endif; ?>
          </div>
          <time><?= suite_e((string) $event['created_at']) ?></time>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
</body></html>
