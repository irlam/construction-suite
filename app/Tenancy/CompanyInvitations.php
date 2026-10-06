<?php
declare(strict_types=1);
namespace Suite\Tenancy;

use RuntimeException;
use Suite\Database\Connection;
use Suite\Support\Audit;

/** Explicit recipient acceptance; no email service or automatic account adoption. */
final class CompanyInvitations
{
    public function ready(): bool { return Connection::tableExists('company_invitations'); }

    private function user(int $id): array
    {
        $stmt=Connection::pdo()->prepare('SELECT id,name,email,active,is_platform_admin FROM users WHERE id=?');
        $stmt->execute([$id]);$user=$stmt->fetch();
        if (!$user || !$user['active']) throw new RuntimeException('Invitation unavailable.');
        return $user;
    }

    private function checkScope(array $author, int $companyId, ?int $projectId, string $role): void
    {
        $company=suite_companies()->requireCompany($author,$companyId);
        if (!$company['active']) throw new RuntimeException('Invitation unavailable.');
        $allowed=$projectId===null ? ['user','company_admin'] : ['admin','manager','site_manager','user','contractor'];
        if (!in_array($role,$allowed,true) || ($role==='company_admin' && empty($author['is_platform_admin']))) {
            throw new RuntimeException('Choose a role you can assign for this scope.');
        }
        if ($projectId!==null) {
            $stmt=Connection::pdo()->prepare('SELECT id FROM projects WHERE id=? AND organization_id=? AND active=1');
            $stmt->execute([$projectId,$companyId]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Project access denied.');
        }
    }

    public function create(array $user,int $companyId,?int $projectId,string $email,string $role,?int $now=null): string
    {
        $author=$this->user((int)$user['id']);
        $this->checkScope($author,$companyId,$projectId,$role);
        if (!$this->ready()) throw new RuntimeException('The platform owner must apply the invitation database update first.');
        $email=strtolower(trim($email));
        if (strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid invitation email.');
        if (strcasecmp($email,(string)$author['email'])===0) throw new RuntimeException('Choose another person to invite.');
        $now??=time();$token=bin2hex(random_bytes(32));
        $stmt=Connection::pdo()->prepare('INSERT INTO company_invitations(token_hash,organization_id,project_id,invited_by,email,role_key,expires_at) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([hash('sha256',$token),$companyId,$projectId,(int)$author['id'],$email,$role,$now+604800]);
        Audit::record('company.invitation_created',(int)$author['id'],$companyId,$projectId,['invitation_id'=>(int)Connection::pdo()->lastInsertId()]);
        return $token;
    }

    public function pending(array $user,int $companyId,?int $now=null): array
    {
        suite_companies()->requireCompany($user,$companyId);
        if (!$this->ready()) return [];
        $stmt=Connection::pdo()->prepare('SELECT i.id,i.email,i.role_key,i.project_id,i.expires_at,p.name AS project_name FROM company_invitations i LEFT JOIN projects p ON p.id=i.project_id AND p.organization_id=i.organization_id WHERE i.organization_id=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>? ORDER BY i.id DESC');
        $stmt->execute([$companyId,$now??time()]);return $stmt->fetchAll();
    }

    public function revoke(array $user,int $companyId,int $invitationId): void
    {
        suite_companies()->requireCompany($user,$companyId);
        if (!$this->ready()) throw new RuntimeException('Invitation unavailable.');
        $stmt=Connection::pdo()->prepare('UPDATE company_invitations SET revoked_at=? WHERE id=? AND organization_id=? AND accepted_at IS NULL AND revoked_at IS NULL');
        $stmt->execute([time(),$invitationId,$companyId]);
        if ($stmt->rowCount()!==1) throw new RuntimeException('Invitation unavailable.');
        Audit::record('company.invitation_revoked',(int)$user['id'],$companyId,null,['invitation_id'=>$invitationId]);
    }

    public function preview(string $token,?int $now=null): array
    {
        if (!$this->ready() || !preg_match('/^[a-f0-9]{64}$/D',$token)) throw new RuntimeException('Invitation unavailable.');
        $stmt=Connection::pdo()->prepare('SELECT i.*,o.name AS company_name,p.name AS project_name FROM company_invitations i JOIN organizations o ON o.id=i.organization_id LEFT JOIN projects p ON p.id=i.project_id AND p.organization_id=i.organization_id WHERE i.token_hash=? AND i.accepted_at IS NULL AND i.revoked_at IS NULL AND i.expires_at>?');
        $stmt->execute([hash('sha256',$token),$now??time()]);$invite=$stmt->fetch();
        if (!$invite) throw new RuntimeException('Invitation unavailable.');
        $this->checkScope($this->user((int)$invite['invited_by']),(int)$invite['organization_id'],$invite['project_id']===null?null:(int)$invite['project_id'],(string)$invite['role_key']);
        unset($invite['token_hash']);return $invite;
    }

    public function accept(string $token,?array $currentUser,string $name,string $password,?int $now=null): int
    {
        $now??=time();$invite=$this->preview($token,$now);$pdo=Connection::pdo();
        $find=$pdo->prepare('SELECT id,active,is_platform_admin FROM users WHERE email=?');$find->execute([$invite['email']]);$account=$find->fetch();
        if ($currentUser!==null) {
            $currentUser=$this->user((int)$currentUser['id']);
            if (strcasecmp((string)$currentUser['email'],(string)$invite['email'])!==0) throw new RuntimeException('Sign out and use the account addressed by this invitation.');
        }
        if ($account) {
            if (!$account['active'] || $account['is_platform_admin']) throw new RuntimeException('Invitation unavailable.');
            if ($currentUser===null) {
                $proof=suite_auth()->attempt((string)$invite['email'],$password,(string)($_SERVER['REMOTE_ADDR']??'unknown'));
                if (!$proof['ok']) throw new RuntimeException('Sign in with the existing account to accept this invitation.');
            }
        } else {
            $name=trim($name);
            if ($name==='' || strlen($name)>160 || strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Enter your name and a password of 12–72 characters.');
        }
        $pdo->beginTransaction();
        try {
            $consume=$pdo->prepare('UPDATE company_invitations SET accepted_at=? WHERE id=? AND accepted_at IS NULL AND revoked_at IS NULL AND expires_at>?');
            $consume->execute([$now,(int)$invite['id'],$now]);
            if ($consume->rowCount()!==1) throw new RuntimeException('Invitation unavailable.');
            $author=$this->user((int)$invite['invited_by']);
            $companyId=(int)$invite['organization_id'];$projectId=$invite['project_id']===null?null:(int)$invite['project_id'];
            $this->checkScope($author,$companyId,$projectId,(string)$invite['role_key']);
            if (!$account) {
                $insert=$pdo->prepare('INSERT INTO users(name,email,password_hash,active,is_platform_admin) VALUES(?,?,?,1,0)');
                $insert->execute([$name,$invite['email'],password_hash($password,PASSWORD_DEFAULT)]);
                $id=(int)$pdo->lastInsertId();
            } else {$id=(int)$account['id'];}
            $find=$pdo->prepare('SELECT id FROM memberships WHERE user_id=? AND organization_id=? LIMIT 1');$find->execute([$id,$companyId]);
            $bootstrapId=null;
            if (!$find->fetchColumn()) {
                $insert=$pdo->prepare("INSERT INTO memberships(user_id,organization_id,project_id,role_key) VALUES(?,?,NULL,'user')");
                $insert->execute([$id,$companyId]);$bootstrapId=(int)$pdo->lastInsertId();
            }
            suite_companies()->saveMembership($author,$companyId,$id,$projectId,(string)$invite['role_key']);
            if ($bootstrapId!==null && $projectId!==null) {
                $delete=$pdo->prepare('DELETE FROM memberships WHERE id=?');$delete->execute([$bootstrapId]);
            }
            Audit::record('company.invitation_accepted',$id,$companyId,$projectId,['invitation_id'=>(int)$invite['id']]);
            $pdo->commit();return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction())$pdo->rollBack();throw $e;
        }
    }
}
