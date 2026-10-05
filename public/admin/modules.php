<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use Suite\Database\Connection;
use Suite\Support\Audit;

$auth = suite_auth();
$user = $auth->requireUser();
if (!$auth->isPlatformAdmin($user)) {
    http_response_code(403);
    exit('You do not have permission to configure modules.');
}

$pdo = suite_db();
$projects = $pdo->query(
    'SELECT p.id, p.name, p.organization_id, o.name AS organization_name
     FROM projects p JOIN organizations o ON o.id = p.organization_id
     ORDER BY o.name, p.name'
)->fetchAll();

$projectId = (int) ($_GET['project_id'] ?? $_POST['project_id'] ?? ($projects[0]['id'] ?? 0));
$project = null;
foreach ($projects as $candidate) {
    if ((int) $candidate['id'] === $projectId) {
        $project = $candidate;
        break;
    }
}

$orgProjectCount = 0;
if ($project) {
    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM projects WHERE organization_id = ? AND active = 1'
    );
    $countStmt->execute([(int) $project['organization_id']]);
    $orgProjectCount = (int) $countStmt->fetchColumn();
}
$ready = Connection::tableExists('project_modules');
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready && $project) {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Refresh and try again.';
    } elseif (($_POST['action'] ?? '') === 'save') {
        $enabled = (array) ($_POST['enabled'] ?? []);
        $externalRefs = (array) ($_POST['external_ref'] ?? []);

        try {
            $pdo->beginTransaction();
            foreach (suite_modules()->definitions() as $module) {
                $key = (string) $module['key'];
                $isEnabled = isset($enabled[$key]) ? 1 : 0;
                $externalRef = trim((string) ($externalRefs[$key] ?? ''));
                $externalRef = $externalRef !== '' ? $externalRef : null;

                $find = $pdo->prepare(
                    'SELECT id FROM project_modules WHERE project_id = ? AND module_key = ? LIMIT 1'
                );
                $find->execute([$projectId, $key]);
                $id = (int) ($find->fetchColumn() ?: 0);

                if ($id > 0) {
                    $stmt = $pdo->prepare(
                        'UPDATE project_modules
                         SET enabled = ?, external_project_ref = ?, updated_at = CURRENT_TIMESTAMP
                         WHERE id = ?'
                    );
                    $stmt->execute([$isEnabled, $externalRef, $id]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO project_modules
                         (project_id, module_key, enabled, external_project_ref)
                         VALUES (?, ?, ?, ?)'
                    );
                    $stmt->execute([$projectId, $key, $isEnabled, $externalRef]);
                }
            }
            $pdo->commit();

            Audit::record(
                'project.modules_updated',
                (int) $user['id'],
                (int) $project['organization_id'],
                $projectId
            );
            $message = 'Project modules updated.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Module settings could not be saved.';
        }
    }
}

