<?php
declare(strict_types=1);
$mysql = getenv('SUITE_TEST_MYSQL') === '1';
$path = $mysql ? null : tempnam(sys_get_temp_dir(), 'suite-settings-');
if (!$mysql) { putenv('DB_DRIVER=sqlite'); putenv('DB_DATABASE=' . $path); }
elseif (getenv('DB_DRIVER') !== 'mysql' || !str_starts_with((string) getenv('DB_DATABASE'), 'suite_test_')) {
    throw new RuntimeException('MySQL fixtures require a disposable suite_test_ database.');
}
require_once dirname(__DIR__) . '/app/bootstrap.php';
try {
    $db = suite_db();
    if ($mysql && (int) $db->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn() !== 0) {
        throw new RuntimeException('MySQL fixtures require an empty database.');
    }
    $db->exec(file_get_contents(SUITE_ROOT . '/database/schema.' . ($mysql ? 'mysql' : 'sqlite') . '.sql'));
    $db->exec("INSERT INTO organizations(id,name,slug) VALUES(1,'Alpha','alpha'),(2,'Beta','beta')");
    $db->exec("INSERT INTO projects(id,organization_id,name) VALUES(1,1,'Alpha One'),(2,2,'Beta One')");
    $db->exec("INSERT INTO users(id,email,name,password_hash,is_platform_admin) VALUES
        (1,'owner@example.test','Owner','unused',1),(2,'alpha@example.test','Alpha Admin','unused',0),
        (3,'beta@example.test','Beta Admin','unused',0),(4,'worker@example.test','Worker','unused',0)");
    $db->exec("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES
        (2,1,NULL,'company_admin'),(3,2,NULL,'company_admin'),(4,1,1,'user')");
    $user = fn(int $id): array => $db->query('SELECT * FROM users WHERE id=' . $id)->fetch();
    $check = static function(bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FAIL: ' . $label); };
    $deny = static function(callable $f, string $label) use ($check): void { try { $f(); } catch (RuntimeException $e) { return; } $check(false, $label); };
    $alpha=$user(2); $beta=$user(3); $worker=$user(4); $branding=suite_company_settings(); $repo=suite_companies();
    $input=['name'=>'Alpha Construction','contact_email'=>'office@example.test','brand_colour'=>'#AABBCC'];
    $check(!$branding->ready(), 'Pre-migration deployment has safe defaults');
    $deny(fn()=>$branding->save($alpha,1,$input), 'Unmigrated save denied');
    $applied=suite_migrator()->migrateAll();
    $check(in_array('004_company_settings',$applied,true), 'Company migration applied');
    $check(suite_migrator()->migrateAll()===[], 'Migrations rerun safely');
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aB1cAAAAASUVORK5CYII=');
    $branding->save($alpha,1,$input,$png);
    $check($db->query('SELECT name FROM organizations WHERE id=1')->fetchColumn()==='Alpha Construction','Company name saved');
    $check($branding->forCompany(1)['brand_colour']==='#aabbcc','Colour normalized');
    $check($branding->logoFor($worker,1)['bytes']===$png,'Assigned project user can view own logo');
    $deny(fn()=>$branding->logoFor($beta,1), 'Other company cannot read logo');
    $deny(fn()=>$branding->save($alpha,2,$input,$png),'Cross-company branding denied');
    $deny(fn()=>$branding->save($worker,1,$input,$png),'Worker cannot edit branding');
    $deny(fn()=>$branding->save($alpha,1,array_merge($input,['brand_colour'=>'red; background:url(evil)'])),'Style injection rejected');
    $deny(fn()=>$branding->save($alpha,1,$input,'<svg onload="alert(1)"></svg>'),'Active SVG rejected');
    $deny(fn()=>$branding->save($alpha,1,$input,str_repeat('x',262145)),'Oversized file rejected');
    $check($branding->logoFor($alpha,1)['bytes']===$png,'Failed uploads preserve current logo');
    $branding->save($alpha,1,array_merge($input,['remove_logo'=>1]));
    $check($branding->logoFor($alpha,1)===null,'Logo can be removed');
    $db->exec("INSERT INTO project_modules(project_id,module_key,enabled,external_project_ref,config_json) VALUES(1,'safety',0,'private-mapping','{\"owner_setting\":true}'),(1,'programme',1,'preserved',NULL)");
    $deny(fn()=>$repo->saveProjectModules($alpha,1,2,['programme']),'Foreign project denied');
    $deny(fn()=>$repo->saveProjectModules($worker,1,1,['programme']),'Worker cannot select tools');
    $deny(fn()=>$repo->saveProjectModules($alpha,1,1,['safety']),'Company cannot bypass platform disabled tool');
    $deny(fn()=>$repo->saveProjectModules($alpha,1,1,['invented']),'Unknown tool rejected');
    $check((int)$db->query('SELECT COUNT(*) FROM company_project_modules')->fetchColumn()===0,'Invalid selection rolls back');
    $repo->saveProjectModules($alpha,1,1,['programme']);
    $check(array_column(suite_modules()->allForProject('platform_admin',1,false),'key')===['programme'],'Only selected permitted tool remains');
    $check(suite_modules()->allForProject('user',1)===[],'Preference never bypasses integration readiness');
    $check($db->query("SELECT external_project_ref FROM project_modules WHERE module_key='programme'")->fetchColumn()==='preserved','Platform mapping preserved');
    $check($db->query("SELECT config_json FROM project_modules WHERE module_key='safety'")->fetchColumn()==='{"owner_setting":true}','Platform config preserved');
    $repo->saveProjectModules($alpha,1,1,[]);
    $check(suite_modules()->allForProject('platform_admin',1,false)===[],'Disabled preference suppresses tool');
    $check(count(suite_modules()->allForProject('platform_admin',2,false))===8,'Other project unchanged');
    $db->exec('UPDATE projects SET active=0 WHERE id=1');
    $deny(fn()=>$repo->saveProjectModules($alpha,1,1,['programme']),'Inactive project cannot change tools');
    $db->exec('UPDATE organizations SET active=0 WHERE id=1');
    $deny(fn()=>$branding->save($alpha,1,$input),'Inactive company cannot change branding');
    echo "PASS: Company branding, protected logos, project tool choices, migration safety and platform permission boundaries.\n";
} finally { if ($path !== null) @unlink($path); }
