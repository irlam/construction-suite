<?php
declare(strict_types=1);
$path = tempnam(sys_get_temp_dir(), 'suite-companies-');
putenv('DB_DRIVER=sqlite'); putenv('DB_DATABASE=' . $path);
require_once dirname(__DIR__) . '/app/bootstrap.php';
try {
    $db = suite_db();
    $db->exec(file_get_contents(SUITE_ROOT . '/database/schema.sqlite.sql'));
    $db->exec("INSERT INTO organizations (id,name,slug) VALUES (1,'Alpha','alpha'),(2,'Beta','beta')");
    $db->exec("INSERT INTO projects (id,organization_id,name) VALUES (1,1,'Alpha One'),(2,1,'Alpha Two'),(3,2,'Beta One')");
    $db->exec("INSERT INTO users (id,email,name,password_hash,is_platform_admin) VALUES
      (1,'owner@example.test','Owner','unused',1),(2,'alpha@example.test','Alpha Admin','unused',0),
      (3,'project@example.test','Project Admin','unused',0),(4,'worker@example.test','Worker','unused',0),
      (5,'beta@example.test','Beta Admin','unused',0),(6,'inactive@example.test','Inactive','unused',0)");
    $db->exec("INSERT INTO memberships (user_id,organization_id,project_id,role_key) VALUES
      (2,1,NULL,'company_admin'),(2,1,1,'user'),(3,1,1,'admin'),(4,1,1,'user'),(5,2,NULL,'company_admin')");
    $user = static function(int $id) use ($db): array { return $db->query('SELECT * FROM users WHERE id = ' . $id)->fetch(); };
    $check = static function(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FAIL: ' . $label); };
    $deny = static function(callable $action, string $label) use ($check): void {
      try { $action(); } catch (RuntimeException $e) { return; }
      $check(false, $label);
    };
    $repo = suite_companies(); $owner=$user(1); $alpha=$user(2); $projectAdmin=$user(3); $worker=$user(4); $beta=$user(5);
    $check(count($repo->managedBy($owner)) === 2, 'Owner sees all companies');
    $check(array_column($repo->managedBy($alpha), 'name') === ['Alpha'], 'Company admin sees own company');
    $check($repo->managedBy($projectAdmin) === [], 'Project admin cannot manage company');
    $check($repo->managedBy($worker) === [], 'Worker cannot manage company');
    $deny(fn()=> $repo->projects($alpha,2), 'Cross-company read denied');
    $deny(fn()=> $repo->saveProject($alpha,2,3,['name'=>'Hijack']), 'Cross-company create denied');
    $deny(fn()=> $repo->saveProject($alpha,1,3,['name'=>'Hijack']), 'Forged project ID denied');
    $check($db->query('SELECT name FROM projects WHERE id=3')->fetchColumn() === 'Beta One', 'Other company unchanged');
    $newId=$repo->saveProject($alpha,1,0,['name'=>'Alpha Three','active'=>1]);
    $check(count($repo->projects($alpha,1))===3, 'Company admin creates own project');
    $repo->saveProject($alpha,1,$newId,['name'=>'Alpha Revised','active'=>1]);
    $check(count(suite_projects()->forUser($alpha))===3, 'Company admin sees newly created project');
    $check(suite_projects()->roleFor($alpha,['id'=>1,'organization_id'=>1])==='admin', 'Company authority outranks project role');
    $deny(fn()=> $repo->saveMembership($alpha,1,5,1,'user'), 'Other company user denied');
    $deny(fn()=> $repo->saveMembership($alpha,1,4,3,'user'), 'Foreign project membership denied');
    $deny(fn()=> $repo->saveMembership($alpha,1,4,1,'company_admin'), 'Company role rejected at project scope');
    $deny(fn()=> $repo->saveMembership($alpha,1,2,null,'user'), 'Self-demotion denied');
    $repo->saveMembership($alpha,1,4,$newId,'site_manager');
    $check(count(suite_projects()->forUser($worker))===2, 'Own-company project access assigned');
    $check(!suite_projects()->selectForUser($worker,3), 'Forged project selection denied');
    $repo->saveMembership($owner,1,3,null,'company_admin');
    $check(count($repo->managedBy($projectAdmin))===1, 'Owner assigns first company administrator');
    $check(suite_modules()->allForProject('platform_admin',null)===[], 'No project never inherits legacy integrations');
    $check(suite_modules()->allForProject('admin',1)===[], 'Unverified legacy tools blocked for tenant users');
    $check(count(suite_modules()->allForProject('platform_admin',1))===8, 'Owner retains working integrations');
    $deny(fn()=> $repo->saveMembership($alpha,1,4,null,'company_admin'), 'Company admin escalation denied');
    $password = bin2hex(random_bytes(12));
    $created = $repo->createUser($alpha,1,['name'=>'New Worker','email'=>'new@example.test','password'=>$password,'scope_project_id'=>1,'role_key'=>'user']);
    $newUser=$user($created);
    $check(password_verify($password,$newUser['password_hash']), 'Password securely hashed');
    $check(count(suite_projects()->forUser($newUser))===1, 'New project user has no all-company access');
    $deny(fn()=> $repo->createUser($alpha,1,['name'=>'Bad','email'=>'bad@example.test','password'=>$password,'scope_project_id'=>3,'role_key'=>'user']), 'Cross-company create rolled back');
    $check(!$db->query("SELECT id FROM users WHERE email='bad@example.test'")->fetchColumn(), 'Failed creation leaves no account');
    $membership = (int) $db->query('SELECT id FROM memberships WHERE user_id=' . $created)->fetchColumn();
    $deny(fn()=> $repo->removeMembership($beta,2,$membership), 'Cross-company revoke denied');
    $repo->removeMembership($alpha,1,$membership);
    $check(suite_projects()->forUser($newUser)===[], 'Revoked user loses project access');
    $db->exec('UPDATE organizations SET active=0 WHERE id=1');
    $check($repo->managedBy($alpha)===[], 'Inactive company revokes company management');
    $check(suite_projects()->forUser($alpha)===[], 'Inactive company revokes project visibility');
    $db->exec('UPDATE users SET active=0 WHERE id=5');
    $check($repo->managedBy($user(5))===[], 'Inactive user denied management');
    echo "PASS: Company/project boundaries, forgery rejection, company roles, inactive access and legacy integration isolation.\n";
} finally { @unlink($path); }
