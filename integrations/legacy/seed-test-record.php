<?php
declare(strict_types=1);
/** Called only by the installer after verifying a named, empty test database. */
function remainingSeed(PDO $db,string $module,string $side,string $root):array
{
 $number=$side==='alpha'?71001:81001;$label=strtoupper($side).' SUITE TEST ONLY';$site=ucfirst($side).' Test Site';
 $picture='suite-'.$side.'-proof.png';$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1EAAAAASUVORK5CYII=',true);
 if($module==='qa'){
   $time=gmdate('Y-m-d\TH:i:s\Z');$suiteUser=$side==='alpha'?8:11;
   $db->prepare('INSERT INTO users(id,name,email,role,suite_id,company_id,created_at) VALUES(1,?,?,?, ?,1,?)')->execute([$label,'fixture-'.$side.'@example.invalid','manager',$suiteUser,$time]);
   $db->exec('INSERT INTO memberships VALUES(1,1)');
   $db->prepare('INSERT INTO locations(id,project_id,name) VALUES(1,1,?)')->execute([$label.' location']);
   $template=$db->query('SELECT id,version,items FROM templates WHERE active=1 ORDER BY id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
   $db->prepare("INSERT INTO inspections(id,project_id,location_id,title,template_id,template_version,checklist,status,assignee_id,creator_id,company_id,created_at,updated_at) VALUES(?,1,1,?,?,?,?,'draft',1,1,1,?,?)")->execute([$number,$label.' — synthetic draft',$template['id'],$template['version'],$template['items'],$time,$time]);
   if(file_put_contents($root.'/private/uploads/'.$picture,$png)!==strlen($png))throw new RuntimeException();
   $db->prepare("INSERT INTO evidence(id,inspection_id,project_id,user_id,item_id,name,mime,size,storage_name,caption,created_at) VALUES(?,?,1,1,'check-1',?,'image/png',?,?,?,?)")->execute([$number,$number,$picture,strlen($png),$picture,'Synthetic test evidence — no real site information',$time]);
   return ['record_id'=>$number,'evidence_id'=>$number,'label'=>$label];
 }
 if(file_put_contents($root.'/uploads/'.$picture,$png)!==strlen($png))throw new RuntimeException();
 if($module==='safety'){
   $db->prepare("INSERT INTO safety_tours(id,tour_date,site,area,lead_name,participants,recipients,responses,photos,status,score_achieved,score_total,score_percent,score_json,signature_name,signature_path) VALUES(?,NOW(),?,?,?,'[]','[]','{}',?,'Open',0,0,0,'{}',?,?)")->execute([$number,$site,$label.' location',$label.' — synthetic tour',json_encode(['uploads/'.$picture]),'TEST ONLY — no person signed','uploads/'.$picture]);
 }elseif($module==='permits'){
   $db->prepare("INSERT INTO users(id,email,password_hash,name,role,status) VALUES('system',?,?,?,'user','inactive')")->execute(['system@example.invalid',password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),'Suite stock-template importer']);
   $wrapper=(new ReflectionClass(\Permits\Db::class))->newInstanceWithoutConstructor();$wrapper->pdo=$db;
   $columns=\Permits\DatabaseMaintenance::ensureFormTemplateColumns($wrapper);if(!empty($columns['errors']))throw new RuntimeException();
   $result=\Permits\FormTemplateSeeder::importFromDirectory($wrapper,$root.'/templates/form-presets');if(!empty($result['errors'])||$result['processed']<1)throw new RuntimeException();
   $template=$db->query('SELECT id FROM form_templates WHERE active=1 ORDER BY name,id LIMIT 1')->fetchColumn();if(!$template)throw new RuntimeException();
   $number='00000000-0000-4000-8000-'.str_pad((string)$number,12,'0',STR_PAD_LEFT);
   $db->prepare("INSERT INTO forms(id,ref_number,template_id,form_data,site_block,status,metadata,requires_approval) VALUES(?,?,?,?,?,'draft',?,1)")->execute([$number,'TEST-SUITE-'.strtoupper($side),$template,json_encode(['siteProject'=>$site,'companyName'=>ucfirst($side).' Suite test company','description'=>$label.' — draft only; no work is authorised.']),$site,json_encode(['suite_test_fixture'=>true])]);
 }else{
   $db->prepare("INSERT INTO cleanup_notices(id,user_name,site_name,location,issued_at,issued_to,issued_by,reason,description,urgency,completed_ok,mcgoff_clear,status,created_at) VALUES(?,?,?,?,NOW(),?,?,?,?,'Normal','No','No','open',NOW())")->execute([$number,'suite-test-fixture',$site,$label.' location',$label.' company',$label.' issuer','Synthetic testing',$label.' — sample notice, no real instruction']);
 }
 return ['record_id'=>$number,'file'=>'uploads/'.$picture,'label'=>$label];
}
