<?php
declare(strict_types=1);
require_once __DIR__.'/.suite/gate.php';
$b=$GLOBALS['suite_gate_binding'];$db=$GLOBALS['suite_gate_db'];$token=null;
try{
 if(($_SERVER['REQUEST_METHOD']??'')==='GET'){
   $old=$_SESSION['suite_gate']['token']??null;$_SESSION=[];session_regenerate_id(true);
   if(is_string($old))try{SuiteGate::request($b,'module-session',['action'=>'revoke','session_token'=>$old]);}catch(Throwable $ignored){}
   $_SESSION['suite_pending']=['state'=>bin2hex(random_bytes(32)),'expires'=>time()+300];
   setcookie(session_name(),session_id(),['path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'None']);
   header('Location: https://suite.defecttracker.uk/launch.php?instance_id='.$b['instance_id'].'&state='.$_SESSION['suite_pending']['state'],true,303);exit;
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new RuntimeException('Method denied',405);
 $pending=$_SESSION['suite_pending']??null;unset($_SESSION['suite_pending']);$code=$_POST['code']??null;$state=$_POST['state']??null;
 if(!is_array($pending)||$pending['expires']<time()||!is_string($code)||!is_string($state)||!preg_match('/^[a-f0-9]{64}$/D',$code)||!preg_match('/^[a-f0-9]{64}$/D',$state)||!hash_equals($pending['state'],$state))throw new RuntimeException('Sign-in expired',401);
 $i=SuiteGate::identity($b,SuiteGate::request($b,'module-handoff',['code'=>$code,'state'=>$state]));$token=$i['session_token']??null;
 if(!is_string($token)||!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Invalid session',401);
 $i=SuiteGate::identity($b,SuiteGate::request($b,'module-session',['action'=>'validate','session_token'=>$token]));$u=SuiteGate::localUser($db,$b,$i,true);
 session_regenerate_id(true);$_SESSION=['suite_gate'=>['token'=>$token,'user_id'=>$i['user_id']],'suite_csrf'=>bin2hex(random_bytes(32))];SuiteGate::applyIdentity($b,$i,$u);
 setcookie(session_name(),session_id(),['path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);header('Location: /dashboard.php',true,303);exit;
}catch(Throwable $error){
 $_SESSION=[];
 if(is_string($token))try{SuiteGate::request($b,'module-session',['action'=>'revoke','session_token'=>$token]);}catch(Throwable $ignored){}
 http_response_code(401);echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><p>Sign-in could not be completed. <a href="https://suite.defecttracker.uk/">Return to Suite</a>.</p>';
}
