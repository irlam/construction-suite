<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';
try{
 if($argc!==2||!in_array($argv[1],['--install','--check','--enable'],true))throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';$hosting=dirname((string)realpath(SUITE_ROOT),2);$items=suite_instances()->all();
 if(count($items)!==14)throw new RuntimeException();foreach($items as $i)if(!$i['ready']||!in_array($i['organization_id'],[7,8],true)||$i['project_id']!==$i['organization_id'])throw new RuntimeException();
 $copy=static function(string $source,string $target):void{if(!is_file($source)||is_link($source)||is_link($target)||realpath(dirname($target))!==dirname($target))throw new RuntimeException();if(!copy($source,$target)||!chmod($target,0644)||!hash_equals(hash_file('sha256',$source),hash_file('sha256',$target)))throw new RuntimeException();};
 if($argv[1]==='--install'){
  foreach(['alpha','beta'] as $side){
   $root=$hosting.'/'.$side.'.defectnotice.site/httpdocs';$phase=$side.' defects';if(realpath($root)!==$root||is_link($root))throw new RuntimeException();
   foreach(['Gateway','ProjectScope','Configuration','SessionStore','SignIn','CookiePolicy'] as $class)if(!class_exists('DefectTracker\\Suite\\'.$class))require_once $root.'/includes/Suite/'.$class.'.php';$phase=$side.' defects binding';$b=DefectTracker\Suite\Configuration::load($root);$i=$items[$side==='alpha'?3:4];if($b['instance_id']!==$i['id']||!hash_equals($b['key'],\Suite\Support\Env::get($i['key_env'],'')))throw new RuntimeException();
   $phase=$side.' defects folder';if(!is_dir($root.'/public/api')&&!mkdir($root.'/public/api',0755))throw new RuntimeException();
   $phase=$side.' defects endpoint';$copy(__DIR__.'/finish-packages/defects-summary.php',$root.'/public/api/suite-summary.php');
   $routeFile=$root.'/public/.htaccess';$rules=(string)file_get_contents($routeFile);if(!str_contains($rules,'api/suite-summary')){$rules=str_replace('api\\.php$|','api\\.php$|api/suite-summary\\.php$|',$rules,$changed);if($changed!==1||file_put_contents($routeFile,$rules,LOCK_EX)===false)throw new RuntimeException();}
   $root=$hosting.'/'.$side.'.programme.defecttracker.uk/httpdocs';$phase=$side.' programme';if(!class_exists('App\\Lib\\SuiteBinding'))require_once $root.'/app/Lib/SuiteBinding.php';$b=App\Lib\SuiteBinding::load($root);$i=$items[$side==='alpha'?1:2];if($b['instance_id']!==$i['id']||!hash_equals($b['key'],\Suite\Support\Env::get($i['key_env'],'')))throw new RuntimeException();
   $copy(__DIR__.'/finish-packages/SuiteHttp.php',$root.'/app/Lib/SuiteHttp.php');$copy(__DIR__.'/finish-packages/programme-summary.php',$root.'/api/suite-summary.php');
   foreach(['qa.defecttracker.uk'=>'public/api/suite-summary.php','sitesafety.site'=>'api/suite-summary.php','sitepermits.site'=>'api/suite-summary.php','sitenotices.site'=>'api/suite-summary.php'] as $domain=>$relative){
    $file=$hosting.'/'.$side.'.'.$domain.'/httpdocs/'.$relative;if(is_link($file)||realpath($file)!==$file)throw new RuntimeException();$text=(string)file_get_contents($file);
    if(!str_contains($text,'summary_method_guard'))$text=preg_replace('/declare\(strict_types=1\);/',"declare(strict_types=1);\n// summary_method_guard\nif ((\$_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') { http_response_code(405); header('Content-Type: application/json'); echo '{\"ok\":false}'; exit; }",$text,1,$n);
    if(file_put_contents($file,$text,LOCK_EX)===false)throw new RuntimeException();
   }
   $qaRoot=$hosting.'/'.$side.'.qa.defecttracker.uk/httpdocs/public';$copy(__DIR__.'/finish-packages/qa-app.css',$qaRoot.'/assets/app.css');$copy(__DIR__.'/finish-packages/qa-service-worker.js',$qaRoot.'/service-worker.js');
   $noticeRoot=$hosting.'/'.$side.'.sitenotices.site/httpdocs';
   foreach(['notice-gate.php'=>'.suite/gate.php','suite-logout.js'=>'assets/js/suite-logout.js','offline-app.js'=>'assets/js/offline-app.js','notice-service-worker.js'=>'service-worker.js'] as $source=>$relative)$copy(__DIR__.'/finish-packages/'.$source,$noticeRoot.'/'.$relative);
  }
  $copy(__DIR__.'/finish-packages/deliveries-summary.php',$hosting.'/sitedeliveries.site/httpdocs/api/suite-instance-summary.php');
  echo '{"ok":true,"reporting_adapters_installed":true,"readiness_changed":false}';exit;
 }
 $checks=0;
 $request=static function(string $url,string $key,string $method='GET'):array{$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['X-Construction-Suite-Key: '.$key]]);$body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);return[$status,json_decode(is_string($body)?$body:'',true)];};
 foreach($items as $id=>$i){
  $phase='summary '.$id.' own';$url=$i['origin'].($i['module_key']==='deliveries'?'/api/suite-instance-summary.php?instance_id='.$id:'/api/suite-summary.php');$key=\Suite\Support\Env::get($i['key_env'],'');
  [$status,$body]=$request($url,$key);if($status!==200||($body['ok']??false)!==true||empty($body['metrics'])||array_filter($body['metrics'],fn($v)=>!is_int($v)||$v<0))throw new RuntimeException();$checks++;
  $foreign=$items[$id%2===1?$id+1:$id-1];
  $phase='summary '.$id.' key or method';foreach([['','GET'],[\Suite\Support\Env::get($foreign['key_env'],''),'GET'],[$key,'POST']] as [$badKey,$method]){[$status]=$request($url,$badKey,$method);if(!in_array($status,[303,400,401,403,405],true))throw new RuntimeException();$checks++;}
  $phase='summary '.$id.' forged scope';if(in_array($i['module_key'],['qa','defects','programme','deliveries'],true)){[$status]=$request($url.(str_contains($url,'?')?'&':'?').($i['module_key']==='deliveries'?'company=999999':'project=999999'),$key);if($status!==403)throw new RuntimeException();$checks++;}
 }
 if($argv[1]==='--enable'){
  $phase='publish scoped summaries';$file=\Suite\Modules\InstanceCatalog::inventoryFile();$list=json_decode((string)file_get_contents($file),true,32,JSON_THROW_ON_ERROR);foreach($list as &$i)$i['summary_verified']=true;unset($i);
  (new \Suite\Modules\InstanceCatalog($list))->all();$temp=$file.'.'.bin2hex(random_bytes(8)).'.tmp';$mask=umask(0077);try{$f=fopen($temp,'xb');}finally{umask($mask);}if(!$f)throw new RuntimeException();$text=json_encode($list,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);try{if(fwrite($f,$text)!==strlen($text)||!fflush($f))throw new RuntimeException();}finally{fclose($f);}if(!rename($temp,$file))throw new RuntimeException();
  Suite\Support\Audit::record('test_summaries.verified',null,null,null,['connections'=>14,'checks'=>$checks]);
 }
 echo json_encode(['ok'=>true,'summary_checks'=>$checks,'connections'=>14,'summaries_enabled'=>$argv[1]==='--enable','customer_connections_changed'=>false]);
}catch(Throwable $e){echo json_encode(['ok'=>false,'phase'=>$phase,'http'=>$status??null,'response_ok'=>$body['ok']??null]);exit(1);}
