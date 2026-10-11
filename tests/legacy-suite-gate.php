<?php
declare(strict_types=1);
require dirname(__DIR__).'/integrations/legacy/SuiteGate.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE suite_deployment(id INTEGER PRIMARY KEY,instance_id INTEGER,organization_id INTEGER,project_id INTEGER,module_key TEXT);INSERT INTO suite_deployment VALUES(1,7,7,7,'safety');CREATE TABLE suite_users(suite_user_id INTEGER PRIMARY KEY,local_user_id TEXT,enabled INTEGER);CREATE TABLE safety_users(id INTEGER PRIMARY KEY,email TEXT UNIQUE,name TEXT,role TEXT,pass_hash TEXT);");
$b=['instance_id'=>7,'organization_id'=>7,'project_id'=>7,'module_key'=>'safety'];
$i=$b+['user_id'=>8,'session_expires_at'=>time()+100,'role'=>'manager','name'=>'Test manager'];$checks=0;
function check(bool $ok):void{global $checks;if(!$ok)throw new RuntimeException('Assertion failed');$checks++;}
function deny(callable $call,int $code):void{try{$call();}catch(RuntimeException $e){check($e->getCode()===$code);return;}throw new RuntimeException('Unexpected access');}
SuiteGate::checkDatabase($db,$b);check(true);
deny(fn()=>SuiteGate::checkDatabase($db,array_replace($b,['organization_id'=>8])),503);
deny(fn()=>SuiteGate::identity($b,['identity'=>array_replace($i,['project_id'=>8])]),403);
$u=SuiteGate::localUser($db,$b,$i,true);check((int)$u['id']===1);
SuiteGate::applyIdentity($b,$i,$u);check($_SESSION['auth']['role']==='admin');
SuiteGate::applyIdentity($b,array_replace($i,['role'=>'viewer']),$u);check($_SESSION['auth']['role']==='user');
foreach(['safety','permits','documents']as$module){
 check(SuiteGate::allowed($module,'/dashboard.php','GET','viewer'));
 check(!SuiteGate::allowed($module,'/dashboard.php','POST','viewer'));
 check(!SuiteGate::allowed($module,'/admin/users.php','GET','manager'));
 check(!SuiteGate::allowed($module,'/tools/create_user.php','GET','manager'));
 check(!SuiteGate::allowed($module,'/delete_tour.php','GET','viewer'));
}
$db->exec('UPDATE suite_users SET enabled=0');deny(fn()=>SuiteGate::localUser($db,$b,$i),403);
deny(fn()=>SuiteGate::request($b,'module-session',[],fn()=>['ok'=>false]),401);
echo "$checks legacy Suite gate checks passed.\n";
