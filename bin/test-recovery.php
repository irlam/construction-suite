<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$phase='arguments';$backups=[];$clones=[];
try{
 if($argc!==2||$argv[1]!=='--verify')throw new RuntimeException();
 require dirname(__DIR__).'/app/bootstrap.php';$hosting=dirname((string)realpath(SUITE_ROOT),2);
 $work=$hosting.'/tmp/suite-recovery-'.bin2hex(random_bytes(12));if(!mkdir($work,0700)||realpath($work)!==$work||is_link($work))throw new RuntimeException();
 $pack=static function(array $rows):array{return array_map(static fn($row)=>array_map(static fn($v)=>is_string($v)?['base64'=>base64_encode($v)]:$v,$row),$rows);};
 $unpack=static function(array $rows):array{return array_map(static fn($row)=>array_map(static fn($v)=>is_array($v)?base64_decode($v['base64'],true):$v,$row),$rows);};
 $digest=static function(array $rows)use($pack):string{$values=array_map(fn($r)=>json_encode($pack([$r])[0],JSON_THROW_ON_ERROR),$rows);sort($values,SORT_STRING);return hash('sha256',implode("\n",$values));};
 $recover=static function(PDO $db,string $label,?array $selection=null)use($work,$pack,$unpack,$digest,&$backups,&$clones,&$phase):array{
  $phase=$label.' backup';$driver=$db->getAttribute(PDO::ATTR_DRIVER_NAME);$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
  if($driver==='sqlite'){
   $file=$work.'/'.$label.'.sqlite';$db->exec('VACUUM INTO '.$db->quote($file));if(!chmod($file,0600))throw new RuntimeException();$backups[]=$file;$restored=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
   $tables=$db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);$rows=0;
   if($restored->query('PRAGMA integrity_check')->fetchColumn()!=='ok')throw new RuntimeException();
   foreach($tables as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException();$original=$db->query('SELECT * FROM "'.$table.'"')->fetchAll();$copy=$restored->query('SELECT * FROM "'.$table.'"')->fetchAll();if(!hash_equals($digest($original),$digest($copy)))throw new RuntimeException();$rows+=count($original);}
   return[count($tables),$rows];
  }
  if($driver!=='mysql')throw new RuntimeException();$tables=$selection?array_keys($selection):$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$archive=[];$rows=0;
  $db->beginTransaction();try{foreach($tables as $table){if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table)||str_starts_with($table,'suite_restore_'))throw new RuntimeException();$sql=$selection[$table][0]??('SELECT * FROM `'.$table.'`');$args=$selection[$table][1]??[];$q=$db->prepare($sql);$q->execute($args);$data=$q->fetchAll();$schema=$db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];$archive[$table]=['schema'=>$schema,'rows'=>$pack($data),'checksum'=>$digest($data)];}$db->commit();}catch(Throwable $e){$db->rollBack();throw $e;}
  $file=$work.'/'.$label.'.json';$mask=umask(0077);try{$h=fopen($file,'xb');}finally{umask($mask);}if(!$h)throw new RuntimeException();$bytes=json_encode($archive,JSON_THROW_ON_ERROR);try{if(fwrite($h,$bytes)!==strlen($bytes)||!fflush($h))throw new RuntimeException();}finally{fclose($h);}$backups[]=$file;
  $phase=$label.' restore';$saved=json_decode((string)file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
  foreach($saved as $table=>$entry){$clone='suite_restore_'.bin2hex(random_bytes(12));$db->exec('CREATE TABLE `'.$clone.'` LIKE `'.$table.'`');$clones[]=[$db,$clone];$data=$unpack($entry['rows']);if($data){$generated=array_column(array_filter($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC),fn($c)=>preg_match('/\b(?:VIRTUAL|STORED) GENERATED\b/i',$c['Extra']??'')===1),'Field');$columns=array_values(array_diff(array_keys($data[0]),$generated));foreach($columns as $column)if(!preg_match('/^[a-zA-Z0-9_]+$/D',$column))throw new RuntimeException();$q=$db->prepare('INSERT INTO `'.$clone.'` (`'.implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');foreach($data as $row)$q->execute(array_map(fn($column)=>$row[$column],$columns));}$copy=$db->query('SELECT * FROM `'.$clone.'`')->fetchAll();if(!hash_equals($entry['checksum'],$digest($copy)))throw new RuntimeException();$rows+=count($copy);$db->exec('DROP TABLE `'.$clone.'`');array_pop($clones);}
  return[count($tables),$rows];
 };
 $fileCount=0;$fileBytes=0;
 $recoverFiles=static function(string $root)use($work,&$backups,&$phase,&$fileCount,&$fileBytes):void{
  $root=realpath($root);if(!$root||!is_dir($root))throw new RuntimeException();$phase='file restore';
  $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
  foreach($files as $source){if($source->isLink())throw new RuntimeException();if(!$source->isFile())continue;
   $path=$source->getPathname();$size=$source->getSize();$file=$work.'/file-'.bin2hex(random_bytes(12));
   $mask=umask(0077);try{$out=fopen($file,'xb');}finally{umask($mask);}if(!$out)throw new RuntimeException();$backups[]=$file;$in=fopen($path,'rb');if(!$in)throw new RuntimeException();
   try{$copied=stream_copy_to_stream($in,$out);if($copied!==$size||!fflush($out))throw new RuntimeException();}finally{fclose($in);fclose($out);}
   if((fileperms($file)&0777)!==0600||!hash_equals(hash_file('sha256',$path),hash_file('sha256',$file)))throw new RuntimeException();
   $fileCount++;$fileBytes+=$size;unlink($file);array_pop($backups);
  }
 };
 $results=['suite'=>$recover(suite_db(),'suite')];
 foreach(['alpha','beta'] as $side){
  $root=$hosting.'/'.$side.'.defectnotice.site/httpdocs';foreach(['Gateway','ProjectScope','Configuration','SessionStore','SignIn','CookiePolicy'] as $class)if(!class_exists('DefectTracker\\Suite\\'.$class))require_once $root.'/includes/Suite/'.$class.'.php';$b=DefectTracker\Suite\Configuration::load($root);$results[$side.'_defects']=$recover(DefectTracker\Suite\Configuration::connect($b),$side.'_defects');
  $root=$hosting.'/'.$side.'.programme.defecttracker.uk/httpdocs';$cfg=(static fn($p)=>require $p)($root.'/app/config/config.php');$db=new PDO($cfg['db']['dsn'],$cfg['db']['user'],$cfg['db']['pass'],$cfg['db']['options']);$results[$side.'_programme']=$recover($db,$side.'_programme');
  $root=$hosting.'/'.$side.'.qa.defecttracker.uk/httpdocs';$cfg=(static fn($p)=>require $p)($root.'/config/local.php');$storage=$cfg['storage']??$root.'/private';$results[$side.'_qa']=$recover(new PDO('sqlite:'.$storage.'/qa.sqlite'),$side.'_qa');
  foreach(['safety'=>'sitesafety.site','permits'=>'sitepermits.site','notices'=>'sitenotices.site'] as $module=>$domain){$b=(static fn($p)=>require $p)($hosting.'/'.$side.'.'.$domain.'/httpdocs/.suite/binding.private.php');$results[$side.'_'.$module]=$recover(new PDO($b['dsn'],$b['db_user'],$b['db_password']),$side.'_'.$module);}
 }
 foreach(['alpha','beta'] as $side)foreach(['defectnotice.site','programme.defecttracker.uk','qa.defecttracker.uk','sitesafety.site','sitepermits.site','sitenotices.site'] as $domain)$recoverFiles($hosting.'/'.$side.'.'.$domain.'/httpdocs');
 $phase='delivery company records';require $hosting.'/sitedeliveries.site/httpdocs/db.php';$bindings=(static fn($p)=>require $p)($hosting.'/sitedeliveries.site/httpdocs/includes/suite-instances.local.php');
 foreach(['alpha'=>5,'beta'=>6] as $side=>$id){$b=$bindings[$id];$results[$side.'_deliveries']=$recover($pdo,$side.'_deliveries',['deliveries'=>['SELECT * FROM deliveries WHERE site_id=? AND company_id=?',[$b['local_site_id'],$b['local_company_id']]]]);}
 echo json_encode(['ok'=>true,'database_restore_checks'=>count($results),'tables_verified'=>array_sum(array_column(array_values($results),0)),'rows_verified'=>array_sum(array_column(array_values($results),1)),'files_restored'=> $fileCount,'file_bytes_verified'=>$fileBytes,'private_backup_permissions'=>'0600','live_records_changed'=>false]);
}catch(Throwable $e){echo json_encode(['ok'=>false,'phase'=>$phase,'error_type'=>get_class($e),'database_error'=>$e instanceof PDOException?($e->errorInfo[1]??null):null]);$failed=true;}
finally{
 foreach($clones as [$db,$table])try{$db->exec('DROP TABLE `'.$table.'`');}catch(Throwable $ignored){}
 foreach($backups as $file)if(isset($work)&&dirname($file)===$work&&realpath($file)===$file&&!is_link($file))unlink($file);
 if(isset($work)&&is_dir($work)&&realpath($work)===$work&&!is_link($work))rmdir($work);
}
if(!empty($failed))exit(1);
