<?php
declare(strict_types=1);
$mysql=getenv('SUITE_TEST_MYSQL')==='1';$path=$mysql?null:tempnam(sys_get_temp_dir(),'suite-invites-');
if (!$mysql) {putenv('DB_DRIVER=sqlite');putenv('DB_DATABASE='.$path);}
elseif (getenv('DB_DRIVER')!=='mysql' || !str_starts_with((string)getenv('DB_DATABASE'),'suite_test_')) throw new RuntimeException('Disposable suite_test_ database required.');
require_once dirname(__DIR__).'/app/bootstrap.php';
try {
 $db=suite_db();
 if ($mysql && (int)$db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()!==0) throw new RuntimeException('Empty fixture database required.');
 $db->exec(file_get_contents(SUITE_ROOT.'/database/schema.'.($mysql?'mysql':'sqlite').'.sql'));suite_migrator()->migrateAll();
 $db->exec("INSERT INTO organizations(id,name,slug) VALUES(1,'Alpha','alpha'),(2,'Beta','beta')");
 $db->exec("INSERT INTO projects(id,organization_id,name) VALUES(1,1,'Alpha One'),(2,1,'Alpha Two'),(3,2,'Beta One')");
 $db->exec("INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES(1,'owner@example.test','Owner','unused',1),(2,'alpha@example.test','Alpha Admin','unused',0),(3,'beta@example.test','Beta Admin','unused',0),(4,'worker@example.test','Worker','unused',0)");
 $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,1,NULL,'company_admin'),(3,2,NULL,'company_admin'),(4,1,1,'user')");
 $user=fn(int $id):array=>$db->query('SELECT * FROM users WHERE id='.$id)->fetch();
 $check=static function(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL: '.$label);};
 $deny=static function(callable $f,string $label)use($check):void {try{$f();}catch(RuntimeException $e){return;}$check(false,$label);};
 $repo=suite_invitations();$alpha=$user(2);$beta=$user(3);$worker=$user(4);$owner=$user(1);$password=bin2hex(random_bytes(12));$now=time();
 $deny(fn()=>$repo->create($alpha,2,3,'new@example.test','user',$now),'Cannot invite into other company');
 $deny(fn()=>$repo->create($alpha,1,3,'new@example.test','user',$now),'Foreign project denied');
 $deny(fn()=>$repo->create($worker,1,1,'new@example.test','user',$now),'Worker cannot invite');
 $deny(fn()=>$repo->create($alpha,1,null,'new@example.test','company_admin',$now),'Company admin escalation denied');
 $deny(fn()=>$repo->create($alpha,1,1,'new@example.test','company_admin',$now),'Invalid scope role denied');
 $token=$repo->create($alpha,1,1,'New@Example.Test','site_manager',$now);
 $check(strlen($token)===64,'Strong random invitation token');
 $check($db->query('SELECT token_hash FROM company_invitations')->fetchColumn()===hash('sha256',$token),'Token stored only as hash');
 $check(!array_key_exists('token_hash',$repo->pending($alpha,1)[0]),'Pending list has no token hashes');
 $check(!array_key_exists('token_hash',$repo->preview($token)),'Preview has no token hash');
 $check(!$db->query("SELECT id FROM users WHERE email='new@example.test'")->fetchColumn(),'Creating invite does not create an account');
 $deny(fn()=>$repo->preview($token,$now+604800),'Exact expiry rejected');
 $deny(fn()=>$repo->accept($token,$beta,'New',$password,$now),'Wrong logged-in account denied');
 $id=$repo->accept($token,null,'New Colleague',$password,$now+1);$joined=$user($id);
 $check(password_verify($password,$joined['password_hash']),'Recipient chooses hashed password');
 $check(array_column(suite_projects()->forUser($joined),'id')==[1],'Invite grants exactly the selected project');
 $check(suite_projects()->roleFor($joined,['id'=>1,'organization_id'=>1])==='site_manager','Stored role granted');
 $deny(fn()=>$repo->accept($token,$joined,'','',$now+2),'Invitation cannot replay');
 $check($repo->pending($alpha,1)===[],'Accepted invite removed from pending');
 $token=$repo->create($alpha,1,2,'new@example.test','manager',$now);
 $check(count(suite_projects()->forUser($joined))===1,'Existing account gets no automatic access');
 $check($repo->accept($token,$joined,'','',$now+1)===$id,'Existing recipient explicitly accepts');
 $check(count(suite_projects()->forUser($joined))===2,'Existing memberships preserved');
 $token=$repo->create($alpha,1,1,'revoked@example.test','user',$now);$inviteId=(int)$repo->pending($alpha,1)[0]['id'];
 $deny(fn()=>$repo->revoke($beta,2,$inviteId),'Foreign invitation revoke denied');
 $repo->revoke($alpha,1,$inviteId);$deny(fn()=>$repo->preview($token),'Revoked invitation denied');
 $token=$repo->create($alpha,1,1,'inactive@example.test','user',$now);
 $db->exec('UPDATE projects SET active=0 WHERE id=1');$deny(fn()=>$repo->accept($token,null,'New',$password,$now+1),'Inactive project denied');$db->exec('UPDATE projects SET active=1 WHERE id=1');
 $db->exec("DELETE FROM memberships WHERE user_id=2 AND project_id IS NULL");$deny(fn()=>$repo->preview($token),'Inviter authority revoked');
 $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,1,NULL,'company_admin')");
 $token=$repo->create($owner,2,null,'firstadmin@example.test','company_admin',$now);$admin=$repo->accept($token,null,'First Admin',$password,$now+1);
 $check(count(suite_companies()->managedBy($user($admin)))===1,'Platform owner can invite company admin');
 $token=$repo->create($alpha,1,1,'disabled@example.test','user',$now);
 $db->exec('UPDATE organizations SET active=0 WHERE id=1');$deny(fn()=>$repo->preview($token),'Inactive company denied');
 if ($mysql) {
  for($race=0;$race<3;$race++) {
   $email='race-'.$race.'@example.test';$token=$repo->create($owner,2,3,$email,'user');$workers=[];
   try {
    for($i=0;$i<2;$i++) {
     $process=proc_open([PHP_BINARY,__DIR__.'/invitation-mysql-worker.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
     if(!is_resource($process))throw new RuntimeException('Worker failed to start.');
     $workers[]=[$process,$pipes];fwrite($pipes[0],json_encode(['token'=>$token,'password'=>$password])."\n");
    }
    foreach($workers as [$process,$pipes]) {$read=[$pipes[1]];$write=null;$except=null;$check(stream_select($read,$write,$except,10)>0&&trim((string)fgets($pipes[1]))==='READY','Invite workers ready');}
    foreach($workers as [$process,$pipes]) {fwrite($pipes[0],"GO\n");fclose($pipes[0]);}
    $accepted=0;
    foreach($workers as [$process,$pipes]) {
     $read=[$pipes[1]];$write=null;$except=null;$check(stream_select($read,$write,$except,10)>0,'Invite worker result');
     $result=json_decode((string)fgets($pipes[1]),true,8,JSON_THROW_ON_ERROR);if(!empty($result['accepted']))$accepted++;
     $stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$check(proc_close($process)===0&&$stderr==='','Invite worker clean exit');
    }
    $workers=[];$check($accepted===1,'Exactly one concurrent invitation acceptance');
    $find=$db->prepare('SELECT COUNT(*) FROM users WHERE email=?');$find->execute([$email]);$check((int)$find->fetchColumn()===1,'Exactly one recipient account');
   } finally {foreach($workers as [$process,$pipes])if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}}
  }
 }
} catch (RuntimeException $e) {
 // A real failure is never converted into a passing test.
 throw $e;
} finally {if($path!==null)@unlink($path);}
echo "PASS: Invitation ownership, single-use expiry, account proof, scoped acceptance and revocation.\n";
