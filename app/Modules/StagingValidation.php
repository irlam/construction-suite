<?php
declare(strict_types=1);
namespace Suite\Modules;

use Suite\Database\Connection;
use RuntimeException;

/** Deployment-owned, one-hour pilot window; never declares an instance ready. */
final class StagingValidation
{
    public function forUser(array $instance, array $user, int $now): array
    {
        if (empty($user['active']) || !empty($user['is_platform_admin'])) throw new RuntimeException('Validation access denied.');
        $expected = [
            1 => [7, 7, 'alpha', 'programme', 'programme.defecttracker.uk'],
            2 => [8, 8, 'beta', 'programme', 'programme.defecttracker.uk'],
            3 => [7, 7, 'alpha', 'defects', 'defectnotice.site'],
            4 => [8, 8, 'beta', 'defects', 'defectnotice.site'],
        ];
        $binding = $expected[$instance['id']] ?? null;
        if (!$binding || $instance['organization_id'] !== $binding[0] || $instance['project_id'] !== $binding[1]
            || $instance['module_key'] !== $binding[3]
            || $instance['origin'] !== 'https://' . $binding[2] . '.' . $binding[4]
            || ($instance['isolation_verified'] ?? null) !== false
            || ($instance['gateway_verified'] ?? null) !== false) throw new RuntimeException('Validation access denied.');
        $root = realpath(SUITE_ROOT);
        $directory = $root . '/private';
        $file = $directory . '/staging-validation.json';
        if (!$root || !is_dir($directory) || is_link($directory) || realpath($directory) !== $directory
            || (fileperms($directory) & 0777) !== 0700 || !is_file($file) || is_link($file)
            || realpath($file) !== $file || (fileperms($file) & 0777) !== 0600
            || filesize($file) > 16384) throw new RuntimeException('Validation access denied.');
        $policy = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($policy) || ($policy['version'] ?? null) !== 1 || ($policy['enabled'] ?? null) !== true
            || !is_int($policy['started_at'] ?? null) || !is_int($policy['expires_at'] ?? null)
            || $policy['started_at'] < 1 || $policy['expires_at'] <= $policy['started_at']
            || $policy['expires_at'] - $policy['started_at'] > 3600
            || $now < $policy['started_at'] || $now >= $policy['expires_at']
            || !is_string($policy['run_id'] ?? null) || !preg_match('/^[a-f0-9]{32}$/D', $policy['run_id'])
            || !is_string($policy['inventory_sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $policy['inventory_sha256'])
            || !is_array($policy['instances'] ?? null) || !array_is_list($policy['instances']) || count($policy['instances']) !== 2) {
            throw new RuntimeException('Validation access denied.');
        }
        $inventory = $directory . '/programme-staging-instances.json';
        if (InstanceCatalog::inventoryFile() !== $inventory || !is_file($inventory) || is_link($inventory)
            || realpath($inventory) !== $inventory || (fileperms($inventory) & 0777) !== 0600
            || !hash_equals($policy['inventory_sha256'], (string) hash_file('sha256', $inventory))) throw new RuntimeException('Validation access denied.');
        $module = $policy['module_key'] ?? 'programme';
        if (!in_array($module, ['programme', 'defects'], true) || $module !== $binding[3]) throw new RuntimeException('Validation access denied.');
        $expected = array_filter($expected, static fn(array $entry): bool => $entry[3] === $module);
        $lists = [];
        foreach ($policy['instances'] as $entry) {
            if (!is_array($entry) || !is_int($entry['instance_id'] ?? null) || !isset($expected[$entry['instance_id']])
                || isset($lists[$entry['instance_id']]) || !is_array($entry['user_ids'] ?? null)
                || !array_is_list($entry['user_ids']) || count($entry['user_ids']) < 1 || count($entry['user_ids']) > 8) throw new RuntimeException('Validation access denied.');
            foreach ($entry['user_ids'] as $id) {
                if (!is_int($id) || $id < 1) throw new RuntimeException('Validation access denied.');
            }
            if (count(array_unique($entry['user_ids'])) !== count($entry['user_ids'])) throw new RuntimeException('Validation access denied.');
            $lists[$entry['instance_id']] = $entry['user_ids'];
        }
        if (!in_array((int) ($user['id'] ?? 0), $lists[$instance['id']] ?? [], true)) throw new RuntimeException('Validation access denied.');
        // A named account with a production assignment must not become a pilot account.
        $q = Connection::pdo()->prepare('SELECT COUNT(*) FROM memberships WHERE user_id=? AND organization_id NOT IN (7,8)');
        $q->execute([(int) $user['id']]);
        if ((int) $q->fetchColumn() !== 0) throw new RuntimeException('Validation access denied.');
        $q = Connection::pdo()->prepare('SELECT COUNT(*) FROM organizations WHERE id=? AND slug=?');
        $q->execute([$binding[0], 'programme-' . $binding[2] . '-staging']);
        if ((int) $q->fetchColumn() !== 1) throw new RuntimeException('Validation access denied.');
        return ['run_id' => $policy['run_id'], 'expires_at' => $policy['expires_at']];
    }
}

