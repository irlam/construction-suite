<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';$checks=[];$sessions=[];$fixtures=[];$originOnly=false;
$check=static function(bool $ok,string $name)use(&$checks):void{if(!$ok)throw new RuntimeException();$checks[]=$name;};
$request=static function(string $url,string $cookie='',?array $post=null,array $extra=[],bool $json=false)use(&$originOnly):array{
 $headers=[];$curl=curl_init($url);$options=[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers){$headers[]=$line;return strlen($line);}];
 if($originOnly)$options[CURLOPT_RESOLVE]=[parse_url($url,PHP_URL_HOST).':443:202.61.233.123'];
 if($post!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=$json?json_encode($post,JSON_THROW_ON_ERROR):http_build_query($post);if($json)$extra[]='Content-Type: application/json';}$options[CURLOPT_HTTPHEADER]=$extra;curl_setopt_array($curl,$options);
 try{$body=curl_exec($curl);if(!is_string($body)||strlen($body)>10485760)throw new RuntimeException();return[(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),$body,$headers];}finally{curl_close($curl);}
};
$cookie=static function(array $headers,string $name):string{foreach($headers as$h)if(preg_match('/^Set-Cookie:\s*'.preg_quote($name,'/').'=([a-zA-Z0-9,-]{16,128})(?:;|\s*$)/i',$h,$m))return$name.'='.$m[1];throw new RuntimeException();};
try{
 $module=$argv[2]??'';$originOnly=($argv[3]??'')==='--origin';if(($argv[1]??'')!=='--run'||$argc>4||!in_array($module,['qa','safety','permits','documents'],true))throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';$hosting=dirname((string)realpath(SUITE_ROOT),2);$pair=['qa'=>[7,'qa.defecttracker.uk'],'safety'=>[9,'sitesafety.site'],'permits'=>[11,'sitepermits.site'],'documents'=>[13,'sitenotices.site']][$module];
 foreach(['alpha'=>[7,7,8,9],'beta'=>[8,10,11,12]]as$side=>[$org,$admin,$manager,$viewer]){
  $id=$pair[0]+($side==='beta'?1:0);$i=suite_instances()->find($id);$root=$hosting.'/'.$side.'.'.$pair[1].'/httpdocs';$phase=$side.' test policy';if(!$i||$i['ready'])throw new RuntimeException();
  $f=json_decode((string)file_get_contents($root.($module==='qa'?'/private':'/.suite').'/test-fixture.json'),true,16,JSON_THROW_ON_ERROR);$f['instance']=$id;$f['origin']=$i['origin'];$f['users']=['admin'=>$admin,'manager'=>$manager,'viewer'=>$viewer];$f['cookie_name']=$module==='qa'?'__Host-qa':'__Host-suite-'.$module;
  $f['read']=match($module){'qa'=>'/api.php?op=inspection&id='.$f['record_id'],'safety'=>'/list.php','permits'=>'/dashboard.php','documents'=>'/forms/clean-up/list.php'};
  $f['file']=$module==='qa'?'/evidence.php?id='.$f['evidence_id'].'&preview=1':'/suite-file.php?file='.rawurlencode($f['file']);$fixtures[$side]=$f;
  $phase=$side.' anonymous record';[$status,$body]=$request($f['origin'].$f['read']);$check(in_array($status,[303,401,403],true),$side.'_anonymous_record_denied');
  $phase=$side.' anonymous evidence';[$status]=$request($f['origin'].$f['file']);$check(in_array($status,[303,401,403],true),$side.'_anonymous_file_denied');
 }
 foreach($fixtures as$side=>$f)foreach($f['users']as$role=>$uid){
  $phase=$side.' '.$role.' handoff';[$status,,$headers]=$request($f['origin'].'/suite-login.php');$check(in_array($status,[302,303],true),$side.'_'.$role.'_pending');$pending=$cookie($headers,$f['cookie_name']);$state=null;
  foreach($headers as$h)if(preg_match('/^Location:\s*(.+)/i',$h,$m)){parse_str((string)parse_url(trim($m[1]),PHP_URL_QUERY),$query);$state=$query['state']??null;}
  if(!is_string($state)||!preg_match('/^[a-f0-9]{64}$/D',$state))throw new RuntimeException();$q=suite_db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$uid]);$user=$q->fetch();$grant=suite_handoff()->issue($user,$f['instance'],$state);
  [$status,,$headers]=$request($f['origin'].'/suite-login.php',$pending,['code'=>$grant,'state'=>$state]);$check($status===303,$side.'_'.$role.'_accepted');$auth=$cookie($headers,$f['cookie_name']);$sessions[$side][$role]=['cookie'=>$auth];
  $phase=$side.' '.$role.' own records';[$status,$body]=$request($f['origin'].$f['read'],$auth);$needle=$module==='permits'?'TEST-SUITE-'.strtoupper($side):$f['label'];$check($status===200&&str_contains($body,$needle),$side.'_'.$role.'_own_company_read');
  if($module==='qa'){[$status,$context]=$request($f['origin'].'/api.php?op=context',$auth);$data=json_decode($context,true);$csrf=$data['csrf']??null;}else{preg_match('/name="suite_csrf" value="([a-f0-9]{64})"/',$body,$match);$csrf=$match[1]??null;}
  if(!is_string($csrf))throw new RuntimeException();$sessions[$side][$role]['csrf']=$csrf;
  $phase=$side.' '.$role.' own evidence';[$status,$body]=$request($f['origin'].$f['file'],$auth);$check($status===200&&str_starts_with($body,"\x89PNG"),$side.'_'.$role.'_own_evidence');
 }
 foreach($fixtures as$side=>$f){
  $other=$side==='alpha'?'beta':'alpha';$foreign=$fixtures[$other];$manager=$sessions[$side]['manager'];$viewer=$sessions[$side]['viewer'];
  $phase=$side.' copied foreign cookie';[$status]=$request($f['origin'].$f['read'],$sessions[$other]['manager']['cookie']);$check(in_array($status,[303,401,403],true),$side.'_foreign_cookie_denied');
  $phase=$side.' foreign record';$foreignPath=match($module){'qa'=>'/api.php?op=inspection&id='.$foreign['record_id'],'safety'=>'/pdf.php?id='.$foreign['record_id'],'permits'=>'/view-permit-public.php?link='.$foreign['unique_link'],'documents'=>'/forms/clean-up/pdf.php?id='.$foreign['record_id']};[$status]=$request($f['origin'].$foreignPath,$manager['cookie']);$check(in_array($status,[400,403,404],true),$side.'_foreign_record_denied');
  $phase=$side.' viewer write';$writePath=$module==='qa'?'/api.php?op=inspection_save':$f['read'];$payload=$module==='qa'?['id'=>$f['record_id'],'project_id'=>1,'revision'=>1,'title'=>'Forbidden Viewer write']:[];$extra=$module==='qa'?['X-CSRF-Token: '.$viewer['csrf'],'X-Idempotency-Key: '.bin2hex(random_bytes(16))]:[];[$status]=$request($f['origin'].$writePath,$viewer['cookie'],$payload,$extra,$module==='qa');$check($status===403,$side.'_viewer_write_denied');
  $phase=$side.' viewer remains usable';[$status]=$request($f['origin'].$f['read'],$viewer['cookie']);$check($status===200,$side.'_viewer_read_after_denial');
  $phase=$side.' scoped admin controls';if($module!=='qa'){[$status]=$request($f['origin'].($module==='safety'?'/admin_users.php':($module==='documents'?'/includes/recipients.php':'/admin.php')),$sessions[$side]['admin']['cookie']);$check($status===403,$side.'_global_admin_denied');}
 }
 $phase='fresh manager downgrade';$managerId=$fixtures['alpha']['users']['manager'];$q=suite_db()->prepare("UPDATE memberships SET role_key=? WHERE user_id=? AND organization_id=7 AND project_id=7");$q->execute(['viewer',$managerId]);
 try{$f=$fixtures['alpha'];$session=$sessions['alpha']['manager'];$payload=$module==='qa'?['id'=>$f['record_id'],'project_id'=>1,'revision'=>1]:[];$extra=$module==='qa'?['X-CSRF-Token: '.$session['csrf'],'X-Idempotency-Key: '.bin2hex(random_bytes(16))]:[];[$status]=$request($f['origin'].($module==='qa'?'/api.php?op=inspection_save':$f['read']),$session['cookie'],$payload,$extra,$module==='qa');$check($status===403,'manager_downgrade_applies_immediately');}finally{$q->execute(['manager',$managerId]);}
 foreach($sessions as$side=>$roles)foreach($roles as$role=>$session){
  $phase=$side.' '.$role.' logout';$f=$fixtures[$side];$path=$module==='qa'?'/api.php?op=logout':'/logout.php';$payload=$module==='qa'?[]:['suite_csrf'=>$session['csrf']];$extra=$module==='qa'?['X-CSRF-Token: '.$session['csrf']]:[];[$status]=$request($f['origin'].$path,$session['cookie'],$payload,$extra,$module==='qa');$check($status===200,$side.'_'.$role.'_logout');unset($sessions[$side][$role]);[$status]=$request($f['origin'].$f['read'],$session['cookie']);$check(in_array($status,[303,401,403],true),$side.'_'.$role.'_replay_denied');
 }
 echo json_encode(['ok'=>true,'module'=>$module,'authenticated_checks'=>count($checks),'public_dns_verified'=>!$originOnly,'tenant_ready'=>false])."\n";
}catch(Throwable $error){echo json_encode(['ok'=>false,'module'=>$module??null,'failed_phase'=>$phase,'passed_checks'=>count($checks),'tenant_ready'=>false])."\n";$failed=true;
}finally{
 foreach($sessions as$side=>$roles)foreach($roles as$session)try{$f=$fixtures[$side];$request($f['origin'].($module==='qa'?'/api.php?op=logout':'/logout.php'),$session['cookie'],$module==='qa'?[]:['suite_csrf'=>$session['csrf']],$module==='qa'?['X-CSRF-Token: '.$session['csrf']]:[],$module==='qa');}catch(Throwable $ignored){}
}
if(!empty($failed))exit(1);
