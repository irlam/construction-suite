<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';$checks=[];
try{
 if($argc!==2||$argv[1]!=='--check')throw new RuntimeException();require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/integrations/legacy/SuiteGate.php';
 $hosting=dirname((string)realpath(SUITE_ROOT),2);
 foreach(['qa'=>[7,'qa.defecttracker.uk'],'safety'=>[9,'sitesafety.site'],'permits'=>[11,'sitepermits.site'],'documents'=>[13,'sitenotices.site']]as$module=>[$first,$domain])foreach(['alpha'=>7,'beta'=>8]as$side=>$org){
  $id=$first+($side==='beta'?1:0);$root=$hosting.'/'.$side.'.'.$domain.'/httpdocs';$phase=$module.' '.$side.' private binding';$i=suite_instances()->find($id);if(!$i||$i['ready'])throw new RuntimeException();
  $file=$root.($module==='qa'?'/private/suite-deployment.json':'/.suite/binding.private.php');if(is_link($file)||(fileperms($file)&0777)!==0600)throw new RuntimeException();
  $b=$module==='qa'?json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR):(static fn(string $file):array=>require $file)($file);
  foreach(['instance_id'=>$id,'organization_id'=>$org,'project_id'=>$org,'module_key'=>$module,'origin'=>'https://'.$side.'.'.$domain]as$key=>$value)if(($b[$key]??null)!==$value)throw new RuntimeException();
  if(!hash_equals((string)\Suite\Support\Env::get($i['key_env'],''),$b['key']))throw new RuntimeException();
  $fixture=json_decode((string)file_get_contents($root.($module==='qa'?'/private':'/.suite').'/test-fixture.json'),true,16,JSON_THROW_ON_ERROR);
  $phase=$module.' '.$side.' company database';
  if($module==='qa'){
   $db=new PDO('sqlite:'.$root.'/private/qa.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$row=$db->query('SELECT * FROM suite_binding WHERE id=1')->fetch(PDO::FETCH_ASSOC);
   foreach(['instance_id'=>$id,'organization_id'=>$org,'project_id'=>$org]as$key=>$value)if((int)($row[$key]??0)!==$value)throw new RuntimeException();
   $table='inspections';$proof=$root.'/private/uploads/suite-'.$side.'-proof.png';
  }else{
   $db=new PDO($b['dsn'],$b['db_user'],$b['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);SuiteGate::checkDatabase($db,$b);
   $table=['safety'=>'safety_tours','permits'=>'forms','documents'=>'cleanup_notices'][$module];$proof=$root.'/uploads/suite-'.$side.'-proof.png';
   $sessionAlias=$hosting.'/tmp/'.$side.'-'.($module==='documents'?'notices':$module).'-suite-sessions';if(is_link($sessionAlias)||(fileperms($sessionAlias)&0777)!==0700)throw new RuntimeException();
   if(!is_file($root.'/.user.ini')||!str_contains((string)file_get_contents($root.'/.user.ini'),'/.suite/gate.php'))throw new RuntimeException();
  }
  if((int)$db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn()!==1)throw new RuntimeException();$q=$db->prepare('SELECT id FROM '.$table.' WHERE id=?');$q->execute([$fixture['record_id']]);if(!$q->fetchColumn())throw new RuntimeException();
  if(!is_file($proof)||is_link($proof)||mime_content_type($proof)!=='image/png')throw new RuntimeException();
  $checks[$module.'_'.$side]=['binding_verified'=>true,'synthetic_records'=>1,'private_evidence'=>true,'tenant_ready'=>false];
 }
 echo json_encode(['ok'=>true,'checks'=>$checks,'writes_performed'=>0,'credentials_disclosed'=>0,'tenant_ready'=>false],JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable $error){echo json_encode(['ok'=>false,'failed_phase'=>$phase,'checks'=>$checks,'tenant_ready'=>false])."\n";exit(1);}
