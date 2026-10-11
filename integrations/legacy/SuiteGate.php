<?php
declare(strict_types=1);

/** Front gate for dedicated company/site copies of legacy applications. */
final class SuiteGate
{
    public static function identity(array $binding,array $reply):array
    {
        $i=$reply['identity']??null;
        if(!is_array($i)||!is_int($i['user_id']??null)||$i['user_id']<1||!is_int($i['session_expires_at']??null)||$i['session_expires_at']<=time())throw new RuntimeException('Invalid session',401);
        foreach(['instance_id','organization_id','project_id','module_key']as$field)if(($i[$field]??null)!==$binding[$field])throw new RuntimeException('Wrong project',403);
        if(!in_array($i['role']??'', ['platform_admin','admin','manager','site_manager','user','contractor','viewer'],true))throw new RuntimeException('Unsupported role',403);
        if(!is_string($i['name']??null)||trim($i['name'])===''||strlen($i['name'])>180)throw new RuntimeException('Invalid identity',401);
        return $i;
    }
    public static function request(array $binding,string $endpoint,array $payload,?callable $transport=null):array
    {
        $payload['instance_id']=$binding['instance_id'];
        if($transport)$reply=$transport($endpoint,$payload);
        else{
            $body='';$ch=curl_init('https://suite.defecttracker.uk/api/v1/'.$endpoint.'.php');
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR),CURLOPT_FOLLOWLOCATION=>false,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Construction-Suite-Key: '.$binding['key']],CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>65536)return 0;$body.=$chunk;return strlen($chunk);}]);
            try{$ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);}finally{curl_close($ch);}
            if(!$ok||$status!==200)throw new RuntimeException('Suite unavailable',401);
            $reply=json_decode($body,true,16,JSON_THROW_ON_ERROR);
        }
        if(!is_array($reply)||($reply['ok']??false)!==true)throw new RuntimeException('Suite unavailable',401);
        return $reply;
    }
    public static function checkDatabase(PDO $db,array $b):void
    {
        $row=$db->query('SELECT instance_id,organization_id,project_id,module_key FROM suite_deployment WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        foreach(['instance_id','organization_id','project_id']as$f)if(!$row||(int)$row[$f]!==$b[$f])throw new RuntimeException('Database binding mismatch',503);
        if($row['module_key']!==$b['module_key'])throw new RuntimeException('Database binding mismatch',503);
    }
    public static function allowed(string $module,string $path,string $method,string $role):bool
    {
        // Administration of identities, credentials, hosting and recipients stays in Suite.
        if(preg_match('~(?:^|/)(?:admin[^/]*|tools|includes|src|bin|config|database|tests|vendor)(?:/|\.php|$)~i',$path))return false;
        if($role!=='viewer')return !in_array($path,['/login_admin.php','/includes/auth.php','/confirm.php','/resend_confirm.php'],true);
        if(!in_array($method,['GET','HEAD'],true))return false;
        $read=[
            'safety'=>['/','/index.php','/dashboard.php','/list.php','/actions.php','/action_view.php','/analytics.php','/audit.php','/pdf.php','/export.php','/success.php','/how_it_works.php'],
            'permits'=>['/','/index.php','/dashboard.php','/permit-board.php','/manager-approvals.php','/view-permit-public.php','/view-inspection-public.php','/qr-code.php','/api/current-permits.php'],
            'documents'=>['/','/index.php','/dashboard.php','/forms/clean-up/list.php','/forms/clean-up/pdf.php','/forms/clean-up/success.php','/api/offline_context.php','/api/offline_notices.php','/api/offline_asset.php']
        ];
        return in_array($path,$read[$module]??[],true)||$path==='/suite-file.php';
    }
    public static function localUser(PDO $db,array $b,array $i,bool $create=false):array
    {
        $q=$db->prepare('SELECT local_user_id,enabled FROM suite_users WHERE suite_user_id=?');$q->execute([$i['user_id']]);$map=$q->fetch(PDO::FETCH_ASSOC);
        if(!$map&&$create){
            $db->beginTransaction();
            try{
                $email='suite-'.$b['instance_id'].'-'.$i['user_id'].'@suite.invalid';$password=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                if($b['module_key']==='safety'){
                    $q=$db->prepare("INSERT INTO safety_users(email,name,role,pass_hash) VALUES(?,?,'user',?)");$q->execute([$email,$i['name'],$password]);$id=(string)$db->lastInsertId();
                }elseif($b['module_key']==='permits'){
                    $id=bin2hex(random_bytes(16));$q=$db->prepare("INSERT INTO users(id,email,name,role,status,password_hash) VALUES(?,?,?,'user','active',?)");$q->execute([$id,$email,$i['name'],$password]);
                }else{
                    $q=$db->prepare("INSERT INTO users(username,email,password,role,confirmed) VALUES(?,?,?,'user',1)");$q->execute(['suite-'.$i['user_id'],$email,$password]);$id=(string)$db->lastInsertId();
                }
                $q=$db->prepare('INSERT INTO suite_users(suite_user_id,local_user_id,enabled) VALUES(?,?,1)');$q->execute([$i['user_id'],$id]);$db->commit();
            }catch(Throwable $error){if($db->inTransaction())$db->rollBack();throw $error;}
            return self::localUser($db,$b,$i);
        }
        if(!$map||!(int)$map['enabled'])throw new RuntimeException('Account unavailable',403);
        $table=$b['module_key']==='safety'?'safety_users':'users';$q=$db->prepare('SELECT * FROM '.$table.' WHERE id=?');$q->execute([$map['local_user_id']]);$user=$q->fetch(PDO::FETCH_ASSOC);
        if(!$user||($b['module_key']==='permits'&&($user['status']??'')!=='active'))throw new RuntimeException('Account unavailable',403);
        return $user;
    }
    public static function applyIdentity(array $b,array $i,array $u):void
    {
        $manager=in_array($i['role'],['platform_admin','admin','manager','site_manager'],true);
        if($b['module_key']==='safety')$_SESSION['auth']=['id'=>(int)$u['id'],'name'=>$i['name'],'email'=>$u['email'],'role'=>$manager?'admin':'user'];
        elseif($b['module_key']==='permits'){
            $_SESSION['user_id']=$u['id'];$_SESSION['user_email']=$u['email'];$_SESSION['user_name']=$i['name'];$_SESSION['user_role']=$manager?'manager':'user';
            $_SESSION['login_time']=$_SESSION['authenticated_at']??time();$_SESSION['authenticated_at']??=time();$_SESSION['last_activity_at']=time();
        }else{$_SESSION['user']=$u['username'];$_SESSION['role']=$_SESSION['user_role']=$manager?'admin':'user';}
    }
}
