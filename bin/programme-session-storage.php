<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if($argc!==2||!in_array($argv[1],['--check','--apply'],true))exit(2);
ini_set('display_errors','0');ini_set('log_errors','0');
try {
    require dirname(__DIR__).'/app/bootstrap.php';
    $hosting=dirname((string)realpath(SUITE_ROOT),2);
    $source=__DIR__.'/programme-suite-http.php';
    if(!is_file($source)||!hash_equals('7880cd96da7985cb4dba062330d534b3e96aa39584693a423801397f9890296d',hash_file('sha256',$source)))throw new RuntimeException();
    require $hosting.'/alpha.programme.defecttracker.uk/httpdocs/app/Lib/SuiteBinding.php';
    $files=[];
    foreach(['alpha'=>[1,7],'beta'=>[2,8]]as$host=>[$instance,$organization]){
        $root=$hosting.'/'.$host.'.programme.defecttracker.uk/httpdocs';
        $binding=\App\Lib\SuiteBinding::load($root);
        if($binding['instance_id']!==$instance||$binding['organization_id']!==$organization||$binding['project_id']!==$organization)throw new RuntimeException();
        $path=$hosting.'/tmp/programme-'.$host.'-suite-sessions';
        if(is_link($path)||!is_dir($hosting.'/tmp'))throw new RuntimeException();
        // Plesk web PHP uses the full host path even when CLI is jailed.
        $binding['session_path']='/var/www/vhosts/hosting215226.ae97b.netcup.net/tmp/programme-'.$host.'-suite-sessions';
        $files[]=[$root.'/app/config/runtime.suite.private.php',$path,$binding];
    }
    if($argv[1]==='--apply')foreach($files as[$file,$path,$binding]){
        $mask=umask(0077);
        try {
            if(!is_dir($path)&&!mkdir($path,0700))throw new RuntimeException();
            if(!chmod($path,0700))throw new RuntimeException();
            $temp=$file.'.'.bin2hex(random_bytes(6)).'.tmp';$handle=fopen($temp,'xb');
        }finally{umask($mask);}
        if(!$handle)throw new RuntimeException();
        $content="<?php\ndeclare(strict_types=1);\n".\App\Lib\SuiteBinding::guard()."\nreturn ".var_export($binding,true).";\n";
        try{if(fwrite($handle,$content)!==strlen($content)||!fflush($handle))throw new RuntimeException();}finally{fclose($handle);}
        if(!chmod($temp,0600)||!rename($temp,$file))throw new RuntimeException();
        $target=dirname($file,2).'/Lib/SuiteHttp.php';
        if(is_link($target)||!copy($source,$target)||!hash_equals(hash_file('sha256',$source),hash_file('sha256',$target)))throw new RuntimeException();
    }
    echo json_encode(['ok'=>true,'mode'=>$argv[1],'separate_private_session_directories'=>$argv[1]==='--apply','credentials_preserved'=>true,'test_window_opened'=>false])."\n";
}catch(Throwable $error){fwrite(STDERR,"Programme session storage could not be configured.\n");exit(1);}
