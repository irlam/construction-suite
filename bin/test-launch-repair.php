<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
try {
    if ($argc !== 2 || !in_array($argv[1], ['--check', '--apply'], true)) throw new RuntimeException();
    require dirname(__DIR__) . '/app/bootstrap.php';
    $hosting = dirname((string) realpath(SUITE_ROOT), 2);
    $apply = $argv[1] === '--apply';
    $modes = [];
    foreach (['assets' => 0755, 'assets/logistics.js' => 0644, 'assets/logistics.css' => 0644] as $relative => $mode) {
        $path = $hosting . '/sitedeliveries.site/httpdocs/' . $relative;
        if (is_link($path) || realpath($path) !== $path) throw new RuntimeException();
        $modes[$relative] = sprintf('%04o', fileperms($path) & 0777);
        if ($apply && !chmod($path, $mode)) throw new RuntimeException();
    }
    $fixed = 0;
    foreach (['alpha', 'beta'] as $side) {
        $root = $hosting . '/' . $side . '.sitenotices.site/httpdocs';
        if (is_link($root) || realpath($root) !== $root) throw new RuntimeException();
        foreach (['dashboard.php', 'includes/auth.php', 'forms/clean-up/list.php', 'forms/clean-up/form.php', 'forms/clean-up/close.php', 'forms/clean-up/submit.php', 'forms/clean-up/success.php', 'api/offline_asset.php', 'api/offline_close.php', 'api/offline_context.php', 'api/offline_notices.php', 'api/offline_receipt.php'] as $relative) {
            $path = $root . '/' . $relative;
            if (is_link($path) || realpath($path) !== $path) throw new RuntimeException();
            $text = (string) file_get_contents($path);
            $text = preg_replace('/^session_start\(\);/m', 'if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }', $text, -1, $count);
            if ($count && $apply && file_put_contents($path, $text, LOCK_EX) === false) throw new RuntimeException();
            $fixed += $count;
        }
    }
    echo json_encode(['ok' => true, 'applied' => $apply, 'asset_modes_before' => $modes, 'notice_session_guards' => $fixed], JSON_THROW_ON_ERROR);
} catch (Throwable $e) { echo '{"ok":false,"phase":"launch repair"}'; exit(1); }