$settings = [];
if ($ready && $project) {
    $stmt = $pdo->prepare(
        'SELECT module_key, enabled, external_project_ref FROM project_modules WHERE project_id = ?'
    );
    $stmt->execute([$projectId]);
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['module_key']] = $row;
    }
}
$definitions = suite_modules()->definitions();
$references = suite_reference_client()->fetch($definitions);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827"><title>Project modules · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"></head>
<body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/admin/">Admin home</a></header>
<main class="app-shell admin-shell">
  <section class="section-heading"><div><p class="eyebrow">PROJECT CONFIGURATION</p><h1>Modules</h1></div><span><?= count($definitions) ?> available products</span></section>

  <?php if (!$ready): ?>
    <div class="notice warning"><strong>A database update is required.</strong><br>Open System and apply the pending database migration before configuring project modules.</div>
    <a class="button primary" href="/admin/system.php">Open System</a>
  <?php else: ?>
    <?php if ($message): ?><div class="notice success"><?= suite_e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>

    <section class="admin-panel">
      <form method="get" class="project-switcher module-project-switcher">
        <label for="project_id">Configure project</label>
        <select id="project_id" name="project_id" onchange="this.form.submit()">
          <?php foreach ($projects as $item): ?>
            <option value="<?= (int) $item['id'] ?>" <?= $projectId === (int) $item['id'] ? 'selected' : '' ?>><?= suite_e((string) $item['organization_name']) ?> · <?= suite_e((string) $item['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </section>

    <?php if ($project): ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="project_id" value="<?= $projectId ?>">

      <?php if ($orgProjectCount > 1): ?>
        <div class="notice warning">
          <strong>Multi-project safety is enabled.</strong>
          Each live module needs an actual external project/site mapping.
          All-data summaries are blocked. Deliveries is a single shared calendar
          and needs a project-aware source before its figures can be shown here.
        </div>
      <?php endif; ?>
      <section class="module-config-grid">
        <?php foreach ($definitions as $module):
          $key = (string) $module['key'];
          $setting = $settings[$key] ?? null;
          $enabledNow = $setting ? (bool) $setting['enabled'] : (bool) ($module['enabled'] ?? true);
          $externalRef = (string) ($setting['external_project_ref'] ?? '');
          $referenceState = $references[$key] ?? null;
          $referenceItems = is_array($referenceState['items'] ?? null) ? $referenceState['items'] : [];
          $referenceConnected = ($referenceState['status'] ?? '') === 'connected';
          $knownValues = array_map(static fn(array $item): string => (string) ($item['value'] ?? ''), $referenceItems);
          $allowAll = !empty($module['summary_allow_all']);
          $externalRefKnown = $externalRef === ''
              || ($allowAll && $externalRef === '__all__')
              || in_array($externalRef, $knownValues, true);
        ?>
          <article class="module-config-card">
            <div class="module-config-head">
              <span class="module-icon"><svg><use href="/assets/icons.svg#<?= suite_e((string) $module['icon']) ?>"></use></svg></span>
              <label class="switch-label"><input type="checkbox" name="enabled[<?= suite_e($key) ?>]" value="1" <?= $enabledNow ? 'checked' : '' ?>><span>Enabled</span></label>
            </div>
            <strong><?= suite_e((string) $module['name']) ?></strong>
            <p><?= suite_e((string) $module['description']) ?></p>
            <label class="module-ref-label">External project reference
              <?php if ($referenceConnected): ?>
                <select name="external_ref[<?= suite_e($key) ?>]">
                  <?php if (!empty($module['summary_allow_all']) && $orgProjectCount <= 1): ?>
                    <?php if ($orgProjectCount <= 1): ?>
                  <option value="__all__" <?= $externalRef === '' || $externalRef === '__all__' ? 'selected' : '' ?>>All data in this module</option>
                  <?php else: ?>
                  <option value="" selected>Mapping required before multi-site reporting</option>
                  <?php endif; ?>
                  <?php else: ?>
                    <option value=""><?= $referenceItems ? 'Choose the matching project/site' : 'No project/site values found yet' ?></option>
                  <?php endif; ?>
                  <?php foreach ($referenceItems as $item): ?>
                    <option value="<?= suite_e((string) $item['value']) ?>" <?= $externalRefKnown && $externalRef === (string) $item['value'] ? 'selected' : '' ?>>
                      <?= suite_e((string) $item['label']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <?php if ($externalRef !== '' && !$externalRefKnown): ?>
                  <span class="mapping-warning">The current value “<?= suite_e($externalRef) ?>” is not a real value from this module. Choose the correct one above.</span>
                <?php elseif (!$referenceItems): ?>
                  <span class="mapping-note">No project/site values exist yet, so the Suite will use all data in this module.</span>
                <?php else: ?>
                  <span class="mapping-note">Loaded directly from <?= suite_e((string) $module['name']) ?>.</span>
                <?php endif; ?>
              <?php elseif ($allowAll): ?>
                <select name="external_ref[<?= suite_e($key) ?>]">
                  <option value="__all__" <?= $externalRef === '' || $externalRef === '__all__' ? 'selected' : '' ?>>All data in this module</option>
                </select>
                <?php if ($referenceState): ?>
                  <span class="mapping-note">Project lookup is <?= suite_e(str_replace('_', ' ', (string) ($referenceState['status'] ?? 'unavailable'))) ?>, so the Suite can use the whole module for now.</span>
                <?php else: ?>
                  <span class="mapping-note">This module has no verified site list. Multi-site reporting is blocked until it supports a real mapping.</span>
                <?php endif; ?>
              <?php else: ?>
                <input type="text" name="external_ref[<?= suite_e($key) ?>]" value="<?= suite_e($externalRef) ?>" placeholder="Optional — mapping lookup is not connected yet">
                <?php if ($referenceState): ?>
                  <span class="mapping-warning">Automatic lookup is <?= suite_e(str_replace('_', ' ', (string) ($referenceState['status'] ?? 'unavailable'))) ?>.</span>
                <?php endif; ?>
              <?php endif; ?>
            </label>
            <small><?= suite_e((string) $module['url']) ?></small>
          </article>
        <?php endforeach; ?>
      </section>

      <div class="sticky-save"><div><strong><?= suite_e((string) $project['name']) ?></strong><span>Changes affect which tools this project can launch.</span></div><button class="button primary" type="submit">Save module configuration</button></div>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</main>
</body></html>
