<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SUITE_TEST_MYSQL') !== '1'
    || getenv('DB_DRIVER') !== 'mysql' || !str_starts_with((string) getenv('DB_DATABASE'), 'suite_test_')) {
    throw new RuntimeException('Gateway fixtures require a disposable suite_test_ MySQL database.');
}
require_once dirname(__DIR__) . '/app/bootstrap.php';
$db = suite_db();
if ((int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() !== 0) {
    throw new RuntimeException('Gateway fixtures require an empty database.');
}
$db->exec(file_get_contents(SUITE_ROOT . '/database/schema.mysql.sql'));
suite_migrator()->migrateAll();
$db->exec("INSERT INTO organizations(id,name,slug) VALUES(1,'Alpha','alpha'),(2,'Beta','beta')");
$db->exec("INSERT INTO projects(id,organization_id,name) VALUES(1,1,'Alpha'),(2,2,'Beta')");
$db->exec("INSERT INTO users(id,email,name,password_hash) VALUES(1,'worker@example.test','Worker','unused')");
$db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(1,1,1,'user')");
$user=$db->query('SELECT * FROM users WHERE id=1')->fetch();
$inventory=[['id'=>1,'organization_id'=>1,'project_id'=>1,'module_key'=>'permits','origin'=>'https://alpha.sitepermits.site','isolation_verified'=>true,'gateway_verified'=>true],
    ['id'=>2,'organization_id'=>2,'project_id'=>2,'module_key'=>'permits','origin'=>'https://beta.sitepermits.site','isolation_verified'=>true,'gateway_verified'=>true]];
$catalog=new \Suite\Modules\InstanceCatalog($inventory);
$handoff=new \Suite\Auth\ModuleHandoff($catalog);
$sessions=new \Suite\Auth\ModuleSession($catalog);
$key=bin2hex(random_bytes(32)); putenv('SUITE_INSTANCE_KEY_1='.$key); putenv('SUITE_INSTANCE_KEY_2='.$key);
$check=static function(bool $ok,string $label):void {if(!$ok)throw new RuntimeException('FAIL: '.$label);};
$deny=static function(callable $f,string $label)use($check):void {try{$f();}catch(RuntimeException $e){return;}$check(false,$label);};
for ($race=0;$race<4;$race++) {
    $state=bin2hex(random_bytes(32)); $code=$handoff->issue($user,1,$state);
    $workers=[];
    try {
        for($i=0;$i<2;$i++) {
            $process=proc_open([PHP_BINARY, __DIR__.'/module-mysql-worker.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('Worker did not start.');
            $workers[]=[$process,$pipes];
            fwrite($pipes[0],json_encode(['inventory'=>$inventory,'code'=>$code,'state'=>$state,'key'=>$key],JSON_THROW_ON_ERROR)."\n");
        }
        foreach($workers as [$process,$pipes]) {
            $read=[$pipes[1]];$write=null;$except=null;
            $check(stream_select($read,$write,$except,10)>0 && trim((string)fgets($pipes[1]))==='READY','Worker rendezvous');
        }
        foreach($workers as [$process,$pipes]) {fwrite($pipes[0],"GO\n");fclose($pipes[0]);}
        $accepted=0;
        foreach($workers as [$process,$pipes]) {
            $read=[$pipes[1]];$write=null;$except=null;
            $check(stream_select($read,$write,$except,10)>0,'Worker result timeout');
            $result=json_decode((string)fgets($pipes[1]),true,8,JSON_THROW_ON_ERROR);
            if(!empty($result['accepted']))$accepted++;
            $stderr=stream_get_contents($pipes[2]);
            fclose($pipes[1]);fclose($pipes[2]);
            $check(proc_close($process)===0 && $stderr==='','Worker completed without errors');
        }
        $workers=[];
        $check($accepted===1,'Concurrent code exchange grants exactly one session');
        $check((int)$db->query('SELECT COUNT(*) FROM module_sessions')->fetchColumn()===$race+1,'One session persisted per race');
        $deny(fn()=>$handoff->redeem(1,$code,$state,$key),'Consumed code cannot replay');
    } finally {
        foreach($workers as [$process,$pipes]) {
            if(is_resource($process)){proc_terminate($process);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}
        }
    }
}
$state=bin2hex(random_bytes(32));$code=$handoff->issue($user,1,$state);
$db->exec('RENAME TABLE module_sessions TO unavailable_module_sessions');
try {
    $deny(fn()=>$handoff->redeem(1,$code,$state,$key),'Failed session creation denies grant');
    $stmt=$db->prepare('SELECT used_at FROM module_handoffs WHERE code_hash=?');$stmt->execute([hash('sha256',$code)]);
    $check($stmt->fetchColumn()===null,'Session storage failure rolls code consumption back on MySQL');
} finally {$db->exec('RENAME TABLE unavailable_module_sessions TO module_sessions');}
$identity=$handoff->redeem(1,$code,$state,$key);$token=$identity['session_token'];
$check($sessions->validate(1,$token,$key)['user_id']===1,'Recovered grant yields a valid session');
$deny(fn()=>$sessions->validate(2,$token,$key),'Other company cannot validate session');
$db->exec("UPDATE memberships SET role_key='site_manager' WHERE user_id=1");
$check($sessions->validate(1,$token,$key)['role']==='site_manager','Current role refreshed on MySQL');
$db->exec("INSERT INTO company_project_modules(project_id,module_key,enabled) VALUES(1,'permits',0)");
$deny(fn()=>$sessions->validate(1,$token,$key),'Company tool choice blocks existing session on MySQL');
$db->exec("UPDATE company_project_modules SET enabled=1 WHERE project_id=1");
$db->exec('DELETE FROM memberships WHERE user_id=1');
$deny(fn()=>$sessions->validate(1,$token,$key),'Membership removal blocks existing session on MySQL');
echo "PASS: MySQL migrations, four concurrent one-use exchanges, rollback, audience checks and fresh permission revocation.\n";
