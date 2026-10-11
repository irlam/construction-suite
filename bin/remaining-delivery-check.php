<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';$checks=0;$sessions=[];
$check=static function(bool $ok)use(&$checks):void{if(!$ok)throw new RuntimeException();$checks++;};
$request=static function(string $path,string $cookie='',?array $post=null,array $headers=[],bool $json=false):array{
 $received=[];$curl=curl_init('https://sitedeliveries.site'.$path);$options=[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$received){$received[]=$line;return strlen($line);}];
 if($post!==null){$options[CURLOPT_POST]=true;$options[CURLOPT_POSTFIELDS]=$json?json_encode($post,JSON_THROW_ON_ERROR):http_build_query($post);if($json)$headers[]='Content-Type: application/json';}$options[CURLOPT_HTTPHEADER]=$headers;curl_setopt_array($curl,$options);
 try{$body=curl_exec($curl);if(!is_string($body)||strlen($body)>10485760)throw new RuntimeException();return[(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),$body,$received];}finally{curl_close($curl);}
};
$cookie=static function(array $headers):string{foreach($headers as$h)if(preg_match('/^Set-Cookie:\s*PHPSESSID=([a-zA-Z0-9,-]{16,128})(?:;|\s*$)/i',$h,$m))return'PHPSESSID='.$m[1];throw new RuntimeException();};
try{
 if($argc!==2||$argv[1]!=='--run')throw new RuntimeException();require dirname(__DIR__).'/app/bootstrap.php';$fixture=json_decode((string)file_get_contents(SUITE_ROOT.'/private/delivery-test-fixtures.json'),true,16,JSON_THROW_ON_ERROR);
 foreach(['alpha'=>[5,7,8,9],'beta'=>[6,10,11,12]]as$side=>[$instance,$admin,$manager,$viewer]){
  $f=$fixture[$side];$i=suite_instances()->find($instance);$phase=$side.' binding';$check($i&&!$i['ready']&&$i['local_company_id']===$f['company_id']&&$i['local_site_id']===$f['site_id']);
  $phase=$side.' anonymous detail';[$status]=$request('/api/logistics.php?op=detail&id='.$f['record_id']);$check($status===401);
  foreach(['admin'=>$admin,'manager'=>$manager,'viewer'=>$viewer]as$role=>$uid){
   $phase=$side.' '.$role.' handoff';[$status,,$headers]=$request('/suite-login.php?instance_id='.$instance);$check($status===303);$pending=$cookie($headers);$state=null;foreach($headers as$h)if(preg_match('/^Location:\s*(.+)/i',$h,$m)){parse_str((string)parse_url(trim($m[1]),PHP_URL_QUERY),$query);$state=$query['state']??null;}if(!is_string($state))throw new RuntimeException();
   $q=suite_db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$uid]);$grant=suite_handoff()->issue($q->fetch(),$instance,$state);[$status,,$headers]=$request('/suite-login.php',$pending,['code'=>$grant,'state'=>$state]);$check($status===303);$auth=$cookie($headers);$sessions[$side][$role]=['cookie'=>$auth];
   $phase=$side.' '.$role.' company context';[$status,$body]=$request('/api/logistics.php?op=context',$auth);$context=json_decode($body,true);$check($status===200&&($context['ok']??false)&&count($context['companies'])===1&&(int)$context['companies'][0]['id']===$f['company_id']&&count($context['sites'])===1&&(int)$context['sites'][0]['id']===$f['site_id']);$sessions[$side][$role]['csrf']=$context['csrf'];
   $phase=$side.' '.$role.' own delivery';[$status,$body]=$request('/api/logistics.php?op=detail&id='.$f['record_id'],$auth);$detail=json_decode($body,true);$check($status===200&&$detail['delivery']['material']===strtoupper($side).' SUITE TEST ONLY');$fixture[$side]['delivery']=$detail['delivery'];
  }
 }
 foreach($fixture as$side=>$f){
  $other=$side==='alpha'?'beta':'alpha';$foreign=$fixture[$other];$manager=$sessions[$side]['manager'];$viewer=$sessions[$side]['viewer'];
  $phase=$side.' foreign-company detail';[$status]=$request('/api/logistics.php?op=detail&id='.$foreign['record_id'],$manager['cookie']);$check($status===403);
  $phase=$side.' Viewer write';[$status]=$request('/api/logistics.php?op=save',$viewer['cookie'],['id'=>$f['record_id'],'revision'=>1,'material'=>'Forbidden write'],['X-CSRF-Token: '.$viewer['csrf']],true);$check($status===403);
  $phase=$side.' shared reservation capacity';[$status]=$request('/api/logistics.php?op=save',$manager['cookie'],['id'=>$f['record_id'],'revision'=>1,'gate_id'=>$foreign['gate_id'],'resource_id'=>$foreign['resource_id'],'unloading_method'=>$other==='alpha'?'Crane':'Forklift','due_datetime'=>$f['delivery']['due_datetime']],['X-CSRF-Token: '.$manager['csrf']],true);$check($status===409);
  $date=substr($f['delivery']['due_datetime'],0,10);$to=date('Y-m-d',strtotime($date.' +7 days'));$query='&site='.$f['site_id'].'&from='.$date.'&to='.$to;
  $phase=$side.' shared availability privacy';[$status,$body]=$request('/api/logistics.php?op=calendar'.$query,$viewer['cookie']);$calendar=json_decode($body,true);$private=array_values(array_filter($calendar['items']??[],static fn(array $row):bool=>!empty($row['private'])));$check($status===200&&count($private)===1&&!array_intersect(['id','company_id','supplier'],array_keys($private[0]))&&!str_contains($body,strtoupper($other).' SUITE TEST ONLY'));
  $phase=$side.' UK CSV download';[$status,$body]=$request('/logistics-report.php?type=csv'.$query,$viewer['cookie']);$check($status===200&&str_contains($body,date('d/m/Y',strtotime($date)))&&str_contains($body,strtoupper($side).' SUITE TEST ONLY')&&!str_contains($body,strtoupper($other).' SUITE TEST ONLY'));
  $phase=$side.' PDF download';[$status,$body]=$request('/logistics-report.php?type=pdf'.$query,$viewer['cookie']);$check($status===200&&str_starts_with($body,'%PDF-'));
 }
 $phase='fresh manager downgrade';$q=suite_db()->prepare("UPDATE memberships SET role_key=? WHERE user_id=8 AND organization_id=7 AND project_id=7");$q->execute(['viewer']);try{$session=$sessions['alpha']['manager'];[$status]=$request('/api/logistics.php?op=save',$session['cookie'],['id'=>$fixture['alpha']['record_id'],'revision'=>1],['X-CSRF-Token: '.$session['csrf']],true);$check($status===403);}finally{$q->execute(['manager']);}
 foreach($sessions as$side=>$roles)foreach($roles as$role=>$session){$phase=$side.' '.$role.' logout';[$status]=$request('/company-logout.php',$session['cookie'],['csrf'=>$session['csrf']]);$check(in_array($status,[302,303],true));unset($sessions[$side][$role]);[$status]=$request('/api/logistics.php?op=detail&id='.$fixture[$side]['record_id'],$session['cookie']);$check($status===401);}
 echo json_encode(['ok'=>true,'module'=>'deliveries','authenticated_checks'=>$checks,'public_dns_verified'=>true,'tenant_ready'=>false])."\n";
}catch(Throwable $error){echo json_encode(['ok'=>false,'failed_phase'=>$phase,'passed_checks'=>$checks,'tenant_ready'=>false])."\n";$failed=true;}
finally{foreach($sessions as$roles)foreach($roles as$session)try{$request('/company-logout.php',$session['cookie'],['csrf'=>$session['csrf']]);}catch(Throwable $ignored){}}
if(!empty($failed))exit(1);
