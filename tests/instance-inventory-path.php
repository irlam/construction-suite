<?php
declare(strict_types=1);
// Reproduce an absolute CLI-jail path being unavailable in the web filesystem.
$root = sys_get_temp_dir() . '/suite-inventory-' . bin2hex(random_bytes(8)) . '/suite.defecttracker.uk/httpdocs';
mkdir($root . '/private', 0700, true); mkdir($root . '/public', 0700);
define('SUITE_ROOT', $root);
require dirname(__DIR__) . '/app/Support/Env.php';
require dirname(__DIR__) . '/app/Modules/InstanceCatalog.php';
function suite_config(string $key): array { return ['programme'=>['url'=>'https://programme.defecttracker.uk']]; }
$file = $root . '/private/programme-staging-instances.json';
$items = [['id'=>1,'organization_id'=>7,'project_id'=>7,'module_key'=>'programme','origin'=>'https://alpha.programme.defecttracker.uk','isolation_verified'=>false,'gateway_verified'=>false]];
file_put_contents($file, json_encode($items)); chmod($file, 0600);
$check = static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
$deny = static function(callable $call,string $message)use($check):void{try{$call();}catch(RuntimeException $e){return;}$check(false,$message);};
$catalog = new \Suite\Modules\InstanceCatalog();
try {
    putenv('SUITE_INSTANCES_FILE='.$file);
    $check($catalog->all()[1]['ready']===false,'Canonical inventory still loads');
    $alias='/suite.defecttracker.uk/httpdocs/private/programme-staging-instances.json';
    putenv('SUITE_INSTANCES_FILE='.$alias);
    $check(\Suite\Modules\InstanceCatalog::inventoryFile()===$file,'Exact jail alias resolves to same private file');
    $check($catalog->all()[1]['project_id']===7,'Alias preserves binding');
    chmod($file,0644);clearstatcache();
    $deny(fn()=>$catalog->all(),'Unsafe standard inventory permissions rejected');
    chmod($file,0600);clearstatcache();
    rename($file,$file.'.original');symlink($file.'.original',$file);clearstatcache();
    $deny(fn()=>$catalog->all(),'Symlink private file rejected');
    unlink($file);rename($file.'.original',$file);clearstatcache();
    putenv('SUITE_INSTANCES_FILE=/wrong-domain/httpdocs/private/programme-staging-instances.json');
    $deny(fn()=>$catalog->all(),'Unrelated missing path never falls back');
    putenv('SUITE_INSTANCES_FILE='.$root.'/public/inventory.json');
    copy($file,$root.'/public/inventory.json');
    $deny(fn()=>$catalog->all(),'Public inventory rejected');
    unlink($root.'/public/inventory.json');
    putenv('SUITE_INSTANCES_FILE='.$alias);
    rename($root.'/private',$root.'/private-original');symlink($root.'/private-original',$root.'/private');clearstatcache();
    $deny(fn()=>$catalog->all(),'Symlink private directory rejected');
    unlink($root.'/private');rename($root.'/private-original',$root.'/private');clearstatcache();
    chmod($root.'/private',0755);clearstatcache();
    $deny(fn()=>$catalog->all(),'Unsafe private directory rejected');
    chmod($root.'/private',0700);clearstatcache();
    putenv('SUITE_INSTANCES_FILE=');
    $check($catalog->all()===[],'Unconfigured deployment stays empty');
    echo "PASS: canonical/CLI-jail inventory paths, same private binding, unrelated/public/symlink/permission rejection and no implicit inventory activation.\n";
} finally {
    unlink($file);rmdir($root.'/private');rmdir($root.'/public');rmdir($root);rmdir(dirname($root));rmdir(dirname($root,2));
}
