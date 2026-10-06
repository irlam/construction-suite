<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$auth=suite_auth();$currentUser=$auth->user();$invite=null;$error=null;$token='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    if (!$auth->verifyCsrf(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:null)) {http_response_code(419);$error='Your session expired. Refresh and try again.';}
    else {
        try {
            $token=is_string($_POST['token']??null)?trim($_POST['token']):'';
            if (($_POST['action']??'preview')==='accept') {
                $id=suite_invitations()->accept($token,$currentUser,(string)($_POST['name']??''),(string)($_POST['password']??''));
                session_regenerate_id(true);
                $_SESSION['suite_user_id']=$id;$_SESSION['suite_signed_in_at']=time();
                unset($_SESSION['suite_csrf'],$_SESSION['suite_project_id']);
                header('Location: /');exit;
            }
            $invite=suite_invitations()->preview($token);
        } catch (RuntimeException $e) {http_response_code(400);$error=$e->getMessage();}
        catch (Throwable $e) {http_response_code(400);$error='The invitation could not be accepted. Try again.';}
    }
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Join a company · Construction Suite</title><link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/app.css"><script src="/assets/js/invite.js" defer></script></head>
<body class="auth-page"><main class="auth-shell"><section class="auth-brand"><img src="/assets/brand/logo.svg" class="auth-logo" alt="Construction Suite"><h1>Join your project team.</h1><p>Your invitation gives you access to the company and project selected by its administrator.</p></section><section class="auth-card"><h2><?= $invite?'Accept invitation':'Open your invitation' ?></h2>
<?php if ($error): ?><div class="notice danger"><?= suite_e($error) ?></div><?php endif; ?>
<?php if ($invite): ?>
<p><strong><?= suite_e((string)$invite['company_name']) ?></strong><br><?= suite_e((string)($invite['project_name']??'All company projects')) ?> · <?= suite_e(str_replace('_',' ',(string)$invite['role_key'])) ?></p>
<p>Invitation for <?= suite_e((string)$invite['email']) ?>.</p>
<form method="post" class="auth-form"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="action" value="accept"><input type="hidden" name="token" value="<?= suite_e($token) ?>">
<?php if (!$currentUser): ?><label>Your name<input name="name" maxlength="160" autocomplete="name"></label><label>Password<input name="password" type="password" autocomplete="current-password" required></label><p class="muted">Already have a Suite account? Use its password. For a new account, choose a password of 12–72 characters.</p><?php else: ?><p>Signed in as <?= suite_e((string)$currentUser['email']) ?>. This must match the invitation.</p><?php endif; ?>
<button class="button primary wide">Accept and open Suite</button></form>
<?php else: ?>
<p>Open the link your company administrator gave you, or paste its invitation code below.</p><form method="post" class="auth-form" id="invitation-preview"><input type="hidden" name="csrf_token" value="<?= suite_e($auth->csrfToken()) ?>"><input type="hidden" name="action" value="preview"><label>Invitation code<input name="token" id="invitation-token" maxlength="64" autocomplete="off" required></label><button class="button primary wide">Open invitation</button></form>
<?php endif; ?><p><a href="/login.php">Suite sign in</a></p></section></main></body></html>
