<?php
declare(strict_types=1);
if(PHP_SAPI==='cli')return;
require_once __DIR__.'/SuiteGate.php';
try{
 $root=dirname(__DIR__);$file=__DIR__.'/binding.private.php';
 if(!is_file($file)||is_link($file)||(fileperms($file)&0777)!==0600)throw new RuntimeException('Missing private connection',503);
 $b=(static fn(string $f):mixed=>require $f)($file);
 if(!is_array($b))throw new RuntimeException('Invalid connection',503);
 foreach(['instance_id','organization_id','project_id']as$f)if(!is_int($b[$f]??null)||$b[$f]<1)throw new RuntimeException('Invalid connection',503);
 if(!in_array($b['module_key']??'', ['safety','permits','documents'],true)||!preg_match('/^[a-f0-9]{64}$/D',(string)($b['key']??'')))throw new RuntimeException('Invalid connection',503);
 $origin=parse_url($b['origin']??'');$host=$origin['host']??'';
 if(($origin['scheme']??'')!=='https'||isset($origin['path'],$origin['query'])||($b['origin']??'')!=='https://'.$host||($_SERVER['HTTP_HOST']??'')!==$host||($_SERVER['HTTPS']??'')!=='on')throw new RuntimeException('Wrong host',403);
 $sessions=$b['session_path']??'';$public=realpath($_SERVER['DOCUMENT_ROOT']??$root);
 if(!is_string($sessions)||!is_dir($sessions)||is_link($sessions)||realpath($sessions)!==$sessions||(fileperms($sessions)&0777)!==0700||!$public||str_starts_with($sessions,$public.'/')||$sessions===$public)throw new RuntimeException('Invalid private session storage',503);
 if(session_status()!==PHP_SESSION_ACTIVE){ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');session_save_path($sessions);session_name('__Host-suite-'.$b['module_key']);session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);session_start();}
 $db=new PDO($b['dsn'],$b['db_user'],$b['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);SuiteGate::checkDatabase($db,$b);
 $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
 if(!is_string($path)||str_contains(rawurldecode($path),'..')||str_contains(rawurldecode($path),'\\'))throw new RuntimeException('Invalid path',403);
 header('Cache-Control: private, no-store');header('Referrer-Policy: same-origin');
 if($path==='/suite-login.php'){$GLOBALS['suite_gate_binding']=$b;$GLOBALS['suite_gate_db']=$db;return;}
 if(in_array($path,['/api/suite-summary.php','/api/suite-references.php'],true)&&($_SERVER['REQUEST_METHOD']??'')==='GET'&&hash_equals($b['key'],(string)($_SERVER['HTTP_X_CONSTRUCTION_SUITE_KEY']??'')))return;
 $auth=$_SESSION['suite_gate']??null;
 if(!is_array($auth)||!preg_match('/^[a-f0-9]{64}$/D',(string)($auth['token']??''))){$_SESSION=[];header('Location: /suite-login.php',true,303);exit;}
 $i=SuiteGate::identity($b,SuiteGate::request($b,'module-session',['action'=>'validate','session_token'=>$auth['token']]));
 if(($auth['user_id']??null)!==$i['user_id'])throw new RuntimeException('Account changed',401);
 $u=SuiteGate::localUser($db,$b,$i);SuiteGate::applyIdentity($b,$i,$u);
 if($b['module_key']==='permits'){$q=$db->prepare('UPDATE users SET role=? WHERE id=?');$q->execute([$_SESSION['user_role'],$u['id']]);}
 if($path==='/logout.php'){
   if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!is_string($_POST['suite_csrf']??null)||!hash_equals($_SESSION['suite_csrf']??'',$_POST['suite_csrf']))throw new RuntimeException('Reload before signing out',403);
   $token=$auth['token'];$_SESSION=[];session_regenerate_id(true);
   try{SuiteGate::request($b,'module-session',['action'=>'revoke','session_token'=>$token]);}catch(Throwable $ignored){}
   header('Clear-Site-Data: "cache", "cookies", "storage"');
   header('Content-Type: text/html; charset=utf-8');
   echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signed out</title><p>Signed out. <a href="https://suite.defecttracker.uk/">Return to Suite</a>.</p><script>(async()=>{try{localStorage.clear();sessionStorage.clear();for(const name of await caches.keys())await caches.delete(name);for(const registration of await navigator.serviceWorker.getRegistrations())await registration.unregister();if(indexedDB.databases)for(const database of await indexedDB.databases())if(database.name)indexedDB.deleteDatabase(database.name);}finally{location.replace("https://suite.defecttracker.uk/");}})();</script>';exit;
 }
 if(!SuiteGate::allowed($b['module_key'],$path,$_SERVER['REQUEST_METHOD']??'',$i['role'])){http_response_code(403);echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><p>This page is unavailable for your role. <a href="/dashboard.php">Return to this project</a>.</p>';exit;}
 $GLOBALS['suite_gate_binding']=$b;$GLOBALS['suite_gate_db']=$db;$GLOBALS['suite_gate_identity']=$i;
 ob_start(static function(string $html):string{
   if(!str_contains(strtolower($html),'<body'))return $html;
   // Static file handling may bypass Apache rewrites. Keep evidence links on
   // the authenticated PHP endpoint; the underlying folders stay private.
   $origin=preg_quote($GLOBALS['suite_gate_binding']['origin'],'~');
   $html=preg_replace_callback('~\b(src|href)=(["\'])(?:'.$origin.')?/?((?:uploads|export|pdfs)/[^"\']*)\2~i',static function(array $m):string{
     $path=parse_url(html_entity_decode($m[3],ENT_QUOTES,'UTF-8'),PHP_URL_PATH);
     return $m[1].'='.$m[2].'/suite-file.php?file='.rawurlencode(rawurldecode((string)$path)).$m[2];
   },$html)??$html;
   $token=htmlspecialchars($_SESSION['suite_csrf']??'',ENT_QUOTES,'UTF-8');
   $role=($GLOBALS['suite_gate_identity']['role']??'')==='viewer'?'<span>View-only access</span>':'';
   $bar='<div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:center;padding:12px;background:#0b1220;color:#e5e7eb"><a style="color:#52c8e8" href="https://suite.defecttracker.uk/">Construction Suite</a>'.$role.'<form method="post" action="/logout.php"><input type="hidden" name="suite_csrf" value="'.$token.'"><button type="submit">Sign out of this tool</button></form></div>';
   return preg_replace('/(<body\b[^>]*>)/i','$1'.$bar,$html,1)??$html;
 });
}catch(Throwable $error){
 if(session_status()===PHP_SESSION_ACTIVE)$_SESSION=[];
 http_response_code(in_array($error->getCode(),[401,403,503],true)?$error->getCode():503);
 header('Cache-Control: no-store');echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Suite access</title><p>Project access could not be verified. <a href="https://suite.defecttracker.uk/">Return to Construction Suite</a>.</p>';exit;
}
