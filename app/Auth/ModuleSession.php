<?php
declare(strict_types=1);
namespace Suite\Auth;
use Suite\Database\Connection;
use Suite\Modules\InstanceCatalog;
use Suite\Support\Env;
use RuntimeException;

/** Opaque app sessions: current Suite access is checked on every validation. */
final class ModuleSession
{
    public function __construct(private readonly InstanceCatalog $catalog) {}

    private function audience(array $i, ?array $validation = null): string
    {
        $binding=[$i['id'],$i['organization_id'],$i['project_id'],$i['module_key'],$i['origin']];
        if ($validation !== null) $binding[]=['validation',$validation['run_id'],$validation['expires_at']];
        return hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
    }

    private function server(int $id, string $key): array
    {
        $instance=$this->catalog->find($id);
        $expected=$instance ? (string) Env::get($instance['key_env'],'') : '';
        if (!$instance || strlen($expected)<32 || !hash_equals($expected,$key)) throw new RuntimeException('Access denied.');
        return $instance;
    }

    private function identity(array $user, array $context, int $now): array
    {
        $i=$context['instance'];
        return ['user_id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],
            'organization_id'=>$i['organization_id'],'project_id'=>$i['project_id'],
            'instance_id'=>$i['id'],'module_key'=>$i['module_key'],'role'=>$context['role'],'issued_at'=>$now];
    }

    /** Internal use after a successful one-use handoff; never a public issue API. */
    public function issue(array $user, int $id, ?int $now=null): array
    {
        $now??=time();
        $context=(new ModuleHandoff($this->catalog))->authorize($user,$id,$now);
        $expires=min($now+28800,$context['validation']['expires_at']??PHP_INT_MAX);
        $token=bin2hex(random_bytes(32));
        $stmt=Connection::pdo()->prepare('INSERT INTO module_sessions (token_hash,audience_hash,instance_id,user_id,expires_at) VALUES (?,?,?,?,?)');
        $stmt->execute([hash('sha256',$token),$this->audience($context['instance'],$context['validation']),$id,(int)$user['id'],$expires]);
        $cleanup=Connection::pdo()->prepare('DELETE FROM module_sessions WHERE expires_at < ?');
        $cleanup->execute([$now-86400]);
        return $this->identity($user,$context,$now)+['session_token'=>$token,'session_expires_at'=>$expires];
    }

    public function validate(int $id, string $token, string $key, ?int $now=null): array
    {
        $now??=time();$instance=$this->server($id,$key);
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new RuntimeException('Access denied.');
        $stmt=Connection::pdo()->prepare('SELECT user_id,audience_hash,expires_at FROM module_sessions WHERE token_hash=? AND instance_id=? AND revoked_at IS NULL AND expires_at>?');
        $stmt->execute([hash('sha256',$token),$id,$now]);$session=$stmt->fetch();
        if (!$session) throw new RuntimeException('Access denied.');
        $stmt=Connection::pdo()->prepare('SELECT id,name,email,active,is_platform_admin FROM users WHERE id=?');
        $stmt->execute([(int)$session['user_id']]);$user=$stmt->fetch();
        if (!$user)throw new RuntimeException('Access denied.');
        $context=(new ModuleHandoff($this->catalog))->authorize($user,$id,$now);
        if (!hash_equals((string)$session['audience_hash'],$this->audience($context['instance'],$context['validation']))) throw new RuntimeException('Access denied.');
        return $this->identity($user,$context,$now)+['session_expires_at'=>(int)$session['expires_at']];
    }

    public function revoke(int $id, string $token, string $key, ?int $now=null): void
    {
        $this->server($id,$key);
        if (!preg_match('/^[a-f0-9]{64}$/D',$token))throw new RuntimeException('Access denied.');
        $stmt=Connection::pdo()->prepare('UPDATE module_sessions SET revoked_at=? WHERE token_hash=? AND instance_id=? AND revoked_at IS NULL');
        $stmt->execute([$now??time(),hash('sha256',$token),$id]);
    }
}
