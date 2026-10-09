<?php
declare(strict_types=1);
$root=sys_get_temp_dir().'/suite-window-cli-'.bin2hex(random_bytes(8));mkdir($root,0700);
$copy=static function(string$from,string$to)use(&$copy):void{mkdir($to,0700);foreach(scandir($from)as$name){if($name==='.'||$name==='..')continue;if(is_dir($from.'/'.$name))$copy($from.'/'.$name,$to.'/'.$name);else copy($from.'/'.$name,$to.'/'.$name);}};
$remove=static function(string$path)use(&$remove):void{if(is_dir($path)&&!is_link($path)){foreach(scandir($path)as$name)if($name!=='.'&&$name!=='..')$remove($path.'/'.$name);rmdir($path);}else unlink($path);};
try{
 foreach(['app','config','database','public','bin']as$directory)$copy(dirname(__DIR__).'/'.$directory,$root.'/'.$directory);
 mkdir($root.'/private',0700);putenv('DB_DRIVER=sqlite');putenv('DB_DATABASE='.$root.'/fixture.sqlite');putenv('SUITE_INSTANCES_FILE='.$root.'/private/programme-staging-instances.json');putenv('SUITE_INSTANCE_KEY_3='.str_repeat('a',64));putenv('SUITE_INSTANCE_KEY_4='.str_repeat('b',64));
 require$root.'/app/bootstrap.php';$db=suite_db();$db->exec(file_get_contents($root.'/database/schema.sqlite.sql'));
 foreach(['001_project_modules','002_module_handoffs','003_module_sessions','004_company_settings']as$migration)$db->exec(file_get_contents($root.'/database/migrations/'.$migration.'.sqlite.sql'));
 $db->exec("INSERT INTO organizations(id,name,slug)VALUES(7,'Alpha','programme-alpha-staging'),(8,'Beta','programme-beta-staging'); INSERT INTO projects(id,organization_id,name)VALUES(7,7,'Alpha'),(8,8,'Beta')");
 $id=7;foreach(['alpha'=>7,'beta'=>8]as$site=>$org)foreach(['admin'=>'company_admin','manager'=>'manager','viewer'=>'viewer']as$label=>$role){$q=$db->prepare('INSERT INTO users(id,email,name,password_hash,is_platform_admin)VALUES(?,?,?,?,0)');$q->execute([$id,'suite-'.$site.'-'.$label.'@example.invalid',$label,password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT)]);$q=$db->prepare('INSERT INTO memberships(user_id,organization_id,project_id,role_key)VALUES(?,?,?,?)');$q->execute([$id++,$org,$label==='admin'?null:$org,$role]);}
 $items=[];foreach(['programme'=>'programme.defecttracker.uk','defects'=>'defectnotice.site']as$module=>$suffix)foreach(['alpha'=>7,'beta'=>8]as$site=>$org)$items[]=['id'=>count($items)+1,'organization_id'=>$org,'project_id'=>$org,'module_key'=>$module,'origin'=>'https://'.$site.'.'.$suffix,'isolation_verified'=>false,'gateway_verified'=>false];
 $inventory=$root.'/private/programme-staging-instances.json';file_put_contents($inventory,json_encode($items));chmod($inventory,0600);
 $run=static function(string $mode,string $module)use($root):array{
  $command=[getenv('PHP_TEST_BINARY')?:PHP_BINARY];if(php_ini_loaded_file())array_push($command,'-c',php_ini_loaded_file());array_push($command,$root.'/bin/staging-window.php',$mode,$module);
  $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('Cannot run window helper');
  $output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
  return[$status,json_decode($output,true,16,JSON_THROW_ON_ERROR),$error];
 };
 $check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
 $policyFile=$root.'/private/staging-validation.json';
 [$status,$result]=$run('--dry-run','defects');$check($status===0&&$result['plan_valid']&&!$result['window_enabled']&&!file_exists($policyFile),'Read-only Defects plan');
 [$status,$result]=$run('--activate','defects');$check($status===0&&$result['window_enabled']&&!$result['tenant_ready'],'Activate Defects');
 clearstatcache();$policy=json_decode(file_get_contents($policyFile),true);$check((fileperms($policyFile)&0777)===0600&&$policy['module_key']==='defects','Private module policy');
 $policyHash=hash_file('sha256',$policyFile);
 foreach(['--activate','--close']as$mode){[$status]=$run($mode,'programme');$check($status!==0&&hash_file('sha256',$policyFile)===$policyHash,'Other module cannot replace/remove policy');}
 $user=$db->query('SELECT * FROM users WHERE id=8')->fetch();$state=bin2hex(random_bytes(32));$now=time();
 $handoff=suite_handoff();$code=$handoff->issue($user,3,$state,$now);$identity=$handoff->redeem(3,$code,$state,str_repeat('a',64),$now);
 $sessions=new \Suite\Auth\ModuleSession(suite_instances());$check($sessions->validate(3,$identity['session_token'],str_repeat('a',64),$now)['role']==='manager','Live fixture session');
 [$status,$result]=$run('--close','defects');$check($status===0&&$result['sessions_revoked']===1&&!file_exists($policyFile),'Close removes policy and revokes session');
 $denied=false;try{$sessions->validate(3,$identity['session_token'],str_repeat('a',64),time());}catch(Throwable$e){$denied=true;}$check($denied,'Session blocked before expiry');
 [$status,$result]=$run('--activate','programme');$check($status===0&&$result['window_enabled'],'Programme pair remains supported');
 [$status]=$run('--close','programme');$check($status===0&&!file_exists($policyFile),'Programme closes independently');
 $check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===6&&(int)$db->query('SELECT COUNT(*) FROM memberships')->fetchColumn()===6&&hash_file('sha256',$inventory)===$policy['inventory_sha256'],'Fixtures and readiness inventory unchanged');
 echo "PASS: module-scoped CLI dry-run, private activation, cross-module protection and immediate session revocation.\n";
}finally{$remove($root);}
