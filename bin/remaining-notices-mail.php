<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
try {
    $mode = $argv[1] ?? '';
    if ($argc !== 2 || !in_array($mode, ['--check', '--apply'], true)) throw new RuntimeException();
    $hosting = dirname((string) realpath(dirname(__DIR__)), 2);
    $root = realpath($hosting . '/sitenotices.site/httpdocs');
    if ($root !== $hosting . '/sitenotices.site/httpdocs') throw new RuntimeException();
    $source = __DIR__ . '/remaining-packages/notices-mail.php';
    $target = $root . '/includes/mail.php';
    $private = $root . '/includes/mail.local.php';
    if (!is_file($source) || !is_file($target) || is_link($private)) throw new RuntimeException();
    if (is_file($private)) {
        $config = require $private;
        if (!is_array($config)) throw new RuntimeException();
    } else {
        require $target;
        $config = ['enabled' => true];
        foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','SMTP_SECURE','MAIL_FROM','MAIL_FROM_NAME'] as $field) {
            if (!defined($field) || (string) constant($field) === '') throw new RuntimeException();
            $config[$field] = constant($field);
        }
    }
    if ($mode === '--apply') {
        if (!is_file($private)) {
            $mask = umask(0077);
            try { $handle = fopen($private, 'xb'); } finally { umask($mask); }
            if (!$handle) throw new RuntimeException();
            try {
                $text = "<?php\nreturn " . var_export($config, true) . ";\n";
                if (fwrite($handle, $text) !== strlen($text) || !fflush($handle)) throw new RuntimeException();
            } finally { fclose($handle); }
        }
        if (!chmod($private, 0600)) throw new RuntimeException();
        $temporary = $target . '.suite-update';
        if (!copy($source, $temporary) || !chmod($temporary, 0644) || !rename($temporary, $target)) throw new RuntimeException();
    }
    echo json_encode(['ok'=>true, 'private_mail_preserved'=>true, 'updated'=>$mode==='--apply', 'emails_sent'=>0]) . "\n";
} catch (Throwable $error) {
    echo "{\"ok\":false,\"emails_sent\":0}\n";
    exit(1);
}
