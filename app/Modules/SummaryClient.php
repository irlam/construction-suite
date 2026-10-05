<?php
declare(strict_types=1);

namespace Suite\Modules;

use Suite\Support\Env;

final class SummaryClient
{
    public function configured(): bool
    {
        return strlen(trim((string) Env::get('SUITE_INTEGRATION_KEY', ''))) >= 32;
    }

    public function fetch(array $modules): array
    {
        $key = trim((string) Env::get('SUITE_INTEGRATION_KEY', ''));
        if (strlen($key) < 32) {
            return [
                'configured' => false,
                'modules' => [],
            ];
        }

        if (!function_exists('curl_multi_init')) {
            return [
                'configured' => true,
                'modules' => [],
                'error' => 'curl_unavailable',
            ];
        }

        $multi = curl_multi_init();
        $handles = [];
        $results = [];

        foreach ($modules as $module) {
            $moduleKey = (string) ($module['key'] ?? '');
            $summaryUrl = (string) ($module['summary_url'] ?? '');
            if ($moduleKey === '' || $summaryUrl === '') {
                continue;
            }

            if (!empty($module['summary_scope_blocked'])) {
                $results[$moduleKey] = [
                    'status' => 'needs_mapping',
                    'metrics' => [],
                ];
                continue;
            }

            $externalRef = trim((string) ($module['external_project_ref'] ?? ''));
            $allowAll = (bool) ($module['summary_allow_all'] ?? false);

            if (($module['summary_requires_ref'] ?? false) && $externalRef === '' && !$allowAll) {
                $results[$moduleKey] = [
                    'status' => 'needs_mapping',
                    'metrics' => [],
                ];
                continue;
            }

            if ($externalRef === '__all__') {
                $externalRef = '';
            }

            $param = trim((string) ($module['summary_param'] ?? ''));
            if ($param !== '' && $externalRef !== '') {
                $separator = str_contains($summaryUrl, '?') ? '&' : '?';
                $summaryUrl .= $separator . rawurlencode($param) . '=' . rawurlencode($externalRef);
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $summaryUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 2,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'ConstructionSuite/0.3 Summary',
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
                $errorCode === 0
                && $httpCode >= 200
                && $httpCode < 300
                && is_array($decoded)
                && ($decoded['ok'] ?? false) === true
            ) {
                $results[$moduleKey] = [
                    'status' => 'connected',
                    'metrics' => is_array($decoded['metrics'] ?? null) ? $decoded['metrics'] : [],
                    'last_updated' => $decoded['last_updated'] ?? null,
                ];
            } else {
                $results[$moduleKey] = [
                    'status' => $httpCode === 401 ? 'unauthorized' : ($httpCode === 503 ? 'not_configured' : 'unavailable'),
                    'metrics' => [],
                    'http_code' => $httpCode,
                ];
            }

            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }

        curl_multi_close($multi);

        return [
            'configured' => true,
            'modules' => $results,
        ];
    }
}
