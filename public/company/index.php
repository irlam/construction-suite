<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
$auth = suite_auth();
$user = $auth->requireUser();
$repo = suite_companies();
$companies = $repo->managedBy($user);
if (!$companies) { http_response_code(403); exit('Company administrator access is required.'); }
$companyId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? $companies[0]['id']);
try { $company = $repo->requireCompany($user, $companyId); }
catch (RuntimeException $e) { http_response_code(403); exit('Company access denied.'); }
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        http_response_code(419); $error = 'Your session expired. Refresh and try again.';
    } else {
        try {
            if (($_POST['action'] ?? '') === 'save_project') {
                $repo->saveProject($user, $companyId, (int) ($_POST['project_id'] ?? 0), $_POST);
            } elseif (($_POST['action'] ?? '') === 'save_membership') {
                $projectId = (int) ($_POST['scope_project_id'] ?? 0);
                $repo->saveMembership($user, $companyId, (int) ($_POST['user_id'] ?? 0), $projectId ?: null, (string) ($_POST['role_key'] ?? ''));
            } elseif (($_POST['action'] ?? '') === 'create_user') {
                $repo->createUser($user, $companyId, $_POST);
            } elseif (($_POST['action'] ?? '') === 'remove_membership') {
                $repo->removeMembership($user, $companyId, (int) ($_POST['membership_id'] ?? 0));
            } else { throw new RuntimeException('Unknown action.'); }
            header('Location: /company/?company_id=' . $companyId . '&saved=1'); exit;
        } catch (RuntimeException $e) { $error = $e->getMessage(); }
        catch (Throwable $e) { $error = 'Changes could not be saved. Try again.'; }
    }
}
$projects = $repo->projects($user, $companyId);
$memberships = $repo->members($user, $companyId);
$members = [];
foreach ($memberships as $membership) $members[(int) $membership['user_id']] = $membership;
if ($auth->isPlatformAdmin($user)) {
    $members = suite_db()->query('SELECT id AS user_id, name, email, active FROM users WHERE is_platform_admin = 0 ORDER BY name')->fetchAll();
}
$edit = null;
$editId = (int) ($_GET['project_id'] ?? 0);
foreach ($projects as $item) if ((int) $item['id'] === $editId) $edit = $item;
if ($editId && !$edit) { http_response_code(403); exit('Project access denied.'); }
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#111827">
<title><?= suite_e((string) $company['name']) ?> · Company dashboard</title>
<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css">
</head><body class="app-page">
<header class="topbar"><a class="brand" href="/"><img src="/assets/brand/logo.svg" alt=""><span><strong>Construction</strong><small>Suite</small></span></a><a class="button small secondary" href="/">Back to hub</a></header>
<main class="app-shell admin-shell">
<section class="section-heading"><div><p class="eyebrow"><?= $auth->isPlatformAdmin($user) ? 'PLATFORM OWNER · COMPANY MANAGEMENT' : 'COMPANY ADMINISTRATION' ?></p><h1><?= suite_e((string) $company['name']) ?></h1><p class="muted">Manage your projects and the people working on them.</p></div></section>
<?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>
<?php if (isset($_GET['saved']) && !$error): ?><div class="notice success">Changes saved.</div><?php endif; ?>
<?php if (count($companies) > 1): ?><section class="admin-panel"><form method="get" class="auth-form"><label>Company<select name="company_id" onchange="this.form.submit()"><?php foreach ($companies as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $companyId ? 'selected' : '' ?>><?= suite_e((string) $item['name']) ?></option><?php endforeach; ?></select></label><button class="button small secondary">Open company</button></form></section><?php endif; ?>
<section class="admin-metrics"><article><strong><?= count($projects) ?></strong><span>Projects</span></article><article><strong><?= count(array_unique(array_column($memberships, 'user_id'))) ?></strong><span>Company users</span></article><article><strong><?= (bool) $company['active'] ? 'Active' : 'Inactive' ?></strong><span>Company status</span></article></section>
<?php if (!$company['active']): ?><div class="notice warning">This company is inactive. The platform owner can reactivate it in Organisations & projects.</div><?php endif; ?>
<section class="admin-panel"><div class="section-heading compact"><h2>Projects</h2><a class="button small secondary" href="/company/?company_id=<?= $companyId ?>#project-form">Add project</a></div><div class="admin-table">
<?php if (!$projects): ?><p class="muted">Add your first project below.</p><?php endif; ?>
<?php foreach ($projects as $item): ?><div class="admin-row admin-row-actions"><span class="module-icon tiny"><svg><use href="/assets/icons.svg#site"></use></svg></span><div><strong><?= suite_e((string) $item['name']) ?></strong><span><?= suite_e((string) ($item['location'] ?? '')) ?></span></div><span class="role-pill"><?= $item['active'] ? 'Active' : 'Inactive' ?></span><a class="button small secondary" href="/company/?company_id=<?= $companyId ?>&project_id=<?= (int) $item['id'] ?>#project-form">Edit</a></div><?php endforeach; ?>
</div></section>
<section class="admin-panel" id="project-form"><h2><?= $edit ? 'Edit project' : 'Add a project' ?></h2><form method="post" class="admin-form-grid">
<input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><input type="hidden" name="project_id" value="<?= (int) ($edit['id'] ?? 0) ?>"><input type="hidden" name="action" value="save_project">
<label>Project name<input name="name" maxlength="180" required value="<?= suite_e((string) ($edit['name'] ?? '')) ?>"></label><label>Project code<input name="code" maxlength="80" value="<?= suite_e((string) ($edit['code'] ?? '')) ?>"></label><label>Location<input name="location" maxlength="190" value="<?= suite_e((string) ($edit['location'] ?? '')) ?>"></label><label class="check-label"><input type="checkbox" name="active" value="1" <?= !$edit || $edit['active'] ? 'checked' : '' ?>> Project active</label><div class="form-action"><button class="button primary">Save project</button><?php if ($edit): ?> <a class="button secondary" href="/company/?company_id=<?= $companyId ?>">Cancel edit</a><?php endif; ?></div></form></section>
<section class="admin-panel"><h2>People & access</h2><div class="admin-table">
<?php if (!$memberships): ?><p class="muted">No company users assigned yet. The platform owner can assign the first company administrator below.</p><?php endif; ?>
<?php foreach ($memberships as $item): ?><div class="admin-row admin-row-actions"><div class="avatar-small"><?= suite_e(strtoupper(substr((string) $item['name'], 0, 1))) ?></div><div><strong><?= suite_e((string) $item['name']) ?></strong><span><?= suite_e((string) $item['email']) ?> · <?= suite_e((string) ($item['project_name'] ?? 'All company projects')) ?></span></div><span class="role-pill"><?= suite_e(str_replace('_', ' ', (string) $item['role_key'])) ?></span><?php if ((int) $item['user_id'] !== (int) $user['id']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><input type="hidden" name="action" value="remove_membership"><input type="hidden" name="membership_id" value="<?= (int) $item['id'] ?>"><button class="button small secondary">Remove access</button></form><?php endif; ?></div><?php endforeach; ?>
</div><p class="muted">Company administrators manage all company projects. Project roles apply only to the chosen project. Add new accounts below. The platform owner can assign an existing account to another company.</p>
<?php if ($members): ?><form method="post" class="admin-form-grid"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><input type="hidden" name="action" value="save_membership">
<label>Person<select name="user_id" required><option value="">Choose person</option><?php foreach ($members as $item): if (!$item['active'] || (int) $item['user_id'] === (int) $user['id']) continue; ?><option value="<?= (int) $item['user_id'] ?>"><?= suite_e((string) $item['name']) ?> · <?= suite_e((string) $item['email']) ?></option><?php endforeach; ?></select></label>
<label>Scope<select name="scope_project_id"><option value="0">All company projects</option><?php foreach ($projects as $item): if (!$item['active']) continue; ?><option value="<?= (int) $item['id'] ?>"><?= suite_e((string) $item['name']) ?></option><?php endforeach; ?></select></label>
<label>Role<select name="role_key"><option value="user">User</option><?php if ($auth->isPlatformAdmin($user)): ?><option value="company_admin">Company administrator — company scope</option><?php endif; ?><option value="admin">Project administrator — project scope</option><option value="manager">Manager — project scope</option><option value="site_manager">Site manager — project scope</option><option value="contractor">Contractor — project scope</option></select></label><div class="form-action"><button class="button primary">Save access</button></div></form><?php endif; ?></section>
<section class="admin-panel"><h2>Add a person</h2><form method="post" class="admin-form-grid" autocomplete="off"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="company_id" value="<?= $companyId ?>"><input type="hidden" name="action" value="create_user"><label>Name<input name="name" maxlength="160" required></label><label>Email<input name="email" type="email" maxlength="190" required></label><label>Initial password<input name="password" type="password" minlength="12" autocomplete="new-password" required></label><label>Scope<select name="scope_project_id"><option value="0">All company projects</option><?php foreach ($projects as $item): if (!$item['active']) continue; ?><option value="<?= (int) $item['id'] ?>"><?= suite_e((string) $item['name']) ?></option><?php endforeach; ?></select></label><label>Role<select name="role_key"><option value="user">User</option><?php if ($auth->isPlatformAdmin($user)): ?><option value="company_admin">Company administrator — company scope</option><?php endif; ?><option value="admin">Project administrator — project scope</option><option value="manager">Manager — project scope</option><option value="site_manager">Site manager — project scope</option><option value="contractor">Contractor — project scope</option></select></label><div class="form-action"><button class="button primary">Create company user</button></div></form></section>
<section class="admin-panel"><h2>Connected tools</h2><p class="muted">The Suite controls its own company and project access. Connected tools currently have separate logins and permissions. They must be upgraded and tested before independent companies use them. Source mappings and tool settings are managed by the platform owner.</p><?php if ($auth->isPlatformAdmin($user)): ?><a class="button secondary" href="/admin/modules.php">Configure project tools</a> <a class="button secondary" href="/admin/users.php">Users & access</a><?php endif; ?></section>
</main></body></html>
