<?php
declare(strict_types=1);
namespace Suite\Auth;

use Suite\Database\Connection;
use Suite\Modules\InstanceCatalog;
use Suite\Support\Env;
use RuntimeException;

/** One-use codes. Identity and access are rechecked when exchanged. */
final class ModuleHandoff
{
    public function __construct(private readonly InstanceCatalog $catalog) {}

    private function audience(array $instance, ?array $validation = null): string
    {
        $binding = [$instance['id'], $instance['organization_id'], $instance['project_id'], $instance['module_key'], $instance['origin']];
        if ($validation !== null) $binding[] = ['validation', $validation['run_id'], $validation['expires_at']];
        return hash('sha256', json_encode($binding, JSON_THROW_ON_ERROR));
    }

    public function authorize(array $user, int $instanceId, ?int $now = null): array
    {
        if (empty($user['active'])) throw new RuntimeException('Access denied.');
        $instance = $this->catalog->find($instanceId);
        if (!$instance) throw new RuntimeException('Instance is not ready.');
        $validation = $instance['ready'] ? null : (new \Suite\Modules\StagingValidation())->forUser($instance, $user, $now ?? time());
        $project = null;
        foreach (suite_projects()->forUser($user) as $candidate) {
            if ((int) $candidate['id'] === $instance['project_id'] && (int) $candidate['organization_id'] === $instance['organization_id']) {
                $project = $candidate; break;
            }
        }
        if (!$project) throw new RuntimeException('Access denied.');
        $role = suite_projects()->roleFor($user, $project);
        if ($validation !== null && !in_array($role, ['admin', 'manager', 'site_manager', 'user', 'viewer'], true)) throw new RuntimeException('Validation access denied.');
        $enabled = false;
        // Resolve tool entitlement independently of the private instance inventory.
        foreach (suite_modules()->allForProject($role, (int) $project['id'], false) as $module) {
            if ($module['key'] === $instance['module_key']) $enabled = true;
        }
        if (!$enabled) throw new RuntimeException('Module access denied.');
        if (strlen((string) Env::get($instance['key_env'], '')) < 32) throw new RuntimeException('Instance authentication is not configured.');
        return ['instance' => $instance, 'project' => $project, 'role' => $role, 'validation' => $validation];
    }

    public function issue(array $user, int $instanceId, string $state, ?int $now = null): string
    {
        $now ??= time();
        $context = $this->authorize($user, $instanceId, $now);
        if (!preg_match('/^[a-f0-9]{64}$/D', $state)) throw new RuntimeException('Invalid browser state.');
        $code = bin2hex(random_bytes(32));
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('INSERT INTO module_handoffs (code_hash, state_hash, audience_hash, instance_id, user_id, expires_at) VALUES (?, ?, ?, ?, ?, ?)');
        $expires = min($now + 60, $context['validation']['expires_at'] ?? PHP_INT_MAX);
        $stmt->execute([hash('sha256', $code), hash('sha256', $state), $this->audience($context['instance'], $context['validation']), $instanceId, (int) $user['id'], $expires]);
        $cleanup = $pdo->prepare('DELETE FROM module_handoffs WHERE expires_at < ?');
        $cleanup->execute([$now - 86400]);
        return $code;
    }

    public function redeem(int $instanceId, string $code, string $state, string $key, ?int $now = null): array
    {
        $now ??= time();
        $instance = $this->catalog->find($instanceId);
        $expected = $instance ? (string) Env::get($instance['key_env'], '') : '';
        if (!$instance || strlen($expected) < 32 || !hash_equals($expected, $key)
            || !preg_match('/^[a-f0-9]{64}$/D', $code) || !preg_match('/^[a-f0-9]{64}$/D', $state)) {
            throw new RuntimeException('Access denied.');
        }
        $pdo = Connection::pdo();
        $stmt = $pdo->prepare('SELECT code_hash, state_hash, audience_hash, user_id FROM module_handoffs WHERE code_hash = ? AND instance_id = ? AND used_at IS NULL AND expires_at >= ?');
        $stmt->execute([hash('sha256', $code), $instanceId, $now]);
        $grant = $stmt->fetch();
        if (!$grant || !hash_equals((string) $grant['state_hash'], hash('sha256', $state))) throw new RuntimeException('Access denied.');
        $stmt = $pdo->prepare('SELECT id, name, email, active, is_platform_admin FROM users WHERE id = ?');
        $stmt->execute([(int) $grant['user_id']]);
        $user = $stmt->fetch();
        if (!$user) throw new RuntimeException('Access denied.');
        $context = $this->authorize($user, $instanceId, $now);
        if (!hash_equals((string) $grant['audience_hash'], $this->audience($context['instance'], $context['validation']))) throw new RuntimeException('Access denied.');
        // Consume the code and create the opaque app session atomically.
        $pdo->beginTransaction();
        try {
            $consume = $pdo->prepare('UPDATE module_handoffs SET used_at = ? WHERE code_hash = ? AND instance_id = ? AND used_at IS NULL AND expires_at >= ?');
            $consume->execute([$now, $grant['code_hash'], $instanceId, $now]);
            if ($consume->rowCount() !== 1) throw new RuntimeException('Access denied.');
            $identity = (new ModuleSession($this->catalog))->issue($user, $instanceId, $now);
            $pdo->commit();
            return $identity;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
