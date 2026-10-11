<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0'); ini_set('log_errors', '0');
$phase = 'arguments';
try {
    $apply = ($argv[1] ?? '') === '--apply';
    if ($argc !== 2 || !in_array($argv[1], ['--check', '--apply'], true)) throw new RuntimeException();
    require dirname(__DIR__) . '/app/bootstrap.php';
    $hosting = dirname((string) realpath(SUITE_ROOT), 2);
    foreach (['alpha' => 7, 'beta' => 8] as $side => $id) {
        $phase = $side . ' binding';
        $root = $hosting . '/' . $side . '.qa.defecttracker.uk/httpdocs';
        $entry = suite_instances()->find($id);
        if (!$entry || $entry['ready'] || $entry['module_key'] !== 'qa' || is_link($root)
            || realpath($root) !== $root || !is_dir($root . '/public')) throw new RuntimeException();
        $binding = json_decode((string) file_get_contents($root . '/private/suite-deployment.json'), true, 16, JSON_THROW_ON_ERROR);
        if ($binding['instance_id'] !== $id || $binding['origin'] !== $entry['origin']) throw new RuntimeException();
        $webRoot = '/var/www/vhosts/hosting215226.ae97b.netcup.net/' . $side . '.qa.defecttracker.uk/httpdocs';
        $guard = "<?php\nini_set('display_errors','0');\n\$scope=dirname(__DIR__).':/tmp';\nini_set('open_basedir',\$scope);\nif(ini_get('open_basedir')!==\$scope){http_response_code(503);exit;}\n";
        $guardFile = $root . '/public/runtime-scope.php';
        if (is_link($guardFile) || (is_file($guardFile) && file_get_contents($guardFile) !== $guard)) throw new RuntimeException();
        if ($apply && !is_file($guardFile) && file_put_contents($guardFile, $guard) !== strlen($guard)) throw new RuntimeException();
        $oldText = "open_basedir=\"$webRoot:/tmp\"\ndisplay_errors=Off\nlog_errors=On\n";
        $text = $oldText . "auto_prepend_file=\"$webRoot/public/runtime-scope.php\"\n";
        $file = $root . '/public/.user.ini';
        if (is_link($file) || (is_file($file) && !in_array(file_get_contents($file), [$text, $oldText], true))) throw new RuntimeException();
        if ($apply && is_file($file) && file_get_contents($file) === $oldText) {
            if (file_put_contents($file, $text) !== strlen($text)) throw new RuntimeException();
        } elseif ($apply && !is_file($file)) {
            $mask = umask(0077);
            try { $handle = fopen($file, 'xb'); } finally { umask($mask); }
            if (!$handle) throw new RuntimeException();
            try { if (fwrite($handle, $text) !== strlen($text) || !fflush($handle)) throw new RuntimeException(); }
            finally { fclose($handle); }
        }
    }
    echo json_encode(['ok' => true, 'instances' => 2, 'runtime_applied' => $apply, 'scope' => 'each_qa_app_only', 'tenant_ready' => false]) . "\n";
} catch (Throwable $error) {
    echo json_encode(['ok' => false, 'failed_phase' => $phase, 'tenant_ready' => false]) . "\n"; exit(1);
}
