<?php
declare(strict_types=1);
header('Content-Type: application/json');header('Cache-Control: no-store');
try{
 $root=dirname(__DIR__,2);
 foreach(['Configuration','Gateway','ProjectScope','CookiePolicy'] as $class)require_once $root.'/includes/Suite/'.$class.'.php';
 $b=DefectTracker\Suite\Configuration::load($root);
 if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo '{"ok":false}';exit;}
 if(($_SERVER['HTTPS']??'')!=='on'||($_SERVER['HTTP_HOST']??'')!==parse_url($b['origin'],PHP_URL_HOST)||!hash_equals($b['key'],(string)($_SERVER['HTTP_X_CONSTRUCTION_SUITE_KEY']??''))){http_response_code(401);echo '{"ok":false}';exit;}
 if(isset($_GET['project'])&&(string)$_GET['project']!==(string)$b['local_project_id']){http_response_code(403);echo '{"ok":false}';exit;}
 $db=DefectTracker\Suite\Configuration::connect($b);
 (new DefectTracker\Suite\ProjectScope(new DefectTracker\Suite\Gateway($b),$db,$b))->assertDatabase();
 $q=$db->prepare("SELECT COUNT(*) total,COALESCE(SUM(status NOT IN ('accepted','closed')),0) active FROM defects WHERE project_id=?");$q->execute([$b['local_project_id']]);
 echo json_encode(['ok'=>true,'module'=>'defects','scope'=>['project'=>$b['local_project_id']],'metrics'=>array_map('intval',$q->fetch(PDO::FETCH_ASSOC))],JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo '{"ok":false,"error":"summary_unavailable"}';}
