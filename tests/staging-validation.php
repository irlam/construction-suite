<?php
declare(strict_types=1);
// Every policy, inventory and database below lives in a fresh temporary tree.
$root = sys_get_temp_dir() . '/suite-validation-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
$copy = static function(string $from, string $to) use (&$copy): void {
    mkdir($to, 0700);
    foreach (scandir($from) as $name) {
        if ($name === '.' || $name === '..') continue;
        if (is_dir($from . '/' . $name)) $copy($from . '/' . $name, $to . '/' . $name);
        else copy($from . '/' . $name, $to . '/' . $name);
    }
};
$remove = static function(string $path) use (&$remove): void {
    if (is_dir($path) && !is_link($path)) {
        foreach (scandir($path) as $name) if ($name !== '.' && $name !== '..') $remove($path . '/' . $name);
        rmdir($path);
    } else unlink($path);
};
try {
    foreach (['app','config','database'] as $dir) $copy(dirname(__DIR__) . '/' . $dir, $root . '/' . $dir);
    mkdir($root . '/private', 0700);
    $mysql = getenv('SUITE_TEST_MYSQL') === '1';
    if (!$mysql) { putenv('DB_DRIVER=sqlite'); putenv('DB_DATABASE=' . $root . '/fixture.sqlite'); }
    elseif (getenv('DB_DRIVER') !== 'mysql' || getenv('DB_DATABASE') !== 'suite_test_staging_validation') {
        throw new RuntimeException('MySQL validation fixtures require the dedicated disposable database.');
    }
    putenv('SUITE_INSTANCES_FILE=' . $root . '/private/programme-staging-instances.json');
    require $root . '/app/bootstrap.php';
    $db = suite_db();
    if ($mysql && (int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn() !== 0) throw new RuntimeException('MySQL fixture database must be empty.');
    $dialect = $mysql ? 'mysql' : 'sqlite';
    $db->exec(file_get_contents(SUITE_ROOT . '/database/schema.' . $dialect . '.sql'));
    foreach (['001_project_modules','002_module_handoffs','003_module_sessions','004_company_settings'] as $migration) $db->exec(file_get_contents(SUITE_ROOT . '/database/migrations/' . $migration . '.' . $dialect . '.sql'));
    $db->exec("INSERT INTO organizations(id,name,slug) VALUES(7,'Alpha','programme-alpha-staging'),(8,'Beta','programme-beta-staging'),(9,'Production','production')");
    $db->exec("INSERT INTO projects(id,organization_id,name) VALUES(7,7,'Alpha'),(8,8,'Beta'),(9,9,'Production')");
    for ($id=1; $id<=5; $id++) {
        $q=$db->prepare('INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES(?,?,?,?,?)');
        $q->execute([$id,'fixture'.$id.'@example.test','Fixture','unused',$id===1?1:0]);
    }
    $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,7,7,'manager'),(3,7,7,'viewer'),(4,8,8,'user'),(5,7,7,'user')");
    $items=[];
    foreach (['alpha'=>[1,7], 'beta'=>[2,8]] as $site=>[$id,$org]) $items[]=['id'=>$id,'organization_id'=>$org,'project_id'=>$org,'module_key'=>'programme','origin'=>'https://'.$site.'.programme.defecttracker.uk','isolation_verified'=>false,'gateway_verified'=>false];
    $inventory=$root.'/private/programme-staging-instances.json';
    file_put_contents($inventory,json_encode($items));chmod($inventory,0600);
    $key=bin2hex(random_bytes(32)); putenv('SUITE_INSTANCE_KEY_1='.$key);putenv('SUITE_INSTANCE_KEY_2='.bin2hex(random_bytes(32)));
    $policyFile=$root.'/private/staging-validation.json';
    $policy=['version'=>1,'enabled'=>true,'started_at'=>100,'expires_at'=>1000,'run_id'=>bin2hex(random_bytes(16)),
        'inventory_sha256'=>hash_file('sha256',$inventory),'instances'=>[['instance_id'=>1,'user_ids'=>[2,3]],['instance_id'=>2,'user_ids'=>[4]]]];
    $write=static function(array $p)use($policyFile):void{file_put_contents($policyFile,json_encode($p));chmod($policyFile,0600);clearstatcache();};
    $user=static function(int $id)use($db):array{$q=$db->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();};
    $handoff=suite_handoff();$sessions=new \Suite\Auth\ModuleSession(suite_instances());$state=bin2hex(random_bytes(32));
    $check=static function(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);};
    $deny=static function(callable $call,string $message)use($check):void{try{$call();}catch(Throwable $e){return;}$check(false,$message);};
    $deny(fn()=>$handoff->issue($user(2),1,$state,101),'Missing policy must deny');
    $write($policy);
    $code=$handoff->issue($user(2),1,$state,101);
    $identity=$handoff->redeem(1,$code,$state,$key,102);
    $check($identity['session_expires_at']===1000,'Validation session capped at policy expiry');
    $token=$identity['session_token'];
    $check($sessions->validate(1,$token,$key,103)['role']==='manager','Named manager permitted');
    $check(!suite_instances()->find(1)['ready'],'Validation never changes readiness');
    $db->exec("UPDATE memberships SET role_key='viewer' WHERE user_id=2");
    $check($sessions->validate(1,$token,$key,103)['role']==='viewer','Viewer downgrade refreshed');
    $deny(fn()=>$handoff->issue($user(1),1,$state,103),'Platform owner not a fixture account');
    $deny(fn()=>$handoff->issue($user(5),1,$state,103),'Unlisted fixture account denied');
    $deny(fn()=>$handoff->issue($user(4),1,$state,103),'Foreign-company fixture denied');
    $bad=$policy;$bad['instances'][0]['user_ids'][]=4;$write($bad);
    $deny(fn()=>$handoff->issue($user(4),1,$state,103),'Allowlisting cannot replace project membership');
    $write($policy);
    $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,9,9,'user')");
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Production-assigned account denied');
    $db->exec('DELETE FROM memberships WHERE organization_id=9');
    $db->exec('UPDATE organizations SET active=0 WHERE id=7');
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Inactive company still denied');
    $db->exec('UPDATE organizations SET active=1 WHERE id=7');
    $db->exec("INSERT INTO company_project_modules(project_id,module_key,enabled) VALUES(7,'programme',0)");
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Disabled company tool still denied');
    $db->exec('DELETE FROM company_project_modules');
    $db->exec('DELETE FROM memberships WHERE user_id=2');
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Membership revocation still denied');
    $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(2,7,7,'manager')");
    foreach (['enabled'=>false,'started_at'=>200,'expires_at'=>4000,'inventory_sha256'=>str_repeat('0',64),'instances'=>[['instance_id'=>1,'user_ids'=>[2]],['instance_id'=>1,'user_ids'=>[3]]]] as $field=>$value) {
        $bad=$policy;$bad[$field]=$value;$write($bad);
        $deny(fn()=>$sessions->validate(1,$token,$key,103),'Invalid policy must revoke: '.$field);
    }
    $write($policy);
    chmod($policyFile,0644);clearstatcache();
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Readable policy rejected');
    chmod($policyFile,0600);clearstatcache();
    rename($policyFile,$policyFile.'.original');symlink($policyFile.'.original',$policyFile);clearstatcache();
    $deny(fn()=>$sessions->validate(1,$token,$key,103),'Symlink policy rejected');
    unlink($policyFile);rename($policyFile.'.original',$policyFile);clearstatcache();
    $deny(fn()=>$handoff->issue($user(2),1,$state,1000),'Window expiry blocks launches');
    $deny(fn()=>$sessions->validate(1,$token,$key,1000),'Window expiry blocks sessions');
    $pending=$handoff->issue($user(2),1,$state,103);
    $bad=$policy;$bad['run_id']=bin2hex(random_bytes(16));$write($bad);
    $deny(fn()=>$sessions->validate(1,$token,$key,104),'Replacement run cannot revive previous sessions');
    $deny(fn()=>$handoff->redeem(1,$pending,$state,$key,104),'Replacement run cannot redeem old grants');
    $write($policy);
    $ready=$items;
    foreach($ready as &$i){$i['isolation_verified']=true;$i['gateway_verified']=true;}unset($i);
    file_put_contents($inventory,json_encode($ready));clearstatcache();
    $deny(fn()=>$sessions->validate(1,$token,$key,104),'Validation session cannot become a ready-instance session');
    $deny(fn()=>$handoff->redeem(1,$pending,$state,$key,104),'Validation grant cannot become a ready-instance grant');
    file_put_contents($inventory,json_encode($items));clearstatcache();
    unlink($policyFile);
    $deny(fn()=>$sessions->validate(1,$token,$key,104),'Removing policy closes window immediately');
    echo "PASS: named pilot access, expiry, current roles, project/company/tool/revocation gates, private policy and run-bound grants/sessions; readiness unchanged.\n";
} finally { $remove($root); }
