<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$phase='arguments';$db=null;$created=false;$file=null;
try{
 $mode=$argv[1]??'--dry-run';$module=$argv[2]??'defects';
 if($argc>3||!in_array($mode,['--dry-run','--activate','--close'],true)||!in_array($module,['programme','defects'],true))throw new RuntimeException();
 $phase='source';
 if(!hash_equals('4b3bdcb50c2cb32d7ddf70dc31aa658c1e73fa5392840a515f7a6c6e364721d6',(string)hash_file('sha256',dirname(__DIR__).'/app/Modules/StagingValidation.php')))throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';
 $phase='private';$root=realpath(SUITE_ROOT);$private=$root.'/private';$file=$private.'/staging-validation.json';
 if(!$root||!is_dir($private)||is_link($private)||realpath($private)!==$private||(fileperms($private)&0777)!==0700)throw new RuntimeException();
 $pair=$module==='programme'?[1,2]:[3,4];$suffix=$module==='programme'?'programme.defecttracker.uk':'defectnotice.site';
 $items=suite_instances()->all();$inventory=\Suite\Modules\InstanceCatalog::inventoryFile();
 if(!$inventory||realpath($inventory)!==$private.'/programme-staging-instances.json')throw new RuntimeException();
 $lists=[];
 foreach(['alpha'=>[$pair[0],7,[7,8,9]],'beta'=>[$pair[1],8,[10,11,12]]]as$site=>[$id,$org,$ids]){
  $entry=$items[$id]??null;if(!$entry||$entry['organization_id']!==$org||$entry['project_id']!==$org||$entry['module_key']!==$module||$entry['origin']!=='https://'.$site.'.'.$suffix||($entry['isolation_verified']??null)!==false||($entry['gateway_verified']??null)!==false||$entry['ready'])throw new RuntimeException();
  $lists[]=['instance_id'=>$id,'user_ids'=>$ids];
 }
 $db=suite_db();
 if($mode==='--close'){
  $phase='close';
  if(file_exists($file)||is_link($file)){
   if(is_link($file)||!is_file($file)||(fileperms($file)&0777)!==0600||filesize($file)>16384)throw new RuntimeException();
   $policy=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);
   if(($policy['version']??null)!==1||($policy['module_key']??'programme')!==$module||($policy['instances']??null)!==$lists)throw new RuntimeException();
   if(!unlink($file))throw new RuntimeException();
  }
  $q=$db->prepare('UPDATE module_sessions SET revoked_at=? WHERE instance_id IN (?,?) AND revoked_at IS NULL');$q->execute([time(),...$pair]);$sessions=$q->rowCount();
  $q=$db->prepare('UPDATE module_handoffs SET used_at=? WHERE instance_id IN (?,?) AND used_at IS NULL');$q->execute([time(),...$pair]);$grants=$q->rowCount();
  echo json_encode(['window_enabled'=>false,'module'=>$module,'policy_absent'=>true,'sessions_revoked'=>$sessions,'grants_revoked'=>$grants,'tenant_ready'=>false])."\n";exit;
 }
 $phase='fixtures';if(file_exists($file)||is_link($file))throw new RuntimeException();
 foreach(['alpha'=>[7,[7,8,9]],'beta'=>[8,[10,11,12]]]as$site=>[$org,$ids]){
  $q=$db->prepare('SELECT COUNT(*) FROM organizations o JOIN projects p ON p.organization_id=o.id WHERE o.id=? AND p.id=? AND o.slug=? AND o.active=1 AND p.active=1');$q->execute([$org,$org,'programme-'.$site.'-staging']);if((int)$q->fetchColumn()!==1)throw new RuntimeException();
  foreach(['admin'=>'company_admin','manager'=>'manager','viewer'=>'viewer']as$label=>$role){
   $id=array_shift($ids);$q=$db->prepare('SELECT id,email,active,is_platform_admin FROM users WHERE id=?');$q->execute([$id]);$user=$q->fetch();
   if(!$user||(int)$user['active']!==1||(int)$user['is_platform_admin']!==0||$user['email']!=='suite-'.$site.'-'.$label.'@example.invalid')throw new RuntimeException();
   $q=$db->prepare('SELECT organization_id,project_id,role_key FROM memberships WHERE user_id=?');$q->execute([$id]);$memberships=$q->fetchAll();
   if(count($memberships)!==1||(int)$memberships[0]['organization_id']!==$org||$memberships[0]['role_key']!==$role||($label==='admin'?$memberships[0]['project_id']!==null:(int)$memberships[0]['project_id']!==$org))throw new RuntimeException();
   $projects=suite_projects()->forUser($user);if(count($projects)!==1||(int)$projects[0]['id']!==$org)throw new RuntimeException();
   $enabled=false;foreach(suite_modules()->allForProject($label,$org,false)as$tool)if($tool['key']===$module)$enabled=true;if(!$enabled)throw new RuntimeException();
  }
 }
 foreach(['module_sessions'=>'revoked_at','module_handoffs'=>'used_at']as$table=>$flag){$q=$db->prepare("SELECT COUNT(*) FROM $table WHERE instance_id IN (?,?) AND $flag IS NULL AND expires_at>=?");$q->execute([...$pair,time()]);if((int)$q->fetchColumn()!==0)throw new RuntimeException();}
 $expires=null;
 if($mode==='--activate'){
  $phase='create';$now=time();$expires=$now+3600;$policy=['version'=>1,'module_key'=>$module,'enabled'=>true,'started_at'=>$now,'expires_at'=>$expires,'run_id'=>bin2hex(random_bytes(16)),'inventory_sha256'=>hash_file('sha256',$inventory),'instances'=>$lists];
  $mask=umask(0077);try{$handle=fopen($file,'xb');}finally{umask($mask);}if(!$handle)throw new RuntimeException();$created=true;
  try{$text=json_encode($policy,JSON_THROW_ON_ERROR);if(fwrite($handle,$text)!==strlen($text)||!fflush($handle))throw new RuntimeException();}finally{fclose($handle);}
 }
 echo json_encode(['plan_valid'=>true,'module'=>$module,'window_enabled'=>$mode==='--activate','accounts'=>6,'window_seconds'=>3600,'expires_at_utc'=>$expires?gmdate('Y-m-d\TH:i:s\Z',$expires):null,'tenant_ready'=>false])."\n";
}catch(Throwable$e){if($created&&$file)@unlink($file);echo json_encode(['ok'=>false,'failed_phase'=>$phase,'tenant_ready'=>false])."\n";exit(1);}
