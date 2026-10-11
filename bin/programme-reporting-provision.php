<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 2 || !in_array($argv[1],['--check','--apply'],true)) exit(2);
ini_set('display_errors','0'); ini_set('log_errors','0');
try {
    require dirname(__DIR__).'/app/bootstrap.php';
    $root = dirname((string)realpath(SUITE_ROOT),2).'/programme.defecttracker.uk/httpdocs';
    $file = $root.'/app/config/runtime.private.php';
    if (!is_file($file) || is_link($file)) throw new RuntimeException();
    $programmeConfig = require $file;
    if (!is_array($programmeConfig) || empty($programmeConfig['db']['dsn'])) throw new RuntimeException();
    $key = trim((string)\Suite\Support\Env::get('SUITE_INTEGRATION_KEY',''));
    if (strlen($key)<32) throw new RuntimeException();
    if ($argv[1]==='--apply') {
        $programmeConfig['suite_reporting_key']=$key;
        $temp=$file.'.'.bin2hex(random_bytes(8)).'.tmp'; $mask=umask(0077);
        try { $handle=fopen($temp,'xb'); } finally { umask($mask); }
        if (!$handle) throw new RuntimeException();
        $content="<?php\n// Private deployment configuration. Never commit.\nreturn ".var_export($programmeConfig,true).";\n";
        try { if(fwrite($handle,$content)!==strlen($content)||!fflush($handle))throw new RuntimeException(); }
        finally { fclose($handle); }
        if (!chmod($temp,0600)||!rename($temp,$file)) throw new RuntimeException();
    }
    echo json_encode(['ok'=>true,'mode'=>$argv[1],'reporting_configured'=>isset($programmeConfig['suite_reporting_key']),'shared_sign_in_enabled'=>false,'database_settings_preserved'=>true])."\n";
} catch (Throwable $error) { fwrite(STDERR,"Programme reporting configuration could not be prepared.\n"); exit(1); }
