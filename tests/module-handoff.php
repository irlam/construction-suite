<?php
declare(strict_types=1);
use Suite\Auth\ModuleHandoff;
use Suite\Modules\InstanceCatalog;
$path=tempnam(sys_get_temp_dir(),'suite-handoff-');
putenv('DB_DRIVER=sqlite'); putenv('DB_DATABASE='.$path);
require_once dirname(__DIR__).'/app/bootstrap.php';
try {
 $db=suite_db(); $db->exec(file_get_contents(SUITE_ROOT.'/database/schema.sqlite.sql'));
 $db->exec(file_get_contents(SUITE_ROOT.'/database/migrations/001_project_modules.sqlite.sql'));
 $db->exec(file_get_contents(SUITE_ROOT.'/database/migrations/002_module_handoffs.sqlite.sql'));
 $db->exec(file_get_contents(SUITE_ROOT.'/database/migrations/002_module_handoffs.sqlite.sql'));
 $db->exec("INSERT INTO organizations(id,name,slug) VALUES(1,'Alpha','alpha'),(2,'Beta','beta')");
 $db->exec("INSERT INTO projects(id,organization_id,name) VALUES(1,1,'Alpha One'),(2,2,'Beta One')");
 $db->exec("INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES(1,'owner@example.test','Owner','unused',1),(2,'worker@example.test','Worker','unused',0)");
 $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,1,1,'user')");
 $owner=$db->query('SELECT * FROM users WHERE id=1')->fetch(); $worker=$db->query('SELECT * FROM users WHERE id=2')->fetch();
 $check=static function(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL: '.$label);};
 $deny=static function(callable $f,string $label)use($check):void {try{$f();}catch(RuntimeException $e){return;}$check(false,$label);};
 $item=['id'=>1,'organization_id'=>1,'project_id'=>1,'module_key'=>'permits','origin'=>'https://alpha.sitepermits.site','isolation_verified'=>true,'gateway_verified'=>true];
 $other=$item; $other['id']=2;$other['organization_id']=2;$other['project_id']=2;$other['origin']='https://beta.sitepermits.site';
 $key=bin2hex(random_bytes(32));putenv('SUITE_INSTANCE_KEY_1='.$key);putenv('SUITE_INSTANCE_KEY_2='.$key);
 $handoff=new ModuleHandoff(new InstanceCatalog([$item,$other]));$state=bin2hex(random_bytes(32));
 $deny(fn()=>$handoff->issue($worker,2,$state,100),'Foreign project denied');
 foreach(['https://sitepermits.site','https://user@alpha.sitepermits.site','https://alpha.sitepermits.site/path','https://alpha..sitepermits.site','https://alpha.sitepermits.site.evil.test'] as $url){$bad=$item;$bad['origin']=$url;$deny(fn()=>(new InstanceCatalog([$bad]))->all(),'Invalid origin denied');}
 $duplicate=$other;$duplicate['origin']=$item['origin'];$deny(fn()=>(new InstanceCatalog([$item,$duplicate]))->all(),'Shared origin denied');
 $unready=$item;$unready['gateway_verified']=false;$deny(fn()=>(new ModuleHandoff(new InstanceCatalog([$unready])))->issue($worker,1,$state,100),'Unverified gateway denied');
 $code=$handoff->issue($worker,1,$state,100);
 $deny(fn()=>$handoff->redeem(1,$code,$state,'wrong',101),'Wrong server key denied');
 $deny(fn()=>$handoff->redeem(1,$code,str_repeat('0',64),$key,101),'Wrong browser state denied');
 $deny(fn()=>$handoff->redeem(2,$code,$state,$key,101),'Wrong audience denied');
 $identity=$handoff->redeem(1,$code,$state,$key,101);$check($identity['project_id']===1&&$identity['user_id']===2,'Scoped identity');
 $deny(fn()=>$handoff->redeem(1,$code,$state,$key,101),'Replay denied');
 $code=$handoff->issue($worker,1,$state,100);$deny(fn()=>$handoff->redeem(1,$code,$state,$key,161),'Expired grant denied');
 $code=$handoff->issue($owner,1,$state,100);$rebound=$item;$rebound['origin']='https://replacement.sitepermits.site';
 $deny(fn()=>(new ModuleHandoff(new InstanceCatalog([$rebound])))->redeem(1,$code,$state,$key,101),'Inventory rebind denied');
 $code=$handoff->issue($worker,1,$state,100);$db->exec('DELETE FROM memberships WHERE user_id=2');
 $deny(fn()=>$handoff->redeem(1,$code,$state,$key,101),'Revoked membership denied');
 $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,1,1,'user')");
 $code=$handoff->issue($worker,1,$state,100);$db->exec('UPDATE users SET active=0 WHERE id=2');
 $deny(fn()=>$handoff->redeem(1,$code,$state,$key,101),'Inactive account denied');
 $db->exec('UPDATE users SET active=1 WHERE id=2');$code=$handoff->issue($worker,1,$state,100);
 $db->exec("INSERT INTO project_modules(project_id,module_key,enabled) VALUES(1,'permits',0)");
 $deny(fn()=>$handoff->redeem(1,$code,$state,$key,101),'Disabled tool denied');
 $check($db->query("SELECT code_hash FROM module_handoffs WHERE code_hash='".hash('sha256',$code)."' AND used_at IS NULL")->fetchColumn()!==false,'Denied redemption does not consume grant');
 echo "PASS: Origin isolation, short-lived single-use handoffs, browser state, audience binding and current access checks.\n";
} finally {@unlink($path);}
