<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$mode=$argv[1]??'--check';$phase='arguments';$sessions=[];$passed=[];
try {
 if($argc>2||!in_array($mode,['--check','--run'],true))throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';
 $hosting=dirname((string)realpath(SUITE_ROOT),2);$alpha=$hosting.'/alpha.defectnotice.site/httpdocs';
 foreach(['Gateway','ProjectScope','Configuration','SessionStore','SignIn','CookiePolicy']as$class)require_once $alpha.'/includes/Suite/'.$class.'.php';
 $fixtures=[];
 foreach(['alpha'=>[3,7,8],'beta'=>[4,8,11]]as$host=>[$instance,$org,$user]){
  $phase='private fixture verification';$root=$hosting.'/'.$host.'.defectnotice.site/httpdocs';
  $phase=$host.' configuration';$binding=\DefectTracker\Suite\Configuration::load($root);$phase=$host.' inventory';$known=suite_instances()->find($instance);
  $phase=$host.' immutable binding';foreach(['instance_id'=>$instance,'organization_id'=>$org,'project_id'=>$org,'local_project_id'=>1,'origin'=>'https://'.$host.'.defectnotice.site','module_key'=>'defects']as$k=>$v)if(($binding[$k]??null)!==$v)throw new RuntimeException();
  $phase=$host.' key binding';if(!$known||$known['ready']||!hash_equals((string)\Suite\Support\Env::get($known['key_env'],''),$binding['key']))throw new RuntimeException();
  $phase=$host.' database';$db=\DefectTracker\Suite\Configuration::connect($binding);
  $phase=$host.' attachment fixture';$stmt=$db->prepare('SELECT a.id FROM suite_attachments a JOIN defects d ON d.id=a.defect_id WHERE d.project_id=? ORDER BY a.created_at,a.id LIMIT 1');$stmt->execute([1]);$attachment=$stmt->fetchColumn();
  if(!is_string($attachment)||!preg_match('/^[a-f0-9]{64}$/D',$attachment)||!is_file($binding['upload_root'].'/'.$attachment))throw new RuntimeException();
  $phase=$host.' project scope';$gateway=new \DefectTracker\Suite\Gateway($binding);$scope=new \DefectTracker\Suite\ProjectScope($gateway,$db,$binding);$scope->assertDatabase();
  $store=new \DefectTracker\Suite\SessionStore($binding['session_root'],$binding);$flow=new \DefectTracker\Suite\SignIn($gateway,$scope,$store);
  $fixtures[$host]=['config'=>$binding]+compact('flow','store','user','instance','attachment');
 }
 if($mode==='--check'){echo json_encode(['private_fixtures_verified'=>true,'suite_contacted'=>false,'writes_performed'=>0,'tenant_ready'=>false])."\n";exit;}
 $phase='authorised fixture handoff';
 foreach($fixtures as$host=>$fixture){
  $pending=$fixture['flow']->begin();parse_str((string)parse_url($pending['url'],PHP_URL_QUERY),$query);
  $stmt=suite_db()->prepare('SELECT id,name,email,active,is_platform_admin FROM users WHERE id=?');$stmt->execute([$fixture['user']]);$user=$stmt->fetch();if(!$user)throw new RuntimeException();
  $code=(new \Suite\Auth\ModuleHandoff(suite_instances()))->issue($user,$fixture['instance'],$query['state']);
  try{$sessions[$host]=$fixture['flow']->accept($pending['pending_id'],$code,$query['state']);}finally{$fixture['store']->remove($pending['pending_id']);}
 }
 $request=static function(string $url,string $cookie):array{
  $body='';$curl=curl_init($url);
  curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>\DefectTracker\Suite\CookiePolicy::SESSION.'='.$cookie,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>10485760)return 0;$body.=$chunk;return strlen($chunk);}]);
  try{$ok=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_HTTP_CODE);if(!$ok)throw new RuntimeException();return [$status,$body];}finally{curl_close($curl);}
 };
 $check=static function(bool $ok,string $label)use(&$passed){if(!$ok)throw new RuntimeException();$passed[$label]=true;};
 foreach($fixtures as$host=>$fixture){
  $other=$host==='alpha'?'beta':'alpha';$origin=$fixture['config']['origin'];$cookie=$sessions[$host]['session_id'];
  $phase=$host.' authenticated register';[$status,$body]=$request($origin.'/api.php',$cookie);$reply=json_decode($body,true);$check($status===200&&($reply['ok']??false)===true&&!empty($reply['defects']),$host.'_own_register');
  $phase=$host.' copied foreign cookie';[$status,$body]=$request($origin.'/api.php',$sessions[$other]['session_id']);$reply=json_decode($body,true);$check($status===401&&($reply['ok']??true)===false,$other.'_cookie_denied_on_'.$host);
  $phase=$host.' own attachment';[$status,$body]=$request($origin.'/files.php?id='.$fixture['attachment'],$cookie);$check($status===200&&hash_equals(hash_file('sha256',$fixture['config']['upload_root'].'/'.$fixture['attachment']),hash('sha256',$body)),$host.'_own_attachment');
  $phase=$host.' foreign attachment';[$status,$body]=$request($origin.'/files.php?id='.$fixtures[$other]['attachment'],$cookie);$check($status===404,$host.'_foreign_attachment_denied');
 }
 $phase='session revocation';
 foreach($fixtures as$host=>$fixture){$session=$sessions[$host];$check($fixture['flow']->logout($session['session_id'],'POST',$session['csrf']),$host.'_suite_revocation');unset($sessions[$host]);[$status]=$request($fixture['config']['origin'].'/api.php',$session['session_id']);$check($status===401,$host.'_logout_denied');}
 echo json_encode(['ok'=>true,'checks'=>$passed,'tenant_ready'=>false,'legacy_workflows_verified'=>false])."\n";
} catch(Throwable $e){echo json_encode(['ok'=>false,'failed_phase'=>$phase,'diagnostic'=>in_array($e->getMessage(),['Instance inventory must be a private deployment file.','Unknown instance module.','Invalid instance entry.','Invalid instance identity.','An isolated instance needs its own HTTPS subdomain under the module domain.','Duplicate instance identity or project binding.'],true)?$e->getMessage():get_class($e).' in '.basename($e->getFile()).':'.$e->getLine(),'checks'=>$passed,'tenant_ready'=>false])."\n";$failed=true;}
finally {foreach($sessions as$host=>$session){try{$fixtures[$host]['flow']->logout($session['session_id'],'POST',$session['csrf']);}catch(Throwable $ignored){try{$fixtures[$host]['store']->remove($session['session_id']);}catch(Throwable $ignored){}}}}
if(!empty($failed))exit(1);
