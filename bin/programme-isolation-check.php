<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$mode=$argv[1]??'--check';$phase='arguments';$fixtures=[];$sessions=[];$checks=[];
$request=static function(string $url,string $cookie='',?array $post=null,string $csrf=''):array {
    $headers=[];$curl=curl_init($url);
    curl_setopt_array($curl,[CURLOPT_FOLLOWLOCATION=>false,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIE=>$cookie,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers){$headers[]=$line;return strlen($line);}]);
    if($post!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post),CURLOPT_HTTPHEADER=>['X-CSRF: '.$csrf]]);
    try {$body=curl_exec($curl);if(!is_string($body)||strlen($body)>10485760)throw new RuntimeException();return [(int)curl_getinfo($curl,CURLINFO_HTTP_CODE),$body,$headers];} finally {curl_close($curl);}
};
$cookie=static function(array $headers,string $name):string {
    foreach($headers as$header)if(preg_match('/^Set-Cookie:\s*'.preg_quote($name,'/').'=([^;]*)/i',$header,$match))return $match[1];
    throw new RuntimeException();
};
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException();$checks[$label]=true;};
try {
    if($argc>2||!in_array($mode,['--check','--run'],true))throw new RuntimeException();
    require dirname(__DIR__).'/app/bootstrap.php';
    $hosting=dirname((string)realpath(SUITE_ROOT),2);
    $alpha=$hosting.'/alpha.programme.defecttracker.uk/httpdocs';
    foreach(['SuiteBinding','SuiteGateway','SuitePreflight']as$class)require_once $alpha.'/app/Lib/'.$class.'.php';
    foreach(['alpha'=>[1,7,8,9],'beta'=>[2,8,11,12]]as$host=>[$instance,$organization,$manager,$viewer]) {
        $phase=$host.' private binding';$root=$hosting.'/'.$host.'.programme.defecttracker.uk/httpdocs';
        $binding=\App\Lib\SuiteBinding::load($root);$known=suite_instances()->find($instance);
        foreach(['instance_id'=>$instance,'organization_id'=>$organization,'project_id'=>$organization,'local_project_id'=>1,'origin'=>'https://'.$host.'.programme.defecttracker.uk']as$field=>$value){$phase=$host.' binding '.$field;if(($binding[$field]??null)!==$value)throw new RuntimeException();}
        $phase=$host.' inventory key';
        if(!$known||$known['ready']||!hash_equals((string)\Suite\Support\Env::get($known['key_env'],''),(string)$binding['key']))throw new RuntimeException();
        $phase=$host.' database';$databaseConfig=(static fn(string $path):mixed=>require $path)($root.'/app/config/config.php');
        $database=new PDO($databaseConfig['db']['dsn'],$databaseConfig['db']['user'],$databaseConfig['db']['pass'],$databaseConfig['db']['options']);
        $preflight=\App\Lib\SuitePreflight::database($database,$binding);
        if(!$preflight||in_array(false,$preflight,true))throw new RuntimeException();
        $fixtures[$host]=compact('binding','database','manager','viewer','instance');
    }
    foreach($fixtures as$host=>$fixture) {
        $phase=$host.' anonymous HTTP gate';[$status]=$request($fixture['binding']['origin'].'/api/workspace.php');
        $check($status===401,$host.'_anonymous_denied');
    }
    if($mode==='--check'){echo json_encode(['ok'=>true,'private_fixtures_verified'=>true,'checks'=>$checks,'suite_contacted'=>false,'writes_performed'=>0,'tenant_ready'=>false])."\n";exit;}
    foreach($fixtures as$host=>$fixture)foreach(['manager','viewer']as$role) {
        $phase=$host.' '.$role.' handoff';$origin=$fixture['binding']['origin'];
        [$status,,$headers]=$request($origin.'/suite-login.php');if($status!==303)throw new RuntimeException();
        $state=$cookie($headers,'__Host-programme_state');if(!preg_match('/^[a-f0-9]{64}$/D',$state))throw new RuntimeException();
        $query=suite_db()->prepare('SELECT id,name,email,active,is_platform_admin FROM users WHERE id=?');$query->execute([$fixture[$role]]);$user=$query->fetch();if(!$user)throw new RuntimeException();
        $code=(new \Suite\Auth\ModuleHandoff(suite_instances()))->issue($user,$fixture['instance'],$state);
        [$status,,$headers]=$request($origin.'/suite-login.php','__Host-programme_state='.$state,['code'=>$code,'state'=>$state]);
        if($status!==303)throw new RuntimeException();
        $value=$cookie($headers,'__Host-programme_suite');if(!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$value))throw new RuntimeException();
        $sessions[$host][$role]=['cookie'=>'__Host-programme_suite='.$value,'csrf'=>''];
        [$status,$body]=$request($origin.'/api/auth.php',$sessions[$host][$role]['cookie']);$identity=json_decode($body,true);
        if($status!==200||($identity['ok']??false)!==true||!is_string($identity['csrf']??null))throw new RuntimeException();
        $sessions[$host][$role]['csrf']=$identity['csrf'];
        $check(($identity['user']['read_only']??false)===($role==='viewer'),$host.'_'.$role.'_role');
    }
    foreach($fixtures as$host=>$fixture) {
        $other=$host==='alpha'?'beta':'alpha';$origin=$fixture['binding']['origin'];$own=$sessions[$host]['manager'];
        $phase=$host.' own workspace';[$status,$body]=$request($origin.'/api/workspace.php?project=1',$own['cookie']);$result=json_decode($body,true);
        $check($status===200&&($result['ok']??false)===true&&(int)($result['project']['id']??0)===1&&count($result['projects']??[])===1,$host.'_own_workspace');
        $phase=$host.' copied foreign cookie';[$status]=$request($origin.'/api/workspace.php',$sessions[$other]['manager']['cookie']);$check($status===401,$other.'_cookie_denied_on_'.$host);
        $phase=$host.' foreign project';[$status]=$request($origin.'/api/workspace.php?project=2',$own['cookie']);$check($status===403,$host.'_foreign_project_denied');
        $phase=$host.' viewer write';$viewer=$sessions[$host]['viewer'];[$status]=$request($origin.'/api/tasks.php',$viewer['cookie'],[],$viewer['csrf']);$check($status===403,$host.'_viewer_write_denied');
    }
    foreach($sessions as$host=>$roles)foreach($roles as$role=>$session) {
        $phase=$host.' '.$role.' logout';$origin=$fixtures[$host]['binding']['origin'];
        [$status]=$request($origin.'/api/auth.php?action=logout',$session['cookie'],[],$session['csrf']);$check($status===200,$host.'_'.$role.'_logout');
        unset($sessions[$host][$role]);[$status]=$request($origin.'/api/workspace.php',$session['cookie']);$check($status===401,$host.'_'.$role.'_revoked');
    }
    echo json_encode(['ok'=>true,'checks'=>$checks,'tenant_ready'=>false,'legacy_workflows_verified'=>false])."\n";
} catch(Throwable $error) {echo json_encode(['ok'=>false,'failed_phase'=>$phase,'checks'=>$checks,'tenant_ready'=>false])."\n";$failed=true;}
finally {
    foreach($sessions as$host=>$roles)foreach($roles as$session)try{$request($fixtures[$host]['binding']['origin'].'/api/auth.php?action=logout',$session['cookie'],[],$session['csrf']);}catch(Throwable $ignored){}
    if($mode==='--run'&&isset($hosting)) {
        // Always revoke this pair's test grants/sessions, including failures before callback.
        try {
            $q=suite_db()->prepare('UPDATE module_sessions SET revoked_at=? WHERE instance_id IN (1,2) AND revoked_at IS NULL');$q->execute([time()]);
            $q=suite_db()->prepare('UPDATE module_handoffs SET used_at=? WHERE instance_id IN (1,2) AND used_at IS NULL');$q->execute([time()]);
        } catch(Throwable $ignored) {}
    }
}
if(!empty($failed))exit(1);
