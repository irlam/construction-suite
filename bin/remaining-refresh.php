<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';
try{
 if($argc!==2||!in_array($argv[1],['--check','--apply'],true))throw new RuntimeException();$apply=$argv[1]==='--apply';require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/integrations/legacy/SuiteGate.php';$hosting=dirname((string)realpath(SUITE_ROOT),2);$updated=0;
 foreach(['safety'=>[9,'sitesafety.site'],'permits'=>[11,'sitepermits.site'],'documents'=>[13,'sitenotices.site']]as$module=>[$first,$domain])foreach(['alpha'=>7,'beta'=>8]as$side=>$org){
  $id=$first+($side==='beta'?1:0);$root=$hosting.'/'.$side.'.'.$domain.'/httpdocs';$phase=$module.' '.$side.' binding';$i=suite_instances()->find($id);$b=(static fn(string $file):array=>require $file)($root.'/.suite/binding.private.php');
  if(!$i||$i['ready']||$b['instance_id']!==$id||$b['organization_id']!==$org||$b['project_id']!==$org||$b['module_key']!==$module)throw new RuntimeException();
  $db=new PDO($b['dsn'],$b['db_user'],$b['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);SuiteGate::checkDatabase($db,$b);
  if($apply){$phase=$module.' '.$side.' gateway update';foreach(['gate.php','SuiteGate.php']as$file)if(!copy(dirname(__DIR__).'/integrations/legacy/'.$file,$root.'/.suite/'.$file))throw new RuntimeException();}
  if($module==='permits'){
   $phase=$side.' synthetic permit';$file=$root.'/.suite/test-fixture.json';$fixture=json_decode((string)file_get_contents($file),true,16,JSON_THROW_ON_ERROR);$q=$db->prepare('SELECT ref_number FROM forms WHERE id=?');$q->execute([$fixture['record_id']]);if($q->fetchColumn()!=='TEST-SUITE-'.strtoupper($side))throw new RuntimeException();
   if($apply){if(!copy(dirname(__DIR__).'/bin/remaining-packages/PermitAccess.php',$root.'/src/PermitAccess.php'))throw new RuntimeException();$link=hash('sha256','suite-synthetic-permit-'.$side);$q=$db->prepare('UPDATE forms SET unique_link=? WHERE id=? AND ref_number=?');$q->execute([$link,$fixture['record_id'],'TEST-SUITE-'.strtoupper($side)]);$fixture['unique_link']=$link;file_put_contents($file,json_encode($fixture,JSON_THROW_ON_ERROR),LOCK_EX);chmod($file,0600);}
  }$updated++;
 }
 echo json_encode(['ok'=>true,'instances_checked'=>$updated,'gateway_updates'=>$apply?$updated:0,'emails_sent'=>0,'tenant_ready'=>false])."\n";
}catch(Throwable $error){echo json_encode(['ok'=>false,'failed_phase'=>$phase,'tenant_ready'=>false])."\n";exit(1);}
