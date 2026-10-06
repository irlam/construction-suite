<?php
declare(strict_types=1);
namespace Suite\Modules;

use Suite\Support\Env;
use RuntimeException;

/** Deployment-owned inventory; a public form can never declare isolation. */
final class InstanceCatalog
{
    public function __construct(private readonly ?array $inventory = null) {}

    public function all(): array
    {
        $items = $this->inventory;
        if ($items === null) {
            $file = Env::get('SUITE_INSTANCES_FILE', '');
            if ($file === '') return [];
            $real = realpath($file);
            $public = realpath(SUITE_ROOT . '/public');
            if (!$real || ($public && str_starts_with($real, $public . DIRECTORY_SEPARATOR))) {
                throw new RuntimeException('Instance inventory must be a private deployment file.');
            }
            $items = json_decode((string) file_get_contents($real), true, 32, JSON_THROW_ON_ERROR);
        }
        if (!is_array($items) || !array_is_list($items)) throw new RuntimeException('Invalid instance inventory.');
        $result = []; $bindings = []; $origins = [];
        foreach ($items as $item) {
            if (!is_array($item)) throw new RuntimeException('Invalid instance entry.');
            foreach (['id', 'organization_id', 'project_id'] as $key) {
                if (!is_int($item[$key] ?? null) || $item[$key] < 1) throw new RuntimeException('Invalid instance identity.');
            }
            $key = (string) ($item['module_key'] ?? '');
            $definition = suite_config('modules')[$key] ?? null;
            if (!is_array($definition)) throw new RuntimeException('Unknown instance module.');
            $url = rtrim((string) ($item['origin'] ?? ''), '/');
            $parts = parse_url($url);
            $baseHost = parse_url((string) $definition['url'], PHP_URL_HOST);
            $host = strtolower((string) ($parts['host'] ?? ''));
            if (!$parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])
                || isset($parts['port']) || isset($parts['query']) || isset($parts['fragment'])
                || ($parts['path'] ?? '') !== '' || !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)
                || !str_ends_with($host, '.' . $baseHost) || $host === $baseHost) {
                throw new RuntimeException('An isolated instance needs its own HTTPS subdomain under the module domain.');
            }
            $url = 'https://' . $host;
            $binding = $item['project_id'] . ':' . $key;
            if (isset($result[$item['id']]) || isset($bindings[$binding]) || isset($origins[$url])) throw new RuntimeException('Duplicate instance identity or project binding.');
            $bindings[$binding] = true; $origins[$url] = true;
            $item['origin'] = $url;
            $item['key_env'] = 'SUITE_INSTANCE_KEY_' . $item['id'];
            $item['ready'] = ($item['isolation_verified'] ?? false) === true && ($item['gateway_verified'] ?? false) === true;
            $result[$item['id']] = $item;
        }
        return $result;
    }

    public function find(int $id): ?array { return $this->all()[$id] ?? null; }

    public function forProject(int $projectId, int $organizationId): array
    {
        return array_values(array_filter($this->all(), static fn(array $i): bool =>
            $i['project_id'] === $projectId && $i['organization_id'] === $organizationId && $i['ready']
        ));
    }
}
