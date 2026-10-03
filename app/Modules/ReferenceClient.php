<?php
declare(strict_types=1);

namespace Suite\Modules;

use Suite\Support\Env;

final class ReferenceClient
{
    public function configured(): bool
    {
        return strlen(trim((string) Env::get('SUITE_INTEGRATION_KEY', ''))) >= 32;
    }

    public function fetch(array $modules): array
    {
        $key = trim((string) Env::get('SUITE_INTEGRATION_KEY', ''));
        if (strlen($key) < 32 || !function_exists('curl_multi_init')) {
            return [];
        }

        $multi = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($modules as $module) {
            $moduleKey = (string) ($module['key'] ?? '');
            $url = (string) ($module['reference_url'] ?? '');
            if ($url === '' && !empty($module['summary_url'])) {
                $url = str_replace('suite-summary.php', 'suite-references.php', (string) $module['summary_url']);
            }
            if ($moduleKey === '' || $url === '' || !str_starts_with($url, 'https://')) {
                continue;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ConstructionSuite/0.3 ReferenceDiscovery',
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'X-Construction-Suite-Key: ' . $key,
                ],
            ]);
            curl_multi_add_handle($multi, $ch);
            $handles[$moduleKey] = $ch;
        }

        if ($handles) {
            do {
                $status = curl_multi_exec($multi, $running);
                if ($running) {
                    curl_multi_select($multi, 0.25);
                }
            } while ($running && $status === CURLM_OK);
        }

        foreach ($handles as $moduleKey => $ch) {
            $body = (string) curl_multi_getcontent($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorCode = curl_errno($ch);
            $decoded = json_decode($body, true);

            if (
                $errorCode === 0 &&
                $httpCode >= 200 &&
                $httpCode < 300 &&
                is_array($decoded) &&
                ($decoded['ok'] ?? false) === true
            ) {
                $items = [];
                foreach ((array) ($decoded['items'] ?? []) as $item) {
                    $value = trim((string) ($item['value'] ?? ''));
                    $label = trim((string) ($item['label'] ?? $value));
                    if ($value === '') continue;
                    $items[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
                }
                $results[$moduleKey] = [
                    'status' => 'connected',
                    'items' => $items,
                ];
            } else {
                $results[$moduleKey] = [
                    'status' => $httpCode === 401 ? 'unauthorized' : ($httpCode === 503 ? 'not_configured' : 'unavailable'),
                    'items' => [],
                ];
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);
        return $results;
    }
}
