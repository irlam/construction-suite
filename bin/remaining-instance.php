<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$phase='arguments';
function remainingPrivate(string $file,string $text):void{
 if(is_link($file)||file_exists($file))throw new RuntimeException('Private target already exists');
 $mask=umask(0077);try{$h=fopen($file,'xb');}finally{umask($mask);}if(!$h)throw new RuntimeException();
 try{if(fwrite($h,$text)!==strlen($text)||!fflush($h))throw new RuntimeException();}finally{fclose($h);}
 if(!chmod($file,0600))throw new RuntimeException();
}
function remainingDirectory(string $path,int $mode=0700):void{
 if(is_link($path)||(!is_dir($path)&&!mkdir($path,$mode,true)))throw new RuntimeException();
 if(realpath($path)!==$path||!chmod($path,$mode))throw new RuntimeException();
}
try{
 if($argc!==4||!in_array($argv[1],['--check','--install'],true)||!in_array($argv[2],['qa','safety','permits','notices'],true)||!in_array($argv[3],['alpha','beta'],true))throw new RuntimeException();
 [$mode,$module,$side]=array_slice($argv,1);require dirname(__DIR__).'/app/bootstrap.php';
 $suite=realpath(SUITE_ROOT);$hosting=dirname($suite,2);$webHosting='/var/www/vhosts/hosting215226.ae97b.netcup.net';
 $domains=['qa'=>'qa.defecttracker.uk','safety'=>'sitesafety.site','permits'=>'sitepermits.site','notices'=>'sitenotices.site'];
 $first=['qa'=>7,'safety'=>9,'permits'=>11,'notices'=>13];$id=$first[$module]+($side==='beta'?1:0);$org=$side==='alpha'?7:8;
 $host=$side.'.'.$domains[$module];$target=$hosting.'/'.$host.'/httpdocs';$webTarget=$webHosting.'/'.$host.'/httpdocs';$source=$hosting.'/'.$domains[$module].'/httpdocs';
 $phase='target host';if(!is_dir($target)||is_link($target)||realpath($target)!==$target||!str_starts_with($target,$hosting.'/'))throw new RuntimeException();
 $phase='package';$packages=$suite.'/bin/remaining-packages';$manifest=json_decode((string)file_get_contents($packages.'/manifest.json'),true,16,JSON_THROW_ON_ERROR);$zip=$packages.'/'.$module.'-suite.zip';
 if(!is_file($zip)||is_link($zip)||!hash_equals($manifest[$module]['sha256']??'',hash_file('sha256',$zip)))throw new RuntimeException();
 $archive=new ZipArchive();if($archive->open($zip)!==true)throw new RuntimeException();
 for($n=0;$n<$archive->numFiles;$n++){$name=$archive->getNameIndex($n);if(!is_string($name)||str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\')||preg_match('~(?:^|/)(?:binding\.private\.php|local\.php|db\.local\.php|runtime\.private\.php|\.env)$~',$name))throw new RuntimeException();}
 $phase='private inventory';$inventoryFile=\Suite\Modules\InstanceCatalog::inventoryFile();if(!$inventoryFile)throw new RuntimeException();$inventory=json_decode((string)file_get_contents($inventoryFile),true,32,JSON_THROW_ON_ERROR);
 foreach($inventory as$entry)if($entry['id']===$id||$entry['origin']==='https://'.$host)throw new RuntimeException('Instance already exists');
 $keyName='SUITE_INSTANCE_KEY_'.$id;if(\Suite\Support\Env::get($keyName,'' )!=='')throw new RuntimeException('Key already exists');
 $phase='native database settings';$native=null;$credentials=null;$database='k87747_'.$module.'_'.$side.'_suite';
 if($module==='safety'){
   require $source.'/includes/config.php';
   $credentials=['host'=>DB_HOST,'port'=>(string)DB_PORT,'user'=>DB_USER,'pass'=>DB_PASS];
   $native=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 }elseif($module==='permits'){
   $nativeResult=(static fn(string $root):array=>require $root.'/src/bootstrap.php')($source);$native=$nativeResult[1]->pdo;
   $credentials=['host'=>$_ENV['DB_HOST'],'port'=>(string)($_ENV['DB_PORT']??3306),'user'=>$_ENV['DB_USERNAME'],'pass'=>$_ENV['DB_PASSWORD']];
 }elseif($module==='notices'){
   [$native,$credentials]=(static function(string $root):array{require $root.'/includes/db.php';return[$pdo,$private];})($source);
 }
 $db=null;$dsn=null;
 if($credentials!==null){
   $phase='empty test database';$dsn='mysql:host='.$credentials['host'].';port='.$credentials['port'].';dbname='.$database.';charset=utf8mb4';
   $db=new PDO($dsn,$credentials['user'],$credentials['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
   if($db->query('SHOW TABLES')->fetchColumn()!==false)throw new RuntimeException('Target database is not empty');
 }
 if($mode==='--check'){echo json_encode(['ok'=>true,'module'=>$module,'side'=>$side,'host'=>$host,'package_verified'=>true,'database_empty'=>true,'writes_performed'=>0,'tenant_ready'=>false])."\n";exit;}
 $phase='archive hosting placeholder';$placeholder=$target.($module==='qa'?'/public':'').'/index.html';if(is_file($placeholder)){ $archiveDir=$suite.'/private/hosting-placeholders';remainingDirectory($archiveDir);if(!rename($placeholder,$archiveDir.'/'.$host.'-index.html'))throw new RuntimeException();}
 $phase='extract code';if(!$archive->extractTo($target))throw new RuntimeException();$archive->close();
 $suiteModule=$module==='notices'?'documents':$module;$key=bin2hex(random_bytes(32));$binding=['instance_id'=>$id,'organization_id'=>$org,'project_id'=>$org,'module_key'=>$suiteModule,'origin'=>'https://'.$host,'key'=>$key];
 if($module==='qa'){
   $phase='QA installation';remainingDirectory($target.'/private');remainingDirectory($target.'/config',0750);
   $qaBinding=['instance_id'=>$id,'organization_id'=>$org,'project_id'=>$org,'local_project_id'=>1,'local_company_id'=>1,'key'=>$key];
   remainingPrivate($target.'/config/local.php',"<?php\nreturn ".var_export(['origin'=>'https://'.$host,'storage'=>$webTarget.'/private','suite'=>$qaBinding,'integration_key'=>$key],true).";\n");
   putenv('QA_STORAGE='.$target.'/private');require $target.'/app/bootstrap.php';Database::install();
   Database::run('INSERT INTO projects(id,name,site,code,created_at) VALUES(1,?,?,?,?)',[ucfirst($side).' Suite test site',ucfirst($side).' Test Site',strtoupper($side).'-TEST',now()]);
   Database::run('INSERT INTO companies(id,name,trade,created_at) VALUES(1,?,?,?)',[ucfirst($side).' Suite test company','General construction',now()]);
   Database::run('INSERT INTO suite_binding VALUES(1,?,?,?,1)',[$id,$org,$org]);
   remainingPrivate($target.'/private/suite-deployment.json',json_encode($binding,JSON_THROW_ON_ERROR));
   $db=Database::db();
 }else{
   $phase='copy empty schema';$db->exec('SET FOREIGN_KEY_CHECKS=0');
   try{foreach($native->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)as$table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException();$ddl=$native->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];$db->exec($ddl);}}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
   $db->exec('CREATE TABLE suite_deployment(id INT PRIMARY KEY,instance_id BIGINT NOT NULL,organization_id BIGINT NOT NULL,project_id BIGINT NOT NULL,module_key VARCHAR(20) NOT NULL) ENGINE=InnoDB');
   $db->prepare('INSERT INTO suite_deployment VALUES(1,?,?,?,?)')->execute([$id,$org,$org,$suiteModule]);
   $db->exec('CREATE TABLE suite_users(suite_user_id BIGINT PRIMARY KEY,local_user_id VARCHAR(36) NOT NULL UNIQUE,enabled TINYINT NOT NULL DEFAULT 1) ENGINE=InnoDB');
   $phase='private gateway';remainingDirectory($target.'/.suite');$sessionAlias=$hosting.'/tmp/'.$side.'-'.$module.'-suite-sessions';remainingDirectory($sessionAlias);
   $binding+=['dsn'=>$dsn,'db_user'=>$credentials['user'],'db_password'=>$credentials['pass'],'session_path'=>$webHosting.'/tmp/'.$side.'-'.$module.'-suite-sessions'];
   remainingPrivate($target.'/.suite/binding.private.php',"<?php\nreturn ".var_export($binding,true).";\n");
   if($module==='safety'){
     $values=['BASE_URL'=>'https://'.$host,'TIMEZONE'=>'Europe/London','DB_HOST'=>$credentials['host'],'DB_PORT'=>(int)$credentials['port'],'DB_NAME'=>$database,'DB_USER'=>$credentials['user'],'DB_PASS'=>$credentials['pass'],'SMTP_HOST'=>'127.0.0.1','SMTP_PORT'=>1,'SMTP_USER'=>'','SMTP_PASS'=>'','MAIL_FROM'=>'test@example.invalid','MAIL_FROM_NAME'=>'Suite test Safety','AUTH_MODE'=>'password','AUTH_PASSWORD'=>bin2hex(random_bytes(32)),'CONSTRUCTION_SUITE_API_KEY'=>$key];
     $text="<?php\n";foreach($values as$f=>$v)$text.='define('.var_export($f,true).','.var_export($v,true).");\n";$text.="define('UPLOAD_DIR', SAFETY_APP_ROOT . '/uploads');\nrequire_once SAFETY_APP_ROOT . '/includes/bootstrap.php';\n";remainingPrivate($target.'/includes/runtime.private.php',$text);
   }elseif($module==='permits'){
     $values=['APP_URL'=>'https://'.$host,'APP_TIMEZONE'=>'Europe/London','APP_DEBUG'=>'false','DB_DRIVER'=>'mysql','DB_HOST'=>$credentials['host'],'DB_PORT'=>$credentials['port'],'DB_DATABASE'=>$database,'DB_USERNAME'=>$credentials['user'],'DB_PASSWORD'=>$credentials['pass'],'SESSION_NAME'=>'__Host-suite-permits','SESSION_COOKIE_SECURE'=>'true','SESSION_COOKIE_HTTPONLY'=>'true','SESSION_COOKIE_SAMESITE'=>'Lax','CONSTRUCTION_SUITE_API_KEY'=>$key,'SMTP_HOST'=>'127.0.0.1','SMTP_PORT'=>'1'];
     $text='';foreach($values as$f=>$v)$text.=$f.'="'.str_replace(['\\','"',"\r","\n"],['\\\\','\\"','',''],$v)."\"\n";remainingPrivate($target.'/.env',$text);
     $vendorSource=$source.'/vendor';$vendorTarget=$target.'/vendor';
     foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vendorSource,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST)as$f){if($f->isLink())throw new RuntimeException();$to=$vendorTarget.substr($f->getPathname(),strlen($vendorSource));if($f->isDir()){if(!is_dir($to)&&!mkdir($to,0750,true))throw new RuntimeException();}elseif(!copy($f->getPathname(),$to))throw new RuntimeException();}
   }else{
     remainingPrivate($target.'/includes/db.local.php',"<?php\nreturn ".var_export(array_replace($credentials,['name'=>$database]),true).";\n");
     remainingPrivate($target.'/includes/suite.local.php',"<?php\ndefine('CONSTRUCTION_SUITE_API_KEY',".var_export($key,true).");\n");
     remainingPrivate($target.'/includes/mail.local.php',"<?php\nreturn ['enabled'=>false];\n");
     file_put_contents($target.'/includes/config.php',"<?php\ndate_default_timezone_set('Europe/London');\ndefine('SITE_NAME','Suite Test Notices');\ndefine('BASE_URL',".var_export('https://'.$host.'/',true).");\n");
   }
   remainingDirectory($target.'/uploads',0750);
   $rules="\n# Suite dedicated instance: protect all private code and tenant files.\nRewriteEngine On\nRewriteRule ^(?:\\.suite|bin|config|database|tests|vendor)(?:/|$) - [R=404,L,NC]\nRewriteRule ^(?:uploads|export|pdfs)/(.*)$ /suite-file.php?file=$0 [QSA,L]\n";
   $old=is_file($target.'/.htaccess')?file_get_contents($target.'/.htaccess'):'';file_put_contents($target.'/.htaccess',$rules.$old);
   remainingPrivate($target.'/.user.ini','auto_prepend_file="'.$webTarget.'/.suite/gate.php'.'"'."\n");
 }
 $phase='synthetic records';require dirname(__DIR__).'/integrations/legacy/seed-test-record.php';
 $fixture=remainingSeed($db,$module,$side,$target);
 remainingPrivate($target.($module==='qa'?'/private':'/.suite').'/test-fixture.json',json_encode($fixture,JSON_THROW_ON_ERROR));
 $phase='register disabled connection';$entry=['id'=>$id,'organization_id'=>$org,'project_id'=>$org,'module_key'=>$suiteModule,'origin'=>'https://'.$host,'isolation_verified'=>false,'gateway_verified'=>false,'summary_verified'=>false];$inventory[]=$entry;
 $catalog=new \Suite\Modules\InstanceCatalog($inventory);$catalog->all();
 $envFile=$suite.'/.env';$env=file_get_contents($envFile);if(preg_match('/^'.preg_quote($keyName,'/').'=/m',$env))throw new RuntimeException();
 $envTemp=$envFile.'.'.bin2hex(random_bytes(8)).'.tmp';remainingPrivate($envTemp,rtrim($env)."\n".$keyName.'='.$key."\n");if(!rename($envTemp,$envFile))throw new RuntimeException();
 $inventoryTemp=$inventoryFile.'.'.bin2hex(random_bytes(8)).'.tmp';remainingPrivate($inventoryTemp,json_encode($inventory,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");if(!rename($inventoryTemp,$inventoryFile))throw new RuntimeException();
 echo json_encode(['ok'=>true,'installed'=>true,'module'=>$module,'side'=>$side,'instance_id'=>$id,'host'=>$host,'emails_sent'=>0,'tenant_ready'=>false])."\n";
}catch(Throwable $error){echo json_encode(['ok'=>false,'failed_phase'=>$phase,'tenant_ready'=>false])."\n";exit(1);}
