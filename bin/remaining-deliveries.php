<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';
function deliveryPrivate(string $path,string $body):void{
 if(is_link($path)||file_exists($path))throw new RuntimeException();$mask=umask(0077);try{$h=fopen($path,'xb');}finally{umask($mask);}if(!$h)throw new RuntimeException();try{if(fwrite($h,$body)!==strlen($body)||!fflush($h))throw new RuntimeException();}finally{fclose($h);}chmod($path,0600);
}
try{
 $mode=$argv[1]??'';if($argc!==2||!in_array($mode,['--check','--install'],true))throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';$root=realpath(SUITE_ROOT);$delivery=dirname($root,2).'/sitedeliveries.site/httpdocs';
 $phase='private inventory';$file=\Suite\Modules\InstanceCatalog::inventoryFile();$items=suite_instances()->all();foreach($items as$i)if(in_array($i['id'],[5,6],true))throw new RuntimeException();
 foreach([5,6]as$id)if(\Suite\Support\Env::get('SUITE_INSTANCE_KEY_'.$id,'' )!=='')throw new RuntimeException();
 if(is_file($delivery.'/includes/suite.local.php')||is_link($delivery.'/includes/suite.local.php'))throw new RuntimeException();
 $phase='delivery code package';$codeZip=$root.'/bin/remaining-packages/deliveries-suite.zip';if(!hash_equals('16b28629f8db449bd888d1630705d709bbab0d44b2d691bea9f914de76e15884',hash_file('sha256',$codeZip)))throw new RuntimeException();$code=new ZipArchive();if($code->open($codeZip)!==true)throw new RuntimeException();
 $phase='delivery schema';$pdo=(static function(string $path):PDO{require $path.'/db.php';return $pdo;})($delivery);
 foreach(['logistics_sites','logistics_companies','logistics_gates','logistics_resources','logistics_users','logistics_memberships','deliveries']as$t)$pdo->query('SELECT 1 FROM '.$t.' LIMIT 0');
 $existing=$pdo->query("SELECT id FROM logistics_sites WHERE name='Suite shared logistics test site'")->fetchColumn();if($existing)throw new RuntimeException();
 if($mode==='--check'){echo json_encode(['ok'=>true,'writes_performed'=>0,'tenant_ready'=>false])."\n";exit;}
 $phase='delivery code';if(!$code->extractTo($delivery))throw new RuntimeException();$code->close();
 $phase='synthetic companies and shared site';$pdo->exec('CREATE TABLE IF NOT EXISTS logistics_suite_users(instance_id BIGINT UNSIGNED NOT NULL,suite_user_id BIGINT UNSIGNED NOT NULL,local_user_id INT NOT NULL,PRIMARY KEY(instance_id,suite_user_id),UNIQUE KEY suite_local_user(local_user_id)) ENGINE=InnoDB');
 $pdo->beginTransaction();$q=$pdo->prepare('INSERT INTO logistics_sites(name,operator_name) VALUES(?,?)');$q->execute(['Suite shared logistics test site','Suite synthetic test operator']);$site=(int)$pdo->lastInsertId();
 $bindings=[];$fixtures=[];
 foreach(['alpha'=>7,'beta'=>8]as$side=>$org){
  $q=$pdo->prepare('INSERT INTO logistics_companies(name) VALUES(?)');$q->execute([ucfirst($side).' Suite logistics test company']);$company=(int)$pdo->lastInsertId();
  $q=$pdo->prepare('INSERT INTO logistics_gates(site_id,name,capacity) VALUES(?,?,1)');$q->execute([$site,$side==='alpha'?'North gate':'South gate']);$gate=(int)$pdo->lastInsertId();
  $q=$pdo->prepare('INSERT INTO logistics_resources(site_id,name,kind) VALUES(?,?,?)');$q->execute([$site,$side==='alpha'?'Test crane 1':'Test forklift 1',$side==='alpha'?'Crane':'Forklift']);$resource=(int)$pdo->lastInsertId();
  $pdo->prepare('INSERT INTO logistics_gate_resources VALUES(?,?)')->execute([$gate,$resource]);
  $q=$pdo->prepare("INSERT INTO deliveries(company_id,gate_id,resource_id,due_datetime,duration_min,unloading_method,supplier,material,quantity,user_name,site_id,status,created_at) VALUES(?,?,?,?,60,?,?,?,?,?,?,'Booked in',NOW())");
  $q->execute([$company,$gate,$resource,date('Y-m-d',strtotime('next monday')).' 10:00:00',$side==='alpha'?'Crane':'Forklift',ucfirst($side).' Suite logistics test company',strtoupper($side).' SUITE TEST ONLY','1 synthetic pallet','Suite test fixture',$site]);$record=(int)$pdo->lastInsertId();
  $id=$side==='alpha'?5:6;$key=bin2hex(random_bytes(32));$bindings[$id]=['instance_id'=>$id,'organization_id'=>$org,'project_id'=>$org,'local_site_id'=>$site,'local_company_id'=>$company,'origin'=>'https://sitedeliveries.site','key'=>$key];
  $items[]=['id'=>$id,'organization_id'=>$org,'project_id'=>$org,'module_key'=>'deliveries','origin'=>'https://sitedeliveries.site','scope_mode'=>'company_site','local_site_id'=>$site,'local_company_id'=>$company,'isolation_verified'=>false,'gateway_verified'=>false,'summary_verified'=>false];
  $fixtures[$side]=['record_id'=>$record,'site_id'=>$site,'company_id'=>$company,'gate_id'=>$gate,'resource_id'=>$resource];
 }$pdo->commit();
 $phase='register disabled delivery connections';(new \Suite\Modules\InstanceCatalog($items))->all();
 deliveryPrivate($delivery.'/includes/suite.local.php',"<?php\nreturn ".var_export($bindings,true).";\n");
 deliveryPrivate($root.'/private/delivery-test-fixtures.json',json_encode($fixtures,JSON_THROW_ON_ERROR));
 $envFile=$root.'/.env';$env=rtrim((string)file_get_contents($envFile))."\n";foreach($bindings as$id=>$b)$env.='SUITE_INSTANCE_KEY_'.$id.'='.$b['key']."\n";
 $temp=$envFile.'.'.bin2hex(random_bytes(8)).'.tmp';deliveryPrivate($temp,$env);if(!rename($temp,$envFile))throw new RuntimeException();
 $temp=$file.'.'.bin2hex(random_bytes(8)).'.tmp';deliveryPrivate($temp,json_encode($items,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));if(!rename($temp,$file))throw new RuntimeException();
 echo json_encode(['ok'=>true,'installed'=>true,'shared_site'=>$site,'instance_ids'=>[5,6],'emails_sent'=>0,'tenant_ready'=>false])."\n";
}catch(Throwable $error){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();echo json_encode(['ok'=>false,'failed_phase'=>$phase,'tenant_ready'=>false])."\n";exit(1);}
