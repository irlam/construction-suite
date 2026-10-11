<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
$phase='arguments';
try {
    $mode=$argv[1]??'--check';
    if($argc>2||!in_array($mode,['--check','--qa-reporting','--delivery-viewers'],true))throw new RuntimeException();
    require dirname(__DIR__).'/app/bootstrap.php';
    $hosting=dirname((string)realpath(SUITE_ROOT),2);
    $phase='private QA configuration';
    $qa=$hosting.'/qa.defecttracker.uk/httpdocs';$file=$qa.'/config/local.php';
    if(is_link($file))throw new RuntimeException();
    $phase='read QA configuration';$config=is_file($file)?require $file:[];if(!is_array($config))throw new RuntimeException();
    $phase='QA database path';$storage=$config['storage']??$qa.'/private';
    if(!is_string($storage)||!is_file($storage.'/qa.sqlite'))throw new RuntimeException();
    $phase='open QA database';$db=new PDO('sqlite:'.$storage.'/qa.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $phase='QA projects';$projects=$db->query('SELECT id,name FROM projects WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $phase='Suite reporting key';$key=trim((string)\Suite\Support\Env::get('SUITE_INTEGRATION_KEY',''));
    if(strlen($key)<32)throw new RuntimeException();
    if($mode==='--qa-reporting') {
        $phase='configure QA reporting';$config['integration_key']=$key;
        $mask=umask(0077);$temp=$file.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {$h=fopen($temp,'xb');}finally{umask($mask);}
        if(!$h)throw new RuntimeException();
        try {$content="<?php\n// Private deployment configuration. Never commit this file.\nreturn ".var_export($config,true).";\n";
            if(fwrite($h,$content)!==strlen($content)||!fflush($h))throw new RuntimeException();
        }finally{fclose($h);}
        if(!chmod($temp,0600)||!rename($temp,$file))throw new RuntimeException();
    }
    if($mode==='--delivery-viewers') {
        $phase='delivery viewer migration';
        require $hosting.'/sitedeliveries.site/httpdocs/db.php';
        if(!isset($pdo)||!($pdo instanceof PDO))throw new RuntimeException();
        $fields=array_column($pdo->query('SHOW COLUMNS FROM logistics_users')->fetchAll(PDO::FETCH_ASSOC),'Field');
        if(!in_array('role',$fields,true))$pdo->exec("ALTER TABLE logistics_users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'booker'");
        $check=array_column($pdo->query('SHOW COLUMNS FROM logistics_users')->fetchAll(PDO::FETCH_ASSOC),'Field');
        if(!in_array('role',$check,true))throw new RuntimeException();
    }
    echo json_encode(['ok'=>true,'mode'=>$mode,'qa_projects'=>$projects,'qa_reporting_configured'=>strlen((string)($config['integration_key']??''))>=32,'shared_sign_in_enabled'=>false,'database_reset'=>false],JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable $e) {echo json_encode(['ok'=>false,'failed_phase'=>$phase])."\n";exit(1);}
