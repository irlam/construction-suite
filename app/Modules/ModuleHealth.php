<?php
declare(strict_types=1);

namespace Suite\Modules;

final class ModuleHealth
{
    public function checkMany(array $modules): array
    {
        if (!function_exists('curl_multi_init')) {
            return array_map(
                static fn(array $module): array => [
                    'key' => (string) ($module['key'] ?? ''),
                    'status' => 'unknown',
                    'http_code' => 0,
                    'latency_ms' => null,
                ],
                $modules
            );
        }

        $multi = curl_multi_init();
        $handles = [];
        $started = microtime(true);

        foreach ($modules as $module) {
            $key = (string) ($module['key'] ?? '');
            $url = (string) ($module['health_url'] ?? $module['url'] ?? '');
            if ($key === '' || $url === '' || !str_starts_with($url, 'https://')) {
                continue;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ConstructionSuite/0.2 Health',
                CURLOPT_HTTPHEADER => ['Accept: application/json,text/html;q=0.8'],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$key] = ['handle' => $ch, 'started' => microtime(true)];
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 0.25);
            }
        } while ($running && $status === CURLM_OK && (microtime(true) - $started) < 5.0);

        $results = [];
        foreach ($modules as $module) {
            $key = (string) ($module['key'] ?? '');
            if (!isset($handles[$key])) {
                $results[$key] = [
                    'key' => $key,
                    'status' => 'unknown',
                    'http_code' => 0,
                    'latency_ms' => null,
                ];
                continue;
            }

            $ch = $handles[$key]['handle'];
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $latency = (int) round((microtime(true) - $handles[$key]['started']) * 1000);
            $error = curl_errno($ch);

            // 401/403 still prove the application is reachable and protecting itself.
            $available = $error === 0 && (($code >= 200 && $code < 400) || in_array($code, [401, 403], true));

            $results[$key] = [
                'key' => $key,
                'status' => $available ? 'available' : 'unavailable',
                'http_code' => $code,
                'latency_ms' => $latency,
            ];

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);
        return array_values($results);
    }
}
